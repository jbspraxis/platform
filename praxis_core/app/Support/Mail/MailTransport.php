<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Support\Mail\Imap\ImapException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Sending one message from a mailbox (PR 3 builds the composer, outbox and signatures on this; docs/MAIL.md,
 * "Sending"). The From line is always the mailbox itself (owner decision 4: the mailbox's name and address;
 * the person shows in their signature, and HQ records who sent it). Bcc travels in the envelope only.
 *
 *   $result = MailTransport::make()->send($user, $account, $email);
 *   // ['message_id' => 'abc@jbspraxis.com', 'raw' => '…as sent…', 'sent_uid' => ?int (its UID in Sent)]
 *
 * Throws MailSendException when SMTP refuses (permanent or not); the copy in Sent is best effort (a failure
 * there is logged, the message has gone).
 */
final class MailTransport
{
    public function __construct(private readonly MailServer $server)
    {
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /** A new Message-ID (without angle brackets) on the mailbox's own domain */
    public static function newMessageId(MailAccount $account): string
    {
        $domain = substr(strrchr($account->email, '@') ?: '', 1) ?: (string) config('mail_client.message_id_domain', 'jbspraxis.com');

        return Str::lower(Str::random(24)) . '.' . now()->format('YmdHis') . '@' . $domain;
    }

    /**
     * @return array{message_id: string, raw: string, sent_uid: ?int}
     */
    public function send(User $user, MailAccount $account, Email $email, bool $copyToSent = true): array
    {
        MailAccess::authorize($user, $account, 'send');

        $email->from(new Address($account->email, (string) $account->display_name));
        $headers = $email->getHeaders();
        if (! $headers->has('Message-ID')) {
            $headers->addIdHeader('Message-ID', self::newMessageId($account));
        }
        if (! $headers->has('Date')) {
            $email->date(now());
        }
        $messageId = trim((string) $headers->get('Message-ID')->getBodyAsString(), '<> ');

        $raw = $this->server->send($account, $email);

        $sentUid = null;
        if ($copyToSent) {
            $sentUid = $this->appendToSent($account, $raw);
        }

        return ['message_id' => $messageId, 'raw' => $raw, 'sent_uid' => $sentUid];
    }

    /** Put a copy in the mailbox's Sent folder (Exim does not), marked read */
    public function appendToSent(MailAccount $account, string $raw): ?int
    {
        $sent = $account->folder('sent');
        if (! $sent) {
            return null;
        }

        try {
            $session = $this->server->open($account);
            try {
                return $session->append($sent->path, $raw, ['\\Seen']);
            } finally {
                $session->logout();
            }
        } catch (ImapException|Throwable $e) {
            Log::warning('Mail: sent, but the copy in Sent could not be saved', ['account' => $account->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
