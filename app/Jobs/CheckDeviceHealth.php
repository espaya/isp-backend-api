<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\MikrotikService;

class CheckDeviceHealth
{
    public function handle()
    {
        Device::all()->each(function (Device $device) {
            try {
                new MikrotikService($device );
                $device->update(['status' => 'online']);
            } catch (\Throwable $e) {
                $device->update(['status' => 'offline']);
            }
        });
    }
}
