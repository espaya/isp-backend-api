<?php

namespace App\Services;

use App\Models\Device;
use Exception;

class DeviceSelectorService
{
    public function selectBestDevice(float $lat, float $lng): Device
    {
        $devices = Device::where('status', 'online')
            ->whereColumn('current_clients', '<', 'max_clients')
            ->get();

        if ($devices->isEmpty()) {
            throw new Exception('No available MikroTik device');
        }

        // Filter by coverage radius
        $devices = $devices->filter(function ($device) use ($lat, $lng) {
            if (!$device->latitude || !$device->longitude) {
                return false;
            }

            return $this->distanceKm(
                $lat,
                $lng,
                $device->latitude,
                $device->longitude
            ) <= $device->coverage_radius_km;
        });

        if ($devices->isEmpty()) {
            throw new Exception('No device covers this location');
        }

        // Sort by signal strength, then load
        return $devices->sortBy([
            fn($d) => -$d->signal_strength,
            fn($d) => $d->current_clients,
        ])->first();
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
