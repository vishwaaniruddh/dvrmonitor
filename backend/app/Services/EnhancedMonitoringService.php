<?php

namespace App\Services;

use App\Models\Dvr;
use App\Models\DvrMonitoringHistory;
use App\Services\DvrApiFactory;
use App\Services\DvrDateTimeParser;
use App\Services\CameraStatusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EnhancedMonitoringService
{
    protected $timeout = 5;
    protected $connectTimeout = 3;
    protected $cameraStatusService;
    
    // DEBUG: This is the UPDATED version of the service

    public function __construct(CameraStatusService $cameraStatusService = null)
    {
        $this->cameraStatusService = $cameraStatusService ?? new CameraStatusService();
        // Removed echo to prevent JSON response corruption
        
        // Create temp directory if it doesn't exist
        $tempDir = 'C:/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        file_put_contents($tempDir . '/debug_constructor.txt', "EnhancedMonitoringService constructed at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    }
    
    public function getVersion(): string
    {
        return "2.1 - WITH CAMERA STATUS CHECKING";
    }

    /**
     * Perform comprehensive monitoring for a single DVR
     * VERSION 2.0 - UPDATED
     */
    public function monitorDvr(Dvr $dvr, string $checkType = 'full_check'): array
    {
        // Create temp directory if it doesn't exist
        $tempDir = 'C:/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        file_put_contents($tempDir . '/debug_monitor.txt', "monitorDvr called for DVR {$dvr->id} at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        
        \Log::info("Enhanced monitoring started for DVR {$dvr->id}", [
            'dvr_name' => $dvr->dvr_name,
            'ip' => $dvr->ip,
            'check_type' => $checkType
        ]);
        
        $startTime = microtime(true);
        $monitoringData = [
            'dvr_id' => $dvr->id,
            'check_type' => $checkType,
            'checked_at' => now('Asia/Kolkata'),
            'ping_success' => false,
            'api_login_success' => false,
            'status' => 'offline',
            'error_message' => null,
            'raw_response' => []
        ];

        try {
            // Step 1: Ping test
            $pingResult = $this->performPingTest($dvr);
            $monitoringData['ping_success'] = $pingResult['success'];
            $monitoringData['ping_response_time'] = $pingResult['response_time'];
            $monitoringData['raw_response']['ping'] = $pingResult;

            if (!$pingResult['success']) {
                $monitoringData['status'] = 'offline';
                $monitoringData['error_message'] = $pingResult['error'] ?? 'Ping failed';
                return $this->saveMonitoringResult($dvr, $monitoringData);
            }

            // Step 2: API Login and Device Time (ONLY if ping successful)
            
            if ($pingResult['success'] && ($checkType === 'full_check' || $checkType === 'api_details')) {
                file_put_contents(__DIR__ . '/../../debug_api_check.txt', "Starting API check for DVR {$dvr->id}\n", FILE_APPEND);
                \Log::info("DVR {$dvr->id} - Starting API check");
                $apiResult = $this->performApiCheck($dvr);
                
                $monitoringData['api_login_success'] = $apiResult['login_success'];
                $monitoringData['raw_response']['api'] = $apiResult;

                \Log::info("DVR {$dvr->id} - API result", [
                    'login_success' => $apiResult['login_success'],
                    'has_dvr_time' => isset($apiResult['dvr_time']),
                    'dvr_time_type' => isset($apiResult['dvr_time']) ? gettype($apiResult['dvr_time']) : 'not_set'
                ]);

                if ($apiResult['login_success']) {
                    $monitoringData['status'] = 'online';

                    // Store DVR device time
                    if (isset($apiResult['dvr_time'])) {
                        // Ensure it's always a string, not a Carbon object
                        if ($apiResult['dvr_time'] instanceof Carbon) {
                            $monitoringData['dvr_device_time'] = $apiResult['dvr_time']->format('Y-m-d H:i:s');
                        } else {
                            $monitoringData['dvr_device_time'] = (string)$apiResult['dvr_time'];
                        }
                        
                        \Log::info("DVR {$dvr->id} - Device time set", [
                            'dvr_device_time' => $monitoringData['dvr_device_time'],
                            'type' => gettype($monitoringData['dvr_device_time'])
                        ]);
                    } else {
                        \Log::warning("DVR {$dvr->id} - No DVR time in API result");
                    }
              

                    // Store detailed DVR information
                    if (isset($apiResult['details'])) {
                        $monitoringData['dvr_details'] = $apiResult['details'];
                    }
                } else {
                    $monitoringData['status'] = 'api_error';
                    $monitoringData['error_message'] = $apiResult['error'] ?? 'API login failed';
                    \Log::warning("DVR {$dvr->id} - API login failed", [
                        'error' => $apiResult['error'] ?? 'Unknown error'
                    ]);
                }
            } else if ($pingResult['success']) {
                // For ping-only checks or when API check is not requested
                $monitoringData['status'] = 'online';
            }
        } catch (\Exception $e) {
            Log::error("Enhanced monitoring failed for DVR {$dvr->id}: {$e->getMessage()}");
            $monitoringData['status'] = 'timeout';
            $monitoringData['error_message'] = $e->getMessage();
        }

        return $this->saveMonitoringResult($dvr, $monitoringData);
    }

    /**
     * Perform ping test
     */
    protected function performPingTest(Dvr $dvr): array
    {
        $startTime = microtime(true);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://{$dvr->ip}:{$dvr->port}/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2); // ms

            $success = ($response !== false && empty($error));

            return [
                'success' => $success,
                'response_time' => $responseTime,
                'http_code' => $httpCode,
                'error' => $error ?: null
            ];
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            return [
                'success' => false,
                'response_time' => $responseTime,
                'http_code' => 0,
                'error' => $e->getMessage()
            ];
        }
    }



    /**
     * Perform API check to get device time and details
     */
    protected function performApiCheck(Dvr $dvr): array
    {
        try {
            // Check if DVR type is supported
            if (!DvrApiFactory::isSupported($dvr->dvr_name)) {
                return [
                    'login_success' => false,
                    'error' => "DVR type '{$dvr->dvr_name}' not supported"
                ];
            }

            $apiService = DvrApiFactory::create($dvr->dvr_name);

            // Test login
            $loginResult = $apiService->login(
                $dvr->ip,
                $dvr->port,
                $dvr->username ?? 'admin',
                $dvr->password ?? 'admin'
            );

            if (!$loginResult['success']) {
                return [
                    'login_success' => false,
                    'error' => $loginResult['message']
                ];
            }

            $sessionToken = $loginResult['session_token'];
            $apiData = [
                'login_success' => true,
                'session_token' => $sessionToken
            ];

            // Get DVR time
            $timeResult = $apiService->getDvrTime($dvr->ip, $dvr->port, $sessionToken);
            if ($timeResult['success']) {
                $dvrTimeStr = $timeResult['dvr_time']['current_time'];
                
                // Use the dedicated DVR DateTime Parser to handle all formats
                $parseResult = DvrDateTimeParser::parseWithContext($dvrTimeStr, "DVR_{$dvr->id}");
                
                if ($parseResult['success']) {
                    $apiData['dvr_time'] = $parseResult['parsed_time'];
                    
                    // Debug: Force write to file to see what's happening
                    file_put_contents(__DIR__ . '/../../debug_api_time.txt', 
                        "DVR {$dvr->id} - Original: {$dvrTimeStr} -> Parsed: {$parseResult['parsed_time']} -> Format: {$parseResult['format_used']}\n", 
                        FILE_APPEND
                    );
                    
                    \Log::info("DVR {$dvr->id} - Time parsed successfully", [
                        'original' => $dvrTimeStr,
                        'parsed' => $parseResult['parsed_time'],
                        'format_detected' => $parseResult['format_used']
                    ]);
                } else {
                    \Log::warning("Could not parse DVR time for DVR {$dvr->id}: {$dvrTimeStr}");
                }
            }

            // Get detailed information
            $details = [];

            // Camera status using snapshot API
            try {
                $cameraInfo = $this->cameraStatusService->getCameraInfo(
                    $dvr->ip, 
                    $dvr->port, 
                    $dvr->username ?? 'admin', 
                    $dvr->password ?? 'admin'
                );
                
                $details['cameras'] = [
                    'total_cameras' => $cameraInfo['total_cameras'],
                    'working_cameras' => $cameraInfo['working_cameras'],
                    'camera_details' => $cameraInfo['cameras'],
                    'summary' => $cameraInfo['summary']
                ];
                
                \Log::info("DVR {$dvr->id} - Camera status checked", [
                    'total_cameras' => $cameraInfo['total_cameras'],
                    'working_cameras' => $cameraInfo['working_cameras']
                ]);
            } catch (\Exception $e) {
                \Log::warning("DVR {$dvr->id} - Camera status check failed: " . $e->getMessage());
                $details['cameras'] = [
                    'total_cameras' => 0,
                    'working_cameras' => 0,
                    'error' => $e->getMessage()
                ];
            }

            // Storage status
            $storageResult = $apiService->getStorageStatus($dvr->ip, $dvr->port, $sessionToken);
            if ($storageResult['success']) {
                $details['storage'] = $storageResult['storage'];
            }

            // Recording status
            $recordingResult = $apiService->getRecordingStatus($dvr->ip, $dvr->port, $sessionToken);
            if ($recordingResult['success']) {
                $details['recording'] = $recordingResult['recording'];
            }

            $apiData['details'] = $details;

            // Logout
            $apiService->logout($dvr->ip, $dvr->port, $sessionToken);

            return $apiData;
        } catch (\Exception $e) {
            return [
                'login_success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Save monitoring result to database
     */
    protected function saveMonitoringResult(Dvr $dvr, array $monitoringData): array
    {
        \Log::info("DVR {$dvr->id} - saveMonitoringResult called", [
            'api_login_success' => $monitoringData['api_login_success'],
            'status' => $monitoringData['status'],
            'has_dvr_device_time' => isset($monitoringData['dvr_device_time'])
        ]);
        
        DB::transaction(function () use ($dvr, $monitoringData) {
            // Save to history table
            DvrMonitoringHistory::create($monitoringData);

            // Update DVR record with latest information
            $updateData = [
                'status' => $monitoringData['status'],
                'last_ping_at' => $monitoringData['checked_at'],
                'ping_response_time' => $monitoringData['ping_response_time'] ?? null,
                'api_accessible' => $monitoringData['api_login_success'],
                'consecutive_failures' => $monitoringData['status'] === 'online' ? 0 : $dvr->consecutive_failures + 1
            ];

            // Update API-related fields if available
            if ($monitoringData['api_login_success']) {
                $updateData['api_login_status'] = 'success';
                $updateData['last_api_check_at'] = $monitoringData['checked_at'];

                if (isset($monitoringData['dvr_device_time'])) {
                    $updateData['dvr_device_time'] = $monitoringData['dvr_device_time'];

                    // Calculate time offset
                    $systemTime = $monitoringData['checked_at'];
                    $deviceTime = Carbon::parse($monitoringData['dvr_device_time']);
                    $updateData['device_time_offset_minutes'] = $deviceTime->diffInMinutes($systemTime, false);
                }

                // Update camera and storage information
                if (isset($monitoringData['dvr_details'])) {
                    $details = $monitoringData['dvr_details'];

                    if (isset($details['cameras'])) {
                        $updateData['current_camera_count'] = $details['cameras']['total_cameras'] ?? null;
                        $updateData['working_camera_count'] = $details['cameras']['working_cameras'] ?? null;
                    }

                    if (isset($details['storage'])) {
                        $updateData['storage_capacity_gb'] = $details['storage']['total_capacity_gb'] ?? null;
                        $updateData['storage_usage_percentage'] = $details['storage']['usage_percentage'] ?? null;
                    }

                    if (isset($details['recording'])) {
                        $updateData['recording_status'] = $details['recording']['recording_active'] ? 'active' : 'inactive';
                    }
                }
            } else {
                $updateData['api_login_status'] = 'failed';
            }

            $dvr->update($updateData);
        });

        \Log::info("DVR {$dvr->id} - Returning result", [
            'dvr_device_time' => $monitoringData['dvr_device_time'] ?? 'null',
            'dvr_device_time_type' => gettype($monitoringData['dvr_device_time'] ?? null)
        ]);

        // Ensure dvr_time is always a string
        $dvrTimeString = null;
        if (isset($monitoringData['dvr_device_time'])) {
            if ($monitoringData['dvr_device_time'] instanceof Carbon) {
                $dvrTimeString = $monitoringData['dvr_device_time']->format('Y-m-d H:i:s');
            } else {
                $dvrTimeString = (string)$monitoringData['dvr_device_time'];
            }
        }

        return [
            'success' => true,
            'dvr_id' => $dvr->id,
            'status' => $monitoringData['status'],
            'ping_success' => $monitoringData['ping_success'],
            'api_success' => $monitoringData['api_login_success'],
            'response_time' => $monitoringData['ping_response_time'] ?? null,
            'dvr_time' => $dvrTimeString, // This should be a string in Y-m-d H:i:s format
            'message' => $monitoringData['error_message'] ?? 'Monitoring completed successfully'
        ];
    }

    /**
     * Monitor multiple DVRs
     */
    public function monitorMultipleDvrs(array $dvrIds, string $checkType = 'full_check'): array
    {
        $results = [];
        $dvrs = Dvr::whereIn('id', $dvrIds)->where('is_active', true)->get();

        foreach ($dvrs as $dvr) {
            $results[$dvr->id] = $this->monitorDvr($dvr, $checkType);
        }

        return $results;
    }

    /**
     * Get monitoring statistics
     */
    public function getMonitoringStats(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        return [
            'total_checks' => DvrMonitoringHistory::where('checked_at', '>=', $since)->count(),
            'successful_pings' => DvrMonitoringHistory::where('checked_at', '>=', $since)->where('ping_success', true)->count(),
            'successful_api_logins' => DvrMonitoringHistory::where('checked_at', '>=', $since)->where('api_login_success', true)->count(),
            'online_dvrs' => DvrMonitoringHistory::where('checked_at', '>=', $since)->where('status', 'online')->distinct('dvr_id')->count(),
            'offline_dvrs' => DvrMonitoringHistory::where('checked_at', '>=', $since)->where('status', 'offline')->distinct('dvr_id')->count(),
            'api_error_dvrs' => DvrMonitoringHistory::where('checked_at', '>=', $since)->where('status', 'api_error')->distinct('dvr_id')->count(),
        ];
    }
}
