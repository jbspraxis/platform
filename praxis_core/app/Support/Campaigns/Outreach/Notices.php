<?php

namespace App\Support\Campaigns\Outreach;

/**
 * Mail servers' notices about an outreach email (docs/CAMPAIGNS.md section 12): a bounce notice (a DSN, "Undelivered
 * Mail Returned to Sender") or a spam complaint report (ARF, "Feedback-Type: abuse"). These are facts from a mail
 * server, so HQ files them by itself; but only when the notice names the outreach email's Message-ID, or names the
 * prospect's address and arrived in the mailbox that sent to them (Inbox::look). A "delayed, still trying" notice is
 * not a bounce.
 */
final class Notices
{
    /**
     * Mail systems that send notices: Postfix and Exim (MAILER-DAEMON), Gmail (mailer-daemon@googlemail.com), Outlook
     * (postmaster@…, MicrosoftExchange<32 hex>@…). Only mail from one of these, or carrying a DSN or ARF part, can be a
     * notice: a person's reply that mentions a delay stays a reply.
     */
    private const SENDERS = '/^(mailer-daemon|mail-daemon|mailerdaemon|postmaster|mail\.delivery\.subsystem|microsoftexchange[0-9a-f]{32})@/i';

    /** Senders of spam complaint reports (feedback loops) besides the mail systems above */
    private const REPORTERS = '/^(abuse|fbl|feedback|feedbackloop|complaints?|scomp)@/i';

    /** Subjects of a notice that the email didn't arrive (English and French providers) */
    private const FAILED = '/(undeliverable|undelivered mail|mail delivery failed|delivery status notification \(failure\)|delivery failure|returned mail|failure notice|delivery has failed|could ?n[o’\']?t be delivered|non remis|échec de (la )?remise|rapport de non-remise|impossible de remettre)/iu';

    /** Subjects of a notice that the server is still trying */
    private const DELAYED = '/(delivery status notification \(delay\)|delayed mail|message delayed|delivery delayed|is delayed|still trying|will retry|remise différée|remise retardée)/iu';

    /** In the text: a permanent refusal (5.x.x, 550–554, or the usual words) */
    private const PERMANENT = '/(\b5\.\d{1,3}\.\d{1,3}\b|\b55[0-4][ -]|user unknown|no such user|does not exist|doesn[’\']?t exist|address (couldn[’\']?t|could not) be found|mailbox (unavailable|not found)|recipient (address )?rejected|n[’\']?existe pas|introuvable)/iu';

    /** In the text: the server will try again (4.x.x, "Action: delayed") */
    private const TEMPORARY = '/(\baction:\s*delayed\b|\b4\.\d{1,3}\.\d{1,3}\b|will (retry|keep trying)|still trying)/iu';

    /** Could this message be a notice at all? Mail from a mail system or a feedback loop, or carrying a DSN or ARF part */
    public static function fromMailSystem(?string $fromEmail, array $mimes = []): bool
    {
        $from = trim((string) $fromEmail);
        $mimes = array_map('strtolower', $mimes);

        return self::isMailSystem($from, $mimes) || preg_match(self::REPORTERS, $from) === 1 || in_array('message/feedback-report', $mimes, true);
    }

    /** A mail server wrote it (MAILER-DAEMON, postmaster, Exchange, or a DSN part): never a person's reply */
    public static function isMailSystem(?string $fromEmail, array $mimes = []): bool
    {
        return preg_match(self::SENDERS, trim((string) $fromEmail)) === 1 || in_array('message/delivery-status', array_map('strtolower', $mimes), true);
    }

    /**
     * What kind of notice this message is, from its sender, subject, attachments' types and text:
     * 'complaint' (an ARF report), 'bounce' (a permanent failure), 'delay' (still trying), or null (not a notice).
     *
     * @param  list<string>  $mimes  the message's attachments' types
     */
    public static function kind(?string $fromEmail, ?string $subject, string $text, array $mimes = []): ?string
    {
        $mimes = array_map('strtolower', $mimes);
        $from = trim((string) $fromEmail);
        $system = preg_match(self::SENDERS, $from) === 1;
        if (in_array('message/feedback-report', $mimes, true)
            || (($system || preg_match(self::REPORTERS, $from)) && preg_match('/^\s*Feedback-Type:\s*abuse\b/im', $text))) {
            return 'complaint';
        }
        if (! $system && ! in_array('message/delivery-status', $mimes, true)) {
            return null;
        }
        $subject = (string) $subject;
        if (preg_match(self::DELAYED, $subject) || (preg_match(self::TEMPORARY, $text) && ! preg_match(self::PERMANENT, $text))) {
            return 'delay';
        }
        if (preg_match(self::FAILED, $subject) || preg_match(self::PERMANENT, $text)) {
            return 'bounce';
        }

        return null; // from a mail server but saying neither: not ours to guess
    }

    /** Message-IDs a notice quotes in its text ("Message-ID: <…>", as Exim and Postfix copy the original headers) */
    public static function quotedIds(string $text): array
    {
        preg_match_all('/^\s*(?:X-)?(?:Original-)?Message-ID:\s*<?([^\s<>"]+@[^\s<>"]+)>?/im', $text, $m);

        return array_values(array_unique(array_map(fn ($id) => trim($id, '<> '), $m[1] ?? [])));
    }

    /** Does the notice name this address as a whole address (not inside a longer one)? */
    public static function names(string $haystack, string $email): bool
    {
        $email = trim(mb_strtolower($email));

        return $email !== '' && preg_match('/(?<![\w.+\-])' . preg_quote($email, '/') . '(?![\w\-]|\.[a-z0-9])/iu', $haystack) === 1;
    }

    /** One line saying why, for the reply row and the do-not-email list: the server's own words, short; a line with the SMTP code first */
    public static function reason(string $text, ?string $subject): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
        foreach (['/\b5\.\d{1,3}\.\d{1,3}\b|\b55[0-4][ -]/', self::PERMANENT] as $pattern) {
            foreach ($lines as $line) {
                if (preg_match($pattern, $line)) {
                    return mb_substr(preg_replace('/\s+/u', ' ', $line), 0, 200);
                }
            }
        }

        return mb_substr(trim((string) $subject) ?: 'Bounce notice from the mail server', 0, 200);
    }
}
