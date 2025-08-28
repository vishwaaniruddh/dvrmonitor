<?php

namespace App\Http\Controllers;

use App\Services\HighPerformanceMonitoringService;
use App\Services\MultiWorkerMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebSocketController extends Controller
{
    /**
     * Get latest monitoring data for real-time dashboard
     */
    public function getLatestData()
    {
        $cachedData = Cache::get('dvr_monitoring_latest');

        if ($cachedData) {
            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'cached' => true
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No recent monitoring data available',
            'cached' => false
        ]);
    }

    /**
     * Trigger immediate monitoring cycle (ultra-fast multi-worker)
     */
    public function triggerMonitoring(Request $request)
    {
        $activeOnly = $request->boolean('active_only', true);
        $useMultiWorker = $request->boolean('multi_worker', true);

        try {
            if ($useMultiWorker) {
                // Use new ultra-fast multi-worker approach
                $multiWorkerService = new MultiWorkerMonitoringService();
                $result = $multiWorkerService->monitorWithMultipleWorkers($activeOnly);

                if ($result['success']) {
                    return response()->json([
                        'success' => true,
                        'message' => '🚀 ULTRA-FAST Multi-Worker Monitoring Started!',
                        'data' => $result,
                        'performance' => [
                            'workers' => $result['workers_started'],
                            'target_speed' => $result['performance_target'],
                            'estimated_time' => $result['estimated_completion_time'],
                            'dvrs_per_worker' => $result['dvrs_per_worker']
                        ]
                    ]);
                } else {
                    return response()->json($result, 400);
                }
            } else {
                // Fallback to original single-threaded approach
                $command = "php " . base_path('artisan') . " dvr:monitor-fast";
                if (!$activeOnly) {
                    $command .= " --all";
                }

                if (PHP_OS_FAMILY === 'Windows') {
                    $command = "start /B " . $command . " > nul 2>&1";
                } else {
                    $command .= " > /dev/null 2>&1 &";
                }

                exec($command);

                return response()->json([
                    'success' => true,
                    'message' => 'Single-threaded monitoring started (legacy mode)',
                    'estimated_time' => '42-60 minutes for 6,685 DVRs',
                    'active_only' => $activeOnly
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start monitoring: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time statistics (cached for performance)
     */
    public function getRealtimeStats()
    {
        // Cache stats for 30 seconds to improve performance
        $stats = Cache::remember('realtime_stats', 30, function () {
            // Single optimized query to get all counts
            $statusCounts = \App\Models\Dvr::selectRaw('
                COUNT(*) as total_dvrs,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_dvrs,
                COUNT(CASE WHEN status = "online" THEN 1 END) as online_dvrs,
                COUNT(CASE WHEN status = "offline" THEN 1 END) as offline_dvrs,
                COUNT(CASE WHEN status = "unknown" OR status IS NULL THEN 1 END) as unknown_dvrs,
                COUNT(CASE WHEN api_accessible = 1 THEN 1 END) as api_accessible,
                MAX(last_ping_at) as last_update,
                AVG(CASE WHEN ping_response_time IS NOT NULL THEN ping_response_time END) as avg_response_time
            ')->first();

            return [
                'total_dvrs' => (int) $statusCounts->total_dvrs,
                'active_dvrs' => (int) $statusCounts->active_dvrs,
                'online_dvrs' => (int) $statusCounts->online_dvrs,
                'offline_dvrs' => (int) $statusCounts->offline_dvrs,
                'unknown_dvrs' => (int) $statusCounts->unknown_dvrs,
                'api_accessible' => (int) $statusCounts->api_accessible,
                'last_update' => $statusCounts->last_update,
                'avg_response_time' => $statusCounts->avg_response_time ? round($statusCounts->avg_response_time, 2) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'timestamp' => now()->toISOString(),
            'cached' => true
        ]);
    }

    /**
     * Get monitoring performance metrics (cached for performance)
     */
    public function getPerformanceMetrics()
    {
        // Cache metrics for 60 seconds
        $metrics = Cache::remember('performance_metrics', 60, function () {
            $recentLogs = \App\Models\DvrMonitoringLog::where('created_at', '>=', now()->subHour())
                ->selectRaw('
                    COUNT(*) as total_checks,
                    AVG(response_time) as avg_response_time,
                    MIN(response_time) as min_response_time,
                    MAX(response_time) as max_response_time,
                    COUNT(CASE WHEN result = "success" THEN 1 END) as success_count,
                    COUNT(CASE WHEN result = "failure" THEN 1 END) as failure_count,
                    COUNT(CASE WHEN result = "timeout" THEN 1 END) as timeout_count
                ')
                ->first();

            return [
                'total_checks' => (int) $recentLogs->total_checks,
                'avg_response_time' => $recentLogs->avg_response_time ? round($recentLogs->avg_response_time, 2) : 0,
                'min_response_time' => $recentLogs->min_response_time ? round($recentLogs->min_response_time, 2) : 0,
                'max_response_time' => $recentLogs->max_response_time ? round($recentLogs->max_response_time, 2) : 0,
                'success_count' => (int) $recentLogs->success_count,
                'failure_count' => (int) $recentLogs->failure_count,
                'timeout_count' => (int) $recentLogs->timeout_count,
                'online_count' => (int) $recentLogs->success_count, // Assuming success = online
            ];
        });

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'period' => 'last_hour',
            'timestamp' => now()->toISOString(),
            'cached' => true
        ]);
    }

    /**
     * Get paginated DVR data for fast table loading
     */
    public function getDvrsPaginated(Request $request)
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:10000',
            'search' => 'string|max:255',
            'status' => 'string|in:online,offline,unknown',
            'sort_by' => 'string|in:dvr_name,ip,port,status,ping_response_time,last_ping_at',
            'sort_direction' => 'string|in:asc,desc'
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 25;
        $search = $validated['search'] ?? '';
        $status = $validated['status'] ?? '';
        $sortBy = $validated['sort_by'] ?? 'dvr_name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        $query = \App\Models\Dvr::select([
            'id',
            'dvr_name',
            'ip',
            'port',
            'status',
            'ping_response_time',
            'api_accessible',
            'is_active',
            'last_ping_at'
        ]);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('dvr_name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($status) {
            if ($status === 'unknown') {
                $query->where(function ($q) {
                    $q->where('status', 'unknown')->orWhereNull('status');
                });
            } else {
                $query->where('status', $status);
            }
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        // Get paginated results
        $dvrs = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $dvrs->items(),
            'pagination' => [
                'current_page' => $dvrs->currentPage(),
                'per_page' => $dvrs->perPage(),
                'total' => $dvrs->total(),
                'last_page' => $dvrs->lastPage(),
                'from' => $dvrs->firstItem(),
                'to' => $dvrs->lastItem()
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection
            ]
        ]);
    }

    /**
     * Get multi-worker monitoring progress
     */
    public function getWorkerProgress()
    {
        try {
            $multiWorkerService = new MultiWorkerMonitoringService();
            $progress = $multiWorkerService->getWorkerProgress();

            return response()->json([
                'success' => true,
                'progress' => $progress,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get worker progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up worker files
     */
    public function cleanupWorkers()
    {
        try {
            $multiWorkerService = new MultiWorkerMonitoringService();
            $multiWorkerService->cleanupWorkers();

            return response()->json([
                'success' => true,
                'message' => 'Worker files cleaned up successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup workers: ' . $e->getMessage()
            ], 500);
        }
    }
}
