<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Initialize variables
$dvrInfo = null;
$error = '';
$currentDateTime = date('Y-m-d H:i:s');
$apiResponse = null;

// Check if IP was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ip'])) {
    $ip = trim($_POST['ip']);
    
    try {
        // First get DVR credentials from database
        $query = "SELECT dvrname, IPAddress, port, UserName, Password 
                  FROM all_dvr_live 
                  WHERE IPAddress = ? AND dvrname = 'Hikvision'";
        
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $dvrInfo = $result->fetch_assoc();
            
            // Now call Hikvision API
            $apiResponse = checkHikvisionStatus(
                $dvrInfo['IPAddress'],
                $dvrInfo['port'],
                $dvrInfo['UserName'],
                $dvrInfo['Password']
            );
        } else {
            $error = "No Hikvision DVR found with IP: " . htmlspecialchars($ip);
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

/**
 * Check Hikvision DVR status using ISAPI
 */
function checkHikvisionStatus($ip, $port, $username, $password) {
    $baseUrl = "http://{$ip}:{$port}/ISAPI";
    $ch = curl_init();
    
    // Array to store all responses
    $responses = [];
    
    try {
        // 1. Get device time (DVR time)
        curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/System/time");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $timeResponse = curl_exec($ch);
        $responses['time'] = simplexml_load_string($timeResponse);
        
        // 2. Get device info
        curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/System/deviceInfo");
        $deviceInfo = curl_exec($ch);
        $responses['deviceInfo'] = simplexml_load_string($deviceInfo);
        
        // 3. Get HDD status
        curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/ContentMgmt/Storage/hdd");
        $hddInfo = curl_exec($ch);
        $responses['hdd'] = simplexml_load_string($hddInfo);
        
        // 4. Get camera status (channels)
        curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/Streaming/channels");
        $channels = curl_exec($ch);
        $responses['channels'] = simplexml_load_string($channels);
        
        // 5. Get recording status
        curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/ContentMgmt/record/tracks");
        $recording = curl_exec($ch);
        $responses['recording'] = simplexml_load_string($recording);
        
    } catch (Exception $e) {
        throw new Exception("Hikvision API error: " . $e->getMessage());
    } finally {
        curl_close($ch);
    }
    
    return $responses;
}

// Function to count working cameras from API response
function countWorkingCameras($apiResponse) {
    if (!isset($apiResponse['channels'])) return 0;
    
    $count = 0;
    foreach ($apiResponse['channels']->channel as $channel) {
        if ((string)$channel->enabled === 'true') {
            $count++;
        }
    }
    return $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hikvision DVR Status Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .dvr-info {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 250px;
        }
        .camera-status {
            margin-top: 15px;
        }
        .status-good {
            color: green;
        }
        .status-bad {
            color: red;
        }
        .api-response {
            margin-top: 20px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hikvision DVR Status Checker</h1>
        
        <form method="post">
            <div class="form-group">
                <label for="ip">Enter Hikvision DVR IP Address:</label>
                <input type="text" id="ip" name="ip" required>
            </div>
            <button type="submit">Check Status</button>
        </form>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($dvrInfo && $apiResponse): ?>
            <div class="dvr-info">
                <h2>Hikvision DVR Information</h2>
                
                <div class="info-row">
                    <div class="info-label">Current Date/Time:</div>
                    <div><?php echo $currentDateTime; ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">DVR Name:</div>
                    <div><?php echo htmlspecialchars($dvrInfo['dvrname']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">IP Address:</div>
                    <div><?php echo htmlspecialchars($dvrInfo['IPAddress']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Port:</div>
                    <div><?php echo htmlspecialchars($dvrInfo['port']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">DVR Time:</div>
                    <div>
                        <?php if (isset($apiResponse['time']->time)): ?>
                            <?php echo htmlspecialchars((string)$apiResponse['time']->time); ?>
                        <?php else: ?>
                            <span class="status-bad">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Device Model:</div>
                    <div>
                        <?php if (isset($apiResponse['deviceInfo']->model)): ?>
                            <?php echo htmlspecialchars((string)$apiResponse['deviceInfo']->model); ?>
                        <?php else: ?>
                            <span class="status-bad">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Firmware Version:</div>
                    <div>
                        <?php if (isset($apiResponse['deviceInfo']->firmwareVersion)): ?>
                            <?php echo htmlspecialchars((string)$apiResponse['deviceInfo']->firmwareVersion); ?>
                        <?php else: ?>
                            <span class="status-bad">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Total Cameras:</div>
                    <div>
                        <?php echo countWorkingCameras($apiResponse); ?> cameras detected
                    </div>
                </div>
                
                <div class="camera-status">
                    <h3>Camera Status</h3>
                    <?php if (isset($apiResponse['channels']->channel)): ?>
                        <?php foreach ($apiResponse['channels']->channel as $channel): ?>
                            <div class="info-row">
                                <div class="info-label">Camera <?php echo ((int)$channel->id + 1); ?>:</div>
                                <div>
                                    <?php if ((string)$channel->enabled === 'true'): ?>
                                        <span class="status-good">Working</span>
                                    <?php else: ?>
                                        <span class="status-bad">Not Working</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="error">Camera status not available</div>
                    <?php endif; ?>
                </div>
                
                <div class="info-row">
                    <div class="info-label">HDD Status:</div>
                    <div>
                        <?php if (isset($apiResponse['hdd']->hddList->hdd)): ?>
                            <?php foreach ($apiResponse['hdd']->hddList->hdd as $hdd): ?>
                                <div>
                                    HDD <?php echo (string)$hdd->id; ?>: 
                                    <?php if ((string)$hdd->status === 'normal'): ?>
                                        <span class="status-good">Normal</span>
                                    <?php else: ?>
                                        <span class="status-bad"><?php echo (string)$hdd->status; ?></span>
                                    <?php endif; ?>
                                    (Total: <?php echo round((int)$hdd->capacity / (1024*1024*1024), 2); ?> GB, 
                                    Free: <?php echo round((int)$hdd->freeSpace / (1024*1024*1024), 2); ?> GB)
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="status-bad">Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Recording Status:</div>
                    <div>
                        <?php if (isset($apiResponse['recording']->trackList->track)): ?>
                            <?php foreach ($apiResponse['recording']->trackList->track as $track): ?>
                                <div>
                                    Camera <?php echo (int)$track->trackID + 1; ?>: 
                                    <?php if ((string)$track->recordingStatus === 'recording'): ?>
                                        <span class="status-good">Recording</span>
                                    <?php else: ?>
                                        <span class="status-bad">Not Recording</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="status-bad">Recording status not available</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($_GET['debug'])): ?>
                    <div class="api-response">
                        <h3>API Responses (Debug)</h3>
                        <?php foreach ($apiResponse as $key => $response): ?>
                            <h4><?php echo ucfirst($key); ?>:</h4>
                            <?php echo htmlspecialchars($response->asXML()); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>