<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\MikrotikService;

class CheckDeviceHealth
{
    public function handle()
    { 
        Device::all()->each(function ($device) {
            try {
                $mikrotik = new MikrotikService(
                    host: $device->host,
                    username: $device->username,
                    password: $device->password,
                    port: $device->api_port ?? 8728
                );

                // Attempt a simple query to check health
                $mikrotik->getUserDataUsage('testuser'); // or just connect

                $device->update(['status' => 'online']);
            } catch (\Exception $e) {
                $device->update(['status' => 'offline']);
            }
        });
    } 
}
