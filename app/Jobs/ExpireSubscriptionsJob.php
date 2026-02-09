<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\MikrotikService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptionsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        try {
            DB::beginTransaction();

            $expiredSubscriptions = Subscription::with('user', 'mikrotikDevice')
                ->where('status', 'active')
                ->whereRaw("expires_at <= NOW()")
                ->get();


            foreach ($expiredSubscriptions as $subscription) {

                $user = $subscription->user;
                $device = $subscription->mikrotikDevice;

                // Disable on MikroTik ONLY if device exists
                if ($device) {
                    $mikrotik = new MikrotikService($device);
                    $mikrotik->disableUser($user->email);
                }

                // ALWAYS expire in DB
                $subscription->update([
                    'status' => 'expired',
                    'is_renewable' => false,
                    'hotspot_password' => '',
                ]);

                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(
                "Failed to expire subscription {$subscription->id}: {$e->getMessage()}"
            );
        }
    }
}
