<?php

namespace App\Policies;

use App\Models\User;

class DashboardPolicy
{
    public function view(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewUserDashboard(User $user): bool
    {
        return $user->role === 'user';
    }
}
