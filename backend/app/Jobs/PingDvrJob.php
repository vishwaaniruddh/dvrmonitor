<?php

namespace App\Jobs;

use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PingDvrJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 30;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Dvr $dvr
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            // Ping the DVR IP
            $pingResult = $this->pingHost($this->dvr->ip);
            $responseTime = (int)((microtime(true) - $startTime) * 1000);
            
            if ($pingResult) {
                // Ping successful
                $this->dvr->update([
                    'status' => 'online',
                    'last_ping_at' => now(),
                    'ping_response_time' => $responseTime,
                    'consecutive_failures' => 0
                ]);

                // Log success
                DvrMonitoringLog::create([
                    'dvr_id' => $this->dvr->id,
                    'check_type' => 'ping',
                    'result' => 'success',
                    'response_time' => $responseTime
                ]);

                // If ping successful, dispatch API fetch job
                FetchDvrDataJob::dispatch($this->dvr);
                
            } else {
                // Ping failed
                $this->handlePingFailure($responseTime);
            }
            
        } catch (\Exception $e) {
            $this->handlePingFailure(null, $e->getMessage());
            Log::error("Ping job failed for DVR {$this->dvr->id}: " . $e->getMessage());
        }
    }

    private function pingHost($ip, $timeout = 5): bool
    {
        // For Windows, use ping command
        $command = "ping -n 1 -w " . ($timeout * 1000) . " " . escapeshellarg($ip);
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }

    private function handlePingFailure(?int $responseTime, ?string $errorMessage = null): void
    {
        $consecutiveFailures = $this->dvr->consecutive_failures + 1;
        
        $this->dvr->update([
            'status' => 'offline',
            'consecutive_failures' => $consecutiveFailures,
            'ping_response_time' => $responseTime
        ]);

        // Log failure
        DvrMonitoringLog::create([
            'dvr_id' => $this->dvr->id,
            'check_type' => 'ping',
            'result' => 'failure',
            'response_time' => $responseTime,
            'error_message' => $errorMessage ?? 'Ping timeout or host unreachable'
        ]);
    }
}
