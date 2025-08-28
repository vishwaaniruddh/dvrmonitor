<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dvrs', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('dvrs', 'camera_count')) {
                $table->integer('camera_count')->nullable()->after('password');
            }
            if (!Schema::hasColumn('dvrs', 'storage_capacity_gb')) {
                $table->decimal('storage_capacity_gb', 10, 2)->nullable()->after('camera_count');
            }
            if (!Schema::hasColumn('dvrs', 'last_detailed_check')) {
                $table->timestamp('last_detailed_check')->nullable()->after('storage_capacity_gb');
            }
            if (!Schema::hasColumn('dvrs', 'api_supported')) {
                $table->boolean('api_supported')->default(false)->after('last_detailed_check');
            }
            if (!Schema::hasColumn('dvrs', 'last_api_response')) {
                $table->json('last_api_response')->nullable()->after('api_supported');
            }
        });
    }

    public function down()
    {
        Schema::table('dvrs', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'camera_count',
                'storage_capacity_gb',
                'last_detailed_check',
                'api_supported',
                'last_api_response'
            ]);
        });
    }
};