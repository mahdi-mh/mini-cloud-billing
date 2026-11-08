<?php

namespace App\Providers;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // anything that moves money, or touches the clock/prices: owner only
        Gate::define('manage-money', fn (User $user) => $user->role === StaffRole::Owner);
        Gate::define('manage-clock', fn (User $user) => $user->role === StaffRole::Owner);

        // servers, ips, customer statuses: support and owner
        Gate::define('manage-infrastructure', fn (User $user) => in_array($user->role, [StaffRole::Owner, StaffRole::Support], true));
    }
}
