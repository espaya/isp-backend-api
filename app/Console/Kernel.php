<?php

namespace App\Console;

use App\Jobs\CheckDeviceHealth;
use App\Models\Device;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\MikrotikService;
use App\Services\SignalSyncService;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Run every minute
        $schedule->call(function () {
            MikrotikService::disableExpiredUsers();
            MikrotikService::enforceDataLimits();
        })->everyMinute();

        $schedule->job(new CheckDeviceHealth)->everyMinute();

        $schedule->call(
            fn() =>
            Device::all()->each(fn($d) => app(SignalSyncService::class)->sync($d))
        )->everyFiveMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
    }
}
