<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\User;
use App\Models\UserUsage;
use App\Services\MikrotikService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = new User();
        $device = new Device();
        $mikrotik = new MikrotikService($device);

        $bytes = $mikrotik->getUserUsage($user->email);

        UserUsage::updateOrCreate(
            [
                'user_id' => $user->id,
                'usage_date' => now()->toDateString(),
            ],
            [
                'bytes_used' => $bytes
            ]
        );
    }
}
