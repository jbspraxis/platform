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
        // The live web PHP has no fileinfo extension: a stand-in for finfo (App\Support\Polyfill\Finfo) keeps file
        // type checks, uploads and storage working everywhere; App\Support\FilesystemManager stays as a second guard
        if (! extension_loaded('fileinfo')) {
            require_once app_path('Support/Polyfill/fileinfo.php');
        }
        $this->app->singleton('filesystem', fn ($app) => new \App\Support\FilesystemManager($app));
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
