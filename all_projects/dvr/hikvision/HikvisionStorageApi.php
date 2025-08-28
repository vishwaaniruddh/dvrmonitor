<?php
// HikvisionStorageApi.php - OOP storage info for Hikvision DVRs
require_once __DIR__ . '/HikvisionApiClient.php';

class HikvisionStorageApi {
    private $client, $ip, $port, $username, $password;
    public function __construct($client, $ip, $port, $username, $password) {
        $this->client = $client;
        $this->ip = $ip;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    public function getStorageInfo() {
        $endpoint = '/ISAPI/ContentMgmt/Storage';
        $url = "http://{$this->ip}:{$this->port}{$endpoint}";
        $xmlStr = $this->client->get($url, $this->username, $this->password);
        if (!$xmlStr) return [
            'storageStatus' => 'Not Working',
            'storageType' => '',
            'storageCapacity' => '',
            'storageFree' => ''
        ];
        $xml = simplexml_load_string($xmlStr);
        $xml->registerXPathNamespace('ns', 'http://www.hikvision.com/ver20/XMLSchema');
        $statusNodes = $xml->xpath('//ns:status');
        if (!$statusNodes) {
            $xml->registerXPathNamespace('std', 'http://www.std-cgi.com/ver20/XMLSchema');
            $statusNodes = $xml->xpath('//std:status');
        }
        $status = isset($statusNodes[0]) ? strtolower(trim((string)$statusNodes[0])) : '';
        $hddTypeNodes = $xml->xpath('//ns:hddType');
        $capacityNodes = $xml->xpath('//ns:capacity');
        $freeNodes = $xml->xpath('//ns:freeSpace');
        if (!$hddTypeNodes) $hddTypeNodes = $xml->xpath('//std:hddType');
        if (!$capacityNodes) $capacityNodes = $xml->xpath('//std:capacity');
        if (!$freeNodes) $freeNodes = $xml->xpath('//std:freeSpace');
        $type = isset($hddTypeNodes[0]) ? (string)$hddTypeNodes[0] : '';
        $capacity = isset($capacityNodes[0]) ? round(((float)$capacityNodes[0]) / 1024, 2) . ' GB' : '';
        $free = isset($freeNodes[0]) ? round(((float)$freeNodes[0]) / 1024, 2) . ' GB' : '';
        return [
            'storageStatus' => $status === 'ok' ? 'Working' : 'Not Working',
            'storageType' => $type,
            'storageCapacity' => $capacity,
            'storageFree' => $free
        ];
    }
}
