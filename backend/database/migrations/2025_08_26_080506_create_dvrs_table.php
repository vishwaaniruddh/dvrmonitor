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
        Schema::create('dvrs', function (Blueprint $table) {
            $table->id();
            $table->string('dvr_name'); // hikvision, cpplus, cpplus_orange, prama, dahua, etc
            $table->string('ip');
            $table->string('username');
            $table->string('password');
            $table->string('port');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dvrs');
    }
};
