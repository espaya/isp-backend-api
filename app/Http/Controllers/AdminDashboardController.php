<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Packages;
use App\Models\Payment;
use App\Models\Device;
use App\Services\MikrotikService;


class AdminDashboardController extends Controller
{
    public function latestUsers()
    {
        try {

            $latestUsers = User::where('role', 'user')->orderBy('created_at', 'desc')->limit(5)->get();

            if ($latestUsers->isEmpty()) {
                return response()->json(['message' => 'No latest users found'], 404);
            }

            return response()->json($latestUsers, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function revenueOverview()
    {
        $monthlyRevenue = Payment::selectRaw("
            MONTH(created_at) as month,
            SUM(amount) as revenue
        ")
            ->where('status', 'success')
            ->whereYear('created_at', now()->year)
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get();

        $months = collect(range(1, 12))->map(function ($month) use ($monthlyRevenue) {
            $data = $monthlyRevenue->firstWhere('month', $month);

            return [
                'month' => Carbon::create()->month($month)->format('M'),
                'revenue' => $data ? (float) $data->revenue / 100 : 0,
            ];
        });

        return response()->json($months);
    }

    public function topCards()
    {
        try {

            $totalUsers = User::count();

            $activePackages = Packages::where('isActive', true)->count();

            $totalPayments = Payment::count();

            $totalRevenue = Payment::where('status', 'success')
                ->sum('amount');

            return response()->json([
                'totalUsers' => $totalUsers,
                'activePackages' => $activePackages,
                'totalPayments' => $totalPayments,
                'totalRevenue' => $totalRevenue / 100, // convert to cedis
            ], 200);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());

            return response()->json([
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }

    public function devicePerformance()
    {
        try {

            $devices = Device::where('status', 'online')->get();

            $totalUsers = 0;
            $totalCpu = 0;
            $totalUptime = 0;
            $totalRx = 0;
            $totalTx = 0;

            foreach ($devices as $device) {

                $mikrotik = new MikrotikService($device);

                // 1️⃣ System Resource
                $resource = $mikrotik->query('/system/resource/print');

                $cpu = $resource[0]['cpu-load'] ?? 0;
                $uptime = $resource[0]['uptime'] ?? 0;

                // 2️⃣ Active Hotspot Users
                $activeUsers = $mikrotik->query('/ip/hotspot/active/print');
                $userCount = count($activeUsers);

                // 3️⃣ Traffic (Interface ether1 example)
                $interfaces = $mikrotik->query('/interface/print');
                $rx = 0;
                $tx = 0;

                foreach ($interfaces as $iface) {
                    $rx += $iface['rx-byte'] ?? 0;
                    $tx += $iface['tx-byte'] ?? 0;
                }

                $totalUsers += $userCount;
                $totalCpu += $cpu;
                $totalUptime += $uptime;
                $totalRx += $rx;
                $totalTx += $tx;
            }

            $deviceCount = max($devices->count(), 1);

            return response()->json([
                'connectedUsers' => $totalUsers,
                'avgCpuLoad' => round($totalCpu / $deviceCount, 2),
                'totalTrafficRxMB' => round($totalRx / 1024 / 1024, 2),
                'totalTrafficTxMB' => round($totalTx / 1024 / 1024, 2),
            ]);
        } catch (\Exception $e) {

            Log::error("Device performance error: " . $e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch device performance'
            ], 500);
        }
    }

    public function systemStatus()
    {
        try {

            // 🖥 Server Uptime
            $uptimeRaw = shell_exec("cat /proc/uptime");

            if (!$uptimeRaw) {
                $serverUptimeHours = 0;
            } else {
                $uptimeParts = explode(" ", trim($uptimeRaw));
                $uptimeSeconds = isset($uptimeParts[0])
                    ? (float) $uptimeParts[0]
                    : 0;

                $serverUptimeHours = round($uptimeSeconds / 3600, 2);
            }

            // 🌐 Active Sessions
            $devices = Device::where('status', 'online')->get();
            $totalActiveSessions = 0;

            foreach ($devices as $device) {
                $mikrotik = new MikrotikService($device);
                $activeUsers = $mikrotik->query('/ip/hotspot/active/print');
                $totalActiveSessions += is_array($activeUsers)
                    ? count($activeUsers)
                    : 0;
            }

            return response()->json([
                'serverUptimeHours' => $serverUptimeHours,
                'activeSessions' => $totalActiveSessions,
            ]);
        } catch (\Throwable $e) {

            Log::error("System status error: " . $e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch system status'
            ], 500);
        }
    }
}
