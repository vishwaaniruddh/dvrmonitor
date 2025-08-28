<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RtspToHlsService
{
    protected $hlsPath;
    protected $publicPath;
    protected $processes = [];

    public function __construct()
    {
        $this->hlsPath = storage_path('app/public/hls');
        $this->publicPath = public_path('hls');
        
        // Create HLS directories if they don't exist
        if (!file_exists($this->hlsPath)) {
            mkdir($this->hlsPath, 0755, true);
        }
        if (!file_exists($this->publicPath)) {
            mkdir($this->publicPath, 0755, true);
        }
    }

    /**
     * Start RTSP to HLS conversion for a specific camera
     */
    public function startStream(string $ip, int $channel, string $username, string $password, int $rtspPort = 554): array
    {
        // TEMPORARY: Disable HLS streaming to prevent server crashes
        // This is a critical fix to keep the server responsive
        return [
            'success' => false,
            'message' => 'HLS streaming temporarily disabled due to server stability issues. Use MJPEG or VLC streaming instead.'
        ];
        
        /* ORIGINAL CODE COMMENTED OUT TO PREVENT SERVER CRASHES
        try {
            $streamId = $this->generateStreamId($ip, $channel);
            $rtspUrl = "rtsp://{$username}:{$password}@{$ip}:{$rtspPort}/cam/realmonitor?channel={$channel}&subtype=0";
            
            // Check if stream is already running
            if ($this->isStreamRunning($streamId)) {
                return [
                    'success' => true,
                    'stream_id' => $streamId,
                    'hls_url' => "/hls/{$streamId}/playlist.m3u8",
                    'message' => 'Stream already running'
                ];
            }

            // Create stream directory
            $streamDir = $this->hlsPath . DIRECTORY_SEPARATOR . $streamId;
            if (!file_exists($streamDir)) {
                mkdir($streamDir, 0755, true);
            }

            // FFmpeg command for RTSP to HLS conversion
            $playlistPath = $streamDir . DIRECTORY_SEPARATOR . 'playlist.m3u8';
            $segmentPath = $streamDir . DIRECTORY_SEPARATOR . 'segment_%03d.ts';
            
            $ffmpegCmd = $this->buildFFmpegCommand($rtspUrl, $playlistPath, $segmentPath);
            
            // Start FFmpeg process in background
            $process = $this->startFFmpegProcess($ffmpegCmd, $streamId);
            
            if ($process) {
                $this->processes[$streamId] = $process;
                
                // Don't wait for playlist - let frontend poll for status
                // This prevents blocking the server
                
                return [
                    'success' => true,
                    'stream_id' => $streamId,
                    'hls_url' => "/hls/{$streamId}/playlist.m3u8",
                    'rtsp_url' => $rtspUrl,
                    'message' => 'Stream started, initializing...'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to start FFmpeg process'
            ];

        } catch (\Exception $e) {
            Log::error("RTSP to HLS conversion failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Stream conversion failed: ' . $e->getMessage()
            ];
        }
        */
    }

    /**
     * Stop HLS stream
     */
    public function stopStream(string $streamId): array
    {
        try {
            // Kill FFmpeg process
            if (isset($this->processes[$streamId])) {
                $this->killProcess($this->processes[$streamId]);
                unset($this->processes[$streamId]);
            }

            // Clean up HLS files
            $streamDir = $this->hlsPath . '/' . $streamId;
            if (file_exists($streamDir)) {
                $this->cleanupStreamFiles($streamDir);
            }

            return [
                'success' => true,
                'message' => 'Stream stopped successfully'
            ];

        } catch (\Exception $e) {
            Log::error("Failed to stop stream: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to stop stream: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get stream status
     */
    public function getStreamStatus(string $streamId): array
    {
        $playlistPath = $this->hlsPath . DIRECTORY_SEPARATOR . $streamId . DIRECTORY_SEPARATOR . 'playlist.m3u8';
        $isRunning = $this->isStreamRunning($streamId);
        $playlistExists = file_exists($playlistPath);

        return [
            'stream_id' => $streamId,
            'is_running' => $isRunning,
            'playlist_exists' => $playlistExists,
            'hls_url' => $playlistExists ? "/hls/{$streamId}/playlist.m3u8" : null,
            'last_updated' => $playlistExists ? filemtime($playlistPath) : null
        ];
    }

    /**
     * Generate unique stream ID
     */
    private function generateStreamId(string $ip, int $channel): string
    {
        return 'stream_' . str_replace('.', '_', $ip) . '_ch' . $channel;
    }

    /**
     * Build FFmpeg command for RTSP to HLS conversion
     */
    private function buildFFmpegCommand(string $rtspUrl, string $playlistPath, string $segmentPath): string
    {
        // Normalize paths for Windows
        $playlistPath = str_replace('/', DIRECTORY_SEPARATOR, $playlistPath);
        $segmentPath = str_replace('/', DIRECTORY_SEPARATOR, $segmentPath);
        
        // For Windows batch files, escape the % characters in the segment template
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $segmentPath = str_replace('%', '%%', $segmentPath);
        }
        
        return sprintf(
            'ffmpeg -i "%s" -c:v libx264 -preset ultrafast -tune zerolatency -c:a aac -b:v 800k -b:a 128k -f hls -hls_time 2 -hls_list_size 5 -hls_flags delete_segments -hls_segment_filename "%s" "%s"',
            $rtspUrl,
            $segmentPath,
            $playlistPath
        );
    }

    /**
     * Start FFmpeg process in background
     */
    private function startFFmpegProcess(string $command, string $streamId): ?array
    {
        $logFile = storage_path("logs/ffmpeg_{$streamId}.log");
        $pidFile = storage_path("logs/ffmpeg_{$streamId}.pid");

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: Use PowerShell Start-Process for true non-blocking execution
            $batchFile = storage_path("logs/ffmpeg_{$streamId}.bat");
            $batchContent = "@echo off\n{$command} > \"{$logFile}\" 2>&1";
            file_put_contents($batchFile, $batchContent);
            
            // Use PowerShell Start-Process with -NoNewWindow for true background execution
            $psCommand = "powershell -Command \"Start-Process -FilePath '{$batchFile}' -WindowStyle Hidden -NoNewWindow\"";
            
            // Use popen for non-blocking execution
            $handle = popen($psCommand, 'r');
            if ($handle) {
                pclose($handle); // Close immediately, don't wait
            }
            
            // Generate a fake PID for tracking
            $pid = time() . rand(100, 999);
            file_put_contents($pidFile, $pid);
            
            return [
                'pid' => $pid,
                'log_file' => $logFile,
                'pid_file' => $pidFile,
                'batch_file' => $batchFile,
                'started_at' => time()
            ];
        } else {
            // Unix/Linux command
            $fullCommand = "nohup {$command} > {$logFile} 2>&1 & echo $! > {$pidFile}";
            
            exec($fullCommand, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($pidFile)) {
                $pid = (int)trim(file_get_contents($pidFile));
                return [
                    'pid' => $pid,
                    'log_file' => $logFile,
                    'pid_file' => $pidFile,
                    'started_at' => time()
                ];
            }
        }

        return null;
    }

    /**
     * Check if stream is currently running
     */
    private function isStreamRunning(string $streamId): bool
    {
        // Check if playlist file exists and is recent
        $playlistPath = $this->hlsPath . DIRECTORY_SEPARATOR . $streamId . DIRECTORY_SEPARATOR . 'playlist.m3u8';
        
        if (file_exists($playlistPath)) {
            $lastModified = filemtime($playlistPath);
            // Consider stream running if playlist was updated in last 30 seconds
            return (time() - $lastModified) < 30;
        }

        // Also check process if we have it tracked
        if (isset($this->processes[$streamId])) {
            $process = $this->processes[$streamId];
            $pid = $process['pid'];

            if (PHP_OS_FAMILY === 'Windows') {
                // For Windows, check if FFmpeg processes are running
                exec("tasklist /FI \"IMAGENAME eq ffmpeg.exe\" 2>NUL", $output);
                return count($output) > 2; // Header lines + actual processes
            } else {
                return file_exists("/proc/{$pid}");
            }
        }

        return false;
    }

    /**
     * Kill FFmpeg process
     */
    private function killProcess(array $process): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Kill all FFmpeg processes (safer approach for Windows)
            exec("taskkill /F /IM ffmpeg.exe 2>NUL");
        } else {
            $pid = $process['pid'];
            exec("kill -9 {$pid} 2>/dev/null");
        }

        // Clean up PID file
        if (file_exists($process['pid_file'])) {
            unlink($process['pid_file']);
        }
    }

    /**
     * Wait for playlist file to be created (non-blocking)
     */
    private function waitForPlaylist(string $playlistPath, int $maxWait = 3): bool
    {
        $waited = 0;
        while ($waited < $maxWait) {
            if (file_exists($playlistPath) && filesize($playlistPath) > 0) {
                return true;
            }
            usleep(500000); // 0.5 seconds instead of 1 second
            $waited++;
        }
        return false;
    }

    /**
     * Clean up stream files
     */
    private function cleanupStreamFiles(string $streamDir): void
    {
        if (is_dir($streamDir)) {
            $files = glob($streamDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($streamDir);
        }
    }

    /**
     * Clean up old/inactive streams
     */
    public function cleanupOldStreams(int $maxAge = 3600): void
    {
        $hlsDir = $this->hlsPath;
        if (!is_dir($hlsDir)) {
            return;
        }

        $streamDirs = glob($hlsDir . '/stream_*');
        foreach ($streamDirs as $streamDir) {
            $playlistPath = $streamDir . '/playlist.m3u8';
            
            if (file_exists($playlistPath)) {
                $lastModified = filemtime($playlistPath);
                if (time() - $lastModified > $maxAge) {
                    $streamId = basename($streamDir);
                    Log::info("Cleaning up old stream: {$streamId}");
                    $this->stopStream($streamId);
                }
            }
        }
    }
}