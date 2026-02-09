<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Packages;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\DeviceSelectorService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MikrotikService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;


class SubscriptionController extends Controller
{
    // List all available packages
    public function index()
    {
        try {
            $packages = Packages::where('isActive', 1)->orderBy('name', 'ASC')->paginate(10);

            if ($packages->isEmpty()) {
                return response()->json(['message' => 'No packages found'], 404);
            }
            return response()->json($packages);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    // List all subscriptions for the authenticated user
    public function subscriptions()
    {
        try {
            $user = Auth::user();

            $subscriptions = Subscription::with(['package', 'payment', 'mikrotikDevice'])
                ->where('user_id', $user->id)
                ->orderBy('starts_at', 'desc')
                ->paginate(10);

            if ($subscriptions->isEmpty()) {
                return response()->json(['message' => 'No subscriptions found'], 404);
            }

            return response()->json($subscriptions);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    // Show a single subscription
    public function show($id)
    {
        try {
            $subscription = Subscription::with(['package', 'payment', 'mikrotikDevice'])
                ->where('user_id', Auth::id())
                ->find($id);

            return response()->json($subscription);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    // Create a new subscription for the user
    public function store(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'payment_id' => 'nullable|exists:payments,id',
            'ip' => 'required|ip',
            'mac' => 'required|regex:/^([0-9A-Fa-f]{2}[:]){5}([0-9A-Fa-f]{2})$/',
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();

            $existingSub = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->first();

            if ($existingSub) {
                return response()->json([
                    'message' => 'You already have an active subscription. Please wait until it expires before purchasing another plan.',
                    'active_subscription' => $existingSub
                ], 409); // Conflict
            }


            $package = Packages::findOrFail($request->package_id);

            $startsAt = Carbon::now();

            $endsAt = match ($package->type) {
                'daily'   => $startsAt->copy()->addDay()->subSecond(),
                'weekly'  => $startsAt->copy()->addDays(7)->subSecond(),
                'monthly' => $startsAt->copy()->addDays(30)->subSecond(),
                default   => throw new Exception('Invalid package type'),
            };


            // Create subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'payment_id' => $request->payment_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
                'auto_renew' => false,
                'is_renewable' => true,
            ]);

            $selector = new DeviceSelectorService();

            // You can get these from captive portal OR IP lookup
            $lat = $request->latitude ?? null;
            $lng = $request->longitude ?? null;

            $device = $selector->selectBestDevice($lat, $lng);

            if (!$device) {
                throw new Exception('No available MikroTik device');
            }

            $mikrotik = new MikrotikService($device);

            // Create or update hotspot user
            $hotspotPassword = Str::random(8);

            $mikrotik->createOrUpdateHotspotUser(
                username: $user->email,
                password: $hotspotPassword,
                profile: $package->mikrotik_profile, // VERY IMPORTANT
                expiresAt: $endsAt
            );

            // sign user in to hotspot internet
            $mikrotik->loginUserToInternet(
                username: $user->email,
                password: $hotspotPassword,
                ip: $request->input('ip'), // client's IP
                mac: $request->input('mac') // client's MAC address
            );

            // track load
            $device->increment('current_clients');

            DB::commit();

            return response()->json([
                'message' => 'Subscription successful. Internet access granted.',
                'subscription' => $subscription,
                'hotspot' => [
                    'username' => $user->email,
                    'password' => $hotspotPassword,
                    'expires_at' => $endsAt,
                ],
            ], 201);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());

            return response()->json([
                'message' => 'Subscription failed',
                'error' => $ex->getMessage(),
            ], 500);
        }
    }

    // Cancel a subscription
    public function cancel($id)
    {
        try {
            $subscription = Subscription::where('user_id', Auth::id())->findOrFail($id);

            // Mark as cancelled and prevent renewal
            $subscription->status = 'cancelled';
            $subscription->is_renewable = false;
            $subscription->save();

            return response()->json([
                'message' => 'Subscription cancelled successfully and cannot be renewed',
                'subscription' => $subscription
            ]);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    // Renew a subscription
    public function renew(Request $request, $id)
    {
        $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'payment_id' => 'nullable|exists:payments,id',
        ]);

        DB::beginTransaction();

        try {
            $currentSubscription = Subscription::where('user_id', Auth::id())->findOrFail($id);

            // Check if the subscription is renewable
            if (!$currentSubscription->is_renewable) {
                return response()->json([
                    'message' => 'This subscription is not renewable.'
                ], 400);
            }

            // Create new subscription as a renewal
            $renewedSubscription = Subscription::create([
                'user_id' => Auth::id(),
                'package_id' => $currentSubscription->package_id,
                'mikrotik_device_id' => $currentSubscription->mikrotik_device_id,
                'payment_id' => $request->payment_id,
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at,
                'status' => 'active',
                'auto_renew' => $currentSubscription->auto_renew,
                'is_renewable' => $currentSubscription->is_renewable,
                'renewed_from_subscription_id' => $currentSubscription->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Subscription renewed successfully',
                'subscription' => $renewedSubscription
            ], 201);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function dataUsage()
    {
        try {
            $user = Auth::user();

            if (!$user) return response()->json(['message' => 'Cannot identify current user'], 404);

            // 🔥 Determine best / assigned device
            $selector = new DeviceSelectorService();

            // Prefer saved subscription device
            $subscription = $user->subscriptions()
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$subscription || !$subscription->device) {
                return response()->json([
                    'message' => 'No active device found for user'
                ], 404);
            }

            $device = $subscription->device;

            // 🔌 Connect to MikroTik
            $mikrotik = new MikrotikService($device);

            // 📊 Get usage
            $usageBytes = $mikrotik->getUserUsage($user->email);

            return response()->json([
                'bytes_used' => $usageBytes,
                'mb_used'    => round($usageBytes / 1024 / 1024, 2),
                'gb_used'    => round($usageBytes / 1024 / 1024 / 1024, 2),
            ]);
        } catch (\Throwable $e) {
            Log::error('Data usage error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to fetch data usage'
            ], 500);
        }
    }

    public function showByReference($reference)
    {
        try {
            // Find the payment ID
            $payment_id = Payment::where('reference', $reference)->value('id');

            if (!$payment_id) {
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Get the subscription for the logged-in user
            $subscription = Subscription::with('mikrotikDevice') // eager load device
                ->where('payment_id', $payment_id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$subscription) {
                return response()->json(['message' => 'Subscription not found'], 404);
            }

            // Safely get device name if it exists
            $deviceName = $subscription->mikrotikDevice?->name ?? null;

            return response()->json([
                'device' => $deviceName,
                'subscription' => $subscription,
                'hotspot_password' => $subscription->hotspot_password,
            ]);
        } catch (Exception $ex) {
            Log::error('showByReference(): ' . $ex->getMessage() . ' on line ' . $ex->getLine());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
