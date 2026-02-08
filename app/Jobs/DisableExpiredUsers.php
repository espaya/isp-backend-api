<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\MikrotikService;
use Carbon\Carbon;

class DisableExpiredUsers
{
    public function handle()
    {
        $subs = Subscription::with('user', 'mikrotikDevice')
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($subs as $sub) {
            if (!$sub->mikrotikDevice) {
                continue;
            }

            try {
                $mikrotik = new MikrotikService($sub->mikrotikDevice);
                $mikrotik->disableUser($sub->user->email);

                $sub->update(['status' => 'expired']);
            } catch (\Throwable $e) {
                logger()->error("Expire failed: {$e->getMessage()}");
            }
        }
    }
}
