<?php

namespace App\Console\Commands;

use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RobustWorkerMonitorCommand extends Command
{
    protected $signature = 'dvr:robust-worker {worker_file}';
    protected $description = 'Process DVRs with robust monitoring logic to avoid false negatives';

    private const CONCURRENT_CONNECTIONS = 8; // Reduced for stability
    private const CONNECTION_TIMEOUT = 3; // Increased timeout
    private const CONNECT_TIMEOUT = 2; // Separate connect timeout
    private const MAX_RETRIES = 2; // Retry failed connections

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

        $this->info("🔥 Robust Worker {$workerId} starting with " . count($dvrs) . " DVRs");

        $startTime = microtime(true);
        $results = [];
        $processed = 0;

        // Process DVRs in smaller concurrent batches for better reliability
        $batches = array_chunk($dvrs, self::CONCURRENT_CONNECTIONS);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResults = $this->processBatchWithRetry($batch);
            $results = array_merge($results, $batchResults);
            $processed += count($batch);
            
            // Update progress
            $progress = round(($processed / count($dvrs)) * 100, 1);
            $this->info("Robust Worker {$workerId}: {$progress}% ({$processed}/" . count($dvrs) . ")");
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
            'completed_at' => date('Y-m-d H:i:s'),
            'monitoring_type' => 'robust'
        ]));

        // Update database with results
        $this->updateDatabase($results);

        $this->info("✅ Robust Worker {$workerId} completed: " . count($results) . " DVRs in " . 
                   round($executionTime, 2) . "s (" . round($dvrPerSecond, 2) . " DVRs/sec)");

        return 0;
    }

    /**
     * Process a batch with retry logic for failed connections
     */
    private function processBatchWithRetry(array $dvrs): array
    {
        $results = $this->processBatchConcurrently($dvrs);
        
        // Retry failed connections
        $failedDvrs = [];
        foreach ($results as $index => $result) {
            if ($result['status'] === 'offline' && $result['http_code'] === 0) {
                $failedDvrs[] = $dvrs[$index];
            }
        }
        
        if (!empty($failedDvrs) && count($failedDvrs) <= 3) { // Only retry small number of failures
            $this->info("Retrying " . count($failedDvrs) . " failed connections...");
            sleep(1); // Brief pause before retry
            
            $retryResults = $this->processBatchConcurrently($failedDvrs);
            
            // Replace failed results with retry results
            $retryIndex = 0;
            foreach ($results as $index => $result) {
                if ($result['status'] === 'offline' && $result['http_code'] === 0 && $retryIndex < count($retryResults)) {
                    $results[$index] = $retryResults[$retryIndex];
                    $retryIndex++;
                }
            }
        }
        
        return $results;
    }

    /**
     * Process a batch of DVRs concurrently with robust logic
     */
    private function processBatchConcurrently(array $dvrs): array
    {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $results = [];

        // Initialize cURL handles with robust settings
        foreach ($dvrs as $index => $dvr) {
            $ch = curl_init();
            $url = "http://{$dvr['ip']}:{$dvr['port']}/";
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::CONNECTION_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true, // Follow redirects
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: keep-alive',
                    'Cache-Control: no-cache'
                ],
                // Try GET request instead of HEAD for better compatibility
                CURLOPT_NOBODY => false,
                CURLOPT_HEADER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                // Add more robust error handling
                CURLOPT_FAILONERROR => false, // Don't fail on HTTP errors
                CURLOPT_LOW_SPEED_LIMIT => 1, // Minimum speed
                CURLOPT_LOW_SPEED_TIME => 2,  // Time limit for minimum speed
            ]);

            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$index] = [
                'handle' => $ch,
                'dvr' => $dvr,
                'start_time' => microtime(true)
            ];
        }

        // Execute all requests concurrently with better error handling
        $running = null;
        do {
            $mrc = curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle, 0.1); // Small delay to prevent CPU spinning
            }
        } while ($running > 0 && $mrc === CURLM_OK);

        // Collect results with more comprehensive status determination
        foreach ($curlHandles as $index => $handleData) {
            $ch = $handleData['handle'];
            $dvr = $handleData['dvr'];
            $startTime = $handleData['start_time'];
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            
            // More comprehensive status determination
            $status = $this->determineStatus($httpCode, $error, $curlInfo, $responseTime);
            
            $results[] = [
                'dvr_id' => $dvr['id'],
                'status' => $status,
                'response_time' => $responseTime,
                'http_code' => $httpCode,
                'error' => $error,
                'timestamp' => date('Y-m-d H:i:s'),
                'api_accessible' => $this->isApiAccessible($httpCode, $error),
                'curl_info' => [
                    'total_time' => $curlInfo['total_time'] ?? 0,
                    'connect_time' => $curlInfo['connect_time'] ?? 0,
                    'primary_ip' => $curlInfo['primary_ip'] ?? '',
                ]
            ];

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
        return $results;
    }

    /**
     * Determine DVR status with comprehensive logic
     */
    private function determineStatus(int $httpCode, string $error, array $curlInfo, float $responseTime): string
    {
        // Connection successful cases
        if ($httpCode >= 200 && $httpCode < 300) {
            return 'online'; // Success responses
        }
        
        if (in_array($httpCode, [301, 302, 303, 307, 308])) {
            return 'online'; // Redirects - device is responding
        }
        
        if (in_array($httpCode, [401, 403, 405])) {
            return 'online'; // Authentication/method errors - device is responding
        }
        
        if ($httpCode === 404) {
            return 'online'; // Not found - device is responding but no web interface
        }
        
        if ($httpCode >= 400 && $httpCode < 500) {
            return 'online'; // Client errors - device is responding
        }
        
        if ($httpCode >= 500 && $httpCode < 600) {
            return 'online'; // Server errors - device is responding but has issues
        }
        
        // Check if we got any response at all
        if ($httpCode > 0) {
            return 'online'; // Any HTTP response means device is reachable
        }
        
        // Check connection time - if we connected but got no HTTP response
        $connectTime = $curlInfo['connect_time'] ?? 0;
        if ($connectTime > 0 && $connectTime < 2.0) {
            return 'online'; // Connected successfully but no HTTP response
        }
        
        // Check for specific network errors that might indicate device is online
        if (strpos($error, 'Empty reply from server') !== false) {
            return 'online'; // Device responded but sent empty response
        }
        
        if (strpos($error, 'Recv failure') !== false) {
            return 'online'; // Connection established but data transfer failed
        }
        
        // Only mark as offline if we're sure it's unreachable
        return 'offline';
    }

    /**
     * Determine if API is accessible
     */
    private function isApiAccessible(int $httpCode, string $error): bool
    {
        // API is accessible if we get any meaningful HTTP response
        return $httpCode > 0 && $httpCode !== 0;
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
                    'last_ping_at' => now(),
                    'consecutive_failures' => $result['status'] === 'offline' ? 
                        \DB::raw('consecutive_failures + 1') : 0
                ]);

                // Log monitoring result
                DvrMonitoringLog::create([
                    'dvr_id' => $result['dvr_id'],
                    'check_type' => 'robust_ping',
                    'result' => $result['status'] === 'online' ? 'success' : 'failure',
                    'response_time' => $result['response_time'],
                    'http_status_code' => $result['http_code'],
                    'error_message' => $result['error'],
                    'details' => json_encode([
                        'robust_worker' => true,
                        'curl_info' => $result['curl_info'] ?? [],
                        'monitoring_version' => '2.0'
                    ])
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Robust worker database update failed: " . $e->getMessage());
        }
    }
}