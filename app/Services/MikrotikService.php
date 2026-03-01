<?php

namespace App\Services;

use App\Models\Device;
use RouterOS\Query;
use Exception;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    protected $client;
    protected Device $device;

    public function __construct(Device $device)
    {
        $this->device = $device;

        $this->client = RouterOSPool::get($device->id, [
            'host'     => (string)$device->ip,
            'api_user' => (string)$device->api_user,
            'api_password' => (string)$device->api_password,
            'api_port' => (int)$device->api_port ?? 8728,
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
        \DateTime|string $expiresAt
    ): bool {
        try {
            // Convert expiresAt to string if DateTime
            if ($expiresAt instanceof \DateTime) {
                $expiresAt = $expiresAt->format('Y-m-d H:i:s');
            }

            if ($this->userExists($username)) {
                $this->updateUser($username, [
                    'password' => $password,
                    'profile'  => $profile,
                    'disabled' => 'no',
                    'comment'  => 'Expires: ' . $expiresAt,
                ]);
                return true;
            }

            $q = new Query('/ip/hotspot/user/add');
            $q->equal('name', $username)
                ->equal('server', 'all')
                ->equal('password', $password)
                ->equal('profile', $profile)
                ->equal('disabled', 'no')
                ->equal('comment', 'Expires: ' . $expiresAt);

            $this->client->query($q)->read();

            return $this->userExists($username); // double check it was added
        } catch (\Exception $ex) {
            Log::error("Failed to create hotspot user $username: " . $ex->getMessage());
            return false;
        }
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

    public function loginUserToInternet(string $username, string $password, string $ip, string $mac): bool
    {
        try {
            // This logs the user into hotspot using their current IP
            $q = new Query('/ip/hotspot/active/login');
            $q->equal('user', $username)
                ->equal('password', $password)
                ->equal('ip', $ip)
                ->equal('mac-address', $mac);

            $this->client->query($q)->read();

            // Verify user is active
            $check = (new Query('/ip/hotspot/active/print'))
                ->where('user', $username);

            $active = $this->client->query($check)->read();

            return !empty($active);
        } catch (\Throwable $ex) {
            Log::error("Failed to login hotspot user {$username}: " . $ex->getMessage());
            return false;
        }
    }

    public function getHotspotActiveUserStatus(string $ip): ?array
    {
        // 1. Get active session by IP
        $query = (new Query('/ip/hotspot/active/print'))
            ->where('address', $ip);

        $active = $this->client->query($query)->read();

        if (empty($active)) {
            return null;
        }

        $activeUser = $active[0];
        $username = $activeUser['user'] ?? null;

        if (!$username) {
            return null;
        }

        // 2. Get hotspot user info (to get profile)
        $userQuery = (new Query('/ip/hotspot/user/print'))
            ->where('name', $username);

        $users = $this->client->query($userQuery)->read();

        $profileName = $users[0]['profile'] ?? null;

        // 3. Get user profile info (rate-limit, data limit, session timeout, etc.)
        $profileData = null;

        if ($profileName) {
            $profileQuery = (new Query('/ip/hotspot/user/profile/print'))
                ->where('name', $profileName);

            $profiles = $this->client->query($profileQuery)->read();

            $profileData = $profiles[0] ?? null;
        }

        return [
            'user' => $activeUser['user'] ?? null,
            'ip' => $activeUser['address'] ?? null,
            'mac' => $activeUser['mac-address'] ?? null,
            'uptime' => $activeUser['uptime'] ?? null,

            'bytes_in' => (int) ($activeUser['bytes-in'] ?? 0),
            'bytes_out' => (int) ($activeUser['bytes-out'] ?? 0),
            'bytes_total' => (int) ($activeUser['bytes-in'] ?? 0) + (int) ($activeUser['bytes-out'] ?? 0),

            'session_time_left' => $activeUser['session-time-left'] ?? null,
            'idle_time' => $activeUser['idle-time'] ?? null,

            // from hotspot user
            'profile' => $profileName,

            // from hotspot profile
            'rate_limit' => $profileData['rate-limit'] ?? null,
            'session_timeout' => $profileData['session-timeout'] ?? null,
            'shared_users' => $profileData['shared-users'] ?? null,

            // data limits (usually stored here if configured)
            'limit_bytes_total' => $profileData['limit-bytes-total'] ?? null,
            'limit_bytes_in' => $profileData['limit-bytes-in'] ?? null,
            'limit_bytes_out' => $profileData['limit-bytes-out'] ?? null,
        ];
    }

    public function getUserActiveSessions(string $username): array
    {
        $query = (new \RouterOS\Query('/ip/hotspot/active/print'))
            ->where('user', $username);

        return $this->client->query($query)->read();
    }

    public function disconnectUserSessions(string $username): void
    {
        $query = (new \RouterOS\Query('/ip/hotspot/active/print'))
            ->where('user', $username);

        $sessions = $this->client->query($query)->read();

        foreach ($sessions as $session) {
            $remove = (new \RouterOS\Query('/ip/hotspot/active/remove'))
                ->equal('.id', $session['.id']);

            $this->client->query($remove)->read();
        }
    }
}
