<?php

namespace App\Support\Mail\Ai;

use App\Models\Mail\MailFolder;
use App\Models\User;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds the mail that answers a question, over HQ's copy and only in the mailboxes the person can read
 * (docs/MAIL.md 7.6). The contract for Praxis AI: Ask about your mail uses it now, a future HQ-wide
 * Ask your company can call it as is. Never Spam, Trash or Drafts; never attachments.
 *
 * MySQL FULLTEXT (mail_messages_search) first; words it can't see (shorter than three letters, or mail
 * not indexed yet) are found with LIKE over the same columns.
 */
final class MailSearch
{
    private const SKIP_ROLES = ['spam', 'trash', 'drafts'];

    private const FILLER = ['a', 'about', 'after', 'all', 'am', 'an', 'and', 'any', 'anything', 'are', 'as', 'ask', 'asked', 'at', 'be',
        'been', 'before', 'by', 'can', 'could', 'did', 'do', 'does', 'email', 'emails', 'find', 'for', 'from', 'get', 'got', 'had', 'has',
        'have', 'he', 'her', 'him', 'his', 'how', 'i', 'if', 'in', 'is', 'it', 'its', 'know', 'last', 'latest', 'let', 'mail', 'mails',
        'me', 'message', 'messages', 'my', 'of', 'on', 'or', 'our', 'please', 'recent', 'regarding', 'said', 'say', 'says', 'sent',
        'she', 'show', 'so', 'tell', 'that', 'the', 'their', 'them', 'there', 'they', 'this', 'to', 'up', 'us', 'was', 'we', 'were',
        'what', 'whats', 'when', 'where', 'which', 'who', 'why', 'will', 'with', 'would', 'wrote', 'you', 'your'];

    /**
     * @param  list<string>|null  $accountIds  narrow to these mailboxes (still only those the person can read)
     * @return list<array{message_id: int, thread_id: int, account_id: string, subject: string, from: string, date: ?string, excerpt: string}>
     */
    public static function for(User $user, string $question, int $limit = 20, ?array $accountIds = null): array
    {
        $readable = MailAccess::accountIds($user, 'read');
        $accounts = $accountIds === null ? $readable : array_values(array_intersect($readable, $accountIds));
        if (! $accounts || $limit < 1) {
            return [];
        }
        $folders = MailFolder::whereIn('account_id', $accounts)->whereNotIn('role', self::SKIP_ROLES)->pluck('id')->all();
        if (! $folders) {
            return [];
        }

        $terms = self::terms($question);
        $base = fn () => DB::table('mail_messages')->whereIn('account_id', $accounts)->whereIn('folder_id', $folders)
            ->whereNotNull('thread_id')
            ->select(['id', 'thread_id', 'account_id', 'message_id_hash', 'subject', 'from_name', 'from_email', 'received_at', 'snippet', 'body_text']);

        if (! $terms) {
            $rows = $base()->orderByDesc('received_at')->limit($limit * 2)->get();

            return self::shape(self::unique($rows), [], $limit);
        }

        $found = collect();
        if (DB::getDriverName() === 'mysql') {
            $found = $base()
                ->selectRaw('MATCH(subject, from_name, from_email, to_text, body_text) AGAINST (? IN NATURAL LANGUAGE MODE) AS score', [implode(' ', $terms)])
                ->whereRaw('MATCH(subject, from_name, from_email, to_text, body_text) AGAINST (? IN NATURAL LANGUAGE MODE)', [implode(' ', $terms)])
                ->orderByDesc('score')->orderByDesc('received_at')->limit($limit * 3)->get();
        }

        if ($found->count() < $limit) {
            $like = $base()->where(function ($q) use ($terms) {
                foreach ($terms as $t) {
                    $p = '%' . addcslashes($t, '\\%_') . '%';
                    $q->orWhere('subject', 'like', $p)->orWhere('from_name', 'like', $p)->orWhere('from_email', 'like', $p)
                        ->orWhere('to_text', 'like', $p)->orWhere('body_text', 'like', $p);
                }
            })->whereNotIn('id', $found->pluck('id'))->orderByDesc('received_at')->limit(300)->get();

            // More of the question's words first, then newer
            $like = $like->sortByDesc(fn ($r) => sprintf('%03d%s', self::hits($r, $terms), $r->received_at))->values();
            $found = $found->concat($like);
        }

        return self::shape(self::unique($found), $terms, $limit);
    }

    /** @return list<string> */
    public static function terms(string $question): array
    {
        $words = preg_split('/[^\p{L}\p{N}@._\'-]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map(fn ($w) => trim($w, "._'-"), $words);
        $words = array_filter($words, fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, self::FILLER, true));

        return array_slice(array_values(array_unique($words)), 0, 8);
    }

    private static function hits(object $row, array $terms): int
    {
        $hay = mb_strtolower(implode(' ', [$row->subject, $row->from_name, $row->from_email, $row->body_text]));

        return count(array_filter($terms, fn ($t) => str_contains($hay, $t)));
    }

    /** One per message (a copy in Inbox and Archive counts once), best first */
    private static function unique(Collection $rows): Collection
    {
        return $rows->unique(fn ($r) => $r->message_id_hash ?: 'id' . $r->id)->values();
    }

    private static function shape(Collection $rows, array $terms, int $limit): array
    {
        return $rows->take($limit)->map(fn ($r) => [
            'message_id' => (int) $r->id,
            'thread_id' => (int) $r->thread_id,
            'account_id' => (string) $r->account_id,
            'subject' => (string) ($r->subject ?: '(no subject)'),
            'from' => trim(($r->from_name ? $r->from_name . ' ' : '') . ($r->from_email ? '<' . $r->from_email . '>' : '')) ?: 'Unknown sender',
            'date' => $r->received_at ? Carbon::parse($r->received_at)->toIso8601String() : null,
            'excerpt' => self::excerpt((string) $r->body_text, (string) $r->snippet, $terms),
        ])->values()->all();
    }

    private static function excerpt(string $body, string $snippet, array $terms, int $width = 400): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', MailText::withoutQuotes($body)));
        if ($text === '') {
            return $snippet;
        }
        $at = null;
        foreach ($terms as $t) {
            $p = mb_stripos($text, $t);
            if ($p !== false && ($at === null || $p < $at)) {
                $at = $p;
            }
        }
        $start = max(0, ($at ?? 0) - 120);
        $piece = mb_substr($text, $start, $width);

        return ($start > 0 ? '… ' : '') . $piece . (mb_strlen($text) > $start + $width ? ' …' : '');
    }
}
