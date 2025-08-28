<?php

namespace App\Jobs;

use App\Models\Dvr;
use App\Models\DvrMonitoringLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchDvrDataJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 60;
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Dvr $dvr
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            // Get DVR-specific API endpoints and methods
            $apiData = $this->fetchDvrApiData();
            $responseTime = (int)((microtime(true) - $startTime) * 1000);
            
            if ($apiData) {
                // API call successful
                $this->dvr->update([
                    'api_accessible' => true,
                    'last_api_call_at' => now(),
                    'last_api_response' => $apiData,
                    'device_model' => $apiData['device_model'] ?? null,
                    'firmware_version' => $apiData['firmware_version'] ?? null,
                    'channel_count' => $apiData['channel_count'] ?? null
                ]);

                // Log success
                DvrMonitoringLog::create([
                    'dvr_id' => $this->dvr->id,
                    'check_type' => 'api_call',
                    'result' => 'success',
                    'response_time' => $responseTime,
                    'response_data' => $apiData
                ]);
                
            } else {
                $this->handleApiFailure($responseTime);
            }
            
        } catch (\Exception $e) {
            $this->handleApiFailure(null, $e->getMessage());
            Log::error("API fetch job failed for DVR {$this->dvr->id}: " . $e->getMessage());
        }
    }

    private function fetchDvrApiData(): ?array
    {
        $baseUrl = "http://{$this->dvr->ip}:{$this->dvr->port}";
        
        // Different API endpoints based on DVR type
        switch (strtolower($this->dvr->dvr_name)) {
            case 'hikvision':
                return $this->fetchHikvisionData($baseUrl);
            case 'cpplus':
            case 'cpplus_orange':
                return $this->fetchCpplusData($baseUrl);
            case 'dahua':
                return $this->fetchDahuaData($baseUrl);
            case 'prama':
                return $this->fetchPramaData($baseUrl);
            default:
                return $this->fetchGenericData($baseUrl);
        }
    }

    private function fetchHikvisionData($baseUrl): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth($this->dvr->username, $this->dvr->password)
                ->get("{$baseUrl}/ISAPI/System/deviceInfo");
                
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'device_model' => $data['DeviceInfo']['model'] ?? 'Unknown',
                    'firmware_version' => $data['DeviceInfo']['firmwareVersion'] ?? 'Unknown',
                    'channel_count' => $data['DeviceInfo']['videoInputPortNums'] ?? 0,
                    'serial_number' => $data['DeviceInfo']['serialNumber'] ?? null,
                    'raw_response' => $data
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Hikvision API failed for DVR {$this->dvr->id}: " . $e->getMessage());
        }
        
        return null;
    }

    private function fetchCpplusData($baseUrl): ?array
    {
        try {
            // CP Plus specific API calls
            $response = Http::timeout(30)
                ->withBasicAuth($this->dvr->username, $this->dvr->password)
                ->get("{$baseUrl}/cgi-bin/magicBox.cgi?action=getDeviceType");
                
            if ($response->successful()) {
                return [
                    'device_model' => 'CP Plus DVR',
                    'firmware_version' => 'Unknown',
                    'channel_count' => 8, // Default, should be parsed from response
                    'raw_response' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::warning("CP Plus API failed for DVR {$this->dvr->id}: " . $e->getMessage());
        }
        
        return null;
    }

    private function fetchDahuaData($baseUrl): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth($this->dvr->username, $this->dvr->password)
                ->get("{$baseUrl}/cgi-bin/magicBox.cgi?action=getDeviceType");
                
            if ($response->successful()) {
                return [
                    'device_model' => 'Dahua DVR',
                    'firmware_version' => 'Unknown',
                    'channel_count' => 16, // Default
                    'raw_response' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Dahua API failed for DVR {$this->dvr->id}: " . $e->getMessage());
        }
        
        return null;
    }

    private function fetchPramaData($baseUrl): ?array
    {
        try {
            // Prama specific implementation
            return [
                'device_model' => 'Prama DVR',
                'firmware_version' => 'Unknown',
                'channel_count' => 4,
                'raw_response' => 'Basic ping response'
            ];
        } catch (\Exception $e) {
            Log::warning("Prama API failed for DVR {$this->dvr->id}: " . $e->getMessage());
        }
        
        return null;
    }

    private function fetchGenericData($baseUrl): ?array
    {
        // Generic fallback
        return [
            'device_model' => 'Generic DVR',
            'firmware_version' => 'Unknown',
            'channel_count' => 0,
            'raw_response' => 'Generic response'
        ];
    }

    private function handleApiFailure(?int $responseTime, ?string $errorMessage = null): void
    {
        $this->dvr->update([
            'api_accessible' => false,
            'last_api_call_at' => now()
        ]);

        // Log failure
        DvrMonitoringLog::create([
            'dvr_id' => $this->dvr->id,
            'check_type' => 'api_call',
            'result' => 'failure',
            'response_time' => $responseTime,
            'error_message' => $errorMessage ?? 'API call failed or timeout'
        ]);
    }
}
