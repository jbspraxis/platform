<?php

namespace App\Support\Mail;

/**
 * Server settings for an address: config('mail_client.servers') for known domains (jbspraxis.com:
 * mail.jbspraxis.com, IMAP 993 and SMTP 465, both SSL, as cPanel's "Mail Client Manual Settings" say),
 * otherwise mail.<domain> with the default ports. The person can change them before connecting.
 */
final class Autodiscover
{
    public static function for(string $email): array
    {
        $domain = strtolower(trim((string) substr(strrchr($email, '@') ?: '', 1)));
        $known = config("mail_client.servers.{$domain}");
        if (is_array($known)) {
            return self::normalise($known);
        }

        $default = config('mail_client.default_server', []);

        return self::normalise([
            'imap_host' => $domain !== '' ? "mail.{$domain}" : '',
            'imap_port' => $default['imap_port'] ?? 993,
            'imap_encryption' => $default['imap_encryption'] ?? 'ssl',
            'smtp_host' => $domain !== '' ? "mail.{$domain}" : '',
            'smtp_port' => $default['smtp_port'] ?? 465,
            'smtp_encryption' => $default['smtp_encryption'] ?? 'ssl',
        ]);
    }

    public static function normalise(array $settings): array
    {
        return [
            'imap_host' => strtolower(trim((string) ($settings['imap_host'] ?? ''))),
            'imap_port' => (int) ($settings['imap_port'] ?? 993),
            'imap_encryption' => in_array($settings['imap_encryption'] ?? null, ['ssl', 'starttls', 'none'], true) ? $settings['imap_encryption'] : 'ssl',
            'smtp_host' => strtolower(trim((string) ($settings['smtp_host'] ?? ''))),
            'smtp_port' => (int) ($settings['smtp_port'] ?? 465),
            'smtp_encryption' => in_array($settings['smtp_encryption'] ?? null, ['ssl', 'starttls', 'none'], true) ? $settings['smtp_encryption'] : 'ssl',
        ];
    }
}
