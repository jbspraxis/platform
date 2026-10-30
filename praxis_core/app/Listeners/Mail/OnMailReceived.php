<?php

namespace App\Listeners\Mail;

use App\Events\Mail\MailReceived;
use App\Support\Mail\Team\NewMailNotices;
use Illuminate\Support\Facades\Log;
use Throwable;

/** New mail: alerts to everyone who can read the mailbox, and Done conversations open again (docs/MAIL.md 7.4, 7.5). Runs inside sync: quick, never throws. */
class OnMailReceived
{
    public function handle(MailReceived $event): void
    {
        try {
            NewMailNotices::received($event->accountId, $event->messageIds);
        } catch (Throwable $e) {
            Log::warning('Mail: new-mail alerts failed', ['account' => $event->accountId, 'error' => $e->getMessage()]);
        }
    }
}
