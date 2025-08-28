<?php
// index.php - OOP Hikvision DVR Dashboard Entry Point
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/HikvisionApiClient.php';
require_once __DIR__ . '/HikvisionCameraApi.php';
require_once __DIR__ . '/HikvisionStorageApi.php';
require_once __DIR__ . '/HikvisionTimeApi.php';
require_once __DIR__ . '/HikvisionDvr.php';

use GuzzleHttp\Client;

// --- Config ---
$dvr_name = 'Hikvision';

if (isset($_GET['batch_ui'])) {
    ?>
    <h2 style="text-align:center;">Hikvision DVR Dashboard (OOP)</h2>
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
                const batchSize = 5;
                let currentBatch = 0;
                function processBatch() {
                    const batch = dvrs.slice(currentBatch, currentBatch + batchSize);
                    if (batch.length === 0) return;
                    const promises = batch.map(dvr => {
                        let card = document.createElement('div');
                        card.className = 'status-card dashboard-card';
                        card.id = 'dvr_' + (currentBatch + dvrs.indexOf(dvr));
                        card.innerHTML = `<div class='card-header'><b>${dvr.ip}</b> <span class='badge badge-loading'>Loading...</span></div>`;
                        offlineDiv.appendChild(card);
                        return fetch(`index.php?api_status=1&ip=${encodeURIComponent(dvr.ip)}&port=${dvr.port}&username=${encodeURIComponent(dvr.username)}&password=${encodeURIComponent(dvr.password)}`)
                            .then(r => r.json())
                            .then(res => {
                                let isOnline = res.status === 'OK';
                                let badge = isOnline ? `<span class='badge badge-online'>Online</span>` : (res.status === 'NO NETWORK' ? `<span class='badge badge-offline'>No Network</span>` : `<span class='badge badge-fail'>Offline</span>`);
                                let cardHtml = `<div class='card-header'><b>${res.ip}</b> ${badge}</div>`;
                                if (isOnline) {
                                    cardHtml += `<div class='card-body'>` +
                                        `<div class='row'><span>DVR Time:</span><span>${res.dvr_time || ''}</span></div>` +
                                        `<div class='row'><span>Login Time:</span><span>${res.login_time || 'N/A'}</span></div>` +
                                        `<div class='row'><span>System Time:</span><span>${res.system_time || 'N/A'}</span></div>` +
                                        `<div class='row'><span>Total Cameras:</span><span>${res.total_cameras || 'N/A'}</span></div>` +
                                        `<div class='row'><span>Storage Type:</span><span>${res.storageType || ''}</span></div>` +
                                        `<div class='row'><span>Storage Status:</span><span>${res.hdd_status || ''}</span></div>` +
                                        `<div class='row'><span>Capacity:</span><span>${res.storageCapacity || ''}${res.storageFree ? ` (Free: ${res.storageFree})` : ''}</span></div>` +
                                        `<div class='row'><span>Recording From:</span><span>${res.recording_from || 'N/A'}</span></div>` +
                                        `<div class='row'><span>Recording To:</span><span>${res.recording_to || 'N/A'}</span></div>` +
                                        `<div class='row'><span>Camera Status:</span><span><ul class='camera-list'>` +
                                        (res.camera_statuses ? Object.entries(res.camera_statuses).map(([num, stat]) => `<li class='${stat=="Working"?"cam-ok":"cam-fail"}'>Camera ${num}: ${stat}</li>`).join('') : '') +
                                        `</ul></span></div>` +
                                        `</div>`;
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
                            setTimeout(processBatch, 100);
                        }
                    });
                }
                processBatch();
            });
    };
    </script>
    <style>
    .dashboard-grid { display: flex; flex-wrap: wrap; gap: 18px; }
    .dashboard-card { width: 370px; min-height: 120px; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #0002; border: 1px solid #e0e0e0; margin-bottom: 10px; transition: box-shadow 0.2s; }
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
    $mysqli = new mysqli('localhost', 'reporting', 'reporting', 'esurv');
    if ($mysqli->connect_error) {
        die(json_encode(['error' => 'DB Connection failed: ' . $mysqli->connect_error]));
    }
    $result = $mysqli->query("SELECT IPAddress as ip, port, UserName as username, Password as password FROM all_dvr_live WHERE dvrname = 'UNV' AND LOWER(live)='y' ");
    $dvrs = [];
    while ($row = $result->fetch_assoc()) {
        $dvrs[] = $row;
    }
    $mysqli->close();
    echo json_encode($dvrs);
    exit;
}
if (isset($_GET['api_status'])) {
    header('Content-Type: application/json');
    $ip = $_GET['ip'];
    $port = $_GET['port'];
    $username = $_GET['username'];
    $password = $_GET['password'];
    // Improve ping reliability: try 2 times before declaring offline
    $ping = ping_ip($ip);
    if ($ping !== 'Working') {
        // Try a second time to avoid false negatives
        sleep(1);
        $ping = ping_ip($ip);
    }
    if ($ping !== 'Working') {
        echo json_encode([
            'ip' => $ip,
            'status' => 'NO NETWORK',
            'camera_statuses' => [],
            'hdd_status' => '',
            'dvr_time' => '',
            'login_time' => '',
            'system_time' => '',
            'total_cameras' => '',
            'storageType' => '',
            'storageStatus' => '',
            'storageCapacity' => '',
            'storageFree' => '',
            'recording_from' => '',
            'recording_to' => ''
        ]);
        exit;
    }
    $dvr = new HikvisionDvr($ip, $port, $username, $password);
    $status = $dvr->getStatus();
    $status['ip'] = $ip;
    $status['status'] = 'OK';
    echo json_encode($status);
    exit;
}

function ping_ip($ip) {
    $os = strtoupper(substr(PHP_OS, 0, 3));
    $ping = ($os === 'WIN') ? "ping -n 1 -w 1000 $ip" : "ping -c 1 -W 1 $ip";
    exec($ping, $output, $status);
    return $status === 0 ? 'Working' : 'Not Working';
}
?>
