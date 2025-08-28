<?php

namespace App\Http\Controllers;

use App\Models\Dvr;
use App\Models\DvrMonitoringHistory;
use App\Services\EnhancedMonitoringService;
use App\Services\CameraStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EnhancedMonitoringController extends Controller
{
    protected $enhancedMonitoringService;
    protected $cameraStatusService;

    public function __construct(EnhancedMonitoringService $enhancedMonitoringService, CameraStatusService $cameraStatusService = null)
    {
        $this->enhancedMonitoringService = $enhancedMonitoringService;
        $this->cameraStatusService = $cameraStatusService ?? new CameraStatusService();
    }

    /**
     * Monitor a single DVR with enhanced checks
     */
    public function monitorSingleDvr(Request $request, $dvrId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'check_type' => 'string|in:ping,api_details,full_check'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $dvr = Dvr::findOrFail($dvrId);
            $checkType = $request->get('check_type', 'full_check');
            
            $result = $this->enhancedMonitoringService->monitorDvr($dvr, $checkType);

            return response()->json([
                'success' => true,
                'message' => 'DVR monitoring completed',
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ],
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error monitoring DVR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monitor multiple DVRs
     */
    public function monitorMultipleDvrs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'dvr_ids' => 'required|array',
            'dvr_ids.*' => 'integer|exists:dvrs,id',
            'check_type' => 'string|in:ping,api_details,full_check'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $checkType = $request->get('check_type', 'full_check');
            $results = $this->enhancedMonitoringService->monitorMultipleDvrs($request->dvr_ids, $checkType);

            return response()->json([
                'success' => true,
                'message' => 'Multiple DVR monitoring completed',
                'total_dvrs' => count($request->dvr_ids),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error monitoring DVRs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monitoring history for a DVR
     */
    public function getDvrHistory(Request $request, $dvrId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'integer|min:1|max:168', // Max 1 week
            'check_type' => 'string|in:ping,api_details,full_check',
            'limit' => 'integer|min:1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $dvr = Dvr::findOrFail($dvrId);
            $hours = $request->get('hours', 24);
            $checkType = $request->get('check_type');
            $limit = $request->get('limit', 100);

            $query = DvrMonitoringHistory::where('dvr_id', $dvrId)
                ->where('checked_at', '>=', now()->subHours($hours))
                ->orderBy('checked_at', 'desc')
                ->limit($limit);

            if ($checkType) {
                $query->where('check_type', $checkType);
            }

            $history = $query->get();

            return response()->json([
                'success' => true,
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ],
                'history' => $history,
                'total_records' => $history->count(),
                'time_range_hours' => $hours
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monitoring statistics
     */
    public function getMonitoringStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'integer|min:1|max:168' // Max 1 week
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $hours = $request->get('hours', 24);
            $stats = $this->enhancedMonitoringService->getMonitoringStats($hours);

            return response()->json([
                'success' => true,
                'message' => 'Monitoring statistics retrieved',
                'time_range_hours' => $hours,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DVRs with time sync issues
     */
    public function getTimeSyncIssues(Request $request): JsonResponse
    {
        try {
            $threshold = $request->get('threshold_minutes', 5);
            
            $dvrsWithTimeIssues = Dvr::where('is_active', true)
                ->whereNotNull('device_time_offset_minutes')
                ->where(function($query) use ($threshold) {
                    $query->where('device_time_offset_minutes', '>', $threshold)
                          ->orWhere('device_time_offset_minutes', '<', -$threshold);
                })
                ->get(['id', 'dvr_name', 'ip', 'port', 'dvr_device_time', 'device_time_offset_minutes', 'last_api_check_at']);

            return response()->json([
                'success' => true,
                'message' => 'DVRs with time sync issues retrieved',
                'threshold_minutes' => $threshold,
                'total_issues' => $dvrsWithTimeIssues->count(),
                'dvrs' => $dvrsWithTimeIssues
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving time sync issues: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test DVR by IP address - Universal module
     */
    public function testDvrByIp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ip' => 'required|ip'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IP address',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $ip = $request->get('ip');
            
            // Find DVR by IP in database
            $dvr = Dvr::where('ip', $ip)->where('is_active', true)->first();
            
            if (!$dvr) {
                return response()->json([
                    'success' => false,
                    'message' => "No active DVR found with IP: {$ip}",
                    'ip' => $ip
                ], 404);
            }

            // Test the DVR using enhanced monitoring
            $result = $this->enhancedMonitoringService->monitorDvr($dvr, 'full_check');
            
            // Test camera status with timeout protection
            $cameraInfo = null;
            try {
                // Set a maximum execution time for camera testing
                set_time_limit(30);
                
                $cameraInfo = $this->cameraStatusService->getCameraInfo(
                    $dvr->ip, 
                    $dvr->port, 
                    $dvr->username ?? 'admin', 
                    $dvr->password ?? 'admin',
                    5 // Test up to 5 channels
                );
            } catch (\Exception $e) {
                \Log::warning("Camera status test failed for DVR {$dvr->id}: " . $e->getMessage());
                $cameraInfo = [
                    'total_cameras' => 0,
                    'working_cameras' => 0,
                    'not_working_cameras' => 0,
                    'cameras' => [],
                    'error' => $e->getMessage()
                ];
            }
            
            // Ensure database transaction is committed and refresh DVR info
            sleep(1); // Small delay to ensure transaction completion
            $dvr->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'DVR test completed',
                'ip' => $ip,
                'dvr_info' => [
                    'id' => $dvr->id,
                    'dvr_name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port,
                    'username' => $dvr->username,
                    'status' => $dvr->status,
                    'last_ping_at' => $dvr->last_ping_at,
                    'dvr_device_time' => $dvr->dvr_device_time,
                    'device_time_offset_minutes' => $dvr->device_time_offset_minutes,
                    'api_login_status' => $dvr->api_login_status
                ],
                'test_result' => [
                    'ping_success' => $result['ping_success'],
                    'api_success' => $result['api_success'],
                    'status' => $result['status'],
                    'response_time' => $result['response_time'],
                    'dvr_time' => $result['dvr_time'],
                    'message' => $result['message']
                ],
                'camera_status' => [
                    'cameras' => [
                        'total_cameras' => $cameraInfo['total_cameras'] ?? 0,
                        'working_cameras' => $cameraInfo['working_cameras'] ?? 0,
                        'not_working_cameras' => ($cameraInfo['total_cameras'] ?? 0) - ($cameraInfo['working_cameras'] ?? 0),
                        'cameras' => array_values($cameraInfo['cameras'] ?? [])
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing DVR: ' . $e->getMessage(),
                'ip' => $request->get('ip')
            ], 500);
        }
    }

    /**
     * Get current status of all DVRs with enhanced information
     */
    public function getCurrentStatus(Request $request): JsonResponse
    {
        try {
            $dvrs = Dvr::where('is_active', true)
                ->select([
                    'id', 'dvr_name', 'ip', 'port', 'status', 'last_ping_at', 'ping_response_time',
                    'api_login_status', 'dvr_device_time', 'last_api_check_at', 'device_time_offset_minutes',
                    'current_camera_count', 'working_camera_count', 'storage_capacity_gb', 
                    'storage_usage_percentage', 'recording_status', 'consecutive_failures'
                ])
                ->orderBy('dvr_name')
                ->get();

            $summary = [
                'total_dvrs' => $dvrs->count(),
                'online_dvrs' => $dvrs->where('status', 'online')->count(),
                'offline_dvrs' => $dvrs->where('status', 'offline')->count(),
                'api_error_dvrs' => $dvrs->where('status', 'api_error')->count(),
                'api_login_success' => $dvrs->where('api_login_status', 'success')->count(),
                'time_sync_issues' => $dvrs->filter(function($dvr) {
                    return $dvr->device_time_offset_minutes !== null && abs($dvr->device_time_offset_minutes) > 5;
                })->count(),
                'recording_active' => $dvrs->where('recording_status', 'active')->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Current DVR status retrieved',
                'summary' => $summary,
                'dvrs' => $dvrs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving current status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test camera status for a specific DVR
     */
    public function testCameraStatus(Request $request, $dvrId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'max_channels' => 'integer|min:1|max:32'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $dvr = Dvr::findOrFail($dvrId);
            $maxChannels = $request->get('max_channels', 5);
            
            // Test camera status using snapshot API
            $cameraInfo = $this->cameraStatusService->getCameraInfo(
                $dvr->ip, 
                $dvr->port, 
                $dvr->username ?? 'admin', 
                $dvr->password ?? 'admin',
                $maxChannels
            );

            return response()->json([
                'success' => true,
                'message' => 'Camera status test completed',
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ],
                'camera_info' => $cameraInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing camera status: ' . $e->getMessage()
            ], 500);
        }
    }
}