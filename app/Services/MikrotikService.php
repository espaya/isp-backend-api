<?php

namespace App\Services;

use App\Models\Device;
use RouterOS\Query;
use Exception;

class MikrotikService
{
    protected $client;
    protected Device $device;

    public function __construct(Device $device)
    {
        $this->device = $device;

        $this->client = RouterOSPool::get($device->id, [
            'host'     => $device->host,
            'username' => $device->username,
            'password' => $device->password,
            'api_port' => $device->api_port,
        ]);
    }

    /* ---------------- HOTSPOT ---------------- */

    public function userExists(string $username): bool
    {
        $q = (new Query('/ip/hotspot/user/print'))->where('name', $username);
        return !empty($this->client->query($q)->read());
    }

    public function createOrUpdateHotspotUser(
        string $username,
        string $password,
        string $profile,
        $expiresAt
    ) {
        if ($this->userExists($username)) {
            $this->updateUser($username, [
                'password' => $password,
                'profile'  => $profile,
                'disabled' => 'no',
                'comment'  => 'Expires: ' . $expiresAt,
            ]);
            return;
        }

        $q = new Query('/ip/hotspot/user/add');
        $q->equal('name', $username)
            ->equal('password', $password)
            ->equal('profile', $profile)
            ->equal('disabled', 'no')
            ->equal('comment', 'Expires: ' . $expiresAt);

        $this->client->query($q)->read();
    }

    public function updateUser(string $username, array $data)
    {
        $print = (new Query('/ip/hotspot/user/print'))->where('name', $username);
        $users = $this->client->query($print)->read();

        if (!$users) {
            throw new Exception('Hotspot user not found');
        }

        $id = $users[0]['.id'];

        $set = (new Query('/ip/hotspot/user/set'))->equal('.id', $id);
        foreach ($data as $k => $v) {
            $set->equal($k, $v);
        }

        $this->client->query($set)->read();
    }

    public function disableUser(string $username)
    {
        $this->updateUser($username, ['disabled' => 'yes']);
    }

    /**
     * Get total usage (bytes) for a user
     */
    public function getUserUsage(string $username): int
    {
        $usage = $this->getUserDataUsage($username);

        if (!$usage) {
            return 0;
        }

        return (int) $usage['bytes_total'];
    }

    /**
     * Get hotspot user data usage
     */
    public function getUserDataUsage(string $username): ?array
    {
        $query = (new Query('/ip/hotspot/user/print'))
            ->where('name', $username);

        $users = $this->client->query($query)->read();

        if (empty($users)) {
            return null;
        }

        $user = $users[0];

        $bytesIn  = (int) ($user['bytes-in'] ?? 0);
        $bytesOut = (int) ($user['bytes-out'] ?? 0);

        return [
            'bytes_in'    => $bytesIn,
            'bytes_out'   => $bytesOut,
            'bytes_total' => $bytesIn + $bytesOut,
        ];
    }
}
