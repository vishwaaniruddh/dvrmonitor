<?php

require_once 'vendor/autoload.php';

use App\Services\RtspToHlsService;

// Create service instance
$hlsService = new RtspToHlsService();

echo "Testing HLS Service...\n";

// Test starting a stream
$result = $hlsService->startStream(
    '10.109.72.104',
    1,
    'admin',
    'css12345',
    554
);

echo "Start Stream Result:\n";
print_r($result);

// Wait a moment
sleep(5);

// Check status
$streamId = 'stream_10_109_72_104_ch1';
$status = $hlsService->getStreamStatus($streamId);

echo "\nStream Status:\n";
print_r($status);

// Check if files exist
$hlsPath = storage_path('app/public/hls/' . $streamId);
echo "\nHLS Directory: {$hlsPath}\n";
if (is_dir($hlsPath)) {
    $files = scandir($hlsPath);
    echo "Files in directory:\n";
    print_r($files);
} else {
    echo "HLS directory does not exist\n";
}

?>