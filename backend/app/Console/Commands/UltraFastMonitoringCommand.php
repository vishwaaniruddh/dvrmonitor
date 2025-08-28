<?php

namespace App\Console\Commands;

use App\Services\UltraFastMonitoringService;
use App\Models\Dvr;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UltraFastMonitoringCommand extends Command
{
    protected $signature = 'dvr:monitor-ultrafast {--interval=30 : Check interval in seconds}';
    protected $description = 'Run DVR monitoring with ultra-fast parallel processing';

    protected $monitoringService;

    public function __construct(UltraFastMonitoringService $monitoringService)
    {
        parent::__construct();
        $this->monitoringService = $monitoringService;
    }

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $interval = max(10, min(300, $interval));

        $this->info('⚡ Starting ULTRA-FAST DVR Monitoring');
        $this->info('=====================================');
        $this->info('🚀 Using cURL multi-handle for maximum speed');
        $this->info('⚡ Processing up to 100 DVRs simultaneously');
        $this->info('Press Ctrl+C to stop monitoring');
        $this->info('');

        // Start ultra-fast monitoring service
        $result = $this->monitoringService->startContinuousMonitoring();
        if (!$result['success']) {
            $this->error('Failed to start ultra-fast monitoring: ' . $result['message']);
            return 1;
        }

        $this->info('✅ Ultra-fast monitoring service started successfully');
        $this->info("⚡ Max concurrent connections: " . $result['max_concurrent']);
        $this->info("⏰ Check interval: {$interval} seconds");
        $this->info('');

        $cycleCount = 0;

        while (true) {
            $cycleCount++;
            $startTime = microtime(true);
            
            $this->info("⚡ ULTRA-FAST Cycle #{$cycleCount} - " . now('Asia/Kolkata')->format('Y-m-d H:i:s T'));
            $this->line('================================================');

            // Get current stats
            $stats = $this->getCurrentStats();
            $this->displayStats($stats);

            // Run ultra-fast monitoring cycle
            $this->runUltraFastMonitoringCycle();

            // Get updated stats
            $newStats = $this->getCurrentStats();
            $this->displayStatsComparison($stats, $newStats);

            $executionTime = round(microtime(true) - $startTime, 2);
            $speed = $stats['active_dvrs'] > 0 ? round($stats['active_dvrs'] / $executionTime, 1) : 0;
            
            $this->info("⚡ Ultra-fast cycle completed in {$executionTime} seconds");
            $this->info("🚀 Processing speed: {$speed} DVRs/second");
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
        $this->line("   Total DVRs: " . number_format($stats['total_dvrs']));
        $this->line("   Active DVRs: " . number_format($stats['active_dvrs']));
        $this->line("   🟢 Online: " . number_format($stats['online_dvrs']));
        $this->line("   🔴 Offline: " . number_format($stats['offline_dvrs']));
        $this->line("   ⚪ Unknown: " . number_format($stats['unknown_dvrs']));
        $this->line("   ⚡ Avg Response: " . ($stats['avg_response_time'] ? round($stats['avg_response_time'], 2) . 'ms' : 'N/A'));
        
        if ($stats['active_dvrs'] > 0) {
            $successRate = round(($stats['online_dvrs'] / ($stats['online_dvrs'] + $stats['offline_dvrs'])) * 100, 1);
            $this->line("   📈 Success Rate: {$successRate}%");
        }
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

    private function runUltraFastMonitoringCycle(): void
    {
        $activeDvrs = Dvr::where('is_active', true)->count();

        if ($activeDvrs === 0) {
            $this->warn('No active DVRs to monitor');
            return;
        }

        $this->line("⚡ Ultra-fast checking {$activeDvrs} active DVRs...");

        $progressBar = $this->output->createProgressBar($activeDvrs);
        $progressBar->setFormat('⚡ Processing: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        $progressBar->start();

        // Run the actual ultra-fast monitoring
        $this->monitoringService->runUltraFastMonitoringCycle();

        $progressBar->finish();
        $this->line('');
    }
}