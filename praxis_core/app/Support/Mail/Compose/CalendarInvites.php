<?php

namespace App\Support\Mail\Compose;

/**
 * Keeps calendar invitations out of what HQ quotes below a reply (docs/MAIL.md, "Replies and calendar invitations").
 *
 * A Google Calendar invitation quoted in a reply carries "Sent by Google Calendar", the Yes / No / Maybe links
 * with their tokens, the guest list and Google's "Forwarding this invitation…" warning, all now coming from
 * jbspraxis.com instead of Google. Receiving spam filters read that as calendar phishing: on 5 November 2026 a
 * one-line reply to MyCity App was refused outright by their filter (Namecheap Jellyfish, "554 5.7.1 Rejected due
 * to high probability of spam") for exactly that. The RSVP links also let anyone the reply reaches answer the
 * invitation as the original guest, which is why Google warns against forwarding it.
 *
 * So a reply quotes everything as before except the invitation itself, which becomes one line saying it was left
 * out; and a reply's subject drops the "Updated invitation: … @ Thu Nov 5, 2026 12pm - 1pm (WAT) (guest@…)" dressing
 * and keeps the event's name. Forwards are left alone: forwarding an invitation is a deliberate choice.
 *
 * Google writes invitations in the language of the organiser's account, so English and French are both read.
 */
final class CalendarInvites
{
    /** A line only an invitation carries: an RSVP or guest-list link, Google's signature or its forwarding warning */
    private const MARKER = '~calendar\.google\.com/calendar/(?:event|render)\?(?:[^\s>]*&)?action=|support\.google\.com/calendar/answer/37135'
        . '|^(?:Sent by|Invitation from) Google Calendar\b|^(?:Envoyé par|Invitation de) Google Agenda\b'
        . '|^Forwarding this invitation could allow|^Le transfert de cette invitation~iu';

    /** Where an invitation's own text begins, when it is not introduced by a quoted header block */
    private const OPENING = '~^(?:This event has been (?:updated|changed|cancell?ed)|You have been invited|Invitation from Google Calendar'
        . '|Join with Google Meet|Cet événement a été (?:modifié|mis à jour|annulé)|Vous avez été invité|Invitation de Google Agenda'
        . '|Participer avec Google Meet|Rejoindre avec Google Meet)\b~iu';

    /** The last lines Google puts at the foot of an invitation */
    private const CLOSING = '~support\.google\.com/calendar/answer/37135|^Forwarding this invitation could allow|^Le transfert de cette invitation'
        . '|^(?:Sent by|Invitation from) Google Calendar\b|^(?:Envoyé par|Invitation de) Google Agenda\b'
        . '|^You are receiving this (?:email|courtesy email)|^Vous recevez cet e-?mail~iu';

    /** A quoted header line that opens an earlier message: Outlook's "From:", Gmail's "On … wrote:", the French forms */
    private const HEADER = '~^(?:From|De|Von)\s*:|^On .{4,200}wrote:\s*$|^Le .{4,200}a écrit\s*:\s*$|^-{2,} ?(?:Original Message|Forwarded message|Message d’origine|Message transféré)~iu';

    /** The subject Google gives an invitation: a kind, the event's name, then " @ " and its date */
    private const SUBJECT = '~^(?:Updated invitation with note|Updated invitation|Invitation|Canceled event with note|Canceled event|Cancelled event'
        . '|Updated event|Accepted|Declined|Tentatively accepted|Invitation mise à jour avec note|Invitation mise à jour|Événement annulé'
        . '|Événement mis à jour|Accepté|Refusé|Peut-être)\s*:\s*(.+?)\s+@\s+(?=.*\d).+$~iu';

    /** How many invitations one quote may carry before the rest is left as it is */
    private const MAX_BLOCKS = 10;

    /** True when the text carries a calendar invitation */
    public static function contains(string $text): bool
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match(self::MARKER, self::bare($line))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The quoted text with every calendar invitation in it replaced by one line. Text with no invitation comes
     * back unchanged, byte for byte.
     */
    public static function stripFromQuote(string $text): string
    {
        if (! self::contains($text)) {
            return $text;
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $from = 0;

        for ($round = 0; $round < self::MAX_BLOCKS; $round++) {
            $marker = self::firstMatch($lines, self::MARKER, $from);
            if ($marker === null) {
                break;
            }

            [$start, $title] = self::start($lines, $marker, $from);
            $end = self::end($lines, $start);

            $prefix = self::prefix($lines[$start]);
            $note = $prefix . '[Calendar invitation' . ($title !== '' ? ' “' . $title . '”' : '') . ' left out of this quote]';
            array_splice($lines, $start, $end - $start + 1, [$note]);
            $from = $start + 1;
        }

        return rtrim(implode("\n", $lines));
    }

    /** True when the subject is one Google gives an invitation or an answer to it */
    public static function isInvitationSubject(string $subject): bool
    {
        return (bool) preg_match(self::SUBJECT, self::withoutReplyPrefix($subject));
    }

    /**
     * An invitation's subject cut to the event's name: "Updated invitation: Partnerships Workflow Discovery - Mycityapp
     * @ Thu Nov 5, 2026 12pm - 1pm (WAT) (etudor@mycityapp.io)" is "Partnerships Workflow Discovery - Mycityapp".
     * Any other subject comes back as it was, reply prefixes and all.
     */
    public static function cleanSubject(string $subject): string
    {
        $bare = self::withoutReplyPrefix($subject);
        if (! preg_match(self::SUBJECT, $bare, $m)) {
            return $subject;
        }

        return trim($m[1]) !== '' ? trim($m[1]) : $subject;
    }

    /**
     * Where the invitation starts, and its name when a quoted header gives it. A header block just above the first
     * marker whose subject is an invitation's starts it; otherwise the invitation's own opening line does; and when
     * nothing precedes it but the event's own lines (the original message is the invitation), it starts at the top.
     *
     * @param  list<string>  $lines
     * @return array{0: int, 1: string}
     */
    private static function start(array $lines, int $marker, int $floor): array
    {
        // A quoted "From: … / Subject: Updated invitation: …" block within the 40 lines above
        for ($i = $marker; $i >= max($floor, $marker - 40); $i--) {
            if (! preg_match('~^(?:Subject|Objet|Betreff)\s*:\s*(.+)$~iu', self::bare($lines[$i]), $s)) {
                continue;
            }
            if (! self::isInvitationSubject($s[1])) {
                break;
            }
            for ($h = $i; $h >= max($floor, $i - 8); $h--) {
                if (preg_match('~^(?:From|De|Von)\s*:~iu', self::bare($lines[$h]))) {
                    return [$h, self::cleanSubject($s[1])];
                }
            }

            return [$i, self::cleanSubject($s[1])];
        }

        // The invitation's own opening line
        $opening = null;
        for ($i = $marker; $i >= $floor; $i--) {
            $bare = self::bare($lines[$i]);
            if (preg_match(self::OPENING, $bare)) {
                $opening = $i;
            }
            if ($i < $marker && preg_match(self::HEADER, $bare)) {
                return [$opening ?? $i + 1, ''];
            }
        }

        // Nothing but the event's own lines above: the whole message is the invitation
        return [$floor === 0 ? 0 : ($opening ?? $marker), ''];
    }

    /**
     * The last line of the invitation that starts at $start: the last of Google's closing lines or RSVP links before
     * the next quoted message begins, with the rest of that paragraph.
     *
     * @param  list<string>  $lines
     */
    private static function end(array $lines, int $start): int
    {
        $count = count($lines);
        $end = $start;

        for ($i = $start + 1; $i < $count; $i++) {
            $bare = self::bare($lines[$i]);
            if (preg_match(self::HEADER, $bare) && ! preg_match(self::MARKER, $bare)) {
                break; // an earlier message begins: the invitation ended before it
            }
            if (preg_match(self::CLOSING, $bare) || preg_match(self::MARKER, $bare)) {
                $end = $i;
            }
        }

        // A closing sentence wrapped over several lines belongs with it
        for ($i = $end + 1, $n = 0; $i < $count && $n < 6; $i++, $n++) {
            $bare = self::bare($lines[$i]);
            if ($bare === '' || preg_match(self::HEADER, $bare)) {
                break;
            }
            $end = $i;
        }

        return $end;
    }

    /** @param  list<string>  $lines */
    private static function firstMatch(array $lines, string $pattern, int $from): ?int
    {
        for ($i = $from, $n = count($lines); $i < $n; $i++) {
            if (preg_match($pattern, self::bare($lines[$i]))) {
                return $i;
            }
        }

        return null;
    }

    /** A line without the "> " quote marks in front of it */
    private static function bare(string $line): string
    {
        return trim((string) preg_replace('/^[\s>]+/u', '', $line));
    }

    /** The "> > " quote marks in front of a line, so the note sits at the same depth */
    private static function prefix(string $line): string
    {
        return preg_match('/^((?:\s*>)+\s?)/u', $line, $m) ? $m[1] : '';
    }

    private static function withoutReplyPrefix(string $subject): string
    {
        return trim((string) preg_replace('/^(?:\s*(?:re|aw|réf?|fwd?|tr)\s*:\s*)+/iu', '', $subject));
    }
}
