<?php
// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/DvrApiClient.php';

function get_device_info(DvrApiClient $client) {
    $currentDateTime = date('Y-m-d H:i:s');
    $loginTime = date('Y-m-d H:i:s');
    $timeData = $client->call('cgi-bin/global.cgi', ['action' => 'getCurrentTime']);
    $dvrTime = isset($timeData['result']) ? $timeData['result'] : 'N/A';
    return [
        'currentDateTime' => $currentDateTime,
        'loginTime' => $loginTime,
        'dvrTime' => $dvrTime
    ];
}

function get_recording_info(DvrApiClient $client, $dvrTime) {
    $recordingFrom = 'N/A';
    $recordingTo = 'N/A';
    $finderData = $client->call('cgi-bin/mediaFileFind.cgi', ['action' => 'factory.create']);
    if ($finderData && isset($finderData['result'])) {
        $finderId = $finderData['result'];
        $client->call('cgi-bin/mediaFileFind.cgi', [
            'action' => 'findFile',
            'object' => $finderId,
            'condition.Channel' => 1,
            'condition.StartTime' => '2000-01-01 00:00:00',
            'condition.EndTime' => '2038-01-01 00:00:00',
        ]);
        $firstFileData = $client->call('cgi-bin/mediaFileFind.cgi', [
            'action' => 'findNextFile',
            'object' => $finderId,
            'count' => 1
        ]);
        if($firstFileData && isset($firstFileData['items[0].StartTime'])) {
            $recordingFrom = $firstFileData['items[0].StartTime'];
        }
        if ($dvrTime !== 'N/A') {
            $recordingTo = $dvrTime;
        }
        $client->call('cgi-bin/mediaFileFind.cgi', ['action' => 'destroy', 'object' => $finderId]);
    }
    return [
        'recordingFrom' => $recordingFrom,
        'recordingTo' => $recordingTo
    ];
}
