<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessAllDvrsJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule DVR monitoring every 5 minutes
Schedule::job(new ProcessAllDvrsJob())->everyFiveMinutes()->name('dvr-monitoring');

// Schedule cleanup of old monitoring logs (keep last 30 days)
Schedule::command('model:prune', ['--model' => 'App\\Models\\DvrMonitoringLog'])->daily();
