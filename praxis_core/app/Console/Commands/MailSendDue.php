<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * HQ Mail: send queued and scheduled mail whose time has come (mail_outbox). PR 3 owns this file and
 * replaces this placeholder (docs/MAIL.md, "Sending"); it is scheduled every minute from PR 1.
 */
class MailSendDue extends Command
{
    protected $signature = 'mail:send-due';

    protected $description = 'HQ Mail: send queued and scheduled mail that is due';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
