<?php
// Hard disk/storage info related functions
require_once __DIR__ . '/DvrApiClient.php';

function get_storage_info(DvrApiClient $client) {
    $storageDataRaw = $client->call('cgi-bin/storageDevice.cgi', ['action' => 'getDeviceAllInfo']);
    $storageType = 'N/A';
    $storageStatus = 'N/A';
    $storageCapacity = 'N/A';
    $storageFree = 'N/A';
    if ($storageDataRaw && isset($storageDataRaw['list.info[0].State'])) {
        $storageStatus = $storageDataRaw['list.info[0].State'];
        $totalBytes = $storageDataRaw['list.info[0].Detail[0].TotalBytes'] ?? 0;
        $usedBytes = $storageDataRaw['list.info[0].Detail[0].UsedBytes'] ?? 0;
        $storageCapacity = round($totalBytes / (1024*1024*1024), 2) . ' GB';
        $storageFree = round(($totalBytes - $usedBytes) / (1024*1024*1024), 2) . ' GB';
        $storageType = "HDD"; // Assumption
    }
    return [
        'storageType' => $storageType,
        'storageStatus' => $storageStatus,
        'storageCapacity' => $storageCapacity,
        'storageFree' => $storageFree
    ];
}
