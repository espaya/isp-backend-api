<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    public function index()
    {
        try {
            $devices = Device::orderBy('name', 'ASC')->paginate(10);

            if ($devices->isEmpty()) {
                return response()->json(['message' => 'No device(s) found'], 404);
            }
            return response()->json($devices, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'unique:devices,name', 'max:255'],
            'description' => ['nullable', 'string'],
            'ip' => ['required', 'ip', 'unique:devices,ip'],
            'location' => ['required', 'string', 'max:255'],
            'monitorEnabled' => ['nullable', 'boolean'],
            'snmpCommunity' => ['required', 'string'],
            'model' => ['required', 'string', 'max:255'],
            'api_password' => ['required', 'string'],
            'api_user' => ['required', 'string']
        ], [
            'name.required' => 'Device name is required.',
            'name.unique' => 'A device with this name already exists.',
            'name.max' => 'Device name must not exceed 255 characters.',

            'ip.required' => 'IP address is required.',
            'ip.ip' => 'Please provide a valid IP address.',
            'ip.unique' => 'This IP address is already assigned to another device.',

            'location.required' => 'Device location is required.',
            'location.max' => 'Location must not exceed 255 characters.',

            'monitorEnabled.boolean' => 'Monitor enabled must be true or false.',

            'snmpCommunity.required' => 'SNMP community is required.',
            'snmpCommunity.string' => 'SNMP community must be a valid string.',

            'model.required' => 'Device model is required.',
            'model.max' => 'Device model must not exceed 255 characters.',
            'api_password.required' => 'This field is required',
            'api_password.string' => 'Invalid inputs',
            'api_user.required' => 'This field is required',
            'api_user.string' => 'Invalid inputs'
        ]);

        DB::beginTransaction();

        try {
            Device::create([
                'name' => $request->name,
                'description' => $request->description ?? "",
                'ip' => $request->ip,
                'location' => $request->location,
                'monitorEnabled' => $request->monitorEnabled,
                'snmpCommunity' => $request->snmpCommunity,
                'model' => $request->model,
                'api_user' => $request->api_url,
                'api_password' => $request->api_password,
            ]);

            DB::commit();

            return response()->json(['message' => 'Add device added successfully'], 200);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function update(Request $request, $id) {}

    public function destroy($id) {}

    public function stats($id)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        // Check if device is online (ping)
        if (!$this->isOnline($device->ip)) {
            return response()->json([
                'status' => 'offline',
                'message' => 'Device is offline',
            ]);
        }

        // Check if SNMP extension is loaded
        if (!function_exists('snmp2_get')) {
            return response()->json([
                'status' => 'online',
                'message' => 'SNMP PHP extension not enabled',
            ], 500);
        }

        // Helper to safely fetch SNMP values
        $snmpFetch = function ($oid) use ($device) {
            try {
                $val = @snmp2_get($device->ip, $device->snmpCommunity, $oid);
                if ($val === false) return 0;
                // Remove quotes and text if present
                return intval(preg_replace('/[^0-9]/', '', $val));
            } catch (\Exception $e) {
                return 0;
            }
        };

        // SNMP OIDs
        $cpuOid = '1.3.6.1.4.1.14988.1.1.3.10.0';
        $memOid = '1.3.6.1.4.1.14988.1.1.3.11.0';
        $clientsOid = '1.3.6.1.4.1.14988.1.1.3.12.0';
        $bwUpOid = '1.3.6.1.2.1.2.2.1.16.2';
        $bwDownOid = '1.3.6.1.2.1.2.2.1.10.2';
        $uptimeOid = '1.3.6.1.2.1.1.3.0';

        $cpu = $snmpFetch($cpuOid);
        $memory = $snmpFetch($memOid);
        $clients = $snmpFetch($clientsOid);
        $bandwidthUp = $snmpFetch($bwUpOid);
        $bandwidthDown = $snmpFetch($bwDownOid);

        // Fetch uptime separately for better formatting
        try {
            $uptimeRaw = @snmp2_get($device->ip, $device->snmpCommunity, $uptimeOid);
            if ($uptimeRaw !== false) {
                // Extract Timeticks number: Timeticks: (835800) 2:19:18.00
                if (preg_match('/\((\d+)\)/', $uptimeRaw, $matches)) {
                    $ticks = intval($matches[1]);
                    $seconds = intval($ticks / 100); // Timeticks = 1/100 sec
                    $hours = floor($seconds / 3600);
                    $minutes = floor(($seconds % 3600) / 60);
                    $secs = $seconds % 60;
                    $uptime = sprintf("%d:%02d:%02d", $hours, $minutes, $secs);
                } else {
                    $uptime = '-';
                }
            } else {
                $uptime = '-';
            }
        } catch (\Exception $e) {
            $uptime = '-';
        }

        return response()->json([
            'status' => 'online',
            'cpu' => $cpu,
            'memory' => $memory,
            'clients' => $clients,
            'bandwidth' => [
                'upload' => $bandwidthUp,
                'download' => $bandwidthDown,
            ],
            'uptime' => $uptime,
        ]);
    }

    private function isOnline($ip)
    {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $status = null;
            exec("ping -n 1 -w 1000 $ip", $output, $status);
            return $status === 0;
        } else {
            $status = null;
            exec("ping -c 1 -W 1 $ip", $output, $status);
            return $status === 0;
        }
    }

    public function cardStats()
    {
        // Get all devices
        $devices = Device::all();

        $total = $devices->count();
        $online = 0;
        $offline = 0;
        $totalClients = 0;
        $totalBandwidth = 0; // in Mbps
        $uptimeSecondsSum = 0;
        $onlineDevicesCount = 0;

        foreach ($devices as $device) {
            // Make sure $device is a single Device model
            if (!$device instanceof Device) {
                continue;
            }

            // Check if device is online
            $isOnline = $this->isOnline($device->ip);

            if ($isOnline) {
                $online++;

                // Fetch live stats for this device
                $stats = $this->getDeviceStats($device);

                $clients = $stats['clients'] ?? 0;
                $bandwidthUp = $stats['bandwidth']['upload'] ?? 0;
                $bandwidthDown = $stats['bandwidth']['download'] ?? 0;
                $uptime = $stats['uptime'] ?? "0:00:00";

                $totalClients += $clients;

                // Convert bandwidth from bytes → Mbps
                $totalBandwidth += (($bandwidthUp + $bandwidthDown) * 8) / 1_000_000;

                // Convert uptime string "HH:MM:SS" → seconds
                if (preg_match('/(\d+):(\d+):(\d+)/', $uptime, $matches)) {
                    $uptimeSeconds = intval($matches[1]) * 3600
                        + intval($matches[2]) * 60
                        + intval($matches[3]);
                } else {
                    $uptimeSeconds = 0;
                }

                $uptimeSecondsSum += $uptimeSeconds;
                $onlineDevicesCount++;
            } else {
                $offline++;
            }
        }

        // Average uptime in days
        $avgUptime = $onlineDevicesCount > 0
            ? round($uptimeSecondsSum / $onlineDevicesCount / 86400, 1)
            : 0;

        return response()->json([
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'totalClients' => $totalClients,
            'totalBandwidth' => round($totalBandwidth, 2), // Mbps
            'avgUptime' => $avgUptime,
        ]);
    }

    /**
     * Fetch live stats for a single device
     */
    private function getDeviceStats(Device $device)
    {
        try {
            $upload = intval(@snmpget($device->ip, $device->snmpCommunity, '1.3.6.1.2.1.2.2.1.16.2') ?: 0);
            $download = intval(@snmpget($device->ip, $device->snmpCommunity, '1.3.6.1.2.1.2.2.1.10.2') ?: 0);

            return [
                'cpu' => intval(@snmpget($device->ip, $device->snmpCommunity, '1.3.6.1.4.1.14988.1.1.3.10.0') ?: 0),
                'memory' => intval(@snmpget($device->ip, $device->snmpCommunity, '1.3.6.1.4.1.14988.1.1.3.11.0') ?: 0),
                'clients' => intval(@snmpget($device->ip, $device->snmpCommunity, '1.3.6.1.4.1.14988.1.1.3.12.0') ?: 0),
                'bandwidth' => [
                    'upload' => $upload,
                    'download' => $download,
                ],
                'uptime' => $this->getUptime($device),
            ];
        } catch (\Exception $e) {
            return [
                'cpu' => 0,
                'memory' => 0,
                'clients' => 0,
                'bandwidth' => ['upload' => 0, 'download' => 0],
                'uptime' => "0:00:00",
            ];
        }
    }

    /**
     * Convert SNMP uptime Timeticks → "HH:MM:SS"
     */
    private function getUptime(Device $device)
    {
        try {
            $uptime = @snmpget($device->ip, $device->snmpCommunity, '1.3.6.1.2.1.1.3.0');
            if ($uptime && preg_match('/\((\d+)\)/', $uptime, $matches)) {
                $seconds = intval($matches[1]) / 100; // Timeticks → seconds
                return gmdate("H:i:s", $seconds);
            }
            return "0:00:00";
        } catch (\Exception $e) {
            return "0:00:00";
        }
    }
}
