<?php

// Bootstrap Laravel
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Dvr;

echo "🔍 Checking DVR Data After Enhanced Monitoring\n";
echo "==============================================\n\n";

$dvr = Dvr::find(6656);

if (!$dvr) {
    echo "❌ DVR not found\n";
    exit(1);
}

echo "DVR: {$dvr->dvr_name} (ID: {$dvr->id})\n";
echo "IP: {$dvr->ip}:{$dvr->port}\n\n";

echo "📊 CURRENT DVR DATA:\n";
echo "===================\n";
echo "Status: " . ($dvr->status ?? 'null') . "\n";
echo "API Login Status: " . ($dvr->api_login_status ?? 'null') . "\n";
echo "DVR Device Time: " . ($dvr->dvr_device_time ?? 'null') . "\n";
echo "Device Time Offset: " . ($dvr->device_time_offset_minutes ?? 'null') . " minutes\n";
echo "Last API Check: " . ($dvr->last_api_check_at ?? 'null') . "\n";
echo "Current Camera Count: " . ($dvr->current_camera_count ?? 'null') . "\n";
echo "Working Camera Count: " . ($dvr->working_camera_count ?? 'null') . "\n";
echo "Storage Capacity: " . ($dvr->storage_capacity_gb ?? 'null') . " GB\n";
echo "Storage Usage: " . ($dvr->storage_usage_percentage ?? 'null') . "%\n";
echo "Recording Status: " . ($dvr->recording_status ?? 'null') . "\n";
echo "Last Ping At: " . ($dvr->last_ping_at ?? 'null') . "\n";
echo "Ping Response Time: " . ($dvr->ping_response_time ?? 'null') . "ms\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "DVR data check completed!\n";