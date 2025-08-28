<?php

namespace App\Http\Middleware;

use App\Services\SimplifiedMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AutoMonitoringMiddleware
{
    protected $monitoringService;

    public function __construct(SimplifiedMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Handle an incoming request and auto-start monitoring
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only run on web requests (not API or console)
        if ($request->is('api/*') || app()->runningInConsole()) {
            return $next($request);
        }

        try {
            // Auto-start monitoring if not already running
            $this->autoStartMonitoring();
            
            // Check if it's time to run monitoring cycle
            $this->monitoringService->checkAndRunMonitoring();
            
        } catch (\Exception $e) {
            Log::error('AutoMonitoringMiddleware error: ' . $e->getMessage());
        }

        return $next($request);
    }

    /**
     * Auto-start monitoring service
     */
    private function autoStartMonitoring(): void
    {
        static $hasChecked = false;
        
        // Only check once per request cycle
        if ($hasChecked) {
            return;
        }
        $hasChecked = true;

        $status = $this->monitoringService->getMonitoringStatus();
        
        if (!$status['active']) {
            Log::info('🤖 Auto-starting simplified DVR monitoring...');
            
            $result = $this->monitoringService->startContinuousMonitoring();
            
            if ($result['success']) {
                Log::info('✅ DVR monitoring auto-started successfully');
            } else {
                Log::warning('⚠️ Failed to auto-start DVR monitoring: ' . $result['message']);
            }
        }
    }
}