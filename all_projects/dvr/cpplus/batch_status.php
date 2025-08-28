<?php
// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/device_api.php';
require_once __DIR__ . '/camera_api.php';
require_once __DIR__ . '/hard_disk_api.php';
require_once __DIR__ . '/DvrApiClient.php';
require_once __DIR__ . '/dvr_activity_logger.php';

header('Content-Type: application/json');

if (!isset($_GET['ip']) || !isset($_GET['port']) || !isset($_GET['username']) || !isset($_GET['password'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$ip = $_GET['ip'];
$port = $_GET['port'];
$username = $_GET['username'];
$password = $_GET['password'];

try {
    // Ping check (Windows compatible)
    $pingResult = null;
    $pingCmd = "ping -n 1 -w 1000 " . escapeshellarg($ip);
    exec($pingCmd, $output, $pingResult);
    
    if ($pingResult !== 0) {
        // Create offline record for no network
        $offlineData = [
            'ip_address' => $ip,
            'status' => 'NO NETWORK',
            'dvr_time' => null,
            'login_time' => date('Y-m-d H:i:s'),
            'system_time' => date('Y-m-d H:i:s'),
            'total_cameras' => 0,
            'storage_type' => 'N/A',
            'storage_status' => 'N/A',
            'storage_capacity' => 'N/A',
            'storage_free' => 'N/A',
            'recording_from' => null,
            'recording_to' => null,
            'camera_status' => array_fill(0, 8, 'N/A')
        ];

        // Save offline status
        $saveResult = save_dvr_activity($offlineData);
        if (isset($saveResult['error'])) {
            log_error("Failed to save offline activity for IP $ip: " . $saveResult['error']);
        }

        $result = [
            'ip' => $ip,
            'status' => 'NO NETWORK',
            'error' => 'Ping failed',
            'deviceInfo' => null,
            'cameraInfo' => null,
            'storageInfo' => null,
            'recordingInfo' => null
        ];
        echo json_encode($result);
        exit;
    }

    // First collect all data from DVR APIs
    $dvrData = collect_dvr_data($ip, $port, $username, $password);

    // If data collection failed, save offline status
    if ($dvrData['status'] === 'Offline') {
        $dvrData['storage_type'] = 'N/A';
        $dvrData['storage_status'] = 'N/A';
        $dvrData['storage_capacity'] = 'N/A';
        $dvrData['storage_free'] = 'N/A';
        $dvrData['total_cameras'] = 0;
        $dvrData['camera_status'] = array_fill(0, 8, 'N/A');
    }

    // Save to database
    $saveResult = save_dvr_activity($dvrData);
    if (isset($saveResult['error'])) {
        log_error("Failed to save activity for IP $ip: " . $saveResult['error']);
    }

    // Prepare response data
    $result = [
        'ip' => $ip,
        'status' => $dvrData['status'],
        'deviceInfo' => [
            'currentDateTime' => $dvrData['system_time'],
            'loginTime' => $dvrData['login_time'],
            'dvrTime' => $dvrData['dvr_time']
        ],
        'cameraInfo' => [
            'totalCameras' => $dvrData['total_cameras'],
            'cameraStatus' => array_map(function($status, $index) {
                return ['number' => $index + 1, 'status' => $status];
            }, array_slice($dvrData['camera_status'], 0, $dvrData['total_cameras']), range(0, $dvrData['total_cameras'] - 1))
        ],
        'storageInfo' => [
            'storageType' => $dvrData['storage_type'],
            'storageStatus' => $dvrData['storage_status'],
            'storageCapacity' => $dvrData['storage_capacity'],
            'storageFree' => $dvrData['storage_free']
        ],
        'recordingInfo' => [
            'recordingFrom' => $dvrData['recording_from'],
            'recordingTo' => $dvrData['recording_to']
        ]
    ];

    echo json_encode($result);

} catch (Exception $e) {
    // Save error status
    $errorData = [
        'ip_address' => $ip,
        'status' => 'ERROR',
        'dvr_time' => null,
        'login_time' => date('Y-m-d H:i:s'),
        'system_time' => date('Y-m-d H:i:s'),
        'total_cameras' => 0,
        'storage_type' => 'N/A',
        'storage_status' => 'N/A',
        'storage_capacity' => 'N/A',
        'storage_free' => 'N/A',
        'recording_from' => null,
        'recording_to' => null,
        'camera_status' => array_fill(0, 8, 'N/A')
    ];

    // Save error status
    $saveResult = save_dvr_activity($errorData);
    if (isset($saveResult['error'])) {
        log_error("Failed to save error activity for IP $ip: " . $saveResult['error']);
    }

    log_error("Error in batch_status.php for IP $ip: " . $e->getMessage());
    echo json_encode([
        'ip' => $ip,
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ]);
}
