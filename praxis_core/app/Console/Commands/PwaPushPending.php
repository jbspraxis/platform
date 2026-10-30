<?php

namespace App\Console\Commands;

use App\Support\Pwa\PushDispatcher;
use Illuminate\Console\Command;

/** Every minute: push bell notifications that weren't pushed straight away (docs/PWA.md). Owner: the "push" part. */
class PwaPushPending extends Command
{
    protected $signature = 'pwa:push-pending {--limit= : at most this many notifications (default: config pwa.push.batch)}';

    protected $description = 'Push recent HQ bell notifications that have not been pushed yet';

    public function handle(): int
    {
        // On the server the code arrives a few minutes before the migration: nothing to do until then
        if (! PushDispatcher::ready()) {
            $this->line('push: the HQ app tables are not there yet (run the migration)');

            return self::SUCCESS;
        }

        $limit = $this->option('limit');
        $s = PushDispatcher::dispatchPending($limit !== null ? max(1, (int) $limit) : null);

        $this->line("push: {$s['checked']} new, {$s['sent']} sent, {$s['partial']} partly sent, {$s['failed']} failed, "
            . "{$s['muted']} muted, {$s['no-device']} without a device, {$s['skipped']} skipped; "
            . "{$s['devices']} device(s) reached, {$s['removed']} removed");

        // The Client Portal's inbox (ClientNotify)
        $c = \App\Support\Pwa\ClientPush::dispatchPending($limit !== null ? max(1, (int) $limit) : null);
        $this->line("portal push: {$c['checked']} new, {$c['pushed']} pushed; {$c['devices']} device(s) reached, {$c['removed']} removed");

        return self::SUCCESS;
    }
}
