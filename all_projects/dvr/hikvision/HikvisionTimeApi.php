<?php
// HikvisionTimeApi.php - OOP time/system/recording info for Hikvision DVRs
require_once __DIR__ . '/HikvisionApiClient.php';

class HikvisionTimeApi {
    private $client, $ip, $port, $username, $password;
    public function __construct($client, $ip, $port, $username, $password) {
        $this->client = $client;
        $this->ip = $ip;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    public function getDvrTime() {
        $endpoint = '/ISAPI/System/time';
        $url = "http://{$this->ip}:{$this->port}{$endpoint}";
        $xmlStr = $this->client->get($url, $this->username, $this->password);
        if (!$xmlStr) return '';
        $xml = simplexml_load_string($xmlStr);
        $xml->registerXPathNamespace('ns', 'http://www.hikvision.com/ver20/XMLSchema');
        $localTimeNodes = $xml->xpath('//ns:localTime');
        if (!$localTimeNodes) {
            $localTimeNodes = $xml->xpath('//localTime');
        }
        return isset($localTimeNodes[0]) ? trim((string)$localTimeNodes[0]) : '';
    }
    public function getTimeDetails() {
        $endpoint = '/ISAPI/System/time';
        $url = "http://{$this->ip}:{$this->port}{$endpoint}";
        $xmlStr = $this->client->get($url, $this->username, $this->password);
        if (!$xmlStr) return [
            'login_time' => '',
            'system_time' => '',
            'recording_from' => '',
            'recording_to' => ''
        ];
        $xml = simplexml_load_string($xmlStr);
        $xml->registerXPathNamespace('ns', 'http://www.hikvision.com/ver20/XMLSchema');
        $loginTimeNodes = $xml->xpath('//ns:loginTime');
        $systemTimeNodes = $xml->xpath('//ns:systemTime');
        $recordingFromNodes = $xml->xpath('//ns:recordingFrom');
        $recordingToNodes = $xml->xpath('//ns:recordingTo');
        if (!$loginTimeNodes) $loginTimeNodes = $xml->xpath('//loginTime');
        if (!$systemTimeNodes) $systemTimeNodes = $xml->xpath('//systemTime');
        if (!$recordingFromNodes) $recordingFromNodes = $xml->xpath('//recordingFrom');
        if (!$recordingToNodes) $recordingToNodes = $xml->xpath('//recordingTo');
        return [
            'login_time' => isset($loginTimeNodes[0]) ? trim((string)$loginTimeNodes[0]) : '',
            'system_time' => isset($systemTimeNodes[0]) ? trim((string)$systemTimeNodes[0]) : '',
            'recording_from' => isset($recordingFromNodes[0]) ? trim((string)$recordingFromNodes[0]) : '',
            'recording_to' => isset($recordingToNodes[0]) ? trim((string)$recordingToNodes[0]) : ''
        ];
    }
}
