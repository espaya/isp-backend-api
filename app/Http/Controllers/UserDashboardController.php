<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;
use App\Models\Device;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;
use App\Models\UserUsage;
use Carbon\Carbon;


class UserDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Get latest active payment/package
            $payment = Payment::where('user_id', $user->id)
                ->where('status', 'success')
                ->latest()
                ->first();

            $package = $payment?->package;

            // Time left calculation
            $timeLeftHours = 0;

            if ($payment && $package) {
                $expiry = $payment->created_at->addDays($package->validity);
                $timeLeftHours = max(now()->diffInHours($expiry, false), 0);
            }

            // MikroTik Active Status
            $isConnected = false;
            $activeSession = null;

            $device = Device::first();

            if ($device && $request->ip()) {

                $mikrotik = new MikrotikService($device);

                $activeSession = $mikrotik->getHotspotActiveUserStatus(
                    $request->ip()
                );

                if ($activeSession) {
                    $isConnected = true;
                }
            }

            // Usage Data Example (replace with real tracking table)
            // $dailyUsage = [50, 120, 200, 180, 220, 160, 100];
            $dailyUsage = UserUsage::where('user_id', $user->id)
                ->whereBetween('usage_date', [
                    now()->subDays(6)->toDateString(),
                    now()->toDateString()
                ])
                ->orderBy('usage_date')
                ->pluck('bytes_used')
                ->map(fn($bytes) => round($bytes / 1024 / 1024, 2)) // convert to MB
                ->values();

            // $weeklyUsage = [5, 6, 4, 7, 8, 6, 5];
            $weeklyUsage = UserUsage::where('user_id', $user->id)
                ->whereBetween('usage_date', [
                    now()->subWeeks(6)->startOfWeek(),
                    now()->endOfWeek()
                ])
                ->get()
                ->groupBy(function ($item) {
                    return Carbon::parse($item->usage_date)->weekOfYear;
                })
                ->map(function ($week) {
                    return round($week->sum('bytes_used') / 1024 / 1024 / 1024, 2); // GB
                })
                ->values();

            // $monthlyUsage = [10, 15, 12, 18, 20, 25, 30, 28, 22, 18, 15, 20];
            $monthlyUsage = UserUsage::where('user_id', $user->id)
                ->whereYear('usage_date', now()->year)
                ->get()
                ->groupBy(function ($item) {
                    return Carbon::parse($item->usage_date)->month;
                })
                ->map(function ($month) {
                    return round($month->sum('bytes_used') / 1024 / 1024 / 1024, 2); // GB
                })
                ->values();

            return response()->json([
                'status' => $isConnected ? 'Connected' : 'Disconnected',
                'timeLeft' => $timeLeftHours,
                'speed' => $package?->speed ?? 0,
                'package' => $package?->name ?? 'No Active Package',
                'price' => ($package?->price) / 1000 ?? 0.00,

                'usage' => [
                    'daily' => $dailyUsage->pad(7, 0),
                    'weekly' => $weeklyUsage->pad(7, 0),
                    'monthly' => $monthlyUsage->pad(12, 0),
                ],

                'session' => $activeSession // 🔥 optional: send real session info
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => 'Failed to load dashboard'
            ], 500);
        }
    }

    public function connectedDevices()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $device = Device::first();

        if (!$device) {
            return response()->json([]);
        }

        $mikrotik = new MikrotikService($device);

        $sessions = $mikrotik->getUserActiveSessions($user->email);

        $devices = collect($sessions)->map(function ($session) {
            return [
                'ip' => $session['address'] ?? null,
                'mac' => $session['mac-address'] ?? null,
                'uptime' => $session['uptime'] ?? null,
                'bytes_in' => (int) ($session['bytes-in'] ?? 0),
                'bytes_out' => (int) ($session['bytes-out'] ?? 0),
            ];
        });

        return response()->json($devices);
    }

    public function disconnectDevices()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $device = Device::first();

        if ($device) {
            $mikrotik = new MikrotikService($device);
            $mikrotik->disconnectUserSessions($user->email);
        }

        return response()->json(['message' => 'All devices disconnected']);
    }
}
