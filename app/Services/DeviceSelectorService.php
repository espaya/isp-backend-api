<?php

namespace App\Services;

use App\Models\Device;
use Exception;

class DeviceSelectorService
{
    public function selectBestDevice(?float $lat = null, ?float $lng = null): Device
    {
        $device = Device::where('status', 'online')
            ->whereColumn('current_clients', '<', 'max_clients')
            ->first();

        if (!$device) {

            // fallback: pick ANY device
            $device = Device::first();

            if (!$device) {
                throw new Exception('No MikroTik device configured');
            }
        }

        return $device;
    }

    private function distanceKm($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLon / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
