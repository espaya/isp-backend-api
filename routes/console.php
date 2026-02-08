<?php

// This file is where you may define all of your Closure based console commands. Each Closure is bound to a command instance allowing a simple approach to interacting with each command's IO methods.
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


use Illuminate\Support\Facades\Schedule;
use App\Jobs\ExpireSubscriptionsJob;
use App\Jobs\EnforceDataLimits;
use App\Jobs\CheckDeviceHealth;
use App\Jobs\ChargeAuthorizationJob;
use App\Jobs\AutoRenewSubscriptions;
use App\Models\Device;
use App\Services\SignalSyncService;

Schedule::job(new ExpireSubscriptionsJob)->everyMinute();
Schedule::job(new EnforceDataLimits)->everyMinute();
Schedule::job(new CheckDeviceHealth)->everyMinute();

Schedule::call(function () {
    Device::all()->each(
        fn($d) => app(SignalSyncService::class)->sync($d)
    );
})->everyFiveMinutes();

Schedule::job(new ChargeAuthorizationJob)->everyMinute();
// Schedule::job(new AutoRenewSubscriptions)->everyMinute();
