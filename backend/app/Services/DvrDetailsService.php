<?php

namespace App\Services;

use App\Models\Dvr;
use App\Services\DvrApiFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DvrDetailsService
{
    /**
     * Get detailed information for a single DVR
     */
    public function getDvrDetails(Dvr $dvr): array
    {
        try {
            // Check if DVR type is supported
            if (!DvrApiFactory::isSupported($dvr->dvr_name)) {
                return [
                    'success' => false,
                    'message' => "DVR type '{$dvr->dvr_name}' is not supported yet",
                    'data' => null
                ];
            }

            // Create appropriate API service
            $apiService = DvrApiFactory::create($dvr->dvr_name);

            // Get all details
            $result = $apiService->getAllDetails(
                $dvr->ip,
                $dvr->port,
                $dvr->username ?? 'admin',
                $dvr->password ?? 'admin'
            );

            if ($result['success']) {
                // Cache the result for 5 minutes to avoid frequent API calls
                $cacheKey = "dvr_details_{$dvr->id}";
                Cache::put($cacheKey, $result['data'], now()->addMinutes(5));

                // Update DVR record with latest info
                $this->updateDvrRecord($dvr, $result['data']);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Error getting DVR details for {$dvr->dvr_name} ({$dvr->ip}): {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Error retrieving DVR details: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Get details for multiple DVRs
     */
    public function getMultipleDvrDetails(array $dvrIds): array
    {
        $results = [];
        $dvrs = Dvr::whereIn('id', $dvrIds)->get();

        foreach ($dvrs as $dvr) {
            $results[$dvr->id] = $this->getDvrDetails($dvr);
        }

        return $results;
    }

    /**
     * Get cached DVR details
     */
    public function getCachedDvrDetails(Dvr $dvr): ?array
    {
        $cacheKey = "dvr_details_{$dvr->id}";
        return Cache::get($cacheKey);
    }

    /**
     * Update DVR record with latest information
     */
    private function updateDvrRecord(Dvr $dvr, array $data): void
    {
        try {
            $updateData = [];

            // Update camera count if available
            if (isset($data['cameras']['total_cameras'])) {
                $updateData['camera_count'] = $data['cameras']['total_cameras'];
            }

            // Update storage info if available
            if (isset($data['storage']['total_capacity_gb'])) {
                $updateData['storage_capacity_gb'] = $data['storage']['total_capacity_gb'];
            }

            // Update last detailed check timestamp
            $updateData['last_detailed_check'] = now('Asia/Kolkata');

            if (!empty($updateData)) {
                $dvr->update($updateData);
            }

        } catch (\Exception $e) {
            Log::error("Error updating DVR record {$dvr->id}: {$e->getMessage()}");
        }
    }

    /**
     * Test DVR API connection
     */
    public function testDvrConnection(Dvr $dvr): array
    {
        try {
            if (!DvrApiFactory::isSupported($dvr->dvr_name)) {
                return [
                    'success' => false,
                    'message' => "DVR type '{$dvr->dvr_name}' is not supported"
                ];
            }

            $apiService = DvrApiFactory::create($dvr->dvr_name);
            
            // Just test login
            $loginResult = $apiService->login(
                $dvr->ip,
                $dvr->port,
                $dvr->username ?? 'admin',
                $dvr->password ?? 'admin'
            );

            if ($loginResult['success']) {
                // Logout immediately
                $apiService->logout($dvr->ip, $dvr->port, $loginResult['session_token']);
            }

            return $loginResult;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
}