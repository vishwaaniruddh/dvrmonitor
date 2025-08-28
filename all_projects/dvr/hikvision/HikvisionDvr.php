<?php
// HikvisionDvr.php - Main orchestrator for Hikvision DVR status
require_once __DIR__ . '/HikvisionApiClient.php';
require_once __DIR__ . '/HikvisionCameraApi.php';
require_once __DIR__ . '/HikvisionStorageApi.php';
require_once __DIR__ . '/HikvisionTimeApi.php';

class HikvisionDvr {
    private $ip, $port, $username, $password, $client, $cameraApi, $storageApi, $timeApi;

    public function __construct($ip, $port, $username, $password) {
        $this->ip = $ip;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->client = new HikvisionApiClient();
        $this->cameraApi = new HikvisionCameraApi($this->client, $ip, $port, $username, $password);
        $this->storageApi = new HikvisionStorageApi($this->client, $ip, $port, $username, $password);
        $this->timeApi = new HikvisionTimeApi($this->client, $ip, $port, $username, $password);
    }

    public function getStatus() {
        $camera_statuses = $this->cameraApi->getCameraStatuses();
        $storage = $this->storageApi->getStorageInfo();
        $dvr_time = $this->timeApi->getDvrTime();
        $time_info = $this->timeApi->getTimeDetails();
        $total_cameras = is_array($camera_statuses) ? count($camera_statuses) : 0;
        return [
            'camera_statuses' => $camera_statuses,
            'hdd_status' => $storage['storageStatus'],
            'storageType' => $storage['storageType'],
            'storageStatus' => $storage['storageStatus'],
            'storageCapacity' => $storage['storageCapacity'],
            'storageFree' => $storage['storageFree'],
            'dvr_time' => $dvr_time,
            'login_time' => $time_info['login_time'],
            'system_time' => $time_info['system_time'],
            'total_cameras' => $total_cameras,
            'recording_from' => $time_info['recording_from'],
            'recording_to' => $time_info['recording_to']
        ];
    }
}
