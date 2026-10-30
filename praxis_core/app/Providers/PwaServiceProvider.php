<?php

namespace App\Providers;

use App\Http\Middleware\PwaLaunch;
use App\Support\Pwa\PushDispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * HQ and the Client Portal as installable apps (docs/PWA.md). Their routes answer on their own hosts only
 * and are registered here, before routes/web.php, whose host groups end with a catch-all 404. The launch
 * middleware notices a cold start of an installed app. Every HQ bell notification is pushed to the person's
 * devices as soon as the request or command that wrote it has finished (the portal's: ClientNotify).
 */
class PwaServiceProvider extends ServiceProvider
{
    private bool $pushQueued = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/pwa.php'));

        // In the web group, before the sign-in check (after the session starts): on the portal "/" is for signed-in
        // clients, and a guest opening the installed app is sent to the sign-in page by that check, which must still
        // play the launch. Both through the kernel: changing its priority list resets the router's groups to its own.
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->addToMiddlewarePriorityBefore(\Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, PwaLaunch::class);
        $kernel->appendMiddlewareToGroup('web', PwaLaunch::class);

        // About 35 places insert into notifications with the query builder; none of them needs to know
        // about push. After the response (or command), the dispatcher pushes what isn't pushed yet;
        // pwa:push-pending (every minute) catches anything this misses.
        DB::listen(function (QueryExecuted $query) {
            if ($this->pushQueued || ! preg_match('/^\s*insert\s+(?:ignore\s+)?into\s+[`"]?notifications[`"]?[\s(]/i', $query->sql)) {
                return;
            }
            $this->pushQueued = true;
            $this->app->terminating(function () {
                $this->pushQueued = false;
                PushDispatcher::dispatchPendingQuietly();
            });
        });
    }
}
