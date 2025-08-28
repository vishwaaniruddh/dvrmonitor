<?php

echo "🚀 Starting DVR Monitoring System\n";
echo "=================================\n\n";

// Set timezone
date_default_timezone_set('Asia/Kolkata');

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\SimplifiedMonitoringService;

// Set Laravel timezone
config(['app.timezone' => 'Asia/Kolkata']);

echo "✅ Timezone set to Asia/Kolkata (IST)\n";
echo "📅 Current time: " . now('Asia/Kolkata')->format('Y-m-d H:i:s T') . "\n\n";

// Initialize simplified monitoring service
$monitoringService = new SimplifiedMonitoringService();

// Start monitoring
echo "🤖 Starting automated monitoring service...\n";
$result = $monitoringService->startContinuousMonitoring();

if ($result['success']) {
    echo "✅ " . $result['message'] . "\n";
    echo "📊 Total DVRs: " . $result['total_dvrs'] . "\n";
    echo "⏰ Check interval: " . $result['monitoring_interval'] . "\n";
    echo "🕐 Started at: " . $result['started_at'] . "\n\n";

    echo "🌐 Dashboard will be available at: http://127.0.0.1:8000/realtime-dashboard.html\n\n";

    echo "� Toa see REAL-TIME monitoring activity:\n";
    echo "   Option 1: Double-click 'realtime_monitor.bat'\n";
    echo "   Option 2: Run 'php realtime_monitor.php' in another terminal\n";
    echo "   Option 3: Run 'php artisan dvr:monitor-realtime' for detailed output\n\n";

    echo "🚀 Starting Laravel development server...\n";
    echo "=========================================\n";
    echo "The monitoring system will run automatically in the background.\n";
    echo "Monitoring cycles happen every 5 minutes when you access the dashboard.\n";
    echo "Press Ctrl+C to stop both the server and monitoring.\n\n";

    // Start Laravel development server
    $command = "php artisan serve --host=127.0.0.1 --port=8000";
    passthru($command);
} else {
    echo "❌ Failed to start monitoring: " . $result['message'] . "\n";
    exit(1);
}
