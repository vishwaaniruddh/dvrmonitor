<?php

namespace App\Contracts;

interface DvrApiInterface
{
    /**
     * Login to DVR and get session token
     */
    public function login(string $ip, int $port, string $username, string $password): array;

    /**
     * Get DVR system time
     */
    public function getDvrTime(string $ip, int $port, string $sessionToken): array;

    /**
     * Get camera count and status
     */
    public function getCameraStatus(string $ip, int $port, string $sessionToken): array;

    /**
     * Get hard disk/storage status
     */
    public function getStorageStatus(string $ip, int $port, string $sessionToken): array;

    /**
     * Get recording status
     */
    public function getRecordingStatus(string $ip, int $port, string $sessionToken): array;

    /**
     * Logout from DVR
     */
    public function logout(string $ip, int $port, string $sessionToken): array;

    /**
     * Get all DVR details in one call
     */
    public function getAllDetails(string $ip, int $port, string $username, string $password): array;
}