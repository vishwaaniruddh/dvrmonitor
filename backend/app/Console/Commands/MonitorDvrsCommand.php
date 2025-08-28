<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAllDvrsJob;
use App\Models\Dvr;
use Illuminate\Console\Command;

class MonitorDvrsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dvr:monitor 
                            {--group= : Monitor specific group only}
                            {--all : Monitor all DVRs including inactive ones}
                            {--stats : Show current DVR statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor all DVRs - ping and fetch data from active devices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('stats')) {
            $this->showStats();
            return;
        }

        $this->info('Starting DVR monitoring process...');
        
        $groupName = $this->option('group');
        $activeOnly = !$this->option('all');
        
        if ($groupName) {
            $this->info("Monitoring DVRs in group: {$groupName}");
        }
        
        if (!$activeOnly) {
            $this->info("Monitoring ALL DVRs (including inactive)");
        }

        // Get count of DVRs to be processed
        $query = Dvr::query();
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        if ($groupName) {
            $query->where('group_name', $groupName);
        }
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->warn('No DVRs found matching the criteria');
            return;
        }

        $this->info("Found {$count} DVRs to monitor");
        
        if (!$this->confirm('Do you want to proceed?')) {
            $this->info('Monitoring cancelled');
            return;
        }

        // Dispatch the main processing job
        ProcessAllDvrsJob::dispatch($groupName, $activeOnly);
        
        $this->info('DVR monitoring jobs have been dispatched to the queue');
        $this->info('Use "php artisan queue:work" to process the jobs');
        $this->info('Monitor progress with "php artisan dvr:monitor --stats"');
    }

    private function showStats()
    {
        $this->info('DVR Statistics:');
        $this->newLine();
        
        $total = Dvr::count();
        $active = Dvr::where('is_active', true)->count();
        $online = Dvr::where('status', 'online')->count();
        $offline = Dvr::where('status', 'offline')->count();
        $unknown = Dvr::where('status', 'unknown')->count();
        $apiAccessible = Dvr::where('api_accessible', true)->count();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total DVRs', $total],
                ['Active DVRs', $active],
                ['Online', $online],
                ['Offline', $offline],
                ['Unknown Status', $unknown],
                ['API Accessible', $apiAccessible],
            ]
        );
        
        // Show recent activity
        $this->newLine();
        $this->info('Recent Activity (Last 10 DVRs checked):');
        
        $recentDvrs = Dvr::whereNotNull('last_ping_at')
            ->orderBy('last_ping_at', 'desc')
            ->limit(10)
            ->get(['id', 'dvr_name', 'ip', 'status', 'last_ping_at', 'ping_response_time']);
            
        if ($recentDvrs->isNotEmpty()) {
            $this->table(
                ['ID', 'Name', 'IP', 'Status', 'Last Ping', 'Response Time (ms)'],
                $recentDvrs->map(function ($dvr) {
                    return [
                        $dvr->id,
                        $dvr->dvr_name,
                        $dvr->ip,
                        $dvr->status,
                        $dvr->last_ping_at?->diffForHumans() ?? 'Never',
                        $dvr->ping_response_time ?? 'N/A'
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No recent ping activity found');
        }
    }
}
