<?php

namespace App\Services;

use App\Contracts\DvrApiInterface;
use App\Services\CpPlusDvrApiService;
use App\Services\HikvisionDvrApiService;
use App\Services\DahuaDvrApiService;


use InvalidArgumentException;

class DvrApiFactory
{
    /**
     * Create DVR API service based on DVR name/brand
     */
    public static function create(string $dvrName): DvrApiInterface
    {
        $dvrName = strtolower(trim($dvrName));

        // CP Plus variants
        if (self::isCpPlus($dvrName)) {
            return new CpPlusDvrApiService();
        }

        // Add more DVR types here
        if (self::isHikvision($dvrName)) {
            return new HikvisionDvrApiService();
        }
        
        if (self::isDahua($dvrName)) {
            return new DahuaDvrApiService();
        }

        throw new InvalidArgumentException("Unsupported DVR type: {$dvrName}");
    }

    /**
     * Check if DVR is CP Plus variant
     */
    private static function isCpPlus(string $dvrName): bool
    {
        $cpPlusVariants = [
            'cpplus',
            'cp-plus',
            'cp_plus',
            'cpplus_orange',
            'cpplus_oragelike',
            'cp-plus-orange',
            'cp_plus_orange'
        ];

        return in_array($dvrName, $cpPlusVariants) || 
               str_contains($dvrName, 'cpplus') || 
               str_contains($dvrName, 'cp-plus') ||
               str_contains($dvrName, 'cp_plus');
    }

    /**
     * Check if DVR is Hikvision variant
     */
    private static function isHikvision(string $dvrName): bool
    {
        $hikvisionVariants = [
            'hikvision',
            'hikvision_nvr',
            'hik',
            'hikvision_ds',
            'ds-7600',
            'ds-7700'
        ];

        return in_array($dvrName, $hikvisionVariants) || 
               str_contains($dvrName, 'hikvision') || 
               str_contains($dvrName, 'hik') ||
               str_contains($dvrName, 'ds-');
    }

    /**
     * Check if DVR is Dahua variant
     */
    private static function isDahua(string $dvrName): bool
    {
        $dahuaVariants = [
            'dahua',
            'dahuva',
            'dh',
            'dahua_nvr',
            'dh-nvr'
        ];

        return in_array($dvrName, $dahuaVariants) || 
               str_contains($dvrName, 'dahuva') || 
               str_contains($dvrName, 'dahua') || 
               str_contains($dvrName, 'dh-');
    }

    /**
     * Get supported DVR types
     */
    public static function getSupportedTypes(): array
    {
        return [
            'cpplus' => 'CP Plus DVR Systems',
            // 'hikvision' => 'Hikvision DVR/NVR Systems',
            // 'dahua' => 'Dahua DVR/NVR Systems'
        ];
    }

    /**
     * Check if DVR type is supported
     */
    public static function isSupported(string $dvrName): bool
    {
        try {
            self::create($dvrName);
            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }
}