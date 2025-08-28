<?php

require_once 'vendor/autoload.php';

use App\Models\Dvr;
use App\Services\EnhancedMonitoringService;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🕐 Update All DVR Device Times\n";
echo "==============================\n\n";

$enhancedService = new EnhancedMonitoringService();

// Get all active DVRs
$dvrs = Dvr::where('is_active', true)->get();

if ($dvrs->isEmpty()) {
    echo "❌ No active DVRs found\n";
    exit(1);
}

echo "📊 Found {$dvrs->count()} active DVRs\n";
echo "🚀 Starting enhanced monitoring to update device times...\n\n";

$results = [
    'total' => $dvrs->count(),
    'successful_ping' => 0,
    'successful_api' => 0,
    'time_retrieved' => 0,
    'time_sync_ok' => 0,
    'time_sync_issues' => 0,
    'offline' => 0
];

$progressBar = str_repeat('=', 50);
$processed = 0;

foreach ($dvrs as $dvr) {
    $processed++;
    $progress = round(($processed / $dvrs->count()) * 50);
    $bar = str_repeat('█', $progress) . str_repeat('░', 50 - $progress);
    
    echo "\r[{$bar}] {$processed}/{$dvrs->count()} - Processing: {$dvr->dvr_name}";
    
    // Use enhanced monitoring to get device time
    $result = $enhancedService->monitorDvr($dvr, 'full_check');
    
    if ($result['ping_success']) {
        $results['successful_ping']++;
        
        if ($result['api_success']) {
            $results['successful_api']++;
            
            if ($result['dvr_time']) {
                $results['time_retrieved']++;
                
                // Check time sync
                $dvr->refresh();
                if ($dvr->device_time_offset_minutes !== null) {
                    if (abs($dvr->device_time_offset_minutes) <= 5) {
                        $results['time_sync_ok']++;
                    } else {
                        $results['time_sync_issues']++;
                    }
                }
            }
        }
    } else {
        $results['offline']++;
    }
    
    // Small delay to avoid overwhelming DVRs
    usleep(100000); // 0.1 second
}

echo "\n\n✅ Processing completed!\n\n";

// Show detailed results
echo "📊 DETAILED RESULTS\n";
echo "===================\n";
echo "Total DVRs: {$results['total']}\n";
echo "Successful pings: {$results['successful_ping']}\n";
echo "Successful API logins: {$results['successful_api']}\n";
echo "Device times retrieved: {$results['time_retrieved']}\n";
echo "Time sync OK (±5 min): {$results['time_sync_ok']}\n";
echo "Time sync issues: {$results['time_sync_issues']}\n";
echo "Offline DVRs: {$results['offline']}\n\n";

// Show success rates
$pingRate = round(($results['successful_ping'] / $results['total']) * 100, 1);
$apiRate = $results['successful_ping'] > 0 ? round(($results['successful_api'] / $results['successful_ping']) * 100, 1) : 0;
$timeRate = $results['successful_api'] > 0 ? round(($results['time_retrieved'] / $results['successful_api']) * 100, 1) : 0;

echo "📈 SUCCESS RATES\n";
echo "================\n";
echo "Ping success rate: {$pingRate}%\n";
echo "API success rate (of pingable): {$apiRate}%\n";
echo "Time retrieval rate (of API accessible): {$timeRate}%\n\n";

// Show DVRs with device time
echo "⏰ DVRs WITH DEVICE TIME\n";
echo "========================\n";
$dvrsWithTime = Dvr::where('is_active', true)
    ->whereNotNull('dvr_device_time')
    ->orderBy('dvr_name')
    ->get();

if ($dvrsWithTime->isEmpty()) {
    echo "❌ No DVRs have device time information\n";
} else {
    foreach ($dvrsWithTime as $dvr) {
        $syncStatus = '';
        if ($dvr->device_time_offset_minutes !== null) {
            $offset = abs($dvr->device_time_offset_minutes);
            $syncStatus = $offset <= 5 ? '✅ Synced' : "⚠️ {$dvr->device_time_offset_minutes}min offset";
        }
        
        echo "• {$dvr->dvr_name} ({$dvr->ip}:{$dvr->port})\n";
        echo "  DVR Time: {$dvr->dvr_device_time}\n";
        echo "  Last Check: {$dvr->last_api_check_at}\n";
        echo "  Sync Status: {$syncStatus}\n\n";
    }
}

// Show time sync issues
if ($results['time_sync_issues'] > 0) {
    echo "⚠️ TIME SYNC ISSUES\n";
    echo "===================\n";
    $timeSyncIssues = Dvr::where('is_active', true)
        ->whereNotNull('device_time_offset_minutes')
        ->where(function($query) {
            $query->where('device_time_offset_minutes', '>', 5)
                  ->orWhere('device_time_offset_minutes', '<', -5);
        })
        ->get();
    
    foreach ($timeSyncIssues as $dvr) {
        echo "• {$dvr->dvr_name} ({$dvr->ip}:{$dvr->port})\n";
        echo "  Offset: {$dvr->device_time_offset_minutes} minutes\n";
        echo "  DVR Time: {$dvr->dvr_device_time}\n";
        echo "  System Time: " . now('Asia/Kolkata')->format('Y-m-d H:i:s') . "\n\n";
    }
}

// Show monitoring statistics
echo "📊 MONITORING STATISTICS\n";
echo "========================\n";
$stats = $enhancedService->getMonitoringStats(1); // Last hour
echo "Total checks (1h): {$stats['total_checks']}\n";
echo "Successful pings: {$stats['successful_pings']}\n";
echo "Successful API logins: {$stats['successful_api_logins']}\n";
echo "Online DVRs: {$stats['online_dvrs']}\n";
echo "Offline DVRs: {$stats['offline_dvrs']}\n";
echo "API Error DVRs: {$stats['api_error_dvrs']}\n\n";

echo "🎯 SUMMARY\n";
echo "==========\n";
if ($results['time_retrieved'] > 0) {
    echo "✅ Successfully retrieved device time from {$results['time_retrieved']} DVRs\n";
    echo "✅ Time synchronization monitoring is working\n";
    echo "✅ Enhanced monitoring system is operational\n";
} else {
    echo "❌ No device times were retrieved\n";
    echo "🔧 Check network connectivity and DVR accessibility\n";
}

echo "\n💡 Next Steps:\n";
echo "• Use enhanced monitoring dashboard: /enhanced-realtime-dashboard.html\n";
echo "• Set up automated monitoring: php artisan dvr:enhanced-monitor\n";
echo "• Monitor time sync issues regularly\n";
echo "• Check offline DVRs for network connectivity\n";