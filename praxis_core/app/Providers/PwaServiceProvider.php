<?php

namespace App\Providers;

use App\Http\Middleware\PwaLaunch;
use App\Support\Pwa\PushDispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * HQ as an installable app (docs/PWA.md). Its routes answer on the HQ host only and are registered
 * here, before routes/web.php, whose HQ group ends with a catch-all 404. The launch middleware notices
 * a cold start of the installed app. Every bell notification is pushed to the person's devices as soon
 * as the request or command that wrote it has finished.
 */
class PwaServiceProvider extends ServiceProvider
{
    private bool $pushQueued = false;

    public function boot(Router $router): void
    {
        $this->loadRoutesFrom(base_path('routes/pwa.php'));

        $router->pushMiddlewareToGroup('web', PwaLaunch::class);

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
