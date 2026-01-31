<?php

namespace App\Console;

use App\Jobs\AutoRenewSubscriptions;
use App\Jobs\CheckDeviceHealth;
use App\Models\Device;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\MikrotikService;
use App\Services\SignalSyncService;
use App\Jobs\DisableExpiredUsers;
use App\Jobs\EnforceDataLimits;
use ChargeAuthorizationJob;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Run every minute
        $schedule->job(new DisableExpiredUsers())->everyMinute();

        $schedule->job(new EnforceDataLimits())->everyMinute();

        $schedule->job(new CheckDeviceHealth)->everyMinute();

        $schedule->call(
            fn() =>
            Device::all()->each(fn($d) => app(SignalSyncService::class)->sync($d))
        )->everyFiveMinutes();

        $schedule->job(new ChargeAuthorizationJob())->everyMinute();

        $schedule->job(new AutoRenewSubscriptions())->everyMinute();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
    }
}
