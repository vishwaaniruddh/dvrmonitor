<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CameraStatusService
{
    protected $timeout = 5;
    protected $connectTimeout = 3;

    /**
     * Check camera status using snapshot API
     * If snapshot is received, camera is working
     */
    public function checkCameraStatus(string $ip, int $port, string $username, string $password, int $maxChannels = 5): array
    {
        $results = [
            'total_cameras' => 0,
            'working_cameras' => 0,
            'cameras' => []
        ];

        Log::info("Starting camera status check for {$ip}:{$port}");

        // Test channels 1 to maxChannels
        for ($channel = 1; $channel <= $maxChannels; $channel++) {
            $cameraResult = $this->testCameraChannel($ip, $port, $username, $password, $channel);
            
            if ($cameraResult['exists']) {
                $results['total_cameras']++;
                $results['cameras'][$channel] = $cameraResult;
                
                if ($cameraResult['working']) {
                    $results['working_cameras']++;
                }
            } else {
                // If channel doesn't exist, stop checking higher channels
                break;
            }
        }

        Log::info("Camera check completed", [
            'ip' => $ip,
            'total_cameras' => $results['total_cameras'],
            'working_cameras' => $results['working_cameras']
        ]);

        return $results;
    }

    /**
     * Test individual camera channel using snapshot API
     */
    protected function testCameraChannel(string $ip, int $port, string $username, string $password, int $channel): array
    {
        $result = [
            'channel' => $channel,
            'exists' => false,
            'working' => false,
            'error' => null,
            'response_time' => 0,
            'image_size' => 0
        ];

        $startTime = microtime(true);

        try {
            // Snapshot API URL: http://<server>/cgi-bin/snapshot.cgi?channel=1&type=0
            $url = "http://{$ip}:{$port}/cgi-bin/snapshot.cgi?channel={$channel}&type=0";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);

            if ($response === false || !empty($error)) {
                $result['error'] = $error ?: 'Request failed';
                return $result;
            }

            // Check if we got a valid response
            if ($httpCode === 200) {
                $result['exists'] = true;
                
                // Check if response contains image data
                if (strpos($contentType, 'image/jpeg') !== false || strpos($response, 'image/jpeg') !== false) {
                    // Extract image data (skip headers)
                    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                    if ($headerSize > 0) {
                        $imageData = substr($response, $headerSize);
                    } else {
                        // Fallback: find double CRLF
                        $pos = strpos($response, "\r\n\r\n");
                        $imageData = $pos !== false ? substr($response, $pos + 4) : $response;
                    }
                    
                    $result['image_size'] = strlen($imageData);
                    
                    // If we got image data, camera is working
                    if ($result['image_size'] > 1000) { // Minimum size for valid JPEG
                        $result['working'] = true;
                    } else {
                        $result['error'] = 'Image too small or corrupted';
                    }
                } else {
                    $result['error'] = 'No image data received';
                }
            } else if ($httpCode === 404) {
                // Channel doesn't exist
                $result['error'] = 'Channel not found';
            } else if ($httpCode === 401) {
                $result['exists'] = true; // Channel exists but auth failed
                $result['error'] = 'Authentication failed';
            } else {
                $result['exists'] = true; // Assume channel exists if we get any response
                $result['error'] = "HTTP {$httpCode}";
            }

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Test RTSP stream availability (optional - more complex)
     */
    public function testRtspStream(string $ip, int $rtspPort, string $username, string $password, int $channel, int $subtype = 0): array
    {
        $result = [
            'channel' => $channel,
            'subtype' => $subtype,
            'available' => false,
            'error' => null,
            'response_time' => 0
        ];

        $startTime = microtime(true);

        try {
            // RTSP URL: rtsp://192.168.1.250:554/cam/realmonitor?channel=1&subtype=0
            $rtspUrl = "rtsp://{$username}:{$password}@{$ip}:{$rtspPort}/cam/realmonitor?channel={$channel}&subtype={$subtype}";
            
            // Simple RTSP DESCRIBE request
            $socket = fsockopen($ip, $rtspPort, $errno, $errstr, $this->connectTimeout);
            
            if (!$socket) {
                $result['error'] = "Connection failed: {$errstr} ({$errno})";
                return $result;
            }

            $request = "DESCRIBE {$rtspUrl} RTSP/1.0\r\n";
            $request .= "CSeq: 1\r\n";
            $request .= "User-Agent: CameraStatusService/1.0\r\n";
            $request .= "\r\n";

            fwrite($socket, $request);
            
            // Set timeout for reading
            stream_set_timeout($socket, $this->timeout);
            
            $response = '';
            while (!feof($socket)) {
                $line = fgets($socket, 1024);
                if ($line === false) break;
                $response .= $line;
                
                // Stop after getting headers
                if (trim($line) === '') break;
            }
            
            fclose($socket);

            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);

            // Check if we got a valid RTSP response
            if (strpos($response, 'RTSP/1.0 200 OK') !== false) {
                $result['available'] = true;
            } else if (strpos($response, 'RTSP/1.0') !== false) {
                $result['error'] = 'RTSP error response';
            } else {
                $result['error'] = 'Invalid RTSP response';
            }

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get comprehensive camera information
     */
    public function getCameraInfo(string $ip, int $port, string $username, string $password, int $maxChannels = 5): array
    {
        // Try to get actual max channels from DVR API first
        $actualMaxChannels = $this->getActualMaxChannels($ip, $port, $username, $password);
        if ($actualMaxChannels > 0) {
            $maxChannels = $actualMaxChannels;
            Log::info("Using actual max channels from DVR API: $maxChannels for IP: $ip");
        } else {
            Log::info("Using fallback max channels: $maxChannels for IP: $ip");
        }
        
        $snapshotResults = $this->checkCameraStatus($ip, $port, $username, $password, $maxChannels);
        
        $result = [
            'ip' => $ip,
            'port' => $port,
            'total_cameras' => $snapshotResults['total_cameras'],
            'working_cameras' => $snapshotResults['working_cameras'],
            'cameras' => [],
            'max_channels_detected' => $actualMaxChannels,
            'summary' => [
                'all_working' => $snapshotResults['working_cameras'] === $snapshotResults['total_cameras'],
                'some_working' => $snapshotResults['working_cameras'] > 0,
                'none_working' => $snapshotResults['working_cameras'] === 0
            ]
        ];

        // Add detailed camera info
        foreach ($snapshotResults['cameras'] as $channel => $camera) {
            $result['cameras'][$channel] = [
                'channel' => $channel,
                'exists' => $camera['exists'],
                'working' => $camera['working'],
                'status' => $camera['working'] ? 'online' : 'offline',
                'error' => $camera['error'],
                'response_time' => $camera['response_time'],
                'image_size' => $camera['image_size']
            ];
        }

        return $result;
    }

    /**
     * Get actual max channels from DVR API
     */
    protected function getActualMaxChannels(string $ip, int $port, string $username, string $password): int
    {
        try {
            // Try CP Plus API first
            $url = "http://{$ip}:{$port}/cgi-bin/magicBox.cgi?action=getProductDefinition&name=MaxRemoteInputChannels";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && $response) {
                // Parse response: table.MaxRemoteInputChannels=3
                if (preg_match('/table\.MaxRemoteInputChannels=(\d+)/', $response, $matches)) {
                    $maxChannels = (int)$matches[1];
                    Log::info("Detected max channels from CP Plus API: $maxChannels for IP: $ip");
                    return $maxChannels;
                }
            }
            
            // TODO: Add support for other DVR types (Hikvision, Dahua, etc.)
            
        } catch (\Exception $e) {
            Log::warning("Failed to get max channels from DVR API for IP: $ip - " . $e->getMessage());
        }
        
        return 0; // Return 0 if detection failed
    }
}