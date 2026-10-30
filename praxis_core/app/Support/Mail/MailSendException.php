<?php

namespace App\Support\Mail;

use RuntimeException;
use Throwable;

/**
 * SMTP said no. $reason: auth (address or password refused), unreachable, rejected (the server refused the
 * message or a recipient; $permanent when it will not change on retry), temporary (try again later).
 */
class MailSendException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'rejected',
        public readonly bool $permanent = false,
        public readonly ?int $smtpCode = null,
    ) {
        parent::__construct($message);
    }

    public static function from(Throwable $e): self
    {
        $text = $e->getMessage();
        $code = (int) $e->getCode();
        if ($code === 0 && preg_match('/\b([45]\d\d)\b/', $text, $m)) {
            $code = (int) $m[1];
        }

        if ($code === 535 || $code === 534 || preg_match('/authenticat|535|credentials/i', $text)) {
            return new self('The outgoing mail server refused the address or password.', 'auth', true, $code ?: null);
        }
        if (preg_match('/Connection could not be established|Unable to connect|timed out|getaddrinfo|refused/i', $text)) {
            return new self('Could not reach the outgoing mail server.', 'unreachable', false, $code ?: null);
        }
        if ($code >= 500) {
            return new self('The outgoing mail server refused the message: ' . self::clean($text), 'rejected', true, $code);
        }
        if ($code >= 400) {
            return new self('The outgoing mail server asked to try again later: ' . self::clean($text), 'temporary', false, $code);
        }

        return new self('Sending failed: ' . self::clean($text), 'temporary', false, $code ?: null);
    }

    private static function clean(string $text): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 300);
    }
}
