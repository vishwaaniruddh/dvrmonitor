<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CpPlusDvrApiService extends BaseDvrApiService
{
    public function login(string $ip, int $port, string $username, string $password): array
    {
        // CP Plus uses HTTP Digest Authentication, no separate login needed
        // Test with getCurrentTime to verify credentials
        $url = $this->buildUrl($ip, $port, '/cgi-bin/global.cgi?action=getCurrentTime');
        
        $response = $this->makeDigestRequest($url, $username, $password);
        
        if ($response['success'] && $response['status_code'] == 200) {
            return [
                'success' => true,
                'session_token' => base64_encode($username . ':' . $password), // Store credentials for subsequent calls
                'message' => 'Login successful'
            ];
        }

        return [
            'success' => false,
            'session_token' => null,
            'message' => 'Login failed: ' . ($response['error'] ?? 'Authentication failed')
        ];
    }

    public function getDvrTime(string $ip, int $port, string $sessionToken): array
    {
        $credentials = base64_decode($sessionToken);
        [$username, $password] = explode(':', $credentials, 2);
        
        $url = $this->buildUrl($ip, $port, '/cgi-bin/global.cgi?action=getCurrentTime');
        $response = $this->makeDigestRequest($url, $username, $password);
        
        return $this->parseResponse($response, 'time');
    }

    public function getCameraStatus(string $ip, int $port, string $sessionToken): array
    {
        $credentials = base64_decode($sessionToken);
        [$username, $password] = explode(':', $credentials, 2);
        
        // First, get the maximum number of channels
        $maxChannelsUrl = $this->buildUrl($ip, $port, '/cgi-bin/magicBox.cgi?action=getProductDefinition&name=MaxRemoteInputChannels');
        $maxChannelsResponse = $this->makeDigestRequest($maxChannelsUrl, $username, $password);
        
        // Get channel titles
        $url1 = $this->buildUrl($ip, $port, '/cgi-bin/configManager.cgi?action=getConfig&name=ChannelTitle');
        $channelResponse = $this->makeDigestRequest($url1, $username, $password);
        
        // Get video loss events
        $url2 = $this->buildUrl($ip, $port, '/cgi-bin/eventManager.cgi?action=getEventIndexes&code=VideoLoss');
        $videoLossResponse = $this->makeDigestRequest($url2, $username, $password);
        
        return $this->parseResponse([
            'max_channels' => $maxChannelsResponse,
            'channels' => $channelResponse,
            'video_loss' => $videoLossResponse
        ], 'cameras');
    }

    /**
     * Get maximum number of channels supported by the DVR
     */
    public function getMaxChannels(string $ip, int $port, string $sessionToken): array
    {
        $credentials = base64_decode($sessionToken);
        [$username, $password] = explode(':', $credentials, 2);
        
        $url = $this->buildUrl($ip, $port, '/cgi-bin/magicBox.cgi?action=getProductDefinition&name=MaxRemoteInputChannels');
        $response = $this->makeDigestRequest($url, $username, $password);
        
        return $this->parseResponse($response, 'max_channels');
    }

    public function getStorageStatus(string $ip, int $port, string $sessionToken): array
    {
        $credentials = base64_decode($sessionToken);
        [$username, $password] = explode(':', $credentials, 2);
        
        $url = $this->buildUrl($ip, $port, '/cgi-bin/storageDevice.cgi?action=getDeviceAllInfo');
        $response = $this->makeDigestRequest($url, $username, $password);
        
        return $this->parseResponse($response, 'storage');
    }

    public function getRecordingStatus(string $ip, int $port, string $sessionToken): array
    {
        $credentials = base64_decode($sessionToken);
        [$username, $password] = explode(':', $credentials, 2);
        
        // Get recording info by finding media files
        $url1 = $this->buildUrl($ip, $port, '/cgi-bin/mediaFileFind.cgi?action=factory.create');
        $factoryResponse = $this->makeDigestRequest($url1, $username, $password);
        
        return $this->parseResponse($factoryResponse, 'recording');
    }

    public function logout(string $ip, int $port, string $sessionToken): array
    {
        // CP Plus uses HTTP Digest Auth, no explicit logout needed
        return [
            'success' => true,
            'message' => 'Logout successful (HTTP Digest Auth)'
        ];
    }

    public function getAllDetails(string $ip, int $port, string $username, string $password): array
    {
        // Login first
        $loginResult = $this->login($ip, $port, $username, $password);
        
        if (!$loginResult['success']) {
            return [
                'success' => false,
                'message' => 'Login failed',
                'data' => null
            ];
        }

        $sessionToken = $loginResult['session_token'];
        $allData = [];

        try {
            // Get all information
            $allData['dvr_time'] = $this->getDvrTime($ip, $port, $sessionToken);
            $allData['cameras'] = $this->getCameraStatus($ip, $port, $sessionToken);
            $allData['storage'] = $this->getStorageStatus($ip, $port, $sessionToken);
            $allData['recording'] = $this->getRecordingStatus($ip, $port, $sessionToken);

            // Logout
            $this->logout($ip, $port, $sessionToken);

            return [
                'success' => true,
                'message' => 'Data retrieved successfully',
                'data' => $allData
            ];

        } catch (\Exception $e) {
            // Ensure logout even if error occurs
            $this->logout($ip, $port, $sessionToken);
            
            Log::error("CP Plus DVR API error: {$e->getMessage()}");
            
            return [
                'success' => false,
                'message' => 'Error retrieving data: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    protected function parseResponse(array $response, string $type): array
    {
        if (!isset($response['success']) || !$response['success']) {
            return [
                'success' => false,
                'data' => null,
                'message' => $response['error'] ?? 'Request failed'
            ];
        }

        $data = $response['data'] ?? [];

        switch ($type) {
            case 'time':
                return [
                    'success' => true,
                    'dvr_time' => $this->parseCpPlusTime($data),
                    'message' => 'DVR time retrieved'
                ];

            case 'cameras':
                return [
                    'success' => true,
                    'cameras' => $this->parseCpPlusCameras($data),
                    'message' => 'Camera status retrieved'
                ];

            case 'max_channels':
                return [
                    'success' => true,
                    'max_channels' => $this->parseCpPlusMaxChannels($data),
                    'message' => 'Max channels retrieved'
                ];

            case 'storage':
                return [
                    'success' => true,
                    'storage' => $this->parseCpPlusStorage($data),
                    'message' => 'Storage status retrieved'
                ];

            case 'recording':
                return [
                    'success' => true,
                    'recording' => $this->parseCpPlusRecording($data),
                    'message' => 'Recording status retrieved'
                ];

            default:
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Unknown response type'
                ];
        }
    }

    /**
     * Add HTTP Digest authentication method
     */
    protected function makeDigestRequest(string $url, string $username, string $password): array
    {
        return parent::makeDigestRequest($url, $username, $password);
    }

    private function parseCpPlusTime($data): array
    {
        // CP Plus returns: result=2025-08-26 22:30:09
        $dvrTime = $data['result'] ?? date('Y-m-d H:i:s');
        
        return [
            'current_time' => $dvrTime,
            'system_time' => date('Y-m-d H:i:s'),
            'timezone' => 'Asia/Kolkata',
            'login_time' => date('Y-m-d H:i:s')
        ];
    }

    private function parseCpPlusCameras($data): array
    {
        $cameras = [];
        $maxChannelsData = $data['max_channels']['data'] ?? [];
        $channelData = $data['channels']['data'] ?? [];
        $videoLossData = $data['video_loss']['data'] ?? [];
        
        // Get maximum channels from API response
        $maxChannels = $this->parseCpPlusMaxChannels($maxChannelsData);
        
        // Initialize all channels based on max channels
        for ($i = 0; $i < $maxChannels; $i++) {
            $cameras[$i] = [
                'channel' => $i + 1,
                'name' => "Channel " . ($i + 1),
                'status' => 'working'
            ];
        }
        
        // Update with actual channel titles: table.ChannelTitle[0].Name=Camera 1
        foreach ($channelData as $key => $value) {
            if (preg_match('/table\.ChannelTitle\[(\d+)\]\.Name/', $key, $matches)) {
                $channelIndex = (int)$matches[1];
                if (isset($cameras[$channelIndex])) {
                    $cameras[$channelIndex]['name'] = $value;
                }
            }
        }
        
        // Check for video loss: channels=0,1,2
        if (isset($videoLossData['channels'])) {
            $lostChannels = explode(',', $videoLossData['channels']);
            foreach ($lostChannels as $lostChannel) {
                $channelIndex = (int)trim($lostChannel);
                if (isset($cameras[$channelIndex])) {
                    $cameras[$channelIndex]['status'] = 'not_working';
                }
            }
        }

        $cameraList = array_values($cameras);
        
        return [
            'total_cameras' => count($cameraList),
            'working_cameras' => count(array_filter($cameraList, fn($c) => $c['status'] === 'working')),
            'not_working_cameras' => count(array_filter($cameraList, fn($c) => $c['status'] === 'not_working')),
            'cameras' => $cameraList,
            'max_channels' => $maxChannels
        ];
    }

    private function parseCpPlusMaxChannels($data): int
    {
        // Parse response: table.MaxRemoteInputChannels=3
        foreach ($data as $key => $value) {
            if (strpos($key, 'MaxRemoteInputChannels') !== false) {
                return (int)$value;
            }
        }
        
        // Fallback to parsing from string response if structured differently
        $responseString = implode("\n", $data);
        if (preg_match('/table\.MaxRemoteInputChannels=(\d+)/', $responseString, $matches)) {
            return (int)$matches[1];
        }
        
        // Default fallback
        return 4;
    }

    private function parseCpPlusStorage($data): array
    {
        $storageData = $data['data'] ?? [];
        
        // Parse: list.info[0].State=OK, list.info[0].Detail[0].TotalBytes=1000000000000
        $storageStatus = $storageData['list.info[0].State'] ?? 'Unknown';
        $totalBytes = (int)($storageData['list.info[0].Detail[0].TotalBytes'] ?? 0);
        $usedBytes = (int)($storageData['list.info[0].Detail[0].UsedBytes'] ?? 0);
        
        $totalCapacityGB = round($totalBytes / (1024 * 1024 * 1024), 2);
        $freeCapacityGB = round(($totalBytes - $usedBytes) / (1024 * 1024 * 1024), 2);
        $usedCapacityGB = round($usedBytes / (1024 * 1024 * 1024), 2);
        
        return [
            'storage_type' => 'HDD',
            'storage_status' => $storageStatus,
            'total_capacity_gb' => $totalCapacityGB,
            'used_capacity_gb' => $usedCapacityGB,
            'free_capacity_gb' => $freeCapacityGB,
            'usage_percentage' => $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0
        ];
    }

    private function parseCpPlusRecording($data): array
    {
        $factoryData = $data['data'] ?? [];
        $finderId = $factoryData['result'] ?? null;
        
        // For now, return basic recording info
        // In a full implementation, we would use the finder ID to get actual recording times
        return [
            'recording_from' => 'N/A',
            'recording_to' => date('Y-m-d H:i:s'),
            'recording_active' => $finderId !== null,
            'finder_id' => $finderId
        ];
    }
}