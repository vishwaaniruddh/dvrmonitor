<?php

namespace App\Console\Commands;

use App\Services\HighPerformanceMonitoringService;
use Illuminate\Console\Command;

class HighPerformanceMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dvr:monitor-fast 
                            {--all : Monitor all DVRs including inactive ones}
                            {--continuous : Run continuous monitoring}
                            {--interval=30 : Interval in seconds for continuous monitoring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'High-performance DVR monitoring with multiprocessing and real-time updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new HighPerformanceMonitoringService();
        $activeOnly = !$this->option('all');
        $continuous = $this->option('continuous');
        $interval = (int) $this->option('interval');

        $this->info('🚀 Starting High-Performance DVR Monitoring');
        $this->info('Features: Multiprocessing + Concurrent Connections + Real-time WebSocket Updates');
        $this->newLine();

        if ($continuous) {
            $this->info("Running continuous monitoring every {$interval} seconds...");
            $this->info('Press Ctrl+C to stop');
            $this->newLine();

            while (true) {
                $this->runMonitoringCycle($service, $activeOnly);
                sleep($interval);
            }
        } else {
            $this->runMonitoringCycle($service, $activeOnly);
        }
    }

    private function runMonitoringCycle(HighPerformanceMonitoringService $service, bool $activeOnly): void
    {
        $startTime = microtime(true);
        
        $this->info('⏱️  Starting monitoring cycle at ' . now()->format('H:i:s'));
        
        $result = $service->monitorAllDvrsMultiprocess($activeOnly);
        
        if ($result['success']) {
            $this->info("✅ Monitoring completed successfully!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total DVRs', $result['total_dvrs']],
                    ['Completed Batches', $result['completed_batches'] . '/' . $result['total_batches']],
                    ['Execution Time', $result['execution_time'] . 's'],
                    ['Performance', round($result['total_dvrs'] / $result['execution_time'], 2) . ' DVRs/second'],
                ]
            );
            
            // Show online/offline summary
            $onlineCount = 0;
            $offlineCount = 0;
            
            foreach ($result['results'] as $batchResults) {
                foreach ($batchResults as $dvrResult) {
                    if ($dvrResult['status'] === 'online') {
                        $onlineCount++;
                    } else {
                        $offlineCount++;
                    }
                }
            }
            
            $this->info("📊 Status Summary: {$onlineCount} online, {$offlineCount} offline");
            $this->info("🔄 Real-time updates broadcasted via WebSocket");
            
        } else {
            $this->error('❌ Monitoring failed: ' . $result['message']);
        }
        
        $this->newLine();
    }
}
