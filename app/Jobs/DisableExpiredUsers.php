<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\MikrotikService;

class DisableExpiredUsers
{
    public function handle()
    {
        $subs = Subscription::with('user', 'device')
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($subs as $sub) {
            if (!$sub->device) {
                continue;
            }

            try {
                $mikrotik = new MikrotikService($sub->device);
                $mikrotik->disableUser($sub->user->email);

                $sub->update(['status' => 'expired']);
            } catch (\Throwable $e) {
                logger()->error("Expire failed: {$e->getMessage()}");
            }
        }
    }
}
