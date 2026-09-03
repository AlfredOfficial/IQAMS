<?php

namespace App\Providers;

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
        // The fixed admin portal role is the production super-admin role.
        Gate::before(function (User $user, string $ability) {
            // The audit trail is deliberately permission-gated even for the
            // admin super-role so access can be revoked independently.
            if ($ability === 'view-audit-logs') {
                return null;
            }

            return $user->hasRole('admin') ? true : null;
        });
    }
}
