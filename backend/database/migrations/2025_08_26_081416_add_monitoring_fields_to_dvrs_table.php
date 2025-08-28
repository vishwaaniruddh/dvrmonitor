<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dvrs', function (Blueprint $table) {
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->timestamp('last_ping_at')->nullable();
            $table->integer('ping_response_time')->nullable(); // in milliseconds
            $table->timestamp('last_api_call_at')->nullable();
            $table->json('last_api_response')->nullable();
            $table->boolean('api_accessible')->default(false);
            $table->integer('consecutive_failures')->default(0);
            $table->string('device_model')->nullable();
            $table->string('firmware_version')->nullable();
            $table->integer('channel_count')->nullable();
            $table->string('location')->nullable();
            $table->string('group_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dvrs', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
