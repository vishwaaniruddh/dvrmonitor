<?php

namespace App\Console\Commands;

use App\Models\Dvr;
use App\Services\EnhancedMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnhancedMonitoringCommand extends Command
{
    protected $signature = 'dvr:enhanced-monitor 
                            {--type=full_check : Type of check (ping, api_details, full_check)}
                            {--dvr-id= : Monitor specific DVR ID}
                            {--batch-size=50 : Number of DVRs to process in each batch}
                            {--delay=1 : Delay between DVR checks in seconds}';

    protected $description = 'Enhanced DVR monitoring with device time and API checks';

    protected $enhancedMonitoringService;

    public function __construct(EnhancedMonitoringService $enhancedMonitoringService)
    {
        parent::__construct();
        $this->enhancedMonitoringService = $enhancedMonitoringService;
    }

    public function handle()
    {
        $checkType = $this->option('type');
        $dvrId = $this->option('dvr-id');
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');

        $this->info("🚀 Starting Enhanced DVR Monitoring");
        $this->info("Check Type: {$checkType}");
        $this->info("Batch Size: {$batchSize}");
        $this->info("Delay: {$delay}s");
        $this->line('');

        if ($dvrId) {
            // Monitor specific DVR
            $this->monitorSingleDvr($dvrId, $checkType);
        } else {
            // Monitor all active DVRs
            $this->monitorAllDvrs($checkType, $batchSize, $delay);
        }

        $this->info("\n✅ Enhanced monitoring completed!");
        
        // Show summary statistics
        $this->showSummaryStats();
    }

    protected function monitorSingleDvr($dvrId, $checkType)
    {
        try {
            $dvr = Dvr::findOrFail($dvrId);
            $this->info("Monitoring DVR: {$dvr->dvr_name} ({$dvr->ip}:{$dvr->port})");
            
            $result = $this->enhancedMonitoringService->monitorDvr($dvr, $checkType);
            
            $this->displayResult($dvr, $result);
            
        } catch (\Exception $e) {
            $this->error("Error monitoring DVR {$dvrId}: {$e->getMessage()}");
        }
    }

    protected function monitorAllDvrs($checkType, $batchSize, $delay)
    {
        $activeDvrs = Dvr::where('is_active', true)->get(['id', 'dvr_name', 'ip', 'port']);
        $totalDvrs = $activeDvrs->count();
        
        if ($totalDvrs === 0) {
            $this->warn('No active DVRs to monitor');
            return;
        }
        
        $this->info("Found {$totalDvrs} active DVRs to monitor");
        
        $progressBar = $this->output->createProgressBar($totalDvrs);
        $progressBar->start();
        
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $apiSuccess = 0;
        
        foreach ($activeDvrs->chunk($batchSize) as $batch) {
            foreach ($batch as $dvr) {
                try {
                    $result = $this->enhancedMonitoringService->monitorDvr($dvr, $checkType);
                    
                    if ($result['ping_success']) {
                        $successful++;
                    } else {
                        $failed++;
                    }
                    
                    if ($result['api_success']) {
                        $apiSuccess++;
                    }
                    
                    $processed++;
                    $progressBar->advance();
                    
                    // Add delay between checks to avoid overwhelming DVRs
                    if ($delay > 0) {
                        sleep($delay);
                    }
                    
                } catch (\Exception $e) {
                    $failed++;
                    $processed++;
                    $progressBar->advance();
                    
                    Log::error("Enhanced monitoring failed for DVR {$dvr->id}: {$e->getMessage()}");
                }
            }
        }
        
        $progressBar->finish();
        $this->line('');
        
        // Display batch summary
        $this->info("\nBatch Summary:");
        $this->info("✅ Successful pings: {$successful}/{$totalDvrs}");
        $this->info("❌ Failed pings: {$failed}/{$totalDvrs}");
        $this->info("🔗 API logins successful: {$apiSuccess}/{$totalDvrs}");
        $this->info("📊 Success rate: " . round(($successful / $totalDvrs) * 100, 1) . "%");
    }

    protected function displayResult($dvr, $result)
    {
        $pingStatus = $result['ping_success'] ? '✅ ONLINE' : '❌ OFFLINE';
        $apiStatus = $result['api_success'] ? '🔗 API OK' : '🚫 API FAIL';
        
        $this->line("Status: {$pingStatus} | {$apiStatus}");
        
        if ($result['response_time']) {
            $this->line("Response Time: {$result['response_time']}ms");
        }
        
        if ($result['dvr_time']) {
            $this->line("DVR Time: {$result['dvr_time']}");
            
            // Check time sync
            $dvr->refresh();
            if ($dvr->device_time_offset_minutes !== null) {
                $offset = abs($dvr->device_time_offset_minutes);
                $syncStatus = $offset <= 5 ? '✅ SYNCED' : '⚠️ OUT OF SYNC';
                $this->line("Time Sync: {$syncStatus} (offset: {$dvr->device_time_offset_minutes} min)");
            }
        }
        
        if (!empty($result['message'])) {
            $this->line("Message: {$result['message']}");
        }
    }

    protected function showSummaryStats()
    {
        $stats = $this->enhancedMonitoringService->getMonitoringStats(1); // Last hour
        
        $this->info("\n📊 Monitoring Statistics (Last Hour):");
        $this->info("Total Checks: {$stats['total_checks']}");
        $this->info("Successful Pings: {$stats['successful_pings']}");
        $this->info("Successful API Logins: {$stats['successful_api_logins']}");
        $this->info("Online DVRs: {$stats['online_dvrs']}");
        $this->info("Offline DVRs: {$stats['offline_dvrs']}");
        $this->info("API Error DVRs: {$stats['api_error_dvrs']}");
        
        // Show DVRs with time sync issues
        $timeSyncIssues = Dvr::where('is_active', true)
            ->whereNotNull('device_time_offset_minutes')
            ->where(function($query) {
                $query->where('device_time_offset_minutes', '>', 5)
                      ->orWhere('device_time_offset_minutes', '<', -5);
            })
            ->count();
            
        if ($timeSyncIssues > 0) {
            $this->warn("⚠️ DVRs with time sync issues: {$timeSyncIssues}");
        }
    }
}