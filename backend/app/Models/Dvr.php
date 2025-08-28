<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dvr extends Model
{
    protected $fillable = [
        'dvr_name',
        'ip',
        'port',
        'username',
        'password',
        'is_active',
        'status',
        'last_ping_at',
        'ping_response_time',
        'last_api_call_at',
        'last_api_response',
        'api_accessible',
        'consecutive_failures',
        'device_model',
        'firmware_version',
        'channel_count',
        'location',
        'group_name'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_accessible' => 'boolean',
        'last_ping_at' => 'datetime',
        'last_api_call_at' => 'datetime',
        'last_api_response' => 'array',
        'dvr_device_time' => 'string', // Force this to be a string, not Carbon
    ];

    // Hide sensitive information when serializing
    protected $hidden = [
        'password',
    ];

    // Relationships
    public function monitoringLogs()
    {
        return $this->hasMany(DvrMonitoringLog::class);
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
