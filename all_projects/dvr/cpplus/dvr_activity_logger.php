<?php
// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/device_api.php';
require_once __DIR__ . '/camera_api.php';
require_once __DIR__ . '/hard_disk_api.php';
require_once __DIR__ . '/DvrApiClient.php';

// Add error logging function
function log_error($message) {
    $logFile = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function collect_dvr_data($ip, $port, $username, $password) {
    try {
        // Create DVR client and fetch all information
        $client = new DvrApiClient($ip, $port, $username, $password);
        
        // Get device info
        $deviceInfo = get_device_info($client);
        
        // Get camera info
        $cameraInfo = get_camera_info($client);
        
        // Get storage info
        $storageInfo = get_storage_info($client);
        
        // Get recording info
        $recordingInfo = get_recording_info($client, $deviceInfo['dvrTime']);

        // Prepare camera status array with defaults
        $cameraStatus = array_fill(0, 8, 'N/A');
        if (isset($cameraInfo['cameraStatus'])) {
            foreach ($cameraInfo['cameraStatus'] as $camera) {
                if ($camera['number'] <= 8) {
                    $cameraStatus[$camera['number'] - 1] = $camera['status'];
                }
            }
        }

        $status = ($deviceInfo['dvrTime'] !== 'N/A') ? 'Online' : 'Offline';

        return [
            'ip_address' => $ip,
            'status' => $status,
            'dvr_time' => $deviceInfo['dvrTime'] !== 'N/A' ? $deviceInfo['dvrTime'] : null,
            'login_time' => $deviceInfo['loginTime'] !== 'N/A' ? $deviceInfo['loginTime'] : null,
            'system_time' => $deviceInfo['currentDateTime'] !== 'N/A' ? $deviceInfo['currentDateTime'] : null,
            'total_cameras' => $cameraInfo['totalCameras'],
            'storage_type' => $storageInfo['storageType'],
            'storage_status' => $storageInfo['storageStatus'],
            'storage_capacity' => $storageInfo['storageCapacity'],
            'storage_free' => $storageInfo['storageFree'],
            'recording_from' => $recordingInfo['recordingFrom'] !== 'N/A' ? $recordingInfo['recordingFrom'] : null,
            'recording_to' => $recordingInfo['recordingTo'] !== 'N/A' ? $recordingInfo['recordingTo'] : null,
            'camera_status' => $cameraStatus
        ];
    } catch (Exception $e) {
        log_error("Error collecting DVR data for IP $ip: " . $e->getMessage());
        throw $e;
    }
}

function save_dvr_activity($dvrData) {
    try {
        log_error("Attempting to save data for IP: " . $dvrData['ip_address']);
        
        $db = new mysqli('localhost', 'reporting', 'reporting', 'esurv');
        if ($db->connect_error) {
            log_error("Database connection failed: " . $db->connect_error);
            return ['error' => 'DB Connection failed: ' . $db->connect_error];
        }

        // Prepare SQL statement
        $sql = "INSERT INTO dvr_activity (
            ip_address, status, dvr_time, login_time, system_time,
            total_cameras, storage_type, storage_status, storage_capacity, storage_free,
            recording_from, recording_to,
            cam1_status, cam2_status, cam3_status, cam4_status,
            cam5_status, cam6_status, cam7_status, cam8_status
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?
        )";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            log_error("Prepare statement failed: " . $db->error);
            $db->close();
            return ['error' => 'Failed to prepare statement: ' . $db->error];
        }

        log_error("Binding parameters for IP: " . $dvrData['ip_address']);

        $stmt->bind_param(
            'sssssissssssssssssss',
            $dvrData['ip_address'],
            $dvrData['status'],
            $dvrData['dvr_time'],
            $dvrData['login_time'],
            $dvrData['system_time'],
            $dvrData['total_cameras'],
            $dvrData['storage_type'],
            $dvrData['storage_status'],
            $dvrData['storage_capacity'],
            $dvrData['storage_free'],
            $dvrData['recording_from'],
            $dvrData['recording_to'],
            $dvrData['camera_status'][0],
            $dvrData['camera_status'][1],
            $dvrData['camera_status'][2],
            $dvrData['camera_status'][3],
            $dvrData['camera_status'][4],
            $dvrData['camera_status'][5],
            $dvrData['camera_status'][6],
            $dvrData['camera_status'][7]
        );

        $success = $stmt->execute();
        if (!$success) {
            log_error("Execute failed: " . $stmt->error);
        } else {
            log_error("Successfully saved data for IP: " . $dvrData['ip_address']);
        }

        $error = $stmt->error;
        
        $stmt->close();
        $db->close();

        if (!$success) {
            return ['error' => 'Failed to insert record: ' . $error];
        }

        return [
            'success' => true,
            'message' => 'Activity logged successfully',
            'data' => [
                'ip' => $dvrData['ip_address'],
                'status' => $dvrData['status'],
                'total_cameras' => $dvrData['total_cameras'],
                'storage_status' => $dvrData['storage_status']
            ]
        ];
    } catch (Exception $e) {
        log_error("Exception while saving data: " . $e->getMessage());
        throw $e;
    }
}

function log_dvr_activity($ip, $port, $username, $password) {
    try {
        // First collect all data
        $dvrData = collect_dvr_data($ip, $port, $username, $password);
        
        // Then quickly save to database
        return save_dvr_activity($dvrData);
    } catch (Exception $e) {
        log_error("Error in log_dvr_activity for IP $ip: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $data = $_REQUEST;
    // var_dump($data);
    // var_dump($_REQUEST);
    if (!isset($data['dvr_ip']) || !isset($data['dvr_port']) || !isset($data['dvr_user']) || !isset($data['dvr_pass'])) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    $result = log_dvr_activity($data['dvr_ip'], $data['dvr_port'], $data['dvr_user'], $data['dvr_pass']);
    echo json_encode($result);
    exit;
} 