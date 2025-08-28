<?php

namespace App\Console\Commands;

use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HybridWorkerMonitorCommand extends Command
{
    protected $signature = 'dvr:hybrid-worker {worker_file}';
    protected $description = 'Process DVRs with hybrid monitoring (ICMP ping + HTTP check) for accurate status';

    private const CONCURRENT_CONNECTIONS = 10;
    private const PING_TIMEOUT = 2; // ICMP ping timeout
    private const HTTP_TIMEOUT = 5; // HTTP timeout

    public function handle()
    {
        $workerFile = $this->argument('worker_file');
        
        if (!file_exists($workerFile)) {
            $this->error("Worker file not found: {$workerFile}");
            return 1;
        }

        $workerData = json_decode(file_get_contents($workerFile), true);
        $dvrs = $workerData['dvrs'];
        $workerId = $workerData['worker_id'];

        $this->info("🔥 Hybrid Worker {$workerId} starting with " . count($dvrs) . " DVRs");

        $startTime = microtime(true);
        $results = [];
        $processed = 0;

        // Process DVRs in batches
        $batches = array_chunk($dvrs, self::CONCURRENT_CONNECTIONS);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResults = $this->processBatchHybrid($batch);
            $results = array_merge($results, $batchResults);
            $processed += count($batch);
            
            // Update progress
            $progress = round(($processed / count($dvrs)) * 100, 1);
            $this->info("Hybrid Worker {$workerId}: {$progress}% ({$processed}/" . count($dvrs) . ")");
        }

        $executionTime = microtime(true) - $startTime;
        $dvrPerSecond = count($dvrs) / $executionTime;

        // Save results
        $resultFile = str_replace('.json', '_result.json', $workerFile);
        file_put_contents($resultFile, json_encode([
            'worker_id' => $workerId,
            'results' => $results,
            'execution_time' => $executionTime,
            'dvrs_per_second' => $dvrPerSecond,
            'total_processed' => count($results),
            'completed_at' => now('Asia/Kolkata')->format('Y-m-d H:i:s'),
            'monitoring_type' => 'hybrid'
        ]));

        // Update database with results
        $this->updateDatabase($results);

        $this->info("✅ Hybrid Worker {$workerId} completed: " . count($results) . " DVRs in " . 
                   round($executionTime, 2) . "s (" . round($dvrPerSecond, 2) . " DVRs/sec)");

        return 0;
    }

    /**
     * Process a batch with hybrid monitoring (ICMP + HTTP)
     */
    private function processBatchHybrid(array $dvrs): array
    {
        $results = [];
        
        foreach ($dvrs as $dvr) {
            $startTime = microtime(true);
            
            // Step 1: ICMP Ping test (primary connectivity check)
            $pingResult = $this->testPing($dvr['ip']);
            
            // Step 2: HTTP test (web interface check)
            $httpResult = $this->testHttp($dvr);
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Determine final status using hybrid logic
            $status = $this->determineHybridStatus($pingResult, $httpResult);
            
            $results[] = [
                'dvr_id' => $dvr['id'],
                'status' => $status,
                'response_time' => $pingResult['success'] ? $pingResult['time'] : $httpResult['time'],
                'http_code' => $httpResult['code'],
                'error' => $httpResult['error'] ?: $pingResult['error'],
                'timestamp' => now('Asia/Kolkata')->format('Y-m-d H:i:s'),
                'api_accessible' => $httpResult['success'],
                'ping_success' => $pingResult['success'],
                'http_success' => $httpResult['success'],
                'ping_time' => $pingResult['time'],
                'http_time' => $httpResult['time'],
                'total_check_time' => $totalTime
            ];
        }
        
        return $results;
    }

    /**
     * Test ICMP ping connectivity
     */
    private function testPing(string $ip): array
    {
        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows ping command
            exec("ping -n 1 -w " . (self::PING_TIMEOUT * 1000) . " {$ip} 2>nul", $output, $returnCode);
        } else {
            // Linux/Mac ping command
            exec("ping -c 1 -W " . self::PING_TIMEOUT . " {$ip} 2>/dev/null", $output, $returnCode);
        }
        
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $success = $returnCode === 0;
        
        // Extract actual ping time from output if available
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
            'error' => $success ? '' : 'Ping timeout or unreachable',
            'output' => implode(' ', $output)
        ];
    }

    /**
     * Test HTTP connectivity
     */
    private function testHttp(array $dvr): array
    {
        $startTime = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://{$dvr['ip']}:{$dvr['port']}/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_NOBODY => true, // HEAD request for speed
            CURLOPT_HEADER => true,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        curl_close($ch);

        // HTTP is successful if we get any meaningful response
        $success = $httpCode > 0 && $httpCode < 600;
        
        return [
            'success' => $success,
            'time' => $responseTime,
            'code' => $httpCode,
            'error' => $error
        ];
    }

    /**
     * Determine final status using hybrid logic
     */
    private function determineHybridStatus(array $pingResult, array $httpResult): string
    {
        // Priority 1: If ping works, device is definitely online
        if ($pingResult['success']) {
            return 'online';
        }
        
        // Priority 2: If HTTP works but ping failed, still online (firewall might block ICMP)
        if ($httpResult['success']) {
            return 'online';
        }
        
        // Priority 3: If we get any HTTP response (even errors), device is responding
        if ($httpResult['code'] > 0) {
            return 'online';
        }
        
        // Only mark offline if both ping and HTTP completely failed
        return 'offline';
    }

    /**
     * Update database with monitoring results
     */
    private function updateDatabase(array $results): void
    {
        try {
            foreach ($results as $result) {
                // Update DVR status
                Dvr::where('id', $result['dvr_id'])->update([
                    'status' => $result['status'],
                    'ping_response_time' => $result['response_time'],
                    'api_accessible' => $result['api_accessible'],
                    'last_ping_at' => now('Asia/Kolkata'),
                    'consecutive_failures' => $result['status'] === 'offline' ? 
                        \DB::raw('consecutive_failures + 1') : 0
                ]);

                // Log monitoring result with hybrid details
                DvrMonitoringLog::create([
                    'dvr_id' => $result['dvr_id'],
                    'check_type' => 'hybrid_ping_http',
                    'result' => $result['status'] === 'online' ? 'success' : 'failure',
                    'response_time' => $result['response_time'],
                    'http_status_code' => $result['http_code'],
                    'error_message' => $result['error'],
                    'details' => json_encode([
                        'hybrid_monitoring' => true,
                        'ping_success' => $result['ping_success'],
                        'http_success' => $result['http_success'],
                        'ping_time' => $result['ping_time'],
                        'http_time' => $result['http_time'],
                        'total_check_time' => $result['total_check_time'],
                        'monitoring_version' => '3.0'
                    ])
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Hybrid worker database update failed: " . $e->getMessage());
        }
    }
}