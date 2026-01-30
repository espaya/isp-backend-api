<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptionsJob
{
    public function handle()
    {
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            return;
        }

        $mikrotik = new MikrotikService();

        foreach ($expiredSubscriptions as $subscription) {
            try {
                $user = $subscription->user;

                // Disable Mikrotik access
                $mikrotik->disableHotspotUser($user->email);

                // Update DB
                $subscription->update([
                    'status' => 'expired',
                    'is_renewable' => false,
                ]);
            } catch (\Exception $e) {
                Log::error('Expire subscription failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
