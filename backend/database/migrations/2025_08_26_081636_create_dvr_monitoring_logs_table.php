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
        Schema::create('dvr_monitoring_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dvr_id')->constrained()->onDelete('cascade');
            $table->enum('check_type', ['ping', 'api_call', 'status_update']);
            $table->enum('result', ['success', 'failure', 'timeout']);
            $table->integer('response_time')->nullable(); // in milliseconds
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['dvr_id', 'check_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dvr_monitoring_logs');
    }
};
