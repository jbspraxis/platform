<?php

namespace App\Console\Commands;

use App\Models\Mail\MailAccount;
use App\Support\Mail\MailAccess;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Console\Command;

/**
 * HQ Mail: copy new mail from every connected mailbox (docs/MAIL.md, "Sync"). Runs every minute from the
 * scheduler; the time is shared between mailboxes, least recently synced first, and a mailbox not finished
 * carries on at the next run.
 */
class MailSyncCommand extends Command
{
    protected $signature = 'mail:sync {--account= : Only this mailbox (id or address)} {--all : Check every folder now} {--budget= : Seconds for this run}';

    protected $description = 'HQ Mail: copy new mail from connected mailboxes into HQ';

    public function handle(MailSync $sync): int
    {
        MailAccess::flush();
        $budget = (float) ($this->option('budget') ?: config('mail_client.sync.budget', 50));
        $deadline = microtime(true) + $budget;

        $accounts = $this->option('account')
            ? MailAccount::where('id', $this->option('account'))->orWhere('email', strtolower((string) $this->option('account')))->get()
            : MailSync::due();

        foreach ($accounts as $account) {
            $left = $deadline - microtime(true);
            if ($left < 3) {
                break;
            }
            $r = $sync->account($account, $left, (bool) $this->option('all'));
            $this->line(sprintf('%s: %s, %d folders, +%d −%d ~%d%s', $account->email, $r['status'], $r['folders'], $r['added'], $r['removed'], $r['updated'],
                isset($r['error']) ? ' (' . $r['error'] . ')' : ''));
        }

        return self::SUCCESS;
    }
}
