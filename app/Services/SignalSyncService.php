<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class SignalSyncService
{
    public function sync(Device $device)
    {
        try {
            $signal = @snmpget(
                $device->ip,
                $device->snmpCommunity,
                '1.3.6.1.4.1.14988.1.1.1.3.1.4.1'
            );

            if (!$signal) {
                Log::warning("SNMP signal fetch failed for device {$device->id} ({$device->ip})");
                return;
            }

            $device->update([
                'signal_strength' => (int) filter_var($signal, FILTER_SANITIZE_NUMBER_INT),
            ]);
        } catch (\Throwable $ex) {
            Log::error("SNMP error for device {$device->id}: " . $ex->getMessage());
        }
    }
}
