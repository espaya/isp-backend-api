<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class HotspotController extends Controller
{
    public function status(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'mac' => 'nullable|string'
        ]);

        $device = Device::first(); // Assuming a single device setup for simplicity
        $mikrotik = new MikrotikService($device);

        $status = $mikrotik->getHotspotActiveUserStatus($request->ip);

        if (!$status) {
            return response()->json([
                'message' => 'User not active on hotspot'
            ], 404);
        }

        return response()->json([
            'status' => $status
        ]);
    }
}
