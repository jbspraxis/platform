<?php

namespace App\Services\Site;

use Closure;

use function Illuminate\Support\defer;

/**
 * Work that may wait for the response: on the site API path the emails are sent right after the
 * response (docs/SITE_API.md section 6.4), so a slow mail server never makes the site report a failure.
 * The old Livewire pages keep sending while the request runs, as before.
 *
 * Laravel's defer() runs the work once the response has been sent (InvokeDeferredCallbacks, a global
 * middleware), once; "always" because the data is already stored when the work is queued.
 */
final class Deferred
{
    public static function run(bool $afterResponse, Closure $work): void
    {
        if ($afterResponse) {
            defer($work, always: true);

            return;
        }

        $work();
    }
}
