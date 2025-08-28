<?php
// HikvisionCameraApi.php - OOP camera/channel info for Hikvision DVRs
require_once __DIR__ . '/HikvisionApiClient.php';

class HikvisionCameraApi {
    private $client, $ip, $port, $username, $password;
    public function __construct($client, $ip, $port, $username, $password) {
        $this->client = $client;
        $this->ip = $ip;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    public function getCameraStatuses() {
        $endpoint = '/ISAPI/System/Video/inputs/channels';
        $url = "http://{$this->ip}:{$this->port}{$endpoint}";
        $xmlStr = $this->client->get($url, $this->username, $this->password);
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
}
