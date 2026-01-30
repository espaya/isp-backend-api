<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {

            // RouterOS API connection
            $table->string('api_user')->after('ip');
            $table->string('api_password')->after('api_user'); // encrypt later
            $table->unsignedSmallInteger('api_port')->default(8728)->after('api_password');

            // Geo location
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Coverage & signal
            $table->unsignedInteger('coverage_radius_km')->default(1)->after('longitude');
            $table->integer('signal_strength')->nullable()->after('coverage_radius_km');

            // Capacity management
            $table->unsignedInteger('max_clients')->default(100)->after('signal_strength');
            $table->unsignedInteger('current_clients')->default(0)->after('max_clients');

            // Device role & status
            $table->enum('device_type', ['hotspot', 'pppoe', 'backhaul'])
                ->default('hotspot')
                ->after('current_clients');

            $table->enum('status', ['online', 'offline', 'maintenance'])
                ->default('offline')
                ->after('device_type');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'api_user',
                'api_password',
                'api_port',
                'latitude',
                'longitude',
                'coverage_radius_km',
                'signal_strength',
                'max_clients',
                'current_clients',
                'device_type',
                'status',
            ]);
        });
    }
};
