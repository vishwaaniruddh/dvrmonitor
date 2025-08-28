<?php

namespace App\Http\Controllers;

use App\Services\SimplifiedMonitoringService;
use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AutomatedMonitoringController extends Controller
{
    protected $monitoringService;

    public function __construct(SimplifiedMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Start automated monitoring service
     */
    public function start(Request $request): JsonResponse
    {
        $result = $this->monitoringService->startContinuousMonitoring();
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Stop automated monitoring service
     */
    public function stop(Request $request): JsonResponse
    {
        $result = $this->monitoringService->stopMonitoring();
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get monitoring service status
     */
    public function status(Request $request): JsonResponse
    {
        $status = $this->monitoringService->getMonitoringStatus();
        
        // Add current DVR statistics
        $dvrStats = Dvr::selectRaw('
            COUNT(*) as total_dvrs,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_dvrs,
            COUNT(CASE WHEN status = "online" THEN 1 END) as online_dvrs,
            COUNT(CASE WHEN status = "offline" THEN 1 END) as offline_dvrs,
            COUNT(CASE WHEN status = "unknown" OR status IS NULL THEN 1 END) as unknown_dvrs,
            AVG(CASE WHEN ping_response_time IS NOT NULL THEN ping_response_time END) as avg_response_time,
            MAX(last_ping_at) as last_update
        ')->first();

        return response()->json([
            'success' => true,
            'service_status' => $status,
            'dvr_statistics' => [
                'total_dvrs' => (int) $dvrStats->total_dvrs,
                'active_dvrs' => (int) $dvrStats->active_dvrs,
                'online_dvrs' => (int) $dvrStats->online_dvrs,
                'offline_dvrs' => (int) $dvrStats->offline_dvrs,
                'unknown_dvrs' => (int) $dvrStats->unknown_dvrs,
                'avg_response_time' => $dvrStats->avg_response_time ? round($dvrStats->avg_response_time, 2) : null,
                'last_update' => $dvrStats->last_update,
            ],
            'timestamp' => now('Asia/Kolkata')->toISOString()
        ]);
    }

    /**
     * Get detailed monitoring statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $hours = $request->get('hours', 1); // Default to last 1 hour
        $hours = min(24, max(1, $hours)); // Limit between 1-24 hours

        // Recent monitoring activity
        $recentLogs = DvrMonitoringLog::where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('
                COUNT(*) as total_checks,
                COUNT(CASE WHEN result = "success" THEN 1 END) as successful_checks,
                COUNT(CASE WHEN result = "failure" THEN 1 END) as failed_checks,
                AVG(response_time) as avg_response_time,
                MIN(response_time) as min_response_time,
                MAX(response_time) as max_response_time
            ')
            ->first();

        $successRate = $recentLogs->total_checks > 0 ? 
            round(($recentLogs->successful_checks / $recentLogs->total_checks) * 100, 2) : 0;

        // Top 10 fastest responding DVRs
        $fastestDvrs = Dvr::where('status', 'online')
            ->whereNotNull('ping_response_time')
            ->orderBy('ping_response_time', 'asc')
            ->limit(10)
            ->get(['dvr_name', 'ip', 'ping_response_time', 'last_ping_at']);

        // Recently failed DVRs
        $failedDvrs = Dvr::where('status', 'offline')
            ->where('updated_at', '>=', now()->subHours($hours))
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['dvr_name', 'ip', 'consecutive_failures', 'updated_at', 'last_ping_at']);

        // Monitoring activity over time (hourly breakdown)
        $hourlyActivity = DvrMonitoringLog::where('created_at', '>=', now()->subHours($hours))
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                COUNT(*) as total_checks,
                COUNT(CASE WHEN result = "success" THEN 1 END) as successful_checks,
                AVG(response_time) as avg_response_time
            ')
            ->groupBy('hour')
            ->orderBy('hour', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'period' => [
                'hours' => $hours,
                'from' => now()->subHours($hours)->toISOString(),
                'to' => now()->toISOString()
            ],
            'monitoring_activity' => [
                'total_checks' => (int) $recentLogs->total_checks,
                'successful_checks' => (int) $recentLogs->successful_checks,
                'failed_checks' => (int) $recentLogs->failed_checks,
                'success_rate' => $successRate,
                'avg_response_time' => $recentLogs->avg_response_time ? round($recentLogs->avg_response_time, 2) : null,
                'min_response_time' => $recentLogs->min_response_time ? round($recentLogs->min_response_time, 2) : null,
                'max_response_time' => $recentLogs->max_response_time ? round($recentLogs->max_response_time, 2) : null,
            ],
            'fastest_dvrs' => $fastestDvrs->map(function ($dvr) {
                return [
                    'dvr_name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'response_time' => $dvr->ping_response_time,
                    'last_ping_at' => $dvr->last_ping_at
                ];
            }),
            'failed_dvrs' => $failedDvrs->map(function ($dvr) {
                return [
                    'dvr_name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'consecutive_failures' => $dvr->consecutive_failures,
                    'last_attempt' => $dvr->updated_at,
                    'last_success' => $dvr->last_ping_at
                ];
            }),
            'hourly_activity' => $hourlyActivity->map(function ($activity) {
                return [
                    'hour' => $activity->hour,
                    'total_checks' => (int) $activity->total_checks,
                    'successful_checks' => (int) $activity->successful_checks,
                    'success_rate' => $activity->total_checks > 0 ? 
                        round(($activity->successful_checks / $activity->total_checks) * 100, 2) : 0,
                    'avg_response_time' => $activity->avg_response_time ? round($activity->avg_response_time, 2) : null
                ];
            }),
            'timestamp' => now('Asia/Kolkata')->toISOString()
        ]);
    }
}