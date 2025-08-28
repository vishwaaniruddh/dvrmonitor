<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dvrs', function (Blueprint $table) {
            // Change dvr_device_time from timestamp to datetime to avoid automatic Carbon conversion
            $table->datetime('dvr_device_time')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('dvrs', function (Blueprint $table) {
            // Revert back to timestamp
            $table->timestamp('dvr_device_time')->nullable()->change();
        });
    }
};