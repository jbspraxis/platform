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
        // Re-run the HQ staff check on Livewire update requests, not only on page loads.
        \Livewire\Livewire::addPersistentMiddleware([
            \App\Http\Middleware\HqStaff::class,
        ]);
    }
}
