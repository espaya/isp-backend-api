<?php

namespace App\Services;

use App\Models\Subscription;
use RouterOS\Client;
use RouterOS\Query;
use Exception;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    protected $client;

    public function __construct(
        $host,
        $username,
        $password,
        $port = 8728
    ) {

        if (!$host) {
            throw new Exception('Mikrotik host is missing');
        }

        $this->client = new Client([
            'host' => $host,
            'user' => $username,
            'pass' => $password,
            'port' => $port,
        ]);
    }

    /**
     * Add a hotspot user
     */
    public function addHotspotUser(string $username, string $password, string $profile = 'default')
    {
        try {
            // Prevent duplicates
            if ($this->hotspotUserExists($username)) {
                return;
            }

            $query = new Query('/ip/hotspot/user/add');
            $query
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profile)
                ->equal('disabled', 'no')
                ->equal('comment', 'Subscribed user');

            $this->client->query($query)->read();
        } catch (Exception $e) {
            throw new Exception("Failed to create hotspot user: " . $e->getMessage());
        }
    }

    /**
     * Remove a hotspot user (FIXED: uses .id)
     */
    public function removeHotspotUser(string $username)
    {
        try {
            $print = new Query('/ip/hotspot/user/print');
            $print->where('name', $username);

            $users = $this->client->query($print)->read();

            if (empty($users)) {
                return;
            }

            $userId = $users[0]['.id'];

            $remove = new Query('/ip/hotspot/user/remove');
            $remove->equal('.id', $userId);

            $this->client->query($remove)->read();
        } catch (Exception $e) {
            throw new Exception("Failed to remove hotspot user: " . $e->getMessage());
        }
    }

    /**
     * Get data usage for a user
     */
    public function getUserDataUsage(string $username)
    {
        $query = new Query('/ip/hotspot/user/print');
        $query->where('name', $username);

        $users = $this->client->query($query)->read();

        if (empty($users)) {
            return null;
        }

        $user = $users[0];

        return [
            'bytes_in' => (int) ($user['bytes-in'] ?? 0),
            'bytes_out' => (int) ($user['bytes-out'] ?? 0),
            'bytes_total' =>
            (int) ($user['bytes-in'] ?? 0) +
                (int) ($user['bytes-out'] ?? 0),
            'limit_bytes_total' => $user['limit-bytes-total'] ?? null,
        ];
    }

    /**
     * Check if hotspot user exists
     */
    public function hotspotUserExists(string $username): bool
    {
        $query = new Query('/ip/hotspot/user/print');
        $query->where('name', $username);

        $users = $this->client->query($query)->read();

        return !empty($users);
    }

    /**
     * Create or update hotspot user
     */
    public function createOrUpdateHotspotUser(
        string $username,
        string $password,
        string $profile,
        $expiresAt
    ) {
        if ($this->hotspotUserExists($username)) {
            $this->updateHotspotUser($username, [
                'password' => $password,
                'profile' => $profile,
                'disabled' => 'no',
                'comment' => 'Expires: ' . $expiresAt,
            ]);
        } else {
            $this->addHotspotUser($username, $password, $profile);
        }
    }

    /**
     * Update hotspot user (FIXED using .id)
     */
    public function updateHotspotUser(string $username, array $data)
    {
        $print = new Query('/ip/hotspot/user/print');
        $print->where('name', $username);

        $users = $this->client->query($print)->read();

        if (empty($users)) {
            throw new Exception('Hotspot user not found');
        }

        $id = $users[0]['.id'];

        $query = new Query('/ip/hotspot/user/set');
        $query->equal('.id', $id);

        foreach ($data as $key => $value) {
            $query->equal($key, $value);
        }

        $this->client->query($query)->read();
    }

    /**
     * Disable hotspot user
     */
    public function disableHotspotUser(string $username)
    {
        $print = new Query('/ip/hotspot/user/print');
        $print->where('name', $username);

        $users = $this->client->query($print)->read();

        if (empty($users)) {
            return;
        }

        $id = $users[0]['.id'];

        $query = new Query('/ip/hotspot/user/set');
        $query->equal('.id', $id)
            ->equal('disabled', 'yes');

        $this->client->query($query)->read();
    }

    public static function disableExpiredUsers()
    {
        $subscriptions = \App\Models\Subscription::where('expires_at', '<', now())
            ->where('status', 'active')
            ->get();

        $device = $subscriptions->package->mikrotikDevice;

        $mikrotik = new MikrotikService(
            $device->host,
            $device->username,
            $device->password,
            $device->api_port ?? 8728
        );


        foreach ($subscriptions as $sub) {
            $mikrotik->removeHotspotUser($sub->user->email);
            $sub->status = 'expired';
            $sub->save();
        }
    }

    public static function enforceDataLimits()
    {
        $subscriptions = Subscription::with('user', 'package')
            ->where('status', 'active')
            ->get();

        $device = $subscriptions->package->mikrotikDevice;

        $mikrotik = new MikrotikService(
            $device->host,
            $device->username,
            $device->password,
            $device->api_port ?? 8728
        );


        foreach ($subscriptions as $sub) {
            $usage = $mikrotik->getUserDataUsage($sub->user->email);

            if (!$usage || !$sub->package->data_limit) continue;

            // Convert GB to bytes
            $limitBytes = $sub->package->data_limit * 1024 * 1024 * 1024;

            if ($usage['bytes_total'] >= $limitBytes) {
                try {
                    $mikrotik->updateHotspotUser($sub->user->email, ['disabled' => 'yes']);
                } catch (Exception $e) {
                    Log::error("Failed to disable user {$sub->user->email} for exceeding data: " . $e->getMessage());
                }
            }
        }
    }
}
