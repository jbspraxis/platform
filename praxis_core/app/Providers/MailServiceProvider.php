<?php

namespace App\Providers;

use App\Support\Mail\MailAccess;
use App\Support\Mail\MailServer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * HQ Mail (docs/MAIL.md). The one MailServer (tests replace it with Tests\Support\Mail\FakeMailServer) and the
 * `use-mail` gate: the mail.view permission, and Mail open to the person (owner-only until the owner opens it).
 * Listeners in app/Listeners/Mail are found by Laravel's event discovery; nothing to register here.
 */
class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailServer::class);
    }

    public function boot(): void
    {
        Gate::define('use-mail', fn ($user) => $user instanceof \App\Models\User && MailAccess::canUse($user));
    }
}
