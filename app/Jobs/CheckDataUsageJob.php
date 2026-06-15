<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\UserUsage;
use App\Models\Device;
use App\Services\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckDataUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Get all active subscriptions
        $subscriptions = Subscription::with('package', 'user')
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $package = $subscription->package;

            // Skip if data limit is unlimited
            if ($package->dataLimit === 'Unlimited Data' || empty($package->dataLimit)) {
                continue;
            }

            // Parse data limit (e.g., "2", "2GB", "5 GB")
            preg_match('/(\d+)/', $package->dataLimit, $matches);
            $dataLimitGB = (int) ($matches[1] ?? 0);

            if ($dataLimitGB <= 0) {
                continue;
            }

            try {
                $device = Device::first();
                
                if (!$device) {
                    Log::warning('No device found for data usage check');
                    continue;
                }

                $mikrotik = new MikrotikService($device);
                
                // Get user data usage from MikroTik
                $usage = $mikrotik->getUserDataUsage($subscription->user->email);
                
                if (!$usage) {
                    Log::warning('No usage data found for user: ' . $subscription->user->email);
                    continue;
                }

                $totalBytesUsed = $usage['bytes_total'] ?? 0;
                
                // Convert to GB
                $totalGBUsed = $totalBytesUsed / (1024 * 1024 * 1024);

                // Store usage record
                UserUsage::updateOrCreate(
                    [
                        'user_id' => $subscription->user_id,
                        'usage_date' => now()->toDateString(),
                    ],
                    [
                        'bytes_used' => $totalBytesUsed,
                    ]
                );

                // Check if user exceeded data limit
                if ($totalGBUsed >= $dataLimitGB) {
                    $this->deactivateSubscription($subscription);
                }
                
            } catch (\Exception $e) {
                Log::error('Failed to check data usage for user: ' . $subscription->user->email, [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function deactivateSubscription($subscription)
    {
        DB::beginTransaction();

        try {
            // Update subscription status
            $subscription->status = 'expired';
            $subscription->expires_at = now();
            $subscription->save();

            // Disable hotspot user in MikroTik
            try {
                $device = Device::first();
                $mikrotik = new MikrotikService($device);
                $mikrotik->disableUser($subscription->user->email);

                Log::info('Hotspot user disabled due to data limit exceeded', [
                    'user_id' => $subscription->user_id,
                    'username' => $subscription->user->email,
                    'data_limit' => $subscription->package->dataLimit
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to disable hotspot user: ' . $e->getMessage());
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to deactivate subscription: ' . $e->getMessage());
        }
    }
}