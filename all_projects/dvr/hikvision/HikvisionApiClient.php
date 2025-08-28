<?php
// HikvisionApiClient.php - Handles HTTP requests, authentication, and error handling for Hikvision DVRs
use GuzzleHttp\Client;

class HikvisionApiClient {
    private $client;
    public function __construct() {
        $this->client = new Client();
    }
    public function get($url, $username, $password, $timeout = 5) {
        try {
            $res = $this->client->request('GET', $url, [
                'auth' => [$username, $password, 'digest'],
                'timeout' => $timeout,
            ]);
            return $res->getBody()->getContents();
        } catch (Exception $e) {
            return false;
        }
    }
}
