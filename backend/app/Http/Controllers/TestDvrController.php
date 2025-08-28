<?php

namespace App\Http\Controllers;

use App\Services\CpPlusDvrApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestDvrController extends Controller
{
    /**
     * Test a specific DVR with provided credentials
     */
    public function testDvr(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string',
            'password' => 'required|string',
            'dvr_type' => 'string|in:cpplus'
        ]);

        try {
            $cpPlusService = new CpPlusDvrApiService();
            
            // Test login first
            $loginResult = $cpPlusService->login(
                $request->ip,
                $request->port,
                $request->username,
                $request->password
            );

            if (!$loginResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Login failed: ' . $loginResult['message'],
                    'data' => null
                ]);
            }

            // Get all details
            $allDetailsResult = $cpPlusService->getAllDetails(
                $request->ip,
                $request->port,
                $request->username,
                $request->password
            );

            return response()->json([
                'success' => $allDetailsResult['success'],
                'message' => $allDetailsResult['message'],
                'dvr_info' => [
                    'ip' => $request->ip,
                    'port' => $request->port,
                    'type' => 'CP Plus'
                ],
                'data' => $allDetailsResult['data']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing DVR: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Quick ping test for DVR connectivity
     */
    public function pingTest(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535'
        ]);

        try {
            $startTime = microtime(true);
            
            // Simple HTTP request to test connectivity
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://{$request->ip}:{$request->port}/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2); // ms

            $isReachable = ($response !== false && empty($error));

            return response()->json([
                'success' => true,
                'reachable' => $isReachable,
                'response_time_ms' => $responseTime,
                'http_code' => $httpCode,
                'message' => $isReachable ? 'DVR is reachable' : 'DVR is not reachable',
                'error' => $error ?: null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'reachable' => false,
                'message' => 'Ping test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}