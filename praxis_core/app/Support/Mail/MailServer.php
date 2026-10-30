<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use App\Support\Mail\Imap\ImapClient;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\Imap\ImapSession;
use App\Support\Mail\Imap\SocketImapSession;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

/**
 * The one door to the mail servers: IMAP sessions and SMTP. Everything in HQ Mail goes through
 * app(MailServer::class) so tests can swap in Tests\Support\Mail\FakeMailServer.
 *
 * $settings everywhere: ['imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption']
 * (encryption: ssl, starttls or none). Throws ImapException (IMAP) and MailSendException (SMTP).
 */
class MailServer
{
    public static function make(): static
    {
        return app(static::class);
    }

    /** A signed-in session on a connected mailbox */
    public function open(MailAccount $account): ImapSession
    {
        return $this->connect(self::settingsOf($account), $account->username, MailCredentials::password($account));
    }

    /** A signed-in session with these settings (used by Connect before anything is saved) */
    public function connect(array $settings, string $username, string $password): ImapSession
    {
        $client = new ImapClient(
            $settings['imap_host'],
            (int) $settings['imap_port'],
            $settings['imap_encryption'],
            (int) config('mail_client.timeout', 20),
            (bool) config('mail_client.verify_tls', true),
        );
        $client->connect();
        $client->login($username, $password);

        return new SocketImapSession($client);
    }

    /** Sign in to SMTP and leave: proves the outgoing settings and password work. Throws MailSendException. */
    public function checkSmtp(array $settings, string $username, string $password): void
    {
        $transport = $this->transport($settings, $username, $password);
        try {
            $transport->start();
        } catch (TransportExceptionInterface $e) {
            throw MailSendException::from($e);
        } finally {
            try {
                $transport->stop();
            } catch (TransportExceptionInterface) {
                // already closed
            }
        }
    }

    /**
     * Send through the mailbox's own SMTP server. From is always the mailbox (MailTransport sets it); the
     * envelope comes from To, Cc and Bcc. @return string the message exactly as sent (no Bcc), for the Sent folder
     */
    public function send(MailAccount $account, Email $email): string
    {
        $transport = $this->transport(self::settingsOf($account), $account->username, MailCredentials::password($account));
        try {
            $sent = $transport->send($email);
        } catch (TransportExceptionInterface $e) {
            throw MailSendException::from($e);
        } finally {
            try {
                $transport->stop();
            } catch (TransportExceptionInterface) {
                // already closed
            }
        }

        return $sent instanceof SentMessage ? $sent->toString() : $email->toString();
    }

    public static function settingsOf(MailAccount $account): array
    {
        return [
            'imap_host' => $account->imap_host,
            'imap_port' => (int) $account->imap_port,
            'imap_encryption' => $account->imap_encryption,
            'smtp_host' => $account->smtp_host,
            'smtp_port' => (int) $account->smtp_port,
            'smtp_encryption' => $account->smtp_encryption,
        ];
    }

    private function transport(array $settings, string $username, string $password): EsmtpTransport
    {
        $transport = new EsmtpTransport($settings['smtp_host'], (int) $settings['smtp_port'], $settings['smtp_encryption'] === 'ssl');
        if ($settings['smtp_encryption'] === 'none') {
            $transport->setAutoTls(false);
        }
        if ($settings['smtp_encryption'] === 'starttls') {
            $transport->setRequireTls(true);
        }
        $transport->setUsername($username);
        $transport->setPassword($password);
        $transport->setLocalDomain(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'hq.jbspraxis.com');

        $stream = $transport->getStream();
        $stream->setTimeout((float) config('mail_client.timeout', 20));
        if (! config('mail_client.verify_tls', true)) {
            $stream->setStreamOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        }

        return $transport;
    }
}
