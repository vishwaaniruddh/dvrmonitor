<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAllDvrsJob;
use App\Jobs\PingDvrJob;
use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Http\Request;

class DvrMonitoringController extends Controller
{
    /**
     * Get monitoring dashboard statistics
     */
    public function dashboard()
    {
        $stats = [
            'total_dvrs' => Dvr::count(),
            'active_dvrs' => Dvr::where('is_active', true)->count(),
            'online_dvrs' => Dvr::where('status', 'online')->count(),
            'offline_dvrs' => Dvr::where('status', 'offline')->count(),
            'unknown_status' => Dvr::where('status', 'unknown')->count(),
            'api_accessible' => Dvr::where('api_accessible', true)->count(),
            'last_monitoring_run' => Dvr::whereNotNull('last_ping_at')
                ->orderBy('last_ping_at', 'desc')
                ->first()?->last_ping_at,
        ];

        // Get status distribution by DVR type
        $statusByType = Dvr::selectRaw('dvr_name, status, COUNT(*) as count')
            ->groupBy('dvr_name', 'status')
            ->get()
            ->groupBy('dvr_name');

        // Get recent monitoring activity
        $recentActivity = DvrMonitoringLog::with('dvr:id,dvr_name,ip')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'stats' => $stats,
            'status_by_type' => $statusByType,
            'recent_activity' => $recentActivity
        ]);
    }

    /**
     * Start monitoring all DVRs
     */
    public function startMonitoring(Request $request)
    {
        $validated = $request->validate([
            'group_name' => 'nullable|string',
            'active_only' => 'boolean'
        ]);

        $groupName = $validated['group_name'] ?? null;
        $activeOnly = $validated['active_only'] ?? true;

        // Get count of DVRs to be processed
        $query = Dvr::query();
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        if ($groupName) {
            $query->where('group_name', $groupName);
        }
        
        $count = $query->count();

        if ($count === 0) {
            return response()->json([
                'message' => 'No DVRs found matching the criteria',
                'count' => 0
            ], 400);
        }

        // Dispatch the monitoring job
        ProcessAllDvrsJob::dispatch($groupName, $activeOnly);

        return response()->json([
            'message' => 'DVR monitoring started successfully',
            'dvr_count' => $count,
            'group_name' => $groupName,
            'active_only' => $activeOnly
        ]);
    }

    /**
     * Monitor specific DVR
     */
    public function monitorSingle(Dvr $dvr)
    {
        if (!$dvr->is_active) {
            return response()->json([
                'message' => 'DVR is not active'
            ], 400);
        }

        PingDvrJob::dispatch($dvr);

        return response()->json([
            'message' => 'Monitoring job dispatched for DVR',
            'dvr' => $dvr->only(['id', 'dvr_name', 'ip', 'status'])
        ]);
    }

    /**
     * Get DVR monitoring history
     */
    public function monitoringHistory(Dvr $dvr, Request $request)
    {
        $validated = $request->validate([
            'check_type' => 'nullable|in:ping,api_call,status_update',
            'result' => 'nullable|in:success,failure,timeout',
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        $query = $dvr->monitoringLogs();

        if (isset($validated['check_type'])) {
            $query->where('check_type', $validated['check_type']);
        }

        if (isset($validated['result'])) {
            $query->where('result', $validated['result']);
        }

        $limit = $validated['limit'] ?? 50;
        
        $logs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'dvr' => $dvr->only(['id', 'dvr_name', 'ip', 'status']),
            'logs' => $logs
        ]);
    }

    /**
     * Get real-time DVR status
     */
    public function realtimeStatus(Request $request)
    {
        $validated = $request->validate([
            'group_name' => 'nullable|string',
            'status' => 'nullable|in:online,offline,unknown',
            'active_only' => 'boolean'
        ]);

        $query = Dvr::query();

        if ($validated['active_only'] ?? true) {
            $query->where('is_active', true);
        }

        if (isset($validated['group_name'])) {
            $query->where('group_name', $validated['group_name']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $dvrs = $query->select([
            'id', 'dvr_name', 'ip', 'status', 'last_ping_at', 
            'ping_response_time', 'api_accessible', 'consecutive_failures',
            'device_model', 'channel_count', 'location', 'group_name'
        ])->get();

        return response()->json([
            'dvrs' => $dvrs,
            'total_count' => $dvrs->count(),
            'filters_applied' => array_filter($validated)
        ]);
    }
}
