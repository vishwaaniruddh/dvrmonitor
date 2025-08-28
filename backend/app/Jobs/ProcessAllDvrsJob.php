<?php

namespace App\Jobs;

use App\Models\Dvr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAllDvrsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $groupName = null,
        public bool $activeOnly = true
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $query = Dvr::query();
            
            if ($this->activeOnly) {
                $query->where('is_active', true);
            }
            
            if ($this->groupName) {
                $query->where('group_name', $this->groupName);
            }
            
            $dvrs = $query->get();
            
            if ($dvrs->isEmpty()) {
                Log::info('No DVRs found to process');
                return;
            }

            Log::info("Processing " . $dvrs->count() . " DVRs");

            // Dispatch individual ping jobs for each DVR
            foreach ($dvrs as $dvr) {
                PingDvrJob::dispatch($dvr);
                Log::info("Dispatched ping job for DVR: {$dvr->dvr_name} ({$dvr->ip})");
            }

            Log::info("Dispatched ping jobs for " . $dvrs->count() . " DVRs");
            
        } catch (\Exception $e) {
            Log::error("ProcessAllDvrsJob failed: " . $e->getMessage());
            throw $e;
        }
    }
}
