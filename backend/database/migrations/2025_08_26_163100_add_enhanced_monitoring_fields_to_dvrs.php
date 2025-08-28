<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dvrs', function (Blueprint $table) {
            // Enhanced monitoring fields
            if (!Schema::hasColumn('dvrs', 'dvr_device_time')) {
                $table->timestamp('dvr_device_time')->nullable()->after('last_ping_at');
            }
            if (!Schema::hasColumn('dvrs', 'api_login_status')) {
                $table->enum('api_login_status', ['success', 'failed', 'not_tested'])->default('not_tested')->after('dvr_device_time');
            }
            if (!Schema::hasColumn('dvrs', 'last_api_check_at')) {
                $table->timestamp('last_api_check_at')->nullable()->after('api_login_status');
            }
            if (!Schema::hasColumn('dvrs', 'device_time_offset_minutes')) {
                $table->integer('device_time_offset_minutes')->nullable()->after('last_api_check_at'); // Difference between system time and DVR time
            }
            if (!Schema::hasColumn('dvrs', 'current_camera_count')) {
                $table->integer('current_camera_count')->nullable()->after('camera_count');
            }
            if (!Schema::hasColumn('dvrs', 'working_camera_count')) {
                $table->integer('working_camera_count')->nullable()->after('current_camera_count');
            }
            if (!Schema::hasColumn('dvrs', 'storage_usage_percentage')) {
                $table->decimal('storage_usage_percentage', 5, 2)->nullable()->after('storage_capacity_gb');
            }
            if (!Schema::hasColumn('dvrs', 'recording_status')) {
                $table->enum('recording_status', ['active', 'inactive', 'unknown'])->default('unknown')->after('storage_usage_percentage');
            }
        });
    }

    public function down()
    {
        Schema::table('dvrs', function (Blueprint $table) {
            $table->dropColumn([
                'dvr_device_time',
                'api_login_status',
                'last_api_check_at',
                'device_time_offset_minutes',
                'current_camera_count',
                'working_camera_count',
                'storage_usage_percentage',
                'recording_status'
            ]);
        });
    }
};