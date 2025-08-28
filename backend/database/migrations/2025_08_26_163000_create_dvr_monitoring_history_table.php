<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dvr_monitoring_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dvr_id');
            $table->enum('check_type', ['ping', 'api_details', 'full_check'])->default('ping');
            $table->enum('status', ['online', 'offline', 'api_error', 'timeout'])->default('offline');
            
            // Ping related fields
            $table->integer('ping_response_time')->nullable(); // milliseconds
            $table->boolean('ping_success')->default(false);
            
            // API related fields
            $table->boolean('api_login_success')->default(false);
            $table->timestamp('dvr_device_time')->nullable(); // DVR's internal time
            $table->json('dvr_details')->nullable(); // Camera, storage, recording info
            
            // Monitoring metadata
            $table->timestamp('checked_at')->default(now());
            $table->string('error_message')->nullable();
            $table->json('raw_response')->nullable(); // Store raw API response for debugging
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['dvr_id', 'checked_at']);
            $table->index(['dvr_id', 'status']);
            $table->index(['check_type', 'checked_at']);
            
            // Foreign key
            $table->foreign('dvr_id')->references('id')->on('dvrs')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dvr_monitoring_history');
    }
};