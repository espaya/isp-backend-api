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

            // Time left calculation (FIXED: check if payment and package exist)
            $timeLeftHours = 0;

            if ($payment && $package && $payment->created_at) {
                $expiry = $payment->created_at->addDays($package->validity);
                $timeLeftHours = max(now()->diffInHours($expiry, false), 0);
            }

            // MikroTik Active Status (FIXED: handle gracefully if MikrotikService fails)
            $isConnected = false;
            $activeSession = null;

            $device = Device::first();

            if ($device && $request->ip()) {
                try {
                    $mikrotik = new MikrotikService($device);
                    $activeSession = $mikrotik->getHotspotActiveUserStatus($request->ip());
                    $isConnected = !is_null($activeSession);
                } catch (\Exception $e) {
                    Log::warning('Mikrotik connection failed: ' . $e->getMessage());
                    // Don't fail the whole request, just set isConnected to false
                    $isConnected = false;
                    $activeSession = null;
                }
            }

            // Usage Data - Daily (FIXED: handle empty results)
            $dailyUsage = collect();
            try {
                $dailyUsage = UserUsage::where('user_id', $user->id)
                    ->whereBetween('usage_date', [
                        now()->subDays(6)->toDateString(),
                        now()->toDateString()
                    ])
                    ->orderBy('usage_date')
                    ->pluck('bytes_used')
                    ->map(fn($bytes) => round($bytes / 1024 / 1024, 2))
                    ->values();
            } catch (\Exception $e) {
                Log::warning('Failed to fetch daily usage: ' . $e->getMessage());
                $dailyUsage = collect();
            }

            // Usage Data - Weekly (FIXED: handle empty results)
            $weeklyUsage = collect();
            try {
                $weeklyUsage = UserUsage::where('user_id', $user->id)
                    ->whereBetween('usage_date', [
                        now()->subWeeks(6)->startOfWeek()->toDateString(),
                        now()->endOfWeek()->toDateString()
                    ])
                    ->get()
                    ->groupBy(function ($item) {
                        return Carbon::parse($item->usage_date)->weekOfYear;
                    })
                    ->map(function ($week) {
                        return round($week->sum('bytes_used') / 1024 / 1024 / 1024, 2);
                    })
                    ->values();
            } catch (\Exception $e) {
                Log::warning('Failed to fetch weekly usage: ' . $e->getMessage());
                $weeklyUsage = collect();
            }

            // Usage Data - Monthly (FIXED: handle empty results)
            $monthlyUsage = collect();
            try {
                $monthlyUsage = UserUsage::where('user_id', $user->id)
                    ->whereYear('usage_date', now()->year)
                    ->get()
                    ->groupBy(function ($item) {
                        return Carbon::parse($item->usage_date)->month;
                    })
                    ->map(function ($month) {
                        return round($month->sum('bytes_used') / 1024 / 1024 / 1024, 2);
                    })
                    ->values();
            } catch (\Exception $e) {
                Log::warning('Failed to fetch monthly usage: ' . $e->getMessage());
                $monthlyUsage = collect();
            }

            return response()->json([
                'status' => $isConnected ? 'Connected' : 'Disconnected',
                'timeLeft' => $timeLeftHours,
                'speed' => $package?->speed ?? 0,
                'package' => $package?->name ?? 'No Active Package',
                'price' => isset($package->price) ? $package->price / 1000 : 0.00,
                'usage' => [
                    'daily' => $dailyUsage->pad(7, 0)->values(),
                    'weekly' => $weeklyUsage->pad(7, 0)->values(),
                    'monthly' => $monthlyUsage->pad(12, 0)->values(),
                ],
                'session' => $activeSession
            ]);
        } catch (\Throwable $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            Log::error('Dashboard trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Failed to load dashboard',
                'error' => config('app.debug') ? $e->getMessage() : null
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
