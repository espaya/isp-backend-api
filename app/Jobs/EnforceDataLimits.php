<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\MikrotikService;

class EnforceDataLimits
{
    public function handle()
    {
        $subs = Subscription::with('user', 'package', 'mikrotikDevice')
            ->where('status', 'active')
            ->get();

        foreach ($subs as $sub) {
            if (!$sub->mikrotikDevice || !$sub->package?->data_limit) {
                continue;
            }

            try {
                $mikrotik = new MikrotikService($sub->mikrotikDevice);
                $usage = $mikrotik->getUserUsage($sub->user->email);

                $limitBytes = $sub->package->data_limit * 1024 ** 3;

                if ($usage >= $limitBytes) {
                    $mikrotik->disableUser($sub->user->email);
                }
            } catch (\Throwable $e) {
                logger()->error("Data limit error: {$e->getMessage()}");
            }
        }
    }
}
