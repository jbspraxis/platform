<?php

namespace App\Providers;

use App\Support\HqApi;
use App\Support\SiteSettings;
use App\Support\Turnstile;
use App\Support\VisitorIp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The live web PHP has no fileinfo extension: a stand-in for finfo (App\Support\Polyfill\Finfo, the same as
        // HQ's) lets the careers CV's "mimes" rule read the file's type
        if (! extension_loaded('fileinfo')) {
            require_once app_path('Support/Polyfill/fileinfo.php');
        }

        // One client per request (it remembers whether the last read was served from the saved copy)
        $this->app->scoped(HqApi::class, fn ($app) => new HqApi(
            (array) $app['config']->get('hq_api', []),
            $app['cache']->store(),
        ));

        // The public Website settings, read once per request (cached by HqApi; never fails the page)
        $this->app->scoped(SiteSettings::class, function ($app) {
            $api = $app->make(HqApi::class);

            return SiteSettings::fromApi($api->settingsIfAvailable(), $api->contactEmail());
        });
    }

    public function boot(): void
    {
        // X-Forwarded-* is trusted only from the proxies in TRUSTED_PROXIES ("cloudflare" = Cloudflare's published
        // ranges; "*" is refused). The visitor IP sent to HQ comes from App\Support\VisitorIp.
        if ($proxies = VisitorIp::trustedProxies()) {
            TrustProxies::at($proxies);
        }

        // The site's own limits, before HQ's stricter ones (docs/SITE_API.md 6.2). A visitor is one IPv4
        // address or one IPv6 /64, so changing address inside one network does not reset them.
        $per = fn (string $name, int $default) => max(1, (int) config('site.limits.' . $name, $default));
        RateLimiter::for('forms', fn (Request $request) => [
            Limit::perMinute($per('forms_per_minute', 6))->by('forms|' . VisitorIp::key($request)),
            Limit::perHour($per('forms_per_hour', 30))->by('forms-hour|' . VisitorIp::key($request)),
        ]);
        RateLimiter::for('chat', fn (Request $request) => [
            Limit::perMinute($per('chat_per_minute', 10))->by('chat|' . VisitorIp::key($request)),
            Limit::perHour($per('chat_per_hour', 60))->by('chat-hour|' . VisitorIp::key($request)),
        ]);
        RateLimiter::for('unsubscribe', fn (Request $request) => Limit::perMinute($per('unsubscribe_per_minute', 30))->by('unsubscribe|' . VisitorIp::key($request)));

        // Cloudflare Turnstile's script, on pages with a form, only when its keys are set
        View::composer('layouts.site', fn ($view) => $view->with('turnstile', Turnstile::enabled()));

        // Header, footer and pages read the settings as $site (company, offices, contact email, social links)
        View::composer(['layouts.*', 'partials.*', 'pages.*', 'kaizen.*', 'errors.*', 'errors::*'], function ($view) {
            $view->with('site', app(SiteSettings::class));
        });

        // An empty error bag for pages rendered outside the session middleware (a 404 for an unknown path);
        // ShareErrorsFromSession replaces it on every normal request
        View::share('errors', new ViewErrorBag);
    }
}
