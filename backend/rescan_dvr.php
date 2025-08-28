<?php

/**
 * Quick DVR Re-scan Script
 * Use this to test specific DVRs after the status fix
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Dvr;
use App\Services\SimplifiedMonitoringService;

if ($argc < 2) {
    echo "Usage: php rescan_dvr.php <IP_ADDRESS>\n";
    echo "Example: php rescan_dvr.php 10.109.9.151\n";
    exit(1);
}

$targetIp = $argv[1];

echo "🔍 Re-scanning DVR: {$targetIp}\n";
echo "==========================\n\n";

// Find the DVR
$dvr = Dvr::where('ip', $targetIp)->first();

if (!$dvr) {
    echo "❌ DVR with IP {$targetIp} not found in database.\n";
    exit(1);
}

echo "📋 DVR Details:\n";
echo "   ID: {$dvr->id}\n";
echo "   Name: {$dvr->dvr_name}\n";
echo "   IP: {$dvr->ip}\n";
echo "   Port: {$dvr->port}\n";
echo "   Current Status: {$dvr->status}\n";
echo "   Last Ping: {$dvr->last_ping_at}\n\n";

// Test with the fixed monitoring service
$service = new SimplifiedMonitoringService();

echo "🔄 Running fixed monitoring check...\n";
$startTime = microtime(true);

$result = $service->monitorSingleDvr($dvr);

$executionTime = round((microtime(true) - $startTime) * 1000, 2);

echo "\n📊 Results:\n";
echo "   Status: " . ($result['status'] === 'online' ? '🟢 ONLINE' : '🔴 OFFLINE') . "\n";
echo "   Response Time: {$result['response_time']}ms\n";
echo "   Execution Time: {$executionTime}ms\n";
echo "   Checked At: {$result['checked_at']}\n";

if (!$result['success']) {
    echo "   Error: {$result['error']}\n";
}

// Refresh DVR from database
$dvr->refresh();

echo "\n📋 Updated DVR Status:\n";
echo "   Database Status: {$dvr->status}\n";
echo "   Ping Response Time: {$dvr->ping_response_time}ms\n";
echo "   API Accessible: " . ($dvr->api_accessible ? 'Yes' : 'No') . "\n";
echo "   Consecutive Failures: {$dvr->consecutive_failures}\n";
echo "   Last Ping At: {$dvr->last_ping_at}\n";

echo "\n✅ Re-scan completed!\n";

// Manual ping test for comparison
echo "\n🔍 Manual ping test for comparison:\n";
$pingCommand = PHP_OS_FAMILY === 'Windows' ? "ping -n 1 -w 2000 {$targetIp}" : "ping -c 1 -W 2 {$targetIp}";
echo "Running: {$pingCommand}\n";
echo "----------------------------------------\n";
system($pingCommand);
echo "----------------------------------------\n";