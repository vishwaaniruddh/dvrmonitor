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
        Schema::table('dvr_monitoring_logs', function (Blueprint $table) {
            // Change check_type from enum to varchar to support more values
            $table->string('check_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dvr_monitoring_logs', function (Blueprint $table) {
            // Revert back to original enum
            $table->enum('check_type', ['ping', 'api_call', 'status_update'])->change();
        });
    }
};