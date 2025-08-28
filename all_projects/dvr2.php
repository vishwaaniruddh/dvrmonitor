<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cpplus DVR Status Monitor</title>
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
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .section-title {
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .status-item {
            display: flex;
            margin-bottom: 8px;
        }
        .status-label {
            font-weight: bold;
            min-width: 200px;
        }
        .camera-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        .camera-card {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .camera-working {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .camera-not-working {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .storage-bar {
            height: 20px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        .storage-used {
            height: 100%;
            background-color: #007bff;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #0069d9;
        }
        .error {
            color: #dc3545;
            margin-top: 10px;
        }
        .success {
            color: #28a745;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cpplus DVR Status Monitor</h1>
        
        <form method="post">
            <div class="form-group">
                <label for="dvr_ip">DVR IP Address:</label>
                <input type="text" id="dvr_ip" name="dvr_ip" placeholder="192.168.1.100" value="<?php echo $_REQUEST['dvr_ip'];?>" required>
            </div>
            
            <div class="form-group">
                <label for="dvr_port">Port:</label>
                <input type="text" id="dvr_port" name="dvr_port" placeholder="80" value="<?php echo $_REQUEST['dvr_port'];?>" required>
            </div>
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="admin" value="<?php echo $_REQUEST['username'];?>" required>
            </div> 
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="text" id="password" name="password" placeholder="password" value="<?php echo $_REQUEST['password'];?>" required>
            </div>
            
            <button type="submit" name="submit">Get DVR Status</button>
        </form>

        <?php
        if (isset($_POST['submit'])) {
            // Get form data
            $dvr_ip = $_POST['dvr_ip'];
            $dvr_port = $_POST['dvr_port'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            try {
                // 1. Current datetime
                echo '<div class="section">';
                echo '<div class="section-title">1. Current Date/Time</div>';
                echo '<div class="status-item"><span class="status-label">Server Time:</span> ' . date("Y-m-d H:i:s") . '</div>';
                
                // Attempt to connect to DVR
                echo $login_url = "http://$dvr_ip:$dvr_port/cgi-bin/api.cgi?cmd=Login";
                $login_data = json_encode([
                    "User" => $username,
                    "Password" => $password
                ]);
                
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => $login_data,
                        'timeout' => 5
                    ]
                ]);
                
                $login_response = @file_get_contents($login_url, false, $context);
                
                if ($login_response === false) {
                    throw new Exception("Failed to connect to DVR. Check IP/Port and network connection.");
                }
                
                $login_data = json_decode($login_response, true);
                
                if (!isset($login_data['Session'])) {
                    throw new Exception("Login failed. Check username and password.");
                }
                
                $session = $login_data['Session'];
                
                // 2. Login time
                echo '<div class="status-item"><span class="status-label">Login Time:</span> ' . date("Y-m-d H:i:s") . '</div>';
                echo '</div>';
                
                // 3. Get DVR time (mock - replace with actual API call)
                echo '<div class="section">';
                echo '<div class="section-title">2. DVR Information</div>';
                echo '<div class="status-item"><span class="status-label">DVR Time:</span> ' . date("Y-m-d H:i:s") . ' (Mock data - replace with actual API call)</div>';
                
                // 4. Get total number of cameras (mock)
                $totalCameras = 8;
                echo '<div class="status-item"><span class="status-label">Total Cameras:</span> ' . $totalCameras . ' (Mock data)</div>';
                echo '</div>';
                
                // 5. Camera status
                echo '<div class="section">';
                echo '<div class="section-title">3. Camera Status</div>';
                echo '<div class="camera-grid">';
                
                for ($i = 1; $i <= $totalCameras; $i++) {
                    $status = ($i % 4 != 0); // Mock status
                    $statusClass = $status ? 'camera-working' : 'camera-not-working';
                    $statusText = $status ? 'Working' : 'Not Working';
                    
                    echo '<div class="camera-card ' . $statusClass . '">';
                    echo '<div>Camera ' . $i . '</div>';
                    echo '<div>' . $statusText . '</div>';
                    echo '</div>';
                }
                
                echo '</div></div>';
                
                // 6. Storage information
                echo '<div class="section">';
                echo '<div class="section-title">4. Storage Information</div>';
                
                // Storage type
                echo '<div class="status-item"><span class="status-label">Storage Type:</span> HDD (Mock data)</div>';
                
                // Storage status
                echo '<div class="status-item"><span class="status-label">Storage Status:</span> Normal (Mock data)</div>';
                
                // Storage capacity
                echo '<div class="status-item"><span class="status-label">Storage Capacity:</span>';
                echo '<div>750 GB used of 2000 GB</div>';
                echo '<div class="storage-bar"><div class="storage-used" style="width: 37.5%"></div></div>';
                echo '</div>';
                
                echo '</div>';
                
                // 7. Recording information
                echo '<div class="section">';
                echo '<div class="section-title">5. Recording Information</div>';
                echo '<div class="status-item"><span class="status-label">Recording From:</span> ' . date("Y-m-d H:i:s", strtotime("-7 days")) . ' (Mock data)</div>';
                echo '<div class="status-item"><span class="status-label">Recording To:</span> ' . date("Y-m-d H:i:s") . ' (Mock data)</div>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
    </div>
</body>
</html>