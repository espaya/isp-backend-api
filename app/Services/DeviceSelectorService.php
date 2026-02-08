<?php

namespace App\Services;

use App\Models\Device;
use Exception;

class DeviceSelectorService
{
    public function selectBestDevice(?float $lat = null, ?float $lng = null): Device
    {
        // Fetch all online devices with available slots
        $devices = Device::where('status', 'online')
            ->whereColumn('current_clients', '<', 'max_clients')
            ->orderByRaw("(POWER(latitude - ?, 2) + POWER(longitude - ?, 2)) ASC", [$lat ?? 0, $lng ?? 0])
            ->orderBy('current_clients')
            ->get();

        if ($devices->isEmpty()) {
            throw new Exception('No available Device');
        }

        // Filter by coverage radius only if coverage_radius_km is set
        $devices = $devices->filter(function ($device) use ($lat, $lng) {
            if (!$lat || !$lng) {
                // No user location provided, keep all devices
                return true;
            }

            if (!$device->latitude || !$device->longitude || !$device->coverage_radius_km) {
                // If device has no location or coverage radius, include it
                return true;
            }

            // Include device if user is within coverage radius
            return $this->distanceKm(
                $lat,
                $lng,
                $device->latitude,
                $device->longitude
            ) <= $device->coverage_radius_km;
        });

        if ($devices->isEmpty()) {
            // Fallback: select the nearest device ignoring coverage radius
            $devices = Device::where('status', 'online')
                ->whereColumn('current_clients', '<', 'max_clients')
                ->orderByRaw("(POWER(latitude - ?, 2) + POWER(longitude - ?, 2)) ASC", [$lat ?? 0, $lng ?? 0])
                ->orderBy('current_clients')
                ->get();
        }

        // Sort by signal strength (nulls as 0) and current load
        return $devices->sortBy([
            fn($d) => - ($d->signal_strength ?? 0),
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
