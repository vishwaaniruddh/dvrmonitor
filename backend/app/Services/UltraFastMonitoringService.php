<?php

namespace App\Services;

use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UltraFastMonitoringService
{
    private const MONITORING_INTERVAL = 300; // 5 minutes
    private const CACHE_TTL = 60; // 1 minute cache
    private const BATCH_SIZE = 100; // Process 100 DVRs at once
    private const MAX_CONCURRENT = 100; // Maximum concurrent connections

    /**
     * Start continuous automated monitoring (ultra-fast version)
     */
    public function startContinuousMonitoring(): array
    {
        try {
            $activeDvrs = Dvr::where('is_active', true)->count();

            if ($activeDvrs === 0) {
                return [
                    'success' => false,
                    'message' => 'No active DVRs found to monitor'
                ];
            }

            Cache::put('automated_monitoring_status', [
                'active' => true,
                'started_at' => now('Asia/Kolkata'),
                'total_dvrs' => $activeDvrs,
                'last_cycle' => now('Asia/Kolkata')->toISOString(),
                'mode' => 'ultra_fast'
            ], self::CACHE_TTL * 10);

            $this->scheduleBackgroundMonitoring();

            return [
                'success' => true,
                'message' => 'Ultra-fast automated monitoring started successfully',
                'total_dvrs' => $activeDvrs,
                'monitoring_interval' => self::MONITORING_INTERVAL . ' seconds',
                'started_at' => now('Asia/Kolkata')->toISOString(),
                'mode' => 'ultra_fast',
                'max_concurrent' => self::MAX_CONCURRENT
            ];

        } catch (\Exception $e) {
            Log::error('Failed to start ultra-fast monitoring: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to start monitoring: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Run ultra-fast monitoring cycle using cURL multi-handle
     */
    public function runUltraFastMonitoringCycle(): void
    {
        $startTime = microtime(true);
        
        $activeDvrs = Dvr::where('is_active', true)->get(['id', 'dvr_name', 'ip', 'port']);
        $totalDvrs = $activeDvrs->count();
        
        if ($totalDvrs === 0) {
            $this->logToConsoleAndFile('No active DVRs to monitor');
            return;
        }

        $this->logToConsoleAndFile("🚀 Starting ULTRA-FAST monitoring cycle for {$totalDvrs} DVRs at " . now('Asia/Kolkata')->format('H:i:s'));
        
        $processed = 0;
        $online = 0;
        $offline = 0;

        // Process DVRs in ultra-fast parallel batches
        $batches = $activeDvrs->chunk(self::BATCH_SIZE);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchStart = microtime(true);
            
            // Process this batch with ultra-fast parallel processing
            $batchResults = $this->processUltraFastBatch($batch, $batchIndex + 1);
            
            $batchOnline = 0;
            $batchOffline = 0;
            
            foreach ($batchResults as $result) {
                $processed++;
                if ($result['status'] === 'online') {
                    $online++;
                    $batchOnline++;
                } else {
                    $offline++;
                    $batchOffline++;
                }
            }
            
            $batchTime = round(microtime(true) - $batchStart, 2);
            $this->logToConsoleAndFile("   ⚡ Batch " . ($batchIndex + 1) . ": {$batch->count()} DVRs in {$batchTime}s ({$batchOnline} online, {$batchOffline} offline) [ULTRA-FAST]");
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        
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
            'mode' => 'ultra_fast'
        ], self::CACHE_TTL * 10);

        $this->logToConsoleAndFile("✅ ULTRA-FAST monitoring cycle completed: {$processed} DVRs in {$executionTime}s ({$online} online, {$offline} offline)");
        $this->logToConsoleAndFile("⚡ Speed: " . round($processed / $executionTime, 1) . " DVRs/second");
        
        $this->updateRealtimeCache();
    }

    /**
     * Process batch using ultra-fast cURL multi-handle
     */
    private function processUltraFastBatch($batch, int $batchNumber): array
    {
        $this->logToConsoleAndFile("   ⚡ Starting ultra-fast processing for batch {$batchNumber} ({$batch->count()} DVRs)");
        
        $results = [];
        $curlHandles = [];
        $multiHandle = curl_multi_init();
        
        // Set multi-handle options for maximum performance
        curl_multi_setopt($multiHandle, CURLMOPT_MAX_TOTAL_CONNECTIONS, self::MAX_CONCURRENT);
        curl_multi_setopt($multiHandle, CURLMOPT_MAX_HOST_CONNECTIONS, 10);
        
        // Create cURL handles for all DVRs in batch
        foreach ($batch as $index => $dvr) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "http://{$dvr->ip}:{$dvr->port}/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2, // Very fast timeout
                CURLOPT_CONNECTTIMEOUT => 1, // Very fast connect timeout
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'DVR-UltraFast/1.0',
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => false,
                CURLOPT_FAILONERROR => false,
                CURLOPT_NOSIGNAL => 1, // Important for multi-threading
            ]);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$index] = ['handle' => $ch, 'dvr' => $dvr, 'start_time' => microtime(true)];
        }
        
        // Execute all handles simultaneously
        $running = null;
        do {
            $mrc = curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle, 0.1); // Small delay to prevent CPU spinning
            }
        } while ($running > 0 && $mrc == CURLM_OK);
        
        // Collect results
        foreach ($curlHandles as $index => $handleData) {
            $ch = $handleData['handle'];
            $dvr = $handleData['dvr'];
            $startTime = $handleData['start_time'];
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $error = curl_error($ch);
            
            $responseTime = round($totalTime * 1000, 2); // Convert to milliseconds
            $status = $this->determineUltraFastStatus($httpCode, $error);
            
            // Update DVR in database
            $dvr->update([
                'status' => $status,
                'ping_response_time' => $responseTime,
                'api_accessible' => $httpCode > 0 && $httpCode < 600,
                'last_ping_at' => now('Asia/Kolkata'),
                'consecutive_failures' => $status === 'offline' ? $dvr->consecutive_failures + 1 : 0,
                'updated_at' => now('Asia/Kolkata')
            ]);

            // Log monitoring result
            DvrMonitoringLog::create([
                'dvr_id' => $dvr->id,
                'check_type' => 'ultra_fast',
                'result' => $status === 'online' ? 'success' : 'failure',
                'response_time' => (int) $responseTime,
                'response_data' => json_encode([
                    'ultra_fast' => true,
                    'http_code' => $httpCode,
                    'total_time' => round((microtime(true) - $startTime) * 1000, 2),
                    'timezone' => 'Asia/Kolkata'
                ]),
                'error_message' => $error ?: null,
                'created_at' => now('Asia/Kolkata'),
                'updated_at' => now('Asia/Kolkata')
            ]);

            $results[$index] = [
                'success' => true,
                'dvr_id' => $dvr->id,
                'status' => $status,
                'response_time' => $responseTime,
                'http_code' => $httpCode
            ];
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multiHandle);
        
        return $results;
    }

    /**
     * Determine status for ultra-fast monitoring (FIXED)
     */
    private function determineUltraFastStatus(int $httpCode, string $error): string
    {
        // Only consider 2xx and 3xx HTTP codes as "online"
        if ($httpCode >= 200 && $httpCode < 400) {
            return 'online';
        }
        
        // 4xx and 5xx errors mean the server is responding but with errors
        // This could be considered "online" but with issues, or "offline"
        // For DVR monitoring, let's be strict: only 2xx/3xx = online
        if ($httpCode >= 400 && $httpCode < 600) {
            return 'offline'; // Server responding but with errors
        }
        
        // No HTTP code (0) usually means connection failed
        if ($httpCode === 0) {
            return 'offline';
        }
        
        return 'offline';
    }

    /**
     * Schedule background monitoring
     */
    private function scheduleBackgroundMonitoring(): void
    {
        $nextRun = now('Asia/Kolkata')->addMinutes(5);
        Cache::put('next_monitoring_run', $nextRun->toISOString(), self::CACHE_TTL * 10);
        
        Log::info("📅 Next ultra-fast monitoring cycle scheduled for: " . $nextRun->format('Y-m-d H:i:s T'));
    }

    /**
     * Check and run monitoring if needed
     */
    public function checkAndRunMonitoring(): void
    {
        try {
            $status = Cache::get('automated_monitoring_status', ['active' => false]);
            
            if (!$status['active']) {
                return;
            }

            $nextRun = Cache::get('next_monitoring_run');
            if (!$nextRun || now('Asia/Kolkata') < \Carbon\Carbon::parse($nextRun)) {
                return;
            }

            $this->logToConsoleAndFile('🔄 Running scheduled ultra-fast monitoring cycle...');
            
            $this->runUltraFastMonitoringCycle();
            $this->scheduleBackgroundMonitoring();
            
        } catch (\Exception $e) {
            $this->logToConsoleAndFile('❌ Error in ultra-fast monitoring: ' . $e->getMessage());
        }
    }

    /**
     * Update real-time cache
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
            'message' => 'Ultra-fast monitoring not started'
        ]);
    }

    /**
     * Stop monitoring
     */
    public function stopMonitoring(): array
    {
        try {
            Cache::forget('automated_monitoring_status');
            Cache::forget('next_monitoring_run');
            
            return [
                'success' => true,
                'message' => 'Ultra-fast automated monitoring stopped',
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
     * Log message to both console and Laravel log
     */
    private function logToConsoleAndFile(string $message): void
    {
        $timestamp = now('Asia/Kolkata')->format('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}";
        
        Log::info($message);
        
        if (php_sapi_name() === 'cli' || defined('STDOUT')) {
            echo $formattedMessage . PHP_EOL;
            if (defined('STDOUT')) {
                fflush(STDOUT);
            }
        }
        
        try {
            $logFile = storage_path('logs/realtime-monitoring.log');
            file_put_contents($logFile, $formattedMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Ignore file write errors
        }
    }
}