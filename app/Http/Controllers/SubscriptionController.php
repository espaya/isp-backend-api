<?php

namespace App\Http\Controllers;

use App\Models\Packages;
use App\Models\Subscription;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MikrotikService;
use Illuminate\Support\Str;


class SubscriptionController extends Controller
{
    // List all available packages
    public function index()
    {
        try {
            $packages = Packages::orderBy('name', 'ASC')->paginate(10);

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
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $package = Packages::findOrFail($request->package_id);

            $startsAt = now();

            $endsAt = match ($package->type) {
                'daily'   => now()->endOfDay(),
                'weekly'  => now()->addWeek(),
                'monthly' => now()->addMonth(),
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

            $mikrotik = new MikrotikService();

            // Create or update hotspot user
            $hotspotPassword = Str::random(8);

            $mikrotik->createOrUpdateHotspotUser(
                username: $user->email,
                password: $hotspotPassword,
                profile: $package->mikrotik_profile, // VERY IMPORTANT
                expiresAt: $endsAt
            );

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
            $mikrotik = new MikrotikService();

            $usage = $mikrotik->getUserDataUsage($user->email);

            if (!$usage) {
                return response()->json(['message' => 'User not found on hotspot'], 404);
            }

            return response()->json($usage);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Failed to fetch data usage'], 500);
        }
    }
}
