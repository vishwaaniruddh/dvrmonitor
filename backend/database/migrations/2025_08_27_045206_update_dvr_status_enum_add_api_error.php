<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the status enum to include 'api_error' and 'timeout'
        DB::statement("ALTER TABLE dvrs MODIFY COLUMN status ENUM('online', 'offline', 'unknown', 'api_error', 'timeout') DEFAULT 'unknown'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE dvrs MODIFY COLUMN status ENUM('online', 'offline', 'unknown') DEFAULT 'unknown'");
    }
};