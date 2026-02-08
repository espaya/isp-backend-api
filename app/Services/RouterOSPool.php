<?php

namespace App\Services;

use RouterOS\Client;
use Illuminate\Support\Facades\Log;

class RouterOSPool
{
    protected static array $pool = [];

    public static function get(int $deviceId, array $config): Client
    {
        if (isset(self::$pool[$deviceId])) {
            return self::$pool[$deviceId];
        }

        try {
            $client = new Client([
                'host' => $config['host'],
                'user' => $config['api_user'],
                'pass' => $config['api_password'],
                'port' => $config['api_port'] ?? 8728,
                'timeout' => 3,
            ]);

            self::$pool[$deviceId] = $client;

            return $client;
        } catch (\Throwable $e) {
            Log::error("RouterOS connection failed [Device {$deviceId}]: {$e->getMessage()}");
            throw $e;
        }
    }

    public static function drop(int $deviceId): void
    {
        unset(self::$pool[$deviceId]);
    }

    public static function flush(): void
    {
        self::$pool = [];
    }
}
