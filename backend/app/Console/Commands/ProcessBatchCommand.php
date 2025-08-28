<?php

namespace App\Console\Commands;

use App\Services\HighPerformanceMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessBatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dvr:process-batch {batch_file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a batch of DVRs for high-performance monitoring';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchFile = $this->argument('batch_file');
        
        if (!file_exists($batchFile)) {
            Log::error("Batch file not found: {$batchFile}");
            return 1;
        }

        try {
            $dvrs = json_decode(file_get_contents($batchFile), true);
            
            if (!$dvrs) {
                Log::error("Invalid batch file format: {$batchFile}");
                return 1;
            }

            $service = new HighPerformanceMonitoringService();
            $results = $service->processBatch($dvrs);

            // Write results to file
            $resultFile = str_replace('.json', '_result.json', $batchFile);
            file_put_contents($resultFile, json_encode($results));

            Log::info("Processed batch with " . count($dvrs) . " DVRs, results saved to {$resultFile}");
            
            return 0;
            
        } catch (\Exception $e) {
            Log::error("Batch processing failed: " . $e->getMessage());
            return 1;
        }
    }
}
