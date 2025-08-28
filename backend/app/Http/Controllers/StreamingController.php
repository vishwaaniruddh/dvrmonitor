<?php

namespace App\Http\Controllers;

use App\Models\Dvr;
use App\Services\RtspStreamingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class StreamingController extends Controller
{
    protected $rtspStreamingService;

    public function __construct(RtspStreamingService $rtspStreamingService)
    {
        $this->rtspStreamingService = $rtspStreamingService;
    }

    /**
     * Get streaming information for a DVR
     */
    public function getStreamingInfo(Request $request, $dvrId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channels' => 'array',
            'channels.*' => 'integer|min:1|max:32',
            'test_streams' => 'in:true,false,1,0'
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
            $channels = $request->get('channels', [1, 2, 3]);
            $testStreams = filter_var($request->get('test_streams', false), FILTER_VALIDATE_BOOLEAN);

            $rtspPort = 554; // Default RTSP port

            if ($testStreams) {
                // Test actual stream availability
                $streamingInfo = $this->rtspStreamingService->getStreamingInfo(
                    $dvr->ip,
                    $dvr->port,
                    $rtspPort,
                    $dvr->username ?? 'admin',
                    $dvr->password ?? 'admin',
                    $channels
                );
            } else {
                // Just generate URLs without testing
                $streamingInfo = $this->generateStreamingUrls($dvr, $channels, $rtspPort);
            }

            return response()->json([
                'success' => true,
                'message' => 'Streaming information retrieved',
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ],
                'streaming_info' => $streamingInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving streaming info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live streaming URLs for a specific channel
     */
    public function getLiveStreamUrls(Request $request, $dvrId, $channel): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subtypes' => 'array',
            'subtypes.*' => 'integer|min:0|max:2',
            'include_mjpeg' => 'boolean'
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
            $subtypes = $request->get('subtypes', [0, 1]);
            $includeMjpeg = $request->get('include_mjpeg', true);
            $rtspPort = 554;

            $urls = [];

            // Generate RTSP URLs
            $rtspUrls = $this->rtspStreamingService->generateLiveStreamUrls(
                $dvr->ip,
                $rtspPort,
                $dvr->username ?? 'admin',
                $dvr->password ?? 'admin',
                $channel,
                $subtypes
            );
            $urls['rtsp'] = $rtspUrls;

            // Generate MJPEG URLs if requested
            if ($includeMjpeg) {
                $mjpegUrls = $this->rtspStreamingService->generateMjpegUrls(
                    $dvr->ip,
                    $dvr->port,
                    $channel,
                    $subtypes
                );
                $urls['mjpeg'] = $mjpegUrls;
            }

            return response()->json([
                'success' => true,
                'message' => 'Live stream URLs generated',
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ],
                'channel' => $channel,
                'urls' => $urls
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating stream URLs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get playback streaming URLs
     */
    public function getPlaybackUrls(Request $request, $dvrId, $channel): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time'
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
            $startTime = $request->get('start_time');
            $endTime = $request->get('end_time');
            $rtspPort = 554;

            $playbackUrls = $this->rtspStreamingService->generatePlaybackUrls(
                $dvr->ip,
                $rtspPort,
                $dvr->username ?? 'admin',
                $dvr->password ?? 'admin',
                $channel,
                $startTime,
                $endTime
            );

            return response()->json([
                'success' => true,
                'message' => 'Playback URLs generated',
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ],
                'channel' => $channel,
                'playback' => $playbackUrls
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating playback URLs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test stream availability
     */
    public function testStream(Request $request, $dvrId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required|integer|min:1|max:32',
            'subtype' => 'integer|min:0|max:2',
            'stream_type' => 'string|in:rtsp,mjpeg'
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
            $channel = $request->get('channel');
            $subtype = $request->get('subtype', 0);
            $streamType = $request->get('stream_type', 'rtsp');
            $rtspPort = 554;

            if ($streamType === 'rtsp') {
                $testResult = $this->rtspStreamingService->testRtspStream(
                    $dvr->ip,
                    $rtspPort,
                    $dvr->username ?? 'admin',
                    $dvr->password ?? 'admin',
                    $channel,
                    $subtype
                );
            } else {
                $testResult = $this->rtspStreamingService->testMjpegStream(
                    $dvr->ip,
                    $dvr->port,
                    $dvr->username ?? 'admin',
                    $dvr->password ?? 'admin',
                    $channel,
                    $subtype
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Stream test completed',
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ],
                'test_result' => $testResult
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing stream: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Proxy MJPEG stream with authentication
     */
    public function getMjpegStream(Request $request, $ip, $channel = 1)
    {
        try {
            // Find DVR by IP or use defaults
            $dvr = Dvr::where('ip', $ip)->first();
            
            if (!$dvr) {
                $dvr = (object) [
                    'ip' => $ip,
                    'port' => 81,
                    'username' => 'admin',
                    'password' => 'css12345'
                ];
            }

            $subtype = $request->get('subtype', 0); // 0 = main, 1 = sub
            $mjpegUrl = "http://{$dvr->ip}:{$dvr->port}/cgi-bin/mjpg/video.cgi?channel={$channel}&subtype={$subtype}";
            
            // Stream MJPEG with authentication
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'Authorization: Digest username="' . $dvr->username . '", password="' . $dvr->password . '"',
                    'timeout' => 30
                ]
            ]);

            return response()->stream(function() use ($mjpegUrl, $dvr) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $mjpegUrl);
                curl_setopt($ch, CURLOPT_USERPWD, $dvr->username . ':' . $dvr->password);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
                    echo $data;
                    return strlen($data);
                });
                curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                curl_exec($ch);
                curl_close($ch);
            }, 200, [
                'Content-Type' => 'multipart/x-mixed-replace; boundary=--myboundary',
                'Cache-Control' => 'no-cache',
                'Connection' => 'close'
            ]);

        } catch (\Exception $e) {
            return response('Stream not available', 404);
        }
    }

    /**
     * Proxy snapshot image with authentication
     */
    public function getSnapshotImage(Request $request, $ip, $channel = 1)
    {
        try {
            // Find DVR by IP or use defaults
            $dvr = Dvr::where('ip', $ip)->first();
            
            if (!$dvr) {
                $dvr = (object) [
                    'ip' => $ip,
                    'port' => 81,
                    'username' => 'admin',
                    'password' => 'css12345',
                    'dvr_name' => 'CPPLUS'
                ];
            }

            // Generate snapshot URL based on DVR type
            $snapshotUrl = $this->getSnapshotUrlForDvr($dvr, $channel);
            
            // Fetch image with authentication
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $snapshotUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERPWD, $dvr->username . ':' . $dvr->password);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpCode == 200 && $imageData && strpos($contentType, 'image') !== false) {
                return response($imageData)
                    ->header('Content-Type', $contentType)
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');
            }

            // If failed, return a placeholder image
            return $this->getPlaceholderImage($channel);

        } catch (\Exception $e) {
            return $this->getPlaceholderImage($channel);
        }
    }

    /**
     * Get snapshot URL for specific DVR type
     */
    private function getSnapshotUrlForDvr($dvr, $channel): string
    {
        $dvrName = strtoupper($dvr->dvr_name ?? 'CPPLUS');
        
        if (in_array($dvrName, ['CPPLUS', 'CPPLUS_ORANGE', 'DAHUA'])) {
            return "http://{$dvr->ip}:{$dvr->port}/cgi-bin/snapshot.cgi?channel={$channel}&type=1";
        } elseif ($dvrName === 'HIKVISION') {
            return "http://{$dvr->ip}:{$dvr->port}/ISAPI/Streaming/channels/{$channel}/picture?snapShotImageType=JPEG&videoResolutionWidth=1280&videoResolutionHeight=720";
        } else {
            // Default to CP Plus format
            return "http://{$dvr->ip}:{$dvr->port}/cgi-bin/snapshot.cgi?channel={$channel}&type=1";
        }
    }

    /**
     * Generate placeholder image
     */
    private function getPlaceholderImage($channel)
    {
        // Create a simple placeholder image
        $width = 320;
        $height = 240;
        $image = imagecreate($width, $height);
        
        // Colors
        $bg = imagecolorallocate($image, 64, 64, 64);
        $text = imagecolorallocate($image, 255, 255, 255);
        $border = imagecolorallocate($image, 128, 128, 128);
        
        // Fill background
        imagefill($image, 0, 0, $bg);
        
        // Draw border
        imagerectangle($image, 0, 0, $width-1, $height-1, $border);
        
        // Add text
        $text1 = "Channel {$channel}";
        $text2 = "Snapshot not available";
        
        // Center text
        imagestring($image, 3, ($width - strlen($text1) * 10) / 2, $height / 2 - 20, $text1, $text);
        imagestring($image, 2, ($width - strlen($text2) * 8) / 2, $height / 2 + 5, $text2, $text);
        
        // Output image
        ob_start();
        imagejpeg($image, null, 80);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);
        
        return response($imageData)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'no-cache');
    }

    /**
     * Get latest snapshot with streaming fallbacks by IP
     */
    public function getSnapshotByIp(Request $request, $ip): JsonResponse
    {
        try {
            // Find DVR by IP or create temporary DVR info
            $dvr = Dvr::where('ip', $ip)->first();
            
            if (!$dvr) {
                // Create temporary DVR info for IP-based testing
                $dvr = (object) [
                    'id' => null,
                    'dvr_name' => 'CPPLUS', // Default to CP Plus for API detection
                    'ip' => $ip,
                    'port' => 81, // Default HTTP port for CP Plus
                    'username' => 'admin',
                    'password' => 'css12345'
                ];
            }

            $channel = $request->get('channel', 1);
            
            // Try to get actual max channels from DVR
            $actualMaxChannels = $this->getMaxChannelsFromDvr($dvr);
            $channels = $request->get('channels', range(1, $actualMaxChannels));

            // Generate snapshot URLs for the requested channel(s)
            if ($request->has('channel')) {
                // Single channel snapshot
                $snapshotUrls = $this->generateSnapshotUrlsForChannel($dvr, $channel);
                $workingSnapshotUrl = $this->findWorkingSnapshotUrl($snapshotUrls);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Snapshot URLs generated for channel ' . $channel,
                    'dvr' => [
                        'name' => $dvr->dvr_name ?? 'Unknown DVR',
                        'ip' => $dvr->ip,
                        'port' => $dvr->port
                    ],
                    'channel' => $channel,
                    'snapshot_info' => [
                        'primary_url' => $workingSnapshotUrl,
                        'all_urls' => $snapshotUrls,
                        'timestamp' => now()->toISOString()
                    ]
                ]);
            } else {
                // Multiple channels snapshot
                $allSnapshots = [];
                foreach ($channels as $ch) {
                    $snapshotUrls = $this->generateSnapshotUrlsForChannel($dvr, $ch);
                    $allSnapshots["channel_{$ch}"] = [
                        'channel' => $ch,
                        'primary_url' => $this->findWorkingSnapshotUrl($snapshotUrls),
                        'all_urls' => $snapshotUrls
                    ];
                }
                
                // Generate streaming URLs as fallbacks
                $rtspPort = 554;
                $streamingInfo = $this->generateStreamingUrlsForSnapshot($dvr, $channels, $rtspPort);

                return response()->json([
                    'success' => true,
                    'message' => 'Snapshot and streaming info retrieved',
                    'dvr' => [
                        'name' => $dvr->dvr_name ?? 'Unknown DVR',
                        'ip' => $dvr->ip,
                        'port' => $dvr->port
                    ],
                    'snapshot_info' => [
                        'channels' => $allSnapshots,
                        'timestamp' => now()->toISOString()
                    ],
                    'streaming_info' => $streamingInfo
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting snapshot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate multiple snapshot URL options for a specific channel
     */
    private function generateSnapshotUrlsForChannel($dvr, int $channel): array
    {
        $urls = [];
        
        // Hikvision/Prama style snapshot URLs
        $urls['hikvision_hd'] = "http://{$dvr->ip}:{$dvr->port}/ISAPI/Streaming/channels/{$channel}/picture?snapShotImageType=JPEG&videoResolutionWidth=1280&videoResolutionHeight=720";
        $urls['hikvision_standard'] = "http://{$dvr->ip}:{$dvr->port}/ISAPI/Streaming/channels/{$channel}/picture";
        
        // CP Plus/CP Plus Orange/Dahua style snapshot URLs
        $urls['cpplus_type1'] = "http://{$dvr->ip}:{$dvr->port}/cgi-bin/snapshot.cgi?channel={$channel}&type=1";
        $urls['cpplus_basic'] = "http://{$dvr->ip}:{$dvr->port}/cgi-bin/snapshot.cgi?channel={$channel}";
        $urls['dahua_snapshot'] = "http://{$dvr->ip}:{$dvr->port}/cgi-bin/snapshot.cgi?channel={$channel}&type=1";
        
        // Generic fallback URLs
        $urls['generic_snapshot'] = "http://{$dvr->ip}:{$dvr->port}/snapshot.jpg?channel={$channel}";
        $urls['mjpeg_frame'] = "http://{$dvr->ip}:{$dvr->port}/cgi-bin/mjpg/video.cgi?channel={$channel}&subtype=0";
        
        return $urls;
    }

    /**
     * Generate multiple snapshot URL options (legacy method for backward compatibility)
     */
    private function generateSnapshotUrls($dvr): array
    {
        return $this->generateSnapshotUrlsForChannel($dvr, 1);
    }

    /**
     * Find the first working snapshot URL
     */
    private function findWorkingSnapshotUrl(array $urls): string
    {
        // Priority order for different DVR types
        $priority = [
            'cpplus_type1',      // CP Plus/Dahua with type=1 (most reliable)
            'cpplus_basic',      // CP Plus/Dahua basic
            'dahua_snapshot',    // Dahua alternative
            'hikvision_hd',      // Hikvision HD
            'hikvision_standard', // Hikvision standard
            'mjpeg_frame',       // MJPEG frame fallback
            'generic_snapshot'   // Generic fallback
        ];
        
        // Return URLs in priority order
        foreach ($priority as $type) {
            if (isset($urls[$type])) {
                return $urls[$type];
            }
        }
        
        // If no priority match, return first available URL
        return reset($urls);
    }

    /**
     * Generate streaming URLs formatted for snapshot response
     */
    private function generateStreamingUrlsForSnapshot($dvr, array $channels, int $rtspPort): array
    {
        $streamingInfo = [
            'dvr' => [
                'name' => $dvr->dvr_name ?? 'Unknown DVR',
                'ip' => $dvr->ip,
                'port' => $dvr->port,
                'rtsp_port' => $rtspPort
            ],
            'channels' => [],
            'summary' => [
                'total_channels' => count($channels),
                'fallback_options' => 4 // RTSP Main, MJPEG Main, RTSP Sub, MJPEG Sub
            ]
        ];

        foreach ($channels as $channel) {
            $channelInfo = [
                'channel_number' => $channel,
                'rtsp_main_stream' => "rtsp://{$dvr->username}:{$dvr->password}@{$dvr->ip}:{$rtspPort}/cam/realmonitor?channel={$channel}&subtype=0",
                'rtsp_sub_stream_1' => "rtsp://{$dvr->username}:{$dvr->password}@{$dvr->ip}:{$rtspPort}/cam/realmonitor?channel={$channel}&subtype=1",
                'mjpeg_main_stream' => "http://{$dvr->ip}:{$dvr->port}/cgi-bin/mjpg/video.cgi?channel={$channel}&subtype=0",
                'mjpeg_sub_stream_1' => "http://{$dvr->ip}:{$dvr->port}/cgi-bin/mjpg/video.cgi?channel={$channel}&subtype=1",
                'playback_example' => "rtsp://{$dvr->username}:{$dvr->password}@{$dvr->ip}:{$rtspPort}/cam/playback?channel={$channel}&starttime=" . date('Y_m_d_H_i_s', strtotime('-1 hour')) . "&endtime=" . date('Y_m_d_H_i_s')
            ];
            
            $streamingInfo['channels'][] = $channelInfo;
        }

        return $streamingInfo;
    }

    /**
     * Get maximum channels from DVR API
     */
    private function getMaxChannelsFromDvr($dvr): int
    {
        try {
            // For CP Plus DVRs, use the magicBox API
            if (strtoupper($dvr->dvr_name ?? '') === 'CPPLUS' || strtoupper($dvr->dvr_name ?? '') === 'CPPLUS_ORANGE') {
                $url = "http://{$dvr->ip}:{$dvr->port}/cgi-bin/magicBox.cgi?action=getProductDefinition&name=MaxRemoteInputChannels";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_USERPWD, ($dvr->username ?? 'admin') . ':' . ($dvr->password ?? 'css12345'));
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode == 200 && $response) {
                    if (preg_match('/table\.MaxRemoteInputChannels=(\d+)/', $response, $matches)) {
                        $maxChannels = (int)$matches[1];
                        \Log::info("Detected $maxChannels channels for DVR IP: {$dvr->ip}");
                        return $maxChannels;
                    }
                }
            }
            
            // TODO: Add support for other DVR types
            // Hikvision: /ISAPI/System/deviceInfo
            // Dahua: /cgi-bin/magicBox.cgi?action=getDeviceType
            
        } catch (\Exception $e) {
            \Log::warning("Failed to detect max channels for DVR IP: {$dvr->ip} - " . $e->getMessage());
        }
        
        // Default fallback
        return 3;
    }

    /**
     * Helper: Generate streaming URLs without testing
     */
    private function generateStreamingUrls(Dvr $dvr, array $channels, int $rtspPort): array
    {
        $streamingInfo = [
            'dvr' => [
                'ip' => $dvr->ip,
                'port' => $dvr->port,
                'rtsp_port' => $rtspPort,
                'username' => $dvr->username ?? 'admin'
            ],
            'channels' => [],
            'summary' => [
                'total_channels' => count($channels),
                'urls_generated' => true,
                'tested' => false
            ]
        ];

        foreach ($channels as $channel) {
            $channelInfo = [
                'channel' => $channel,
                'urls' => []
            ];

            // Generate RTSP URLs
            $rtspUrls = $this->rtspStreamingService->generateLiveStreamUrls(
                $dvr->ip,
                $rtspPort,
                $dvr->username ?? 'admin',
                $dvr->password ?? 'admin',
                $channel
            );

            // Generate MJPEG URLs
            $mjpegUrls = $this->rtspStreamingService->generateMjpegUrls(
                $dvr->ip,
                $dvr->port,
                $channel
            );

            // Combine URLs
            foreach ($rtspUrls as $type => $info) {
                $channelInfo['urls']['rtsp_' . $type] = $info['url'];
            }

            foreach ($mjpegUrls as $type => $info) {
                $channelInfo['urls']['mjpeg_' . $type] = $info['url'];
            }

            // Add playback template
            $channelInfo['urls']['playback_template'] = "rtsp://{$dvr->username}:{$dvr->password}@{$dvr->ip}:{$rtspPort}/cam/playback?channel={$channel}&starttime={START_TIME}&endtime={END_TIME}";

            $streamingInfo['channels'][$channel] = $channelInfo;
        }

        return $streamingInfo;
    }
}