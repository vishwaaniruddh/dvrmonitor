<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DvrMonitoringHistory extends Model
{
    use HasFactory;

    protected $table = 'dvr_monitoring_history';

    protected $fillable = [
        'dvr_id',
        'check_type',
        'status',
        'ping_response_time',
        'ping_success',
        'api_login_success',
        'dvr_device_time',
        'dvr_details',
        'checked_at',
        'error_message',
        'raw_response'
    ];

    protected $casts = [
        'ping_success' => 'boolean',
        'api_login_success' => 'boolean',
        'dvr_device_time' => 'datetime',
        'checked_at' => 'datetime',
        'dvr_details' => 'array',
        'raw_response' => 'array'
    ];

    /**
     * Get the DVR that owns this monitoring record
     */
    public function dvr(): BelongsTo
    {
        return $this->belongsTo(Dvr::class);
    }

    /**
     * Scope for recent records
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('checked_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for specific check type
     */
    public function scopeCheckType($query, $type)
    {
        return $query->where('check_type', $type);
    }

    /**
     * Scope for successful checks
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'online');
    }

    /**
     * Scope for failed checks
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['offline', 'api_error', 'timeout']);
    }

    /**
     * Get formatted response time
     */
    public function getFormattedResponseTimeAttribute(): string
    {
        return $this->ping_response_time ? $this->ping_response_time . 'ms' : 'N/A';
    }

    /**
     * Get time difference from system time
     */
    public function getDeviceTimeOffsetAttribute(): ?int
    {
        if (!$this->dvr_device_time) {
            return null;
        }

        $systemTime = $this->checked_at;
        $deviceTime = $this->dvr_device_time;
        
        return $deviceTime->diffInMinutes($systemTime, false);
    }
}