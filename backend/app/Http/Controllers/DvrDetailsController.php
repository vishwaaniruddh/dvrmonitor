<?php

namespace App\Http\Controllers;

use App\Models\Dvr;
use App\Services\DvrDetailsService;
use App\Services\DvrApiFactory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DvrDetailsController extends Controller
{
    protected $dvrDetailsService;

    public function __construct(DvrDetailsService $dvrDetailsService)
    {
        $this->dvrDetailsService = $dvrDetailsService;
    }

    /**
     * Get detailed information for a specific DVR
     */
    public function getDvrDetails(Request $request, $dvrId): JsonResponse
    {
        try {
            $dvr = Dvr::findOrFail($dvrId);
            $result = $this->dvrDetailsService->getDvrDetails($dvr);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port,
                    'status' => $dvr->status
                ],
                'details' => $result['data']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving DVR details: ' . $e->getMessage(),
                'dvr' => null,
                'details' => null
            ], 500);
        }
    }

    /**
     * Get cached details for a DVR
     */
    public function getCachedDetails(Request $request, $dvrId): JsonResponse
    {
        try {
            $dvr = Dvr::findOrFail($dvrId);
            $cachedData = $this->dvrDetailsService->getCachedDvrDetails($dvr);

            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cached data retrieved',
                    'dvr' => [
                        'id' => $dvr->id,
                        'name' => $dvr->dvr_name,
                        'ip' => $dvr->ip,
                        'port' => $dvr->port,
                        'status' => $dvr->status
                    ],
                    'details' => $cachedData,
                    'cached' => true
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No cached data available',
                'dvr' => null,
                'details' => null,
                'cached' => false
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving cached data: ' . $e->getMessage(),
                'dvr' => null,
                'details' => null,
                'cached' => false
            ], 500);
        }
    }

    /**
     * Test DVR API connection
     */
    public function testConnection(Request $request, $dvrId): JsonResponse
    {
        try {
            $dvr = Dvr::findOrFail($dvrId);
            $result = $this->dvrDetailsService->testDvrConnection($dvr);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'dvr' => [
                    'id' => $dvr->id,
                    'name' => $dvr->dvr_name,
                    'ip' => $dvr->ip,
                    'port' => $dvr->port
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'dvr' => null
            ], 500);
        }
    }

    /**
     * Get details for multiple DVRs
     */
    public function getMultipleDetails(Request $request): JsonResponse
    {
        $request->validate([
            'dvr_ids' => 'required|array',
            'dvr_ids.*' => 'integer|exists:dvrs,id'
        ]);

        try {
            $results = $this->dvrDetailsService->getMultipleDvrDetails($request->dvr_ids);

            return response()->json([
                'success' => true,
                'message' => 'Multiple DVR details retrieved',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving multiple DVR details: ' . $e->getMessage(),
                'results' => null
            ], 500);
        }
    }

    /**
     * Get supported DVR types
     */
    public function getSupportedTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'supported_types' => DvrApiFactory::getSupportedTypes(),
            'message' => 'Supported DVR types retrieved'
        ]);
    }

    /**
     * Check if DVR type is supported
     */
    public function checkSupport(Request $request): JsonResponse
    {
        $request->validate([
            'dvr_name' => 'required|string'
        ]);

        $isSupported = DvrApiFactory::isSupported($request->dvr_name);

        return response()->json([
            'success' => true,
            'dvr_name' => $request->dvr_name,
            'supported' => $isSupported,
            'message' => $isSupported ? 'DVR type is supported' : 'DVR type is not supported'
        ]);
    }
}