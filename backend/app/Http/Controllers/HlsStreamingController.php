<?php

namespace App\Http\Controllers;

use App\Models\Dvr;
use App\Services\RtspToHlsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class HlsStreamingController extends Controller
{
    protected $hlsService;

    public function __construct(RtspToHlsService $hlsService)
    {
        $this->hlsService = $hlsService;
    }

    /**
     * Start HLS stream for a DVR channel
     */
    public function startStream(Request $request, $ip, $channel): JsonResponse
    {
        $validator = Validator::make(['ip' => $ip, 'channel' => $channel], [
            'ip' => 'required|ip',
            'channel' => 'required|integer|min:1|max:32'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid parameters',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Find DVR by IP or use defaults
            $dvr = Dvr::where('ip', $ip)->first();
            
            if (!$dvr) {
                $dvr = (object) [
                    'ip' => $ip,
                    'username' => 'admin',
                    'password' => 'css12345'
                ];
            }

            $result = $this->hlsService->startStream(
                $ip,
                (int)$channel,
                $dvr->username ?? 'admin',
                $dvr->password ?? 'css12345',
                554 // RTSP port
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Stream started successfully',
                    'data' => [
                        'stream_id' => $result['stream_id'],
                        'hls_url' => $result['hls_url'],
                        'ip' => $ip,
                        'channel' => $channel
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting stream: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop HLS stream
     */
    public function stopStream(Request $request, $ip, $channel): JsonResponse
    {
        try {
            $streamId = 'stream_' . str_replace('.', '_', $ip) . '_ch' . $channel;
            $result = $this->hlsService->stopStream($streamId);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'stream_id' => $streamId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error stopping stream: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stream status
     */
    public function getStreamStatus(Request $request, $ip, $channel): JsonResponse
    {
        try {
            $streamId = 'stream_' . str_replace('.', '_', $ip) . '_ch' . $channel;
            $status = $this->hlsService->getStreamStatus($streamId);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting stream status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve HLS playlist file
     */
    public function servePlaylist(Request $request, $streamId): Response
    {
        $playlistPath = storage_path("app/public/hls/{$streamId}/playlist.m3u8");
        
        if (!file_exists($playlistPath)) {
            return response('Playlist not found', 404);
        }

        return response(file_get_contents($playlistPath))
            ->header('Content-Type', 'application/vnd.apple.mpegurl')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /**
     * Serve HLS segment file
     */
    public function serveSegment(Request $request, $streamId, $segment): Response
    {
        $segmentPath = storage_path("app/public/hls/{$streamId}/{$segment}");
        
        if (!file_exists($segmentPath)) {
            return response('Segment not found', 404);
        }

        return response(file_get_contents($segmentPath))
            ->header('Content-Type', 'video/mp2t')
            ->header('Cache-Control', 'max-age=10')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /**
     * List all active streams
     */
    public function listActiveStreams(): JsonResponse
    {
        try {
            $hlsPath = storage_path('app/public/hls');
            $activeStreams = [];

            if (is_dir($hlsPath)) {
                $streamDirs = glob($hlsPath . '/stream_*');
                
                foreach ($streamDirs as $streamDir) {
                    $streamId = basename($streamDir);
                    $status = $this->hlsService->getStreamStatus($streamId);
                    
                    if ($status['is_running'] || $status['playlist_exists']) {
                        $activeStreams[] = $status;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'active_streams' => $activeStreams,
                    'total_count' => count($activeStreams)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error listing streams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up old streams
     */
    public function cleanupStreams(Request $request): JsonResponse
    {
        try {
            $maxAge = $request->get('max_age', 3600); // 1 hour default
            $this->hlsService->cleanupOldStreams($maxAge);

            return response()->json([
                'success' => true,
                'message' => 'Stream cleanup completed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during cleanup: ' . $e->getMessage()
            ], 500);
        }
    }
}