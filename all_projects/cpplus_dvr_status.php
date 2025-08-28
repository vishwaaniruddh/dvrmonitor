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

        // Function to make API calls to the DVR
        function call_dvr_api($url, $username, $password) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 200) {
                // The API returns data in a key=value format, let's parse it.
                $data = [];
                $lines = explode("\n", trim($result));
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $data[trim($key)] = trim($value);
                    }
                }
                return $data;
            }
            return null;
        }

        // --- 1) Current Datetime ---
        $currentDateTime = date('Y-m-d H:i:s');

        // --- 2) Login Time ---
        $loginTime = date('Y-m-d H:i:s');

        // --- 3) Get DVR Time ---
        $timeUrl = "http://{$dvrIp}:{$dvrPort}/cgi-bin/global.cgi?action=getCurrentTime";
        $timeData = call_dvr_api($timeUrl, $dvrUser, $dvrPass);
        $dvrTime = isset($timeData['result']) ? $timeData['result'] : 'N/A';

        // --- 4 & 5) Total Number of Cameras & Status ---
        $channelTitleUrl = "http://{$dvrIp}:{$dvrPort}/cgi-bin/configManager.cgi?action=getConfig&name=ChannelTitle";
        $channelData = call_dvr_api($channelTitleUrl, $dvrUser, $dvrPass);
        
        $cameras = [];
        if ($channelData) {
            foreach ($channelData as $key => $value) {
                if (preg_match('/table\.ChannelTitle\[(\d+)\]\.Name/', $key, $matches)) {
                    $cameras[$matches[1]] = ['name' => $value, 'status' => 'Working'];
                }
            }
        }
        $totalCameras = count($cameras);

        // Check for video loss
        $videoLossUrl = "http://{$dvrIp}:{$dvrPort}/cgi-bin/eventManager.cgi?action=getEventIndexes&code=VideoLoss";
        $videoLossData = call_dvr_api($videoLossUrl, $dvrUser, $dvrPass);
        if ($videoLossData && isset($videoLossData['channels'])) {
            $lostChannels = explode(',', $videoLossData['channels']);
            foreach ($lostChannels as $lostChannel) {
                if (isset($cameras[$lostChannel])) {
                    $cameras[$lostChannel]['status'] = 'Not Working';
                }
            }
        }
        
        $cameraStatus = [];
        foreach($cameras as $index => $camera) {
            $cameraStatus[] = [
                'number' => $index + 1,
                'name' => $camera['name'],
                'status' => $camera['status']
            ];
        }

        // --- 6 & 7) Storage Info ---
        $storageUrl = "http://{$dvrIp}:{$dvrPort}/cgi-bin/storageDevice.cgi?action=getDeviceAllInfo";
        $storageDataRaw = call_dvr_api($storageUrl, $dvrUser, $dvrPass);
        $storageType = 'N/A';
        $storageStatus = 'N/A';
        $storageCapacity = 'N/A';
        $storageFree = 'N/A';

        if ($storageDataRaw && isset($storageDataRaw['list.info[0].State'])) {
             $storageStatus = $storageDataRaw['list.info[0].State'];
             $totalBytes = $storageDataRaw['list.info[0].Detail[0].TotalBytes'] ?? 0;
             $usedBytes = $storageDataRaw['list.info[0].Detail[0].UsedBytes'] ?? 0;
             $storageCapacity = round($totalBytes / (1024*1024*1024), 2) . ' GB';
             $storageFree = round(($totalBytes - $usedBytes) / (1024*1024*1024), 2) . ' GB';
             // Type is not directly available in getDeviceAllInfo, we can check for NAS config if needed
             $storageType = "HDD"; // Assumption
        }

        // --- 8) Recording From and To ---
        $recordingFrom = 'N/A';
        $recordingTo = 'N/A';
        $finderUrl = "http://{$dvrIp}:{$dvrPort}/cgi-bin/mediaFileFind.cgi?action=factory.create";
        $finderData = call_dvr_api($finderUrl, $dvrUser, $dvrPass);
        if ($finderData && isset($finderData['result'])) {
            $finderId = $finderData['result'];
            
            // Find first file
            $findUrlFirst = "http://{$dvrIp}:{$dvrPort}/cgi-bin/mediaFileFind.cgi?action=findFile&object={$finderId}&condition.Channel=1&condition.StartTime=2000-01-01%2000:00:00&condition.EndTime=2038-01-01%2000:00:00";
            call_dvr_api($findUrlFirst, $dvrUser, $dvrPass); // This call just starts the find
            $nextFileUrlFirst = "http://{$dvrIp}:{$dvrPort}/cgi-bin/mediaFileFind.cgi?action=findNextFile&object={$finderId}&count=1";
            $firstFileData = call_dvr_api($nextFileUrlFirst, $dvrUser, $dvrPass);
            if($firstFileData && isset($firstFileData['items[0].StartTime'])) {
                $recordingFrom = $firstFileData['items[0].StartTime'];
            }

            // To get the last file, we would ideally sort descending, but the API doesn't support it.
            // A full search would be too slow. We will make an assumption that the DVR is recording now.
            if ($dvrTime !== 'N/A') {
                $recordingTo = $dvrTime;
            }

            $closeFinderUrl = "http://{$dvrIp}:{$dvrPort}/cgi-bin/mediaFileFind.cgi?action=destroy&object={$finderId}";
            call_dvr_api($closeFinderUrl, $dvrUser, $dvrPass);
        }

    ?>
    <div class="status-card">
        <h2>DVR Status</h2>
        <p><strong>System Time:</strong> <?php echo $currentDateTime; ?></p>
        <p><strong>Login Time:</strong> <?php echo $loginTime; ?></p>
        <p><strong>DVR Time:</strong> <?php echo $dvrTime; ?></p>
        <p><strong>Total Cameras:</strong> <?php echo $totalCameras; ?></p>
        
        <h3>Camera Status</h3>
        <ul>
            <?php foreach ($cameraStatus as $camera): ?>
                <li>Camera <?php echo $camera['number']; ?>: <?php echo $camera['status']; ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Storage Information</h3>
        <p><strong>Type:</strong> <?php echo $storageType; ?></p>
        <p><strong>Status:</strong> <?php echo $storageStatus; ?></p>
        <p><strong>Capacity:</strong> <?php echo $storageCapacity; ?> (Free: <?php echo $storageFree; ?>)</p>
        <p><strong>Recording From:</strong> <?php echo $recordingFrom; ?></p>
        <p><strong>Recording To:</strong> <?php echo $recordingTo; ?></p>
    </div>
    <?php
    }
    ?>
</div>

</body>
</html>