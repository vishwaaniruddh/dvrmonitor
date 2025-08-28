<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RtspStreamingService
{
    protected $timeout = 10;
    protected $connectTimeout = 5;

    /**
     * Generate RTSP URLs for live streaming
     */
    public function generateLiveStreamUrls(string $ip, int $rtspPort, string $username, string $password, int $channel, array $subtypes = [0, 1]): array
    {
        $urls = [];
        
        foreach ($subtypes as $subtype) {
            $streamType = $this->getStreamTypeName($subtype);
            $urls[$streamType] = [
                'url' => "rtsp://{$username}:{$password}@{$ip}:{$rtspPort}/cam/realmonitor?channel={$channel}&subtype={$subtype}",
                'channel' => $channel,
                'subtype' => $subtype,
                'type' => 'live',
                'description' => "Channel {$channel} - {$streamType}"
            ];
        }
        
        return $urls;
    }

    /**
     * Generate RTSP URLs for playback
     */
    public function generatePlaybackUrls(string $ip, int $rtspPort, string $username, string $password, int $channel, string $startTime, string $endTime = null): array
    {
        // Convert datetime to DVR format (YYYY_MM_DD_HH_MM_SS)
        $formattedStartTime = $this->formatTimeForDvr($startTime);
        $formattedEndTime = $endTime ? $this->formatTimeForDvr($endTime) : null;
        
        $url = "rtsp://{$username}:{$password}@{$ip}:{$rtspPort}/cam/playback?channel={$channel}&starttime={$formattedStartTime}";
        
        if ($formattedEndTime) {
            $url .= "&endtime={$formattedEndTime}";
        }
        
        return [
            'playback' => [
                'url' => $url,
                'channel' => $channel,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'type' => 'playback',
                'description' => "Channel {$channel} Playback: {$startTime}" . ($endTime ? " to {$endTime}" : "")
            ]
        ];
    }

    /**
     * Generate MJPEG streaming URLs (easier for web browsers)
     */
    public function generateMjpegUrls(string $ip, int $port, int $channel, array $subtypes = [0, 1]): array
    {
        $urls = [];
        
        foreach ($subtypes as $subtype) {
            $streamType = $this->getStreamTypeName($subtype);
            $urls[$streamType] = [
                'url' => "http://{$ip}:{$port}/cgi-bin/mjpg/video.cgi?channel={$channel}&subtype={$subtype}",
                'channel' => $channel,
                'subtype' => $subtype,
                'type' => 'mjpeg',
                'description' => "Channel {$channel} - {$streamType} (MJPEG)"
            ];
        }
        
        return $urls;
    }

    /**
     * Test RTSP stream availability
     */
    public function testRtspStream(string $ip, int $rtspPort, string $username, string $password, int $channel, int $subtype = 0): array
    {
        $result = [
            'channel' => $channel,
            'subtype' => $subtype,
            'available' => false,
            'error' => null,
            'response_time' => 0,
            'stream_info' => null
        ];

        $startTime = microtime(true);

        try {
            // Create RTSP URL
            $rtspUrl = "rtsp://{$ip}:{$rtspPort}/cam/realmonitor?channel={$channel}&subtype={$subtype}";
            
            // Simple RTSP DESCRIBE request
            $socket = fsockopen($ip, $rtspPort, $errno, $errstr, $this->connectTimeout);
            
            if (!$socket) {
                $result['error'] = "Connection failed: {$errstr} ({$errno})";
                return $result;
            }

            // Send DESCRIBE request
            $request = "DESCRIBE {$rtspUrl} RTSP/1.0\r\n";
            $request .= "CSeq: 1\r\n";
            $request .= "User-Agent: RtspStreamingService/1.0\r\n";
            $request .= "Authorization: Basic " . base64_encode("{$username}:{$password}") . "\r\n";
            $request .= "\r\n";

            fwrite($socket, $request);
            
            // Set timeout for reading
            stream_set_timeout($socket, $this->timeout);
            
            $response = '';
            while (!feof($socket)) {
                $line = fgets($socket, 1024);
                if ($line === false) break;
                $response .= $line;
                
                // Stop after getting complete response
                if (strpos($response, "\r\n\r\n") !== false) break;
            }
            
            fclose($socket);

            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);

            // Parse RTSP response
            if (strpos($response, 'RTSP/1.0 200 OK') !== false) {
                $result['available'] = true;
                $result['stream_info'] = $this->parseRtspResponse($response);
            } else if (strpos($response, 'RTSP/1.0 401') !== false) {
                $result['error'] = 'Authentication required';
            } else if (strpos($response, 'RTSP/1.0 404') !== false) {
                $result['error'] = 'Stream not found';
            } else if (strpos($response, 'RTSP/1.0') !== false) {
                $result['error'] = 'RTSP error response';
            } else {
                $result['error'] = 'Invalid RTSP response';
            }

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Test MJPEG stream availability
     */
    public function testMjpegStream(string $ip, int $port, string $username, string $password, int $channel, int $subtype = 0): array
    {
        $result = [
            'channel' => $channel,
            'subtype' => $subtype,
            'available' => false,
            'error' => null,
            'response_time' => 0,
            'content_type' => null
        ];

        $startTime = microtime(true);

        try {
            $url = "http://{$ip}:{$port}/cgi-bin/mjpg/video.cgi?channel={$channel}&subtype={$subtype}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);

            if ($response === false || !empty($error)) {
                $result['error'] = $error ?: 'Request failed';
                return $result;
            }

            if ($httpCode === 200) {
                $result['available'] = true;
                $result['content_type'] = $contentType;
                
                // Check if it's a valid MJPEG stream
                if (strpos($contentType, 'multipart/x-mixed-replace') !== false) {
                    $result['stream_type'] = 'mjpeg_multipart';
                } else if (strpos($contentType, 'image/jpeg') !== false) {
                    $result['stream_type'] = 'jpeg_image';
                } else {
                    $result['stream_type'] = 'unknown';
                }
            } else if ($httpCode === 401) {
                $result['error'] = 'Authentication failed';
            } else if ($httpCode === 404) {
                $result['error'] = 'Stream not found';
            } else {
                $result['error'] = "HTTP {$httpCode}";
            }

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get comprehensive streaming information for a DVR
     */
    public function getStreamingInfo(string $ip, int $port, int $rtspPort, string $username, string $password, array $channels = [1, 2, 3]): array
    {
        $streamingInfo = [
            'dvr' => [
                'ip' => $ip,
                'port' => $port,
                'rtsp_port' => $rtspPort,
                'username' => $username
            ],
            'channels' => [],
            'summary' => [
                'total_channels' => 0,
                'rtsp_available' => 0,
                'mjpeg_available' => 0,
                'both_available' => 0
            ]
        ];

        foreach ($channels as $channel) {
            $channelInfo = [
                'channel' => $channel,
                'rtsp' => [],
                'mjpeg' => [],
                'urls' => []
            ];

            // Test RTSP streams (main and sub)
            for ($subtype = 0; $subtype <= 1; $subtype++) {
                $rtspTest = $this->testRtspStream($ip, $rtspPort, $username, $password, $channel, $subtype);
                $channelInfo['rtsp'][$subtype] = $rtspTest;
                
                if ($rtspTest['available']) {
                    $streamType = $this->getStreamTypeName($subtype);
                    $channelInfo['urls']['rtsp_' . $streamType] = "rtsp://{$username}:{$password}@{$ip}:{$rtspPort}/cam/realmonitor?channel={$channel}&subtype={$subtype}";
                }
            }

            // Test MJPEG streams
            for ($subtype = 0; $subtype <= 1; $subtype++) {
                $mjpegTest = $this->testMjpegStream($ip, $port, $username, $password, $channel, $subtype);
                $channelInfo['mjpeg'][$subtype] = $mjpegTest;
                
                if ($mjpegTest['available']) {
                    $streamType = $this->getStreamTypeName($subtype);
                    $channelInfo['urls']['mjpeg_' . $streamType] = "http://{$ip}:{$port}/cgi-bin/mjpg/video.cgi?channel={$channel}&subtype={$subtype}";
                }
            }

            // Generate playback URLs
            $channelInfo['urls']['playback_template'] = "rtsp://{$username}:{$password}@{$ip}:{$rtspPort}/cam/playback?channel={$channel}&starttime={START_TIME}&endtime={END_TIME}";

            $streamingInfo['channels'][$channel] = $channelInfo;
            $streamingInfo['summary']['total_channels']++;

            // Update summary
            $hasRtsp = !empty(array_filter($channelInfo['rtsp'], fn($test) => $test['available']));
            $hasMjpeg = !empty(array_filter($channelInfo['mjpeg'], fn($test) => $test['available']));
            
            if ($hasRtsp) $streamingInfo['summary']['rtsp_available']++;
            if ($hasMjpeg) $streamingInfo['summary']['mjpeg_available']++;
            if ($hasRtsp && $hasMjpeg) $streamingInfo['summary']['both_available']++;
        }

        return $streamingInfo;
    }

    /**
     * Helper: Get stream type name
     */
    private function getStreamTypeName(int $subtype): string
    {
        return match($subtype) {
            0 => 'main_stream',
            1 => 'sub_stream_1',
            2 => 'sub_stream_2',
            default => 'unknown_stream'
        };
    }

    /**
     * Helper: Format time for DVR (YYYY_MM_DD_HH_MM_SS)
     */
    private function formatTimeForDvr(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        return date('Y_m_d_H_i_s', $timestamp);
    }

    /**
     * Helper: Parse RTSP response for stream information
     */
    private function parseRtspResponse(string $response): array
    {
        $info = [];
        
        // Extract basic info from SDP
        if (preg_match('/Content-Type:\s*application\/sdp/i', $response)) {
            $info['content_type'] = 'application/sdp';
        }
        
        // Extract video codec info
        if (preg_match('/a=rtpmap:\d+\s+(\w+)\/(\d+)/i', $response, $matches)) {
            $info['video_codec'] = $matches[1];
            $info['clock_rate'] = $matches[2];
        }
        
        // Extract framerate
        if (preg_match('/a=framerate:([\d.]+)/i', $response, $matches)) {
            $info['framerate'] = floatval($matches[1]);
        }
        
        return $info;
    }
}