<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DvrDateTimeParser
{
    /**
     * Standard output format for all DVR times
     */
    const OUTPUT_FORMAT = 'Y-m-d H:i:s';

    /**
     * Common date/time formats used by different DVR systems
     */
    protected static $formats = [
        // Standard formats
        'Y-m-d H:i:s',              // 2025-08-27 14:30:00
        'Y-m-d\TH:i:s\Z',           // 2025-08-27T14:30:00Z (ISO 8601)
        'Y-m-d\TH:i:s.u\Z',         // 2025-08-27T14:30:00.000000Z (ISO with microseconds)
        'Y-m-d\TH:i:sP',            // 2025-08-27T14:30:00+05:30 (ISO with timezone)
        'Y-m-d\TH:i:s.uP',          // 2025-08-27T14:30:00.000000+05:30
        
        // Date only formats (will set time to 00:00:00)
        'Y-m-d',                    // 2025-08-27
        
        // European formats (DD-MM-YYYY)
        'd-m-Y H:i:s',              // 27-08-2025 14:30:00
        'd-m-Y',                    // 27-08-2025
        'd/m/Y H:i:s',              // 27/08/2025 14:30:00
        'd/m/Y',                    // 27/08/2025
        
        // US formats (MM/DD/YYYY)
        'm/d/Y H:i:s',              // 08/27/2025 14:30:00
        'm/d/Y',                    // 08/27/2025
        
        // Flexible formats with single digits
        'j-n-Y H:i:s',              // 27-8-2025 14:30:00 (day-month-year)
        'j-n-Y',                    // 27-8-2025
        'n-j-Y H:i:s',              // 8-27-2025 14:30:00 (month-day-year)
        'n-j-Y',                    // 8-27-2025
        'Y-n-j H:i:s',              // 2025-8-27 14:30:00 (year-month-day)
        'Y-n-j',                    // 2025-8-27
        
        // Formats with spaces (some DVRs use spaces instead of dashes)
        'j n Y H:i:s',              // 27 8 2025 14:30:00
        'j n Y',                    // 27 8 2025
        'Y n j H:i:s',              // 2025 8 27 14:30:00
        'Y n j',                    // 2025 8 27
        
        // 12-hour formats
        'Y-m-d h:i:s A',            // 2025-08-27 02:30:00 PM
        'd-m-Y h:i:s A',            // 27-08-2025 02:30:00 PM
        'm/d/Y h:i:s A',            // 08/27/2025 02:30:00 PM
        
        // Unix timestamp (if it's a number)
        'U',                        // Unix timestamp
    ];

    /**
     * Parse any DVR date/time format and return standardized format
     *
     * @param string|int $dateTime The date/time string from DVR
     * @param string $timezone Timezone to use (default: Asia/Kolkata)
     * @return string|null Standardized date/time string or null if parsing fails
     */
    public static function parse($dateTime, string $timezone = 'Asia/Kolkata'): ?string
    {
        if (empty($dateTime)) {
            return null;
        }

        // Convert to string and trim whitespace
        $dateTimeStr = trim((string)$dateTime);
        
        // Handle Unix timestamp (numeric values)
        if (is_numeric($dateTimeStr)) {
            try {
                $carbon = Carbon::createFromTimestamp($dateTimeStr, $timezone);
                return $carbon->format(self::OUTPUT_FORMAT);
            } catch (\Exception $e) {
                // Only log if Laravel is available
                if (class_exists('\Illuminate\Support\Facades\Log') && function_exists('app') && app()->bound('log')) {
                    Log::warning("Failed to parse Unix timestamp: {$dateTimeStr}", ['error' => $e->getMessage()]);
                }
            }
        }

        // Try each format
        foreach (self::$formats as $format) {
            try {
                $carbon = Carbon::createFromFormat($format, $dateTimeStr, $timezone);
                
                if ($carbon && $carbon->year > 1900 && $carbon->year < 2100) {
                    // If it's a date-only format, ensure time is set to 00:00:00
                    if (!str_contains($format, 'H:i:s') && !str_contains($format, 'h:i:s')) {
                        $carbon->setTime(0, 0, 0);
                    }
                    
                    return $carbon->format(self::OUTPUT_FORMAT);
                }
            } catch (\Exception $e) {
                // Continue to next format
                continue;
            }
        }

        // Last resort: try Carbon's flexible parsing
        try {
            $carbon = Carbon::parse($dateTimeStr, $timezone);
            
            if ($carbon && $carbon->year > 1900 && $carbon->year < 2100) {
                return $carbon->format(self::OUTPUT_FORMAT);
            }
        } catch (\Exception $e) {
            // Only log if Laravel is available
            if (class_exists('\Illuminate\Support\Facades\Log') && function_exists('app') && app()->bound('log')) {
                Log::warning("Failed to parse DVR date/time with all methods", [
                    'input' => $dateTimeStr,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return null;
    }

    /**
     * Parse and validate DVR date/time with additional context
     *
     * @param string|int $dateTime The date/time string from DVR
     * @param string $dvrId DVR identifier for logging
     * @param string $timezone Timezone to use
     * @return array Result with parsed time and metadata
     */
    public static function parseWithContext($dateTime, string $dvrId = 'unknown', string $timezone = 'Asia/Kolkata'): array
    {
        $originalInput = $dateTime;
        $parsedTime = self::parse($dateTime, $timezone);
        
        $result = [
            'success' => $parsedTime !== null,
            'original_input' => $originalInput,
            'parsed_time' => $parsedTime,
            'format_used' => null,
            'timezone' => $timezone
        ];

        if ($parsedTime) {
            // Try to determine which format was used
            $result['format_used'] = self::detectFormat($originalInput);
            
            Log::info("DVR {$dvrId} - Date/time parsed successfully", [
                'original' => $originalInput,
                'parsed' => $parsedTime,
                'format' => $result['format_used']
            ]);
        } else {
            Log::error("DVR {$dvrId} - Failed to parse date/time", [
                'input' => $originalInput
            ]);
        }

        return $result;
    }

    /**
     * Detect which format was likely used for the input
     *
     * @param string $dateTime Original input
     * @return string|null Detected format or null
     */
    protected static function detectFormat(string $dateTime): ?string
    {
        $dateTime = trim($dateTime);

        // Check for common patterns
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z?$/', $dateTime)) {
            return 'ISO 8601';
        }
        
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{2}:\d{2}$/', $dateTime)) {
            return 'Standard MySQL';
        }
        
        if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}/', $dateTime)) {
            return 'European (DD-MM-YYYY)';
        }
        
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}/', $dateTime)) {
            return 'US (MM/DD/YYYY)';
        }
        
        if (is_numeric($dateTime)) {
            return 'Unix Timestamp';
        }

        return 'Unknown';
    }

    /**
     * Add a custom format to the parser
     *
     * @param string $format Carbon-compatible format string
     */
    public static function addCustomFormat(string $format): void
    {
        if (!in_array($format, self::$formats)) {
            array_unshift(self::$formats, $format); // Add to beginning for priority
        }
    }

    /**
     * Get all supported formats
     *
     * @return array List of supported formats
     */
    public static function getSupportedFormats(): array
    {
        return self::$formats;
    }

    /**
     * Test the parser with sample inputs
     *
     * @return array Test results
     */
    public static function runTests(): array
    {
        $testCases = [
            '2025-08-27 14:30:00',
            '2025-08-27T14:30:00Z',
            '2025-08-27T14:30:00.000000Z',
            '27-08-2025 14:30:00',
            '08/27/2025 14:30:00',
            '27-8-2025 14:30:00',
            '2025-8-27',
            '1724756400', // Unix timestamp
            '27 8 2025 14:30:00',
            '2025-08-27 02:30:00 PM'
        ];

        $results = [];
        foreach ($testCases as $testCase) {
            $results[$testCase] = self::parse($testCase);
        }

        return $results;
    }
}