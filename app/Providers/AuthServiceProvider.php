<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Policies\DashboardPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // Map your models to policies
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        'dashboard' => DashboardPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // $this->registerPolicies(); 

        // Optionally, define Gates for your roles
        Gate::define('isAdmin', fn(User $user) => $user->role === 'admin');
        Gate::define('isUser', fn(User $user) => $user->role === 'user');
    }
}
