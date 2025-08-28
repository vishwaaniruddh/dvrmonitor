<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DvrMonitoringLog extends Model
{
    protected $fillable = [
        'dvr_id',
        'check_type',
        'result',
        'response_time',
        'response_data',
        'error_message'
    ];

    protected $casts = [
        'response_data' => 'array',
    ];

    // Relationships
    public function dvr()
    {
        return $this->belongsTo(Dvr::class);
    }
}
