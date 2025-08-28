<?php

/**
 * Real-time DVR Monitoring Script
 * Shows live monitoring activity in the terminal
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Bootstrap Laravel
$app = Application::configure(basePath: __DIR__)
    ->withRouting(
        web: __DIR__.'/routes/web.php',
        api: __DIR__.'/routes/api.php',
        commands: __DIR__.'/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Set timezone
date_default_timezone_set('Asia/Kolkata');

echo "🚀 Real-time DVR Monitoring Dashboard\n";
echo "====================================\n";
echo "Press Ctrl+C to stop monitoring\n\n";

// Colors for terminal output
function colorText($text, $color) {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Function to clear screen
function clearScreen() {
    if (PHP_OS_FAMILY === 'Windows') {
        system('cls');
    } else {
        system('clear');
    }
}

// Function to get monitoring status
function getMonitoringStatus() {
    try {
        $cache = app('cache');
        return $cache->get('automated_monitoring_status', ['active' => false]);
    } catch (Exception $e) {
        return ['active' => false, 'error' => $e->getMessage()];
    }
}

// Function to get DVR statistics
function getDvrStats() {
    try {
        $dvr = app('App\Models\Dvr');
        return $dvr::selectRaw('
            COUNT(*) as total_dvrs,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_dvrs,
            COUNT(CASE WHEN status = "online" THEN 1 END) as online_dvrs,
            COUNT(CASE WHEN status = "offline" THEN 1 END) as offline_dvrs,
            COUNT(CASE WHEN status = "unknown" OR status IS NULL THEN 1 END) as unknown_dvrs,
            AVG(CASE WHEN ping_response_time IS NOT NULL THEN ping_response_time END) as avg_response_time,
            MAX(last_ping_at) as last_update
        ')->first();
    } catch (Exception $e) {
        return null;
    }
}

// Function to get recent monitoring logs
function getRecentLogs($limit = 10) {
    try {
        $log = app('App\Models\DvrMonitoringLog');
        return $log::with('dvr:id,dvr_name,ip')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['dvr_id', 'result', 'response_time', 'created_at']);
    } catch (Exception $e) {
        return collect();
    }
}

// Function to display dashboard
function displayDashboard() {
    clearScreen();
    
    echo colorText("🚀 Real-time DVR Monitoring Dashboard", 'cyan') . "\n";
    echo colorText("====================================", 'cyan') . "\n";
    echo "Updated: " . date('Y-m-d H:i:s T') . "\n\n";
    
    // Monitoring Status
    $status = getMonitoringStatus();
    echo colorText("🤖 Monitoring Service Status:", 'yellow') . "\n";
    if ($status['active']) {
        echo "   Status: " . colorText("🟢 ACTIVE", 'green') . "\n";
        if (isset($status['started_at'])) {
            echo "   Started: " . $status['started_at'] . "\n";
        }
        if (isset($status['last_cycle'])) {
            echo "   Last Cycle: " . $status['last_cycle'] . "\n";
        }
        if (isset($status['last_cycle_stats'])) {
            $stats = $status['last_cycle_stats'];
            echo "   Last Results: " . colorText($stats['online'] . " online", 'green') . 
                 ", " . colorText($stats['offline'] . " offline", 'red') . 
                 " in " . $stats['execution_time'] . "s\n";
        }
    } else {
        echo "   Status: " . colorText("🔴 INACTIVE", 'red') . "\n";
        echo "   " . colorText("Visit the dashboard to start monitoring", 'yellow') . "\n";
    }
    echo "\n";
    
    // DVR Statistics
    $dvrStats = getDvrStats();
    if ($dvrStats) {
        echo colorText("📊 DVR Statistics:", 'yellow') . "\n";
        echo "   Total DVRs: " . number_format($dvrStats->total_dvrs) . "\n";
        echo "   Active DVRs: " . number_format($dvrStats->active_dvrs) . "\n";
        echo "   " . colorText("🟢 Online: " . number_format($dvrStats->online_dvrs), 'green') . "\n";
        echo "   " . colorText("🔴 Offline: " . number_format($dvrStats->offline_dvrs), 'red') . "\n";
        echo "   " . colorText("⚪ Unknown: " . number_format($dvrStats->unknown_dvrs), 'white') . "\n";
        
        if ($dvrStats->avg_response_time) {
            echo "   ⚡ Avg Response: " . round($dvrStats->avg_response_time, 2) . "ms\n";
        }
        
        if ($dvrStats->last_update) {
            echo "   🕐 Last Update: " . $dvrStats->last_update . "\n";
        }
        
        // Calculate success rate
        $totalChecked = $dvrStats->online_dvrs + $dvrStats->offline_dvrs;
        if ($totalChecked > 0) {
            $successRate = round(($dvrStats->online_dvrs / $totalChecked) * 100, 1);
            $color = $successRate >= 90 ? 'green' : ($successRate >= 70 ? 'yellow' : 'red');
            echo "   📈 Success Rate: " . colorText($successRate . "%", $color) . "\n";
        }
    } else {
        echo colorText("❌ Unable to fetch DVR statistics", 'red') . "\n";
    }
    echo "\n";
    
    // Recent Activity
    $recentLogs = getRecentLogs(8);
    if ($recentLogs->count() > 0) {
        echo colorText("📋 Recent Monitoring Activity:", 'yellow') . "\n";
        foreach ($recentLogs as $log) {
            $time = $log->created_at->format('H:i:s');
            $result = $log->result === 'success' ? 
                colorText("✅", 'green') : colorText("❌", 'red');
            $responseTime = $log->response_time ? round($log->response_time, 1) . 'ms' : 'N/A';
            $dvrName = $log->dvr ? $log->dvr->dvr_name : 'Unknown';
            $dvrIp = $log->dvr ? $log->dvr->ip : 'Unknown';
            
            echo "   [{$time}] {$result} {$dvrName} ({$dvrIp}) - {$responseTime}\n";
        }
    } else {
        echo colorText("📋 No recent monitoring activity", 'yellow') . "\n";
    }
    echo "\n";
    
    echo colorText("🌐 Dashboard URL: http://127.0.0.1:8000/realtime-dashboard.html", 'cyan') . "\n";
    echo colorText("Press Ctrl+C to stop monitoring", 'white') . "\n";
}

// Main monitoring loop
$previousStats = null;
$cycleCount = 0;

while (true) {
    $cycleCount++;
    
    try {
        displayDashboard();
        
        // Show cycle information
        echo "\n" . colorText("Cycle #{$cycleCount} - Refreshing in 10 seconds...", 'blue') . "\n";
        
        sleep(10);
        
    } catch (Exception $e) {
        echo colorText("❌ Error: " . $e->getMessage(), 'red') . "\n";
        sleep(5);
    }
}