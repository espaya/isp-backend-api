<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\PaymentAuthorization;
use App\Services\PaystackService;
use App\Services\MikrotikService;
use Illuminate\Support\Str;

class AutoRenewSubscriptions
{
    public function handle()
    {
        $subs = Subscription::with('user', 'package', 'mikrotikDevice')
            ->where('status', 'active')
            ->where('expires_at', '<=', now()->addMinutes(10))
            ->get();

        foreach ($subs as $sub) {
            $user = $sub->user;
            $package = $sub->package;

            $auth = PaymentAuthorization::where('user_id', $user->id)->first();

            if (!$auth) {
                continue; // no saved card
            }

            try {
                $reference = 'ISP_RENEW_' . Str::uuid();

                $response = PaystackService::chargeAuthorization(
                    $user->email,
                    $auth->authorization_code,
                    $package->price * 100,
                    $reference
                );

                if ($response['data']['status'] !== 'success') {
                    continue;
                }

                // Extend subscription
                $sub->update([
                    'expires_at' => match ($package->type) {
                        'daily' => now()->endOfDay(),
                        'weekly' => now()->addWeek(),
                        'monthly' => now()->addMonth(),
                    },
                ]);

                // Re-enable MikroTik
                $mikrotik = new MikrotikService($sub->mikrotikDevice);
                $mikrotik->createOrUpdateHotspotUser(
                    $user->email,
                    Str::random(8),
                    $package->mikrotik_profile,
                    $sub->expires_at
                );
            } catch (\Throwable $e) {
                logger()->error("Auto-renew failed: " . $e->getMessage());
            }
        }
    }
}
