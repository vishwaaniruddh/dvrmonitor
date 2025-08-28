<?php
// Camera info related functions
require_once __DIR__ . '/DvrApiClient.php';

function get_camera_info(DvrApiClient $client) {
    $channelData = $client->call('cgi-bin/configManager.cgi', ['action' => 'getConfig', 'name' => 'ChannelTitle']);
    $cameras = [];
    if ($channelData) {
        foreach ($channelData as $key => $value) {
            if (preg_match('/table\\.ChannelTitle\\[(\\d+)\\]\\.Name/', $key, $matches)) {
                $cameras[$matches[1]] = ['name' => $value, 'status' => 'Working'];
            }
        }
    }
    $totalCameras = count($cameras);
    $videoLossData = $client->call('cgi-bin/eventManager.cgi', ['action' => 'getEventIndexes', 'code' => 'VideoLoss']);
    if ($videoLossData && isset($videoLossData['channels'])) {
        $lostChannels = explode(',', $videoLossData['channels']);
        foreach ($lostChannels as $lostChannel) {
            if (isset($cameras[$lostChannel])) {
                $cameras[$lostChannel]['status'] = 'Not Working';
            }
        }
    }
    $cameraStatus = [];
    foreach($cameras as $index => $camera) {
        $cameraStatus[] = [
            'number' => $index + 1,
            'name' => $camera['name'],
            'status' => $camera['status']
        ];
    }
    return [
        'totalCameras' => $totalCameras,
        'cameraStatus' => $cameraStatus
    ];
}
