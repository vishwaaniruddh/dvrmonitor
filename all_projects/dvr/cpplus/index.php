<?php
// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

// Main entry point for CP Plus DVR Status (modularized)
require_once __DIR__ . '/device_api.php';
require_once __DIR__ . '/camera_api.php';
require_once __DIR__ . '/hard_disk_api.php';
require_once __DIR__ . '/DvrApiClient.php';
require_once __DIR__ . '/dvr_activity_logger.php';

function fetch_dvr_list_from_db() {
    $db = new mysqli('localhost', 'reporting', 'reporting', 'esurv');
    if ($db->connect_error) {
        die('DB Connection failed: ' . $db->connect_error);
    }
    
    // Get all active DVRs without limit
    $result = $db->query("SELECT IPAddress as ip, port, UserName as username, Password as password 
        FROM all_dvr_live 
        WHERE dvrname in ('CPPLUS','CPPLUS_ORANGE') 
        AND LOWER(live)='y'");
    
    $dvrs = [];
    while ($row = $result->fetch_assoc()) {
        $dvrs[] = $row;
    }

    // Close connection immediately after getting data
    $result->close();
    $db->close();
    
    return $dvrs;
}

function multi_dvr_status($dvrList) {
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $results = [];
    foreach ($dvrList as $i => $dvr) {
        $client = new DvrApiClient($dvr['ip'], $dvr['port'], $dvr['username'], $dvr['password']);
        $ch = $client->getCurlHandle('cgi-bin/global.cgi', ['action' => 'getCurrentTime']);
        $curlHandles[$i] = $ch;
        curl_multi_add_handle($multiHandle, $ch);
    }
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);
    foreach ($curlHandles as $i => $ch) {
        $response = curl_multi_getcontent($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $results[$i] = [
            'ip' => $dvrList[$i]['ip'],
            'status' => $http_code == 200 ? 'OK' : 'FAIL',
            'response' => $response
        ];
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiHandle);
    return $results;
}

if (isset($_GET['test50'])) {
    $start = microtime(true);
    $dvrList = fetch_dvr_list_from_db(50);
    $results = multi_dvr_status($dvrList);
    $end = microtime(true);
    $duration = $end - $start;
    echo '<h2>Tested 50 DVRs</h2>';
    echo '<p>Total time: ' . round($duration, 2) . ' seconds</p>';
    echo '<table border="1"><tr><th>IP</th><th>Status</th><th>Response (truncated)</th></tr>';
    foreach ($results as $res) {
        echo '<tr><td>' . htmlspecialchars($res['ip']) . '</td><td>' . $res['status'] . '</td><td>' . htmlspecialchars(substr($res['response'],0,100)) . '</td></tr>';
    }
    echo '</table>';
    exit;
}
if (isset($_GET['batch_ui'])) {
    ?>
    <h2 style="text-align:center;">CP Plus DVR Dashboard</h2>
    <div style="display:flex;justify-content:center;margin-bottom:20px;gap:20px;">
        <button id="startBatch" class="btn" style="font-size:1.1em;">Start Batch Test</button>
        <span id="dashboardStats" style="font-size:1.1em;"></span>
    </div>
    <div id="onlineSection">
        <h3 style="color:green;">Online DVRs</h3>
        <div id="onlineResults" class="dashboard-grid"></div>
    </div>
    <div id="offlineSection">
        <h3 style="color:red;">Offline/No Network DVRs</h3>
        <div id="offlineResults" class="dashboard-grid"></div>
    </div>
    <script>
    document.getElementById('startBatch').onclick = function() {
        this.disabled = true;
        fetch('index.php?get_dvrs=1')
            .then(r => r.json())
            .then(dvrs => {
                let onlineCount = 0, offlineCount = 0;
                let onlineDiv = document.getElementById('onlineResults');
                let offlineDiv = document.getElementById('offlineResults');
                let stats = document.getElementById('dashboardStats');
                onlineDiv.innerHTML = '';
                offlineDiv.innerHTML = '';
                stats.innerHTML = '';

                // Process DVRs in parallel but with a limit of 5 concurrent requests
                const batchSize = 5;
                let currentBatch = 0;
                
                function processBatch() {
                    const batch = dvrs.slice(currentBatch, currentBatch + batchSize);
                    if (batch.length === 0) return; // All done
                    
                    const promises = batch.map(dvr => {
                        let card = document.createElement('div');
                        card.className = 'status-card dashboard-card';
                        card.id = 'dvr_' + (currentBatch + dvrs.indexOf(dvr));
                        card.innerHTML = `<div class='card-header'><b>${dvr.ip}</b> <span class='badge badge-loading'>Loading...</span></div>`;
                        offlineDiv.appendChild(card);
                        
                        return fetch(`batch_status.php?ip=${encodeURIComponent(dvr.ip)}&port=${dvr.port}&username=${encodeURIComponent(dvr.username)}&password=${encodeURIComponent(dvr.password)}`)
                            .then(r => r.json())
                            .then(res => {
                                let isOnline = res.status === 'Online';
                                let badge = isOnline ? `<span class='badge badge-online'>Online</span>` : (res.status === 'NO NETWORK' ? `<span class='badge badge-offline'>No Network</span>` : `<span class='badge badge-fail'>Offline</span>`);
                                let cardHtml = `<div class='card-header'><b>${res.ip}</b> ${badge}</div>`;
                                if (isOnline && res.deviceInfo && res.deviceInfo.dvrTime) {
                                    cardHtml += `<div class='card-body'>
                                        <div class='row'><span>DVR Time:</span><span>${res.deviceInfo.dvrTime}</span></div>
                                        <div class='row'><span>Login Time:</span><span>${res.deviceInfo.loginTime}</span></div>
                                        <div class='row'><span>System Time:</span><span>${res.deviceInfo.currentDateTime}</span></div>
                                        <div class='row'><span>Total Cameras:</span><span>${res.cameraInfo.totalCameras}</span></div>
                                        <div class='row'><span>Storage Type:</span><span>${res.storageInfo.storageType}</span></div>
                                        <div class='row'><span>Storage Status:</span><span>${res.storageInfo.storageStatus}</span></div>
                                        <div class='row'><span>Capacity:</span><span>${res.storageInfo.storageCapacity} (Free: ${res.storageInfo.storageFree})</span></div>
                                        <div class='row'><span>Recording From:</span><span>${res.recordingInfo.recordingFrom || 'N/A'}</span></div>
                                        <div class='row'><span>Recording To:</span><span>${res.recordingInfo.recordingTo}</span></div>
                                        <div class='row'><span>Camera Status:</span><span><ul class='camera-list'>
                                            ${res.cameraInfo && res.cameraInfo.cameraStatus ? res.cameraInfo.cameraStatus.map(c => `<li class='${c.status=="Working"?"cam-ok":"cam-fail"}'>Camera ${c.number}: ${c.status}</li>`).join('') : ''}
                                        </ul></span></div>
                                    </div>`;
                                } else {
                                    cardHtml += `<div class='card-body'><b style='color:red;'>${res.status === 'NO NETWORK' ? 'No network connectivity (ping failed)' : 'API Failure or No Data'}</b></div>`;
                                }
                                card.innerHTML = cardHtml;
                                
                                if (isOnline) {
                                    onlineDiv.appendChild(card);
                                    onlineCount++;
                                } else {
                                    offlineDiv.appendChild(card);
                                    offlineCount++;
                                }
                                stats.innerHTML = `<span style='color:green;'>Online: ${onlineCount}</span> | <span style='color:red;'>Offline/No Network: ${offlineCount}</span> | Total: ${dvrs.length}`;
                            });
                    });
                    
                    Promise.all(promises).then(() => {
                        currentBatch += batchSize;
                        if (currentBatch < dvrs.length) {
                            setTimeout(processBatch, 100); // Add small delay between batches
                        }
                    });
                }
                
                processBatch(); // Start processing
            });
    };
    </script>
    <style>
    .dashboard-grid { display: flex; flex-wrap: wrap; gap: 18px; }
    .dashboard-card { width: 370px; min-height: 220px; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #0002; border: 1px solid #e0e0e0; margin-bottom: 10px; transition: box-shadow 0.2s; }
    .dashboard-card:hover { box-shadow: 0 4px 24px #007bff33; }
    .card-header { font-size: 1.1em; font-weight: bold; padding: 10px 15px; background: #f5f7fa; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; border-radius: 10px 10px 0 0; }
    .card-body { padding: 12px 15px; }
    .row { display: flex; justify-content: space-between; margin-bottom: 6px; }
    .badge { padding: 3px 10px; border-radius: 12px; font-size: 0.95em; font-weight: 600; }
    .badge-online { background: #d4f8e8; color: #1ca661; border: 1px solid #1ca661; }
    .badge-offline, .badge-fail { background: #ffeaea; color: #d32f2f; border: 1px solid #d32f2f; }
    .badge-loading { background: #e3e3e3; color: #888; border: 1px solid #bbb; }
    .camera-list { margin: 0; padding-left: 18px; }
    .cam-ok { color: #1ca661; }
    .cam-fail { color: #d32f2f; font-weight: bold; }
    h3 { margin-bottom: 8px; }
    </style>
    <?php
    exit;
}
if (isset($_GET['get_dvrs'])) {
    header('Content-Type: application/json');
    echo json_encode(fetch_dvr_list_from_db());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CP Plus DVR Status</title>
    <style>
        body { font-family: sans-serif; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="password"], input[type="number"] { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { padding: 10px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        .status-card { border: 1px solid #ccc; padding: 15px; margin-top: 20px; }
        .status-card h2 { margin-top: 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>CP Plus DVR Status Checker</h1>
    <form method="POST" action="">
        <div class="form-group">
            <label for="dvr_ip">DVR IP Address</label>
            <input type="text" id="dvr_ip" name="dvr_ip" required>
        </div>
        <div class="form-group">
            <label for="dvr_port">DVR Port</label>
            <input type="number" id="dvr_port" name="dvr_port" value="80" required>
        </div>
        <div class="form-group">
            <label for="dvr_user">Username</label>
            <input type="text" id="dvr_user" name="dvr_user" required>
        </div>
        <div class="form-group">
            <label for="dvr_pass">Password</label>
            <input type="password" id="dvr_pass" name="dvr_pass" required>
        </div>
        <button type="submit" class="btn">Get DVR Status</button>
    </form>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dvrIp = $_POST['dvr_ip'];
        $dvrPort = $_POST['dvr_port'];
        $dvrUser = $_POST['dvr_user'];
        $dvrPass = $_POST['dvr_pass'];
        $client = new DvrApiClient($dvrIp, $dvrPort, $dvrUser, $dvrPass);
        $deviceInfo = get_device_info($client);
        $cameraInfo = get_camera_info($client);
        $storageInfo = get_storage_info($client);
        $recordingInfo = get_recording_info($client, $deviceInfo['dvrTime']);
    ?>
    <div class="status-card">
        <h2>DVR Status</h2>
        <p><strong>System Time:</strong> <?php echo $deviceInfo['currentDateTime']; ?></p>
        <p><strong>Login Time:</strong> <?php echo $deviceInfo['loginTime']; ?></p>
        <p><strong>DVR Time:</strong> <?php echo $deviceInfo['dvrTime']; ?></p>
        <p><strong>Total Cameras:</strong> <?php echo $cameraInfo['totalCameras']; ?></p>
        <h3>Camera Status</h3>
        <ul>
            <?php foreach ($cameraInfo['cameraStatus'] as $camera): ?>
                <li>Camera <?php echo $camera['number']; ?>: <?php echo $camera['status']; ?></li>
            <?php endforeach; ?>
        </ul>
        <h3>Storage Information</h3>
        <p><strong>Type:</strong> <?php echo $storageInfo['storageType']; ?></p>
        <p><strong>Status:</strong> <?php echo $storageInfo['storageStatus']; ?></p>
        <p><strong>Capacity:</strong> <?php echo $storageInfo['storageCapacity']; ?> (Free: <?php echo $storageInfo['storageFree']; ?>)</p>
        <p><strong>Recording From:</strong> <?php echo $recordingInfo['recordingFrom']; ?></p>
        <p><strong>Recording To:</strong> <?php echo $recordingInfo['recordingTo']; ?></p>
    </div>
    <?php } ?>
</div>
</body>
</html>
