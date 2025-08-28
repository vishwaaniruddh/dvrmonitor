<?php

namespace App\Console\Commands;

use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WorkerMonitorCommand extends Command
{
    protected $signature = 'dvr:worker {worker_file}';
    protected $description = 'Process DVRs in a worker thread for ultra-fast monitoring';

    private const CONCURRENT_CONNECTIONS = 10; // Concurrent connections per worker
    private const CONNECTION_TIMEOUT = 1; // 1 second timeout for speed

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
        $timeout = $workerData['timeout'] ?? 1;

        $this->info("🔥 Worker {$workerId} starting with " . count($dvrs) . " DVRs");

        $startTime = microtime(true);
        $results = [];
        $processed = 0;

        // Process DVRs in concurrent batches
        $batches = array_chunk($dvrs, self::CONCURRENT_CONNECTIONS);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResults = $this->processBatchConcurrently($batch, $timeout);
            $results = array_merge($results, $batchResults);
            $processed += count($batch);
            
            // Update progress
            $progress = round(($processed / count($dvrs)) * 100, 1);
            $this->info("Worker {$workerId}: {$progress}% ({$processed}/" . count($dvrs) . ")");
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
            'completed_at' => date('Y-m-d H:i:s')
        ]));

        // Update database with results
        $this->updateDatabase($results);

        $this->info("✅ Worker {$workerId} completed: " . count($results) . " DVRs in " . 
                   round($executionTime, 2) . "s (" . round($dvrPerSecond, 2) . " DVRs/sec)");

        return 0;
    }

    /**
     * Process a batch of DVRs concurrently using cURL multi-handle
     */
    private function processBatchConcurrently(array $dvrs, int $timeout): array
    {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $results = [];

        // Initialize cURL handles for all DVRs in batch
        foreach ($dvrs as $index => $dvr) {
            $ch = curl_init();
            $url = "http://{$dvr['ip']}:{$dvr['port']}/";
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'DVR-Monitor/1.0',
                CURLOPT_NOBODY => true, // HEAD request for speed
                CURLOPT_HEADER => true,
            ]);

            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$index] = [
                'handle' => $ch,
                'dvr' => $dvr,
                'start_time' => microtime(true)
            ];
        }

        // Execute all requests concurrently
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        // Collect results
        foreach ($curlHandles as $index => $handleData) {
            $ch = $handleData['handle'];
            $dvr = $handleData['dvr'];
            $startTime = $handleData['start_time'];
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            // Determine status
            $status = 'offline';
            if ($httpCode >= 200 && $httpCode < 400) {
                $status = 'online';
            } elseif ($httpCode === 401 || $httpCode === 403) {
                $status = 'online'; // Authentication required but device is responding
            }

            $results[] = [
                'dvr_id' => $dvr['id'],
                'status' => $status,
                'response_time' => $responseTime,
                'http_code' => $httpCode,
                'error' => $error,
                'timestamp' => date('Y-m-d H:i:s'),
                'api_accessible' => in_array($httpCode, [200, 401, 403])
            ];

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
        return $results;
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
                    'check_type' => 'ping',
                    'result' => $result['status'] === 'online' ? 'success' : 'failure',
                    'response_time' => $result['response_time'],
                    'http_status_code' => $result['http_code'],
                    'error_message' => $result['error'],
                    'details' => json_encode([
                        'worker_processed' => true,
                        'concurrent_batch' => true
                    ])
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Worker database update failed: " . $e->getMessage());
        }
    }
}