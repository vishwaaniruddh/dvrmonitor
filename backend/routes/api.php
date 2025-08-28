<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DvrController;
use App\Http\Controllers\DvrMonitoringController;
use App\Http\Controllers\DvrDetailsController;
use App\Http\Controllers\TestDvrController;
use App\Http\Controllers\EnhancedMonitoringController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// DVR Routes
Route::apiResource('dvrs', DvrController::class);

// DVR Monitoring Routes
Route::prefix('dvr-monitoring')->group(function () {
    Route::get('/dashboard', [DvrMonitoringController::class, 'dashboard']);
    Route::post('/start', [DvrMonitoringController::class, 'startMonitoring']);
    Route::get('/realtime-status', [DvrMonitoringController::class, 'realtimeStatus']);
    Route::post('/dvrs/{dvr}/monitor', [DvrMonitoringController::class, 'monitorSingle']);
    Route::get('/dvrs/{dvr}/history', [DvrMonitoringController::class, 'monitoringHistory']);
});

// High-Performance Monitoring & WebSocket Routes
Route::prefix('realtime')->group(function () {
    Route::get('/latest', [App\Http\Controllers\WebSocketController::class, 'getLatestData']);
    Route::post('/trigger', [App\Http\Controllers\WebSocketController::class, 'triggerMonitoring']);
    Route::get('/stats', [App\Http\Controllers\WebSocketController::class, 'getRealtimeStats']);
    Route::get('/metrics', [App\Http\Controllers\WebSocketController::class, 'getPerformanceMetrics']);
    Route::get('/dvrs', [App\Http\Controllers\WebSocketController::class, 'getDvrsPaginated']);
    Route::get('/progress', [App\Http\Controllers\WebSocketController::class, 'getWorkerProgress']);
    Route::post('/cleanup', [App\Http\Controllers\WebSocketController::class, 'cleanupWorkers']);
});

// Automated Monitoring Service Routes
Route::prefix('automated-monitoring')->group(function () {
    Route::post('/start', [App\Http\Controllers\AutomatedMonitoringController::class, 'start']);
    Route::post('/stop', [App\Http\Controllers\AutomatedMonitoringController::class, 'stop']);
    Route::get('/status', [App\Http\Controllers\AutomatedMonitoringController::class, 'status']);
    Route::get('/statistics', [App\Http\Controllers\AutomatedMonitoringController::class, 'statistics']);
});

// DVR Details API Routes
Route::get('/dvr-details/supported-types', [DvrDetailsController::class, 'getSupportedTypes']);
Route::post('/dvr-details/check-support', [DvrDetailsController::class, 'checkSupport']);
Route::get('/dvr-details/{dvrId}', [DvrDetailsController::class, 'getDvrDetails']);
Route::get('/dvr-details/{dvrId}/cached', [DvrDetailsController::class, 'getCachedDetails']);
Route::post('/dvr-details/{dvrId}/test-connection', [DvrDetailsController::class, 'testConnection']);
Route::post('/dvr-details/multiple', [DvrDetailsController::class, 'getMultipleDetails']);

// DVR Testing Routes
Route::post('/test-dvr', [TestDvrController::class, 'testDvr']);
Route::post('/ping-dvr', [TestDvrController::class, 'pingTest']);

// Enhanced Monitoring Routes
Route::prefix('enhanced-monitoring')->group(function () {
    Route::post('/dvr/{dvrId}', [EnhancedMonitoringController::class, 'monitorSingleDvr']);
    Route::post('/multiple', [EnhancedMonitoringController::class, 'monitorMultipleDvrs']);
    Route::get('/history/{dvrId}', [EnhancedMonitoringController::class, 'getDvrHistory']);
    Route::get('/stats', [EnhancedMonitoringController::class, 'getMonitoringStats']);
    Route::get('/time-sync-issues', [EnhancedMonitoringController::class, 'getTimeSyncIssues']);
    Route::get('/current-status', [EnhancedMonitoringController::class, 'getCurrentStatus']);
    Route::post('/test-by-ip', [EnhancedMonitoringController::class, 'testDvrByIp']);
    Route::post('/test-camera-status/{dvrId}', [EnhancedMonitoringController::class, 'testCameraStatus']);
});

// Streaming Routes
Route::prefix('streaming')->group(function () {
    Route::get('/dvr/{dvrId}/info', [App\Http\Controllers\StreamingController::class, 'getStreamingInfo']);
    Route::get('/dvr/{dvrId}/channel/{channel}/live', [App\Http\Controllers\StreamingController::class, 'getLiveStreamUrls']);
    Route::get('/dvr/{dvrId}/channel/{channel}/playback', [App\Http\Controllers\StreamingController::class, 'getPlaybackUrls']);
    Route::post('/dvr/{dvrId}/test-stream', [App\Http\Controllers\StreamingController::class, 'testStream']);
    Route::get('/dvr/{ip}/snapshot', [App\Http\Controllers\StreamingController::class, 'getSnapshotByIp']);
    Route::get('/dvr/{ip}/image/{channel?}', [App\Http\Controllers\StreamingController::class, 'getSnapshotImage']);
    Route::get('/dvr/{ip}/stream/{channel?}', [App\Http\Controllers\StreamingController::class, 'getMjpegStream']);
});

// HLS Streaming Routes
Route::prefix('hls')->group(function () {
    Route::post('/start/{ip}/{channel}', [App\Http\Controllers\HlsStreamingController::class, 'startStream']);
    Route::post('/stop/{ip}/{channel}', [App\Http\Controllers\HlsStreamingController::class, 'stopStream']);
    Route::get('/status/{ip}/{channel}', [App\Http\Controllers\HlsStreamingController::class, 'getStreamStatus']);
    Route::get('/streams', [App\Http\Controllers\HlsStreamingController::class, 'listActiveStreams']);
    Route::post('/cleanup', [App\Http\Controllers\HlsStreamingController::class, 'cleanupStreams']);
});

// HLS File Serving Routes
Route::get('/hls/{streamId}/playlist.m3u8', [App\Http\Controllers\HlsStreamingController::class, 'servePlaylist']);
Route::get('/hls/{streamId}/{segment}', [App\Http\Controllers\HlsStreamingController::class, 'serveSegment'])->where('segment', '.*\.ts');
