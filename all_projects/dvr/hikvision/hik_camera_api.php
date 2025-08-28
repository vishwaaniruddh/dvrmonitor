<?php
// hik_camera_api.php - Camera/channel info for Hikvision DVRs
require_once __DIR__ . '/HikvisionApiClient.php';

function fetch_camera_status_sync($ip, $port, $username, $password) {
    $endpoint = '/ISAPI/System/Video/inputs/channels';
    $url = "http://$ip:$port$endpoint";
    $client = new HikvisionApiClient();
    $xmlStr = $client->get($url, $username, $password);
    if (!$xmlStr) return [];
    $xml = simplexml_load_string($xmlStr);
    $xml->registerXPathNamespace('ns', 'http://www.hikvision.com/ver20/XMLSchema');
    $camera_statuses = [];
    $channels = $xml->xpath('//ns:VideoInputChannel');
    if (!$channels) {
        $channels = $xml->xpath('//VideoInputChannel');
    }
    foreach ($channels as $channel) {
        $id = (string)$channel->id;
        $resDesc = isset($channel->resDesc) ? trim((string)$channel->resDesc) : '';
        $camera_statuses[$id] = ($resDesc && stripos($resDesc, 'NO VIDEO') !== false) ? 'Not Working' : 'Working';
    }
    return $camera_statuses;
}
