<?php

namespace App\Console\Commands;

use App\Support\Mail\Compose\Outbox;
use App\Support\Mail\Compose\OutboxSender;
use Illuminate\Console\Command;

/**
 * HQ Mail: send queued and scheduled mail whose time has come (mail_outbox), through each mailbox's own SMTP
 * (docs/MAIL.md, "Sending"). Scheduled every minute from PR 1; the composer also sends right after the 10-second
 * undo window, so this run mostly carries scheduled mail, retries, and mail whose page was closed.
 */
class MailSendDue extends Command
{
    protected $signature = 'mail:send-due';

    protected $description = 'HQ Mail: send queued and scheduled mail that is due';

    public function handle(): int
    {
        $counts = OutboxSender::make()->due();
        $pruned = Outbox::make()->pruneUntouched();
        $this->info("Sent {$counts['sent']}, to try again {$counts['retry']}, not sent {$counts['failed']}, stuck {$counts['stuck']}, empty drafts removed {$pruned}.");

        return self::SUCCESS;
    }
}
