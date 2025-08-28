<?php

namespace App\Console\Commands;

use App\Services\SimplifiedMonitoringService;
use App\Models\Dvr;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RealtimeMonitoringCommand extends Command
{
    protected $signature = 'dvr:monitor-realtime {--interval=30 : Check interval in seconds}';
    protected $description = 'Run DVR monitoring with real-time console output';

    protected $monitoringService;

    public function __construct(SimplifiedMonitoringService $monitoringService)
    {
        parent::__construct();
        $this->monitoringService = $monitoringService;
    }

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $interval = max(10, min(300, $interval)); // Between 10 seconds and 5 minutes

        $this->info('🚀 Starting Real-time DVR Monitoring');
        $this->info('=====================================');
        $this->info('Press Ctrl+C to stop monitoring');
        $this->info('');

        // Start monitoring service
        $result = $this->monitoringService->startContinuousMonitoring();
        if (!$result['success']) {
            $this->error('Failed to start monitoring: ' . $result['message']);
            return 1;
        }

        $this->info('✅ Monitoring service started successfully');
        $this->info("⏰ Check interval: {$interval} seconds");
        $this->info('');

        $cycleCount = 0;

        while (true) {
            $cycleCount++;
            $startTime = microtime(true);
            
            $this->info("🔄 Cycle #{$cycleCount} - " . now('Asia/Kolkata')->format('Y-m-d H:i:s T'));
            $this->line('----------------------------------------');

            // Get current stats
            $stats = $this->getCurrentStats();
            $this->displayStats($stats);

            // Run monitoring cycle
            $this->runMonitoringCycle();

            // Get updated stats
            $newStats = $this->getCurrentStats();
            $this->displayStatsComparison($stats, $newStats);

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("⏱️  Cycle completed in {$executionTime} seconds");
            $this->info("⏰ Next cycle in {$interval} seconds...");
            $this->line('');

            sleep($interval);
        }
    }

    private function getCurrentStats(): array
    {
        return Dvr::selectRaw('
            COUNT(*) as total_dvrs,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_dvrs,
            COUNT(CASE WHEN status = "online" THEN 1 END) as online_dvrs,
            COUNT(CASE WHEN status = "offline" THEN 1 END) as offline_dvrs,
            COUNT(CASE WHEN status = "unknown" OR status IS NULL THEN 1 END) as unknown_dvrs,
            AVG(CASE WHEN ping_response_time IS NOT NULL THEN ping_response_time END) as avg_response_time
        ')->first()->toArray();
    }

    private function displayStats(array $stats): void
    {
        $this->line("📊 Current Status:");
        $this->line("   Total DVRs: " . $stats['total_dvrs']);
        $this->line("   Active DVRs: " . $stats['active_dvrs']);
        $this->line("   🟢 Online: " . $stats['online_dvrs']);
        $this->line("   🔴 Offline: " . $stats['offline_dvrs']);
        $this->line("   ⚪ Unknown: " . $stats['unknown_dvrs']);
        $this->line("   ⚡ Avg Response: " . ($stats['avg_response_time'] ? round($stats['avg_response_time'], 2) . 'ms' : 'N/A'));
        $this->line('');
    }

    private function displayStatsComparison(array $before, array $after): void
    {
        $onlineChange = $after['online_dvrs'] - $before['online_dvrs'];
        $offlineChange = $after['offline_dvrs'] - $before['offline_dvrs'];

        if ($onlineChange != 0 || $offlineChange != 0) {
            $this->line("📈 Changes this cycle:");
            if ($onlineChange > 0) {
                $this->line("   🟢 +" . $onlineChange . " came online");
            } elseif ($onlineChange < 0) {
                $this->line("   🔴 " . abs($onlineChange) . " went offline");
            }
            
            if ($offlineChange > 0) {
                $this->line("   🔴 +" . $offlineChange . " went offline");
            } elseif ($offlineChange < 0) {
                $this->line("   🟢 " . abs($offlineChange) . " came online");
            }
        } else {
            $this->line("📊 No status changes this cycle");
        }
        $this->line('');
    }

    private function runMonitoringCycle(): void
    {
        $activeDvrs = Dvr::where('is_active', true)->get(['id', 'dvr_name', 'ip', 'port']);
        $totalDvrs = $activeDvrs->count();

        if ($totalDvrs === 0) {
            $this->warn('No active DVRs to monitor');
            return;
        }

        $this->line("🔍 Checking {$totalDvrs} active DVRs...");

        $processed = 0;
        $online = 0;
        $offline = 0;
        $batchSize = 20;

        $batches = $activeDvrs->chunk($batchSize);
        $progressBar = $this->output->createProgressBar($totalDvrs);
        $progressBar->start();

        foreach ($batches as $batchIndex => $batch) {
            $batchStart = microtime(true);
            $batchOnline = 0;
            $batchOffline = 0;

            foreach ($batch as $dvr) {
                $result = $this->monitoringService->monitorSingleDvr($dvr);
                $processed++;
                
                if ($result['success'] && $result['status'] === 'online') {
                    $online++;
                    $batchOnline++;
                } else {
                    $offline++;
                    $batchOffline++;
                }
                
                $progressBar->advance();
            }

            $batchTime = round(microtime(true) - $batchStart, 2);
        }

        $progressBar->finish();
        $this->line('');
        $this->line("✅ Processed {$processed} DVRs ({$online} online, {$offline} offline)");
    }
}