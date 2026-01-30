<?php

namespace App\Services;

use App\Models\Device;

class SignalSyncService
{
    function sync(Device $device)
    {
        $signal = snmpget(
            $device->ip,
            $device->snmpCommunity,
            '1.3.6.1.4.1.14988.1.1.1.3.1.4.1'
        );


        if ($signal) {
            $device->update([
                'signal_strength' => (int) filter_var($signal, FILTER_SANITIZE_NUMBER_INT),
            ]);
        }
    }
}
