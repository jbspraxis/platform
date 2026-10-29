<?php

namespace App\Providers;

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
        // Re-run the HQ staff and active-client checks on Livewire update requests, not only on page loads.
        // (Livewire already re-runs 'auth' and 'can:' route middleware.)
        \Livewire\Livewire::addPersistentMiddleware([
            \App\Http\Middleware\HqStaff::class,
            \App\Http\Middleware\ClientActive::class,
        ]);

        // Permissions from config/access.php: $user->can('finance.view'), @can, can: middleware.
        // Anything that isn't a catalogue permission falls through to normal gates/policies.
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            return \App\Support\Access::isPermission($ability) ? \App\Support\Access::allows($user, $ability) : null;
        });
    }
}
