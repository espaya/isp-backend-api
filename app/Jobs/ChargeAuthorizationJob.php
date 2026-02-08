<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\PaystackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChargeAuthorizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $subs = Subscription::expired()->with('user', 'package')->get();

        foreach ($subs as $sub) {
            $auth = $sub->user->paymentAuthorization;

            if (!$auth) continue;

            $reference = 'ISP_RENEW_' . uniqid();

            $response = PaystackService::chargeAuthorization(
                $sub->user->email,
                $auth->authorization_code,
                $sub->package->price * 100,
                $reference
            );

            if ($response['status'] && $response['data']['status'] === 'success') {
                // reactivate subscription + MikroTik user
            }
        }
    }
}
