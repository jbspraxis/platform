<?php

namespace App\Events\Mail;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * New mail arrived in a mailbox's Inbox (docs/MAIL.md, "Events"). Raised by sync for messages that came in
 * since the last check (never for the first copy of a mailbox, catching up after a long pause, or mail the
 * mailbox sent itself). PR 4 listens: notifications to everyone who can read the mailbox, and a Done
 * conversation goes back to Open.
 */
class MailReceived
{
    use Dispatchable;

    /**
     * @param  list<int>  $messageIds  mail_messages.id, oldest first
     * @param  list<int>  $threadIds   their threads
     */
    public function __construct(
        public readonly string $accountId,
        public readonly array $messageIds,
        public readonly array $threadIds,
    ) {
    }
}
