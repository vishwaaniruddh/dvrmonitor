<?php

namespace App\Services;

use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use App\Services\EnhancedMonitoringService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SimplifiedMonitoringService
{
    private const MONITORING_INTERVAL = 300; // 5 minutes
    private const CACHE_TTL = 60; // 1 minute cache
    private const BATCH_SIZE = 50; // Process 50 DVRs at once in parallel
    
    protected $enhancedMonitoringService;
    protected $useEnhancedMonitoring = true; // Enable enhanced monitoring with device time

    public function __construct()
    {
        $this->enhancedMonitoringService = new EnhancedMonitoringService();
    }

    /**
     * Start continuous automated monitoring (simplified - no queues)
     */
    public function startContinuousMonitoring(): array
    {
        try {
            // Get all active DVRs
            $activeDvrs = Dvr::where('is_active', true)->count();

            if ($activeDvrs === 0) {
                return [
                    'success' => false,
                    'message' => 'No active DVRs found to monitor'
                ];
            }

            // Update monitoring status
            Cache::put('automated_monitoring_status', [
                'active' => true,
                'started_at' => now('Asia/Kolkata'),
                'total_dvrs' => $activeDvrs,
                'last_cycle' => now('Asia/Kolkata')->toISOString(),
                'mode' => 'simplified'
            ], self::CACHE_TTL * 10);

            // Start background monitoring process
            $this->scheduleBackgroundMonitoring();

            return [
                'success' => true,
                'message' => 'Simplified automated monitoring started successfully',
                'total_dvrs' => $activeDvrs,
                'monitoring_interval' => self::MONITORING_INTERVAL . ' seconds',
                'started_at' => now('Asia/Kolkata')->toISOString(),
                'mode' => 'simplified'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to start simplified monitoring: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to start monitoring: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Schedule background monitoring using Laravel's built-in mechanisms
     */
    private function scheduleBackgroundMonitoring(): void
    {
        // Use Laravel's cache-based scheduling
        $nextRun = now('Asia/Kolkata')->addMinutes(5);
        Cache::put('next_monitoring_run', $nextRun->toISOString(), self::CACHE_TTL * 10);

        Log::info("📅 Next monitoring cycle scheduled for: " . $nextRun->format('Y-m-d H:i:s T'));
    }

    /**
     * Check if it's time to run monitoring and execute if needed
     */
    public function checkAndRunMonitoring(): void
    {
        try {
            $status = Cache::get('automated_monitoring_status', ['active' => false]);

            if (!$status['active']) {
                return; // Monitoring is not active
            }

            $nextRun = Cache::get('next_monitoring_run');
            if (!$nextRun || now('Asia/Kolkata') < \Carbon\Carbon::parse($nextRun)) {
                return; // Not time yet
            }

            $this->logToConsoleAndFile('🔄 Running scheduled monitoring cycle...');

            // Run monitoring cycle
            $this->runMonitoringCycle();

            // Schedule next run
            $this->scheduleBackgroundMonitoring();
        } catch (\Exception $e) {
            $this->logToConsoleAndFile('❌ Error in checkAndRunMonitoring: ' . $e->getMessage());
        }
    }

    /**
     * Run a complete monitoring cycle
     */
    private function runMonitoringCycle(): void
    {
        $startTime = microtime(true);

        // Get active DVRs in batches
        $activeDvrs = Dvr::where('is_active', true)->get(['id', 'dvr_name', 'ip', 'port']);
        $totalDvrs = $activeDvrs->count();

        if ($totalDvrs === 0) {
            $this->logToConsoleAndFile('No active DVRs to monitor');
            return;
        }

        $this->logToConsoleAndFile("🚀 Starting monitoring cycle for {$totalDvrs} DVRs at " . now('Asia/Kolkata')->format('H:i:s'));

        $processed = 0;
        $online = 0;
        $offline = 0;

        // Process DVRs in parallel batches using multi-processing
        $batches = $activeDvrs->chunk(self::BATCH_SIZE);

        foreach ($batches as $batchIndex => $batch) {
            $batchStart = microtime(true);

            // Process this batch in parallel
            $batchResults = $this->processBatchInParallel($batch, $batchIndex + 1);

            // Aggregate results
            $batchOnline = 0;
            $batchOffline = 0;

            foreach ($batchResults as $result) {
                $processed++;
                if ($result['success'] && $result['status'] === 'online') {
                    $online++;
                    $batchOnline++;
                } else {
                    $offline++;
                    $batchOffline++;
                }
            }

            $batchTime = round(microtime(true) - $batchStart, 2);
            $this->logToConsoleAndFile("   📦 Batch " . ($batchIndex + 1) . ": {$batch->count()} DVRs in {$batchTime}s ({$batchOnline} online, {$batchOffline} offline) [PARALLEL]");
        }

        $executionTime = round(microtime(true) - $startTime, 2);

        // Update monitoring status
        Cache::put('automated_monitoring_status', [
            'active' => true,
            'started_at' => Cache::get('automated_monitoring_status.started_at', now('Asia/Kolkata')),
            'total_dvrs' => $totalDvrs,
            'last_cycle' => now('Asia/Kolkata')->toISOString(),
            'last_cycle_stats' => [
                'processed' => $processed,
                'online' => $online,
                'offline' => $offline,
                'execution_time' => $executionTime
            ],
            'mode' => 'simplified'
        ], self::CACHE_TTL * 10);

        $this->logToConsoleAndFile("✅ Monitoring cycle completed: {$processed} DVRs in {$executionTime}s ({$online} online, {$offline} offline)");
        $this->logToConsoleAndFile("⏰ Next cycle scheduled for: " . now('Asia/Kolkata')->addMinutes(5)->format('H:i:s'));

        // Update real-time cache
        $this->updateRealtimeCache();
    }

    /**
     * Monitor a single DVR with enhanced functionality
     */
    public function monitorSingleDvr(Dvr $dvr): array
    {
        $startTime = microtime(true);

        try {
            if ($this->useEnhancedMonitoring) {
                // Use enhanced monitoring with device time and API checks
                $result = $this->enhancedMonitoringService->monitorDvr($dvr, 'full_check');
                
                return [
                    'success' => $result['success'],
                    'dvr_id' => $dvr->id,
                    'status' => $result['status'],
                    'response_time' => $result['response_time'],
                    'ping_success' => $result['ping_success'],
                    'api_success' => $result['api_success'],
                    'dvr_time' => $result['dvr_time'],
                    'checked_at' => now('Asia/Kolkata')->toISOString()
                ];
            } else {
                // Fallback to simple monitoring
                return $this->performSimpleMonitoring($dvr);
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to monitor DVR {$dvr->id} ({$dvr->ip}): " . $e->getMessage());

            // Mark as offline due to error
            $dvr->update([
                'status' => 'offline',
                'last_ping_at' => now('Asia/Kolkata'),
                'consecutive_failures' => $dvr->consecutive_failures + 1,
                'updated_at' => now('Asia/Kolkata')
            ]);

            return [
                'success' => false,
                'dvr_id' => $dvr->id,
                'error' => $e->getMessage(),
                'status' => 'offline',
                'checked_at' => now('Asia/Kolkata')->toISOString()
            ];
        }
    }

    /**
     * Fallback simple monitoring method
     */
    private function performSimpleMonitoring(Dvr $dvr): array
    {
        $startTime = microtime(true);
        
        // Perform hybrid monitoring (ICMP + HTTP)
        $pingResult = $this->performPingCheck($dvr->ip);
        $httpResult = $this->performHttpCheck($dvr);

        // Determine final status
        $status = $this->determineStatus($pingResult, $httpResult);
        $responseTime = $pingResult['success'] ? $pingResult['time'] : $httpResult['time'];

        // Update DVR status in database
        $dvr->update([
            'status' => $status,
            'ping_response_time' => $responseTime,
            'api_accessible' => $httpResult['success'],
            'last_ping_at' => now('Asia/Kolkata'),
            'consecutive_failures' => $status === 'offline' ?
                $dvr->consecutive_failures + 1 : 0,
            'updated_at' => now('Asia/Kolkata')
        ]);

        // Log monitoring result (simplified)
        DvrMonitoringLog::create([
            'dvr_id' => $dvr->id,
            'check_type' => 'simplified',
            'result' => $status === 'online' ? 'success' : 'failure',
            'response_time' => (int) $responseTime,
            'response_data' => json_encode([
                'simplified' => true,
                'ping_success' => $pingResult['success'],
                'http_success' => $httpResult['success'],
                'http_code' => $httpResult['code'] ?? 0,
                'total_time' => round((microtime(true) - $startTime) * 1000, 2),
                'timezone' => 'Asia/Kolkata'
            ]),
            'error_message' => $httpResult['error'] ?? $pingResult['error'] ?? null,
            'created_at' => now('Asia/Kolkata'),
            'updated_at' => now('Asia/Kolkata')
        ]);

        return [
            'success' => true,
            'dvr_id' => $dvr->id,
            'status' => $status,
            'response_time' => $responseTime,
            'checked_at' => now('Asia/Kolkata')->toISOString()
        ];
    }

    /**
     * Perform ICMP ping check
     */
    private function performPingCheck(string $ip): array
    {
        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            exec("ping -n 1 -w 2000 {$ip} 2>nul", $output, $returnCode);
        } else {
            exec("ping -c 1 -W 2 {$ip} 2>/dev/null", $output, $returnCode);
        }

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $success = $returnCode === 0;

        // Extract actual ping time from output
        $actualPingTime = $responseTime;
        foreach ($output as $line) {
            if (preg_match('/time[<=](\d+)ms/i', $line, $matches)) {
                $actualPingTime = (float)$matches[1];
                break;
            } elseif (preg_match('/time=(\d+\.?\d*)ms/i', $line, $matches)) {
                $actualPingTime = (float)$matches[1];
                break;
            }
        }

        return [
            'success' => $success,
            'time' => $success ? $actualPingTime : $responseTime,
            'error' => $success ? '' : 'Ping timeout or unreachable'
        ];
    }

    /**
     * Perform HTTP check
     */
    private function performHttpCheck(Dvr $dvr): array
    {
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://{$dvr->ip}:{$dvr->port}/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'DVR-Monitor/1.0',
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        curl_close($ch);

        $success = $httpCode > 0 && $httpCode < 600;

        return [
            'success' => $success,
            'time' => $responseTime,
            'code' => $httpCode,
            'error' => $error
        ];
    }

    /**
     * Determine final status using hybrid logic (FIXED)
     */
    private function determineStatus(array $pingResult, array $httpResult): string
    {
        // Primary check: ICMP ping
        if ($pingResult['success']) {
            return 'online';
        }

        // Secondary check: HTTP response
        if ($httpResult['success']) {
            return 'online';
        }

        // Additional check: Valid HTTP response codes (200-399 range)
        if ($httpResult['code'] >= 200 && $httpResult['code'] < 400) {
            return 'online';
        }

        // If both ping and HTTP fail, it's offline
        return 'offline';
    }

    /**
     * Update real-time cache for dashboard
     */
    private function updateRealtimeCache(): void
    {
        try {
            $stats = Dvr::selectRaw('
                COUNT(*) as total_dvrs,
                COUNT(CASE WHEN status = "online" THEN 1 END) as online_dvrs,
                COUNT(CASE WHEN status = "offline" THEN 1 END) as offline_dvrs,
                COUNT(CASE WHEN status = "unknown" OR status IS NULL THEN 1 END) as unknown_dvrs,
                AVG(CASE WHEN ping_response_time IS NOT NULL THEN ping_response_time END) as avg_response_time,
                MAX(last_ping_at) as last_update
            ')->first();

            Cache::put('realtime_stats', [
                'total_dvrs' => (int) $stats->total_dvrs,
                'online_dvrs' => (int) $stats->online_dvrs,
                'offline_dvrs' => (int) $stats->offline_dvrs,
                'unknown_dvrs' => (int) $stats->unknown_dvrs,
                'avg_response_time' => $stats->avg_response_time ? round($stats->avg_response_time, 2) : null,
                'last_update' => $stats->last_update,
                'updated_at' => now('Asia/Kolkata')->toISOString()
            ], self::CACHE_TTL);
        } catch (\Exception $e) {
            Log::error('Failed to update realtime cache: ' . $e->getMessage());
        }
    }

    /**
     * Get monitoring status
     */
    public function getMonitoringStatus(): array
    {
        return Cache::get('automated_monitoring_status', [
            'active' => false,
            'message' => 'Monitoring not started'
        ]);
    }

    /**
     * Stop automated monitoring
     */
    public function stopMonitoring(): array
    {
        try {
            Cache::forget('automated_monitoring_status');
            Cache::forget('next_monitoring_run');

            return [
                'success' => true,
                'message' => 'Simplified automated monitoring stopped',
                'stopped_at' => now('Asia/Kolkata')->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to stop monitoring: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process a batch of DVRs in parallel using multi-processing
     */
    private function processBatchInParallel($batch, int $batchNumber): array
    {
        $results = [];
        $processes = [];
        $pipes = [];

        $this->logToConsoleAndFile("   🚀 Starting parallel processing for batch {$batchNumber} ({$batch->count()} DVRs)");

        // Create parallel processes for each DVR in the batch
        foreach ($batch as $index => $dvr) {
            $cmd = $this->buildMonitoringCommand($dvr);

            $descriptorspec = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];

            $process = proc_open($cmd, $descriptorspec, $pipe);

            if (is_resource($process)) {
                $processes[$index] = $process;
                $pipes[$index] = $pipe;
                fclose($pipe[0]); // Close stdin
            } else {
                // Fallback to sequential processing for this DVR
                $results[$index] = $this->monitorSingleDvr($dvr);
            }
        }

        // Collect results from all processes
        foreach ($processes as $index => $process) {
            $output = stream_get_contents($pipes[$index][1]);
            $error = stream_get_contents($pipes[$index][2]);

            fclose($pipes[$index][1]);
            fclose($pipes[$index][2]);

            $returnCode = proc_close($process);

            // Parse the output to get monitoring result
            $result = $this->parseMonitoringOutput($output, $batch[$index], $returnCode);
            $results[$index] = $result;
        }

        return $results;
    }

    /**
     * Build command for parallel DVR monitoring
     */
    private function buildMonitoringCommand($dvr): string
    {
        $phpPath = PHP_BINARY;
        $scriptPath = base_path('monitor_single_dvr.php');

        // Create the monitoring script if it doesn't exist
        $this->ensureMonitoringScriptExists();

        return "{$phpPath} {$scriptPath} {$dvr->id} {$dvr->ip} {$dvr->port} 2>&1";
    }

    /**
     * Ensure the single DVR monitoring script exists
     */
    private function ensureMonitoringScriptExists(): void
    {
        $scriptPath = base_path('monitor_single_dvr.php');

        if (!file_exists($scriptPath)) {
            $scriptContent = $this->getMonitoringScriptContent();
            file_put_contents($scriptPath, $scriptContent);
        }
    }

    /**
     * Get the content for the single DVR monitoring script
     */
    private function getMonitoringScriptContent(): string
    {
        return '<?php
// Single DVR Monitoring Script for Parallel Processing
require_once __DIR__ . "/vendor/autoload.php";

$app = require_once __DIR__ . "/bootstrap/app.php";
$app->make("Illuminate\\Contracts\\Console\\Kernel")->bootstrap();

use App\\Models\\Dvr;
use App\\Models\\DvrMonitoringLog;

if ($argc < 4) {
    echo json_encode(["error" => "Missing arguments"]);
    exit(1);
}

$dvrId = $argv[1];
$ip = $argv[2];
$port = $argv[3];

try {
    $dvr = Dvr::find($dvrId);
    if (!$dvr) {
        echo json_encode(["error" => "DVR not found"]);
        exit(1);
    }

    $startTime = microtime(true);
    
    // Perform ping check
    $pingResult = performPingCheck($ip);
    
    // Perform HTTP check
    $httpResult = performHttpCheck($ip, $port);
    
    // Determine status
    $status = determineStatus($pingResult, $httpResult);
    $responseTime = $pingResult["success"] ? $pingResult["time"] : $httpResult["time"];
    
    // Update DVR in database
    $dvr->update([
        "status" => $status,
        "ping_response_time" => $responseTime,
        "api_accessible" => $httpResult["success"],
        "last_ping_at" => now("Asia/Kolkata"),
        "consecutive_failures" => $status === "offline" ? $dvr->consecutive_failures + 1 : 0,
        "updated_at" => now("Asia/Kolkata")
    ]);

    // Log result
    DvrMonitoringLog::create([
        "dvr_id" => $dvr->id,
        "check_type" => "parallel",
        "result" => $status === "online" ? "success" : "failure",
        "response_time" => (int) $responseTime,
        "response_data" => json_encode([
            "parallel" => true,
            "ping_success" => $pingResult["success"],
            "http_success" => $httpResult["success"],
            "http_code" => $httpResult["code"] ?? 0,
            "total_time" => round((microtime(true) - $startTime) * 1000, 2),
            "timezone" => "Asia/Kolkata"
        ]),
        "error_message" => $httpResult["error"] ?? $pingResult["error"] ?? null,
        "created_at" => now("Asia/Kolkata"),
        "updated_at" => now("Asia/Kolkata")
    ]);

    echo json_encode([
        "success" => true,
        "dvr_id" => $dvr->id,
        "status" => $status,
        "response_time" => $responseTime,
        "checked_at" => now("Asia/Kolkata")->toISOString()
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "dvr_id" => $dvrId,
        "error" => $e->getMessage(),
        "status" => "offline"
    ]);
}

function performPingCheck(string $ip): array
{
    $startTime = microtime(true);
    $output = [];
    $returnCode = 0;
    
    if (PHP_OS_FAMILY === "Windows") {
        exec("ping -n 1 -w 1000 {$ip} 2>nul", $output, $returnCode);
    } else {
        exec("ping -c 1 -W 1 {$ip} 2>/dev/null", $output, $returnCode);
    }
    
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    $success = $returnCode === 0;
    
    $actualPingTime = $responseTime;
    foreach ($output as $line) {
        if (preg_match("/time[<=](\\d+)ms/i", $line, $matches)) {
            $actualPingTime = (float)$matches[1];
            break;
        } elseif (preg_match("/time=(\\d+\\.?\\d*)ms/i", $line, $matches)) {
            $actualPingTime = (float)$matches[1];
            break;
        }
    }
    
    return [
        "success" => $success,
        "time" => $success ? $actualPingTime : $responseTime,
        "error" => $success ? "" : "Ping timeout or unreachable"
    ];
}

function performHttpCheck(string $ip, int $port): array
{
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}:{$port}/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => "DVR-Monitor/1.0",
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true,
        CURLOPT_FAILONERROR => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    curl_close($ch);

    $success = $httpCode > 0 && $httpCode < 600;
    
    return [
        "success" => $success,
        "time" => $responseTime,
        "code" => $httpCode,
        "error" => $error
    ];
}

function determineStatus(array $pingResult, array $httpResult): string
{
    if ($pingResult["success"]) return "online";
    if ($httpResult["success"]) return "online";
    if ($httpResult["code"] > 0) return "online";
    return "offline";
}
';
    }

    /**
     * Parse monitoring output from parallel process
     */
    private function parseMonitoringOutput(string $output, $dvr, int $returnCode): array
    {
        $decoded = json_decode(trim($output), true);

        if ($decoded && is_array($decoded)) {
            return $decoded;
        }

        // Fallback if JSON parsing fails
        return [
            'success' => false,
            'dvr_id' => $dvr->id,
            'error' => 'Failed to parse monitoring output: ' . substr($output, 0, 100),
            'status' => 'offline',
            'checked_at' => now('Asia/Kolkata')->toISOString()
        ];
    }

    /**
     * Log message to both console and Laravel log
     */
    private function logToConsoleAndFile(string $message): void
    {
        $timestamp = now('Asia/Kolkata')->format('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}";

        // Log to Laravel log file
        Log::info($message);

        // Output to console if running in CLI or if we can detect console
        if (php_sapi_name() === 'cli' || defined('STDOUT')) {
            echo $formattedMessage . PHP_EOL;
            if (defined('STDOUT')) {
                fflush(STDOUT);
            }
        }

        // Also try to write to a real-time log file
        try {
            $logFile = storage_path('logs/realtime-monitoring.log');
            file_put_contents($logFile, $formattedMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Ignore file write errors
        }
    }
}
