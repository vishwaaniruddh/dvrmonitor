<?php

namespace App\Services;

use App\Contracts\DvrApiInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseDvrApiService implements DvrApiInterface
{
    protected $timeout = 10; // seconds
    protected $connectTimeout = 5; // seconds

    /**
     * Make HTTP request with error handling
     */
    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->withOptions(['verify' => false]) // Disable SSL verification for DVRs
                ->{$method}($url, $options);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'data' => $response->json() ?? $response->body(),
                'error' => $response->successful() ? null : $response->body()
            ];
        } catch (\Exception $e) {
            Log::error("DVR API Request failed: {$e->getMessage()}", [
                'url' => $url,
                'method' => $method,
                'options' => $options
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make HTTP Digest Authentication request (for CP Plus DVRs)
     */
    protected function makeDigestRequest(string $url, string $username, string $password): array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($error)) {
                throw new \Exception("cURL error: {$error}");
            }

            // Parse CP Plus response format (key=value pairs)
            $data = [];
            if ($httpCode == 200 && $response) {
                $lines = explode("\n", trim($response));
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false) {
                        [$key, $value] = explode('=', $line, 2);
                        $data[trim($key)] = trim($value);
                    }
                }
            }

            return [
                'success' => $httpCode == 200,
                'status_code' => $httpCode,
                'data' => $data,
                'raw_response' => $response,
                'error' => $httpCode != 200 ? "HTTP {$httpCode}" : null
            ];

        } catch (\Exception $e) {
            Log::error("DVR Digest Auth Request failed: {$e->getMessage()}", [
                'url' => $url,
                'username' => $username
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'data' => [],
                'raw_response' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build DVR URL
     */
    protected function buildUrl(string $ip, int $port, string $endpoint): string
    {
        return "http://{$ip}:{$port}{$endpoint}";
    }

    /**
     * Parse response based on DVR type
     */
    abstract protected function parseResponse(array $response, string $type): array;

    /**
     * Get DVR-specific headers
     */
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }
}