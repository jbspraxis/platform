<?php

namespace App\Support\Mail\Read;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Models\User;
use App\Support\Mail\MailAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The conversation list on the Mail page: a folder's conversations, or a search over HQ's copy (the last 12
 * months), with the filters and a page at a time. Each row is a MailThread with these extra fields, counted
 * over the messages in view (the folder's, or the search's): row_unread, row_flagged, row_last_at,
 * row_count, row_attach and, in search, row_folders (folder ids).
 *
 * Filters: all, unread, mine (assigned to the person), open, done (the last three are teamwork, PR 4).
 */
final class ThreadList
{
    public const PER_PAGE = 50;

    public const FILTERS = ['all', 'unread', 'mine', 'open', 'done'];

    /** @return array{rows: \Illuminate\Support\Collection<int, MailThread>, total: int} */
    public static function folder(User $user, MailFolder $folder, string $filter = 'all', int $page = 1): array
    {
        abort_unless(MailAccess::can($user, $folder->account, 'read'), 403);

        $messages = MailMessage::query()->where('folder_id', $folder->id);

        return self::page($user, $folder->account_id, $messages, $filter, $page, false);
    }

    /**
     * Search HQ's copy of one mailbox. Words match the subject, people, addresses and text (all of them must
     * match); "from:ada" looks at the sender only. Spam and Trash are searched only from inside them.
     *
     * @return array{rows: \Illuminate\Support\Collection<int, MailThread>, total: int}
     */
    public static function search(User $user, MailAccount $account, string $query, ?MailFolder $within = null, string $filter = 'all', int $page = 1): array
    {
        abort_unless(MailAccess::can($user, $account, 'read'), 403);

        $messages = MailMessage::query()->where('mail_messages.account_id', $account->id);
        if ($within && in_array($within->role, ['spam', 'trash'], true)) {
            $messages->where('folder_id', $within->id);
        } else {
            $messages->whereNotIn('folder_id', MailFolder::where('account_id', $account->id)->whereIn('role', ['spam', 'trash'])->select('id'));
        }

        [$words, $from] = self::parse($query);
        if (! $words && ! $from) {
            return ['rows' => collect(), 'total' => 0];
        }
        foreach ($from as $f) {
            $messages->where(fn ($q) => $q->where('from_email', 'like', '%' . self::like($f) . '%')->orWhere('from_name', 'like', '%' . self::like($f) . '%'));
        }

        if ($words && DB::getDriverName() === 'mysql') {
            $full = self::fullText($words);
            if ($full !== '') {
                $found = (clone $messages)->whereRaw('MATCH(subject, from_name, from_email, to_text, body_text) AGAINST (? IN BOOLEAN MODE)', [$full]);
                $result = self::page($user, $account->id, $found, $filter, $page, true);
                if ($result['total'] > 0) {
                    return $result;
                }
                // Nothing: the index skips very common and very short words, and mail stored a moment ago; look again word by word
            }
        }

        foreach ($words as $word) {
            $like = '%' . self::like($word) . '%';
            $messages->where(fn ($q) => $q->where('subject', 'like', $like)->orWhere('from_name', 'like', $like)
                ->orWhere('from_email', 'like', $like)->orWhere('to_text', 'like', $like)->orWhere('body_text', 'like', $like));
        }

        return self::page($user, $account->id, $messages, $filter, $page, true);
    }

    /** @return array{0: list<string>, 1: list<string>} words, from: values */
    public static function parse(string $query): array
    {
        $words = $from = [];
        preg_match_all('/(from:)?("([^"]*)"|\S+)/iu', mb_substr(trim($query), 0, 200), $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $value = trim($m[3] ?? '') !== '' ? trim($m[3]) : trim($m[2], '"');
            if ($value === '') {
                continue;
            }
            if ($m[1] !== '') {
                $from[] = $value;
            } else {
                $words[] = $value;
            }
        }

        return [array_slice($words, 0, 8), array_slice($from, 0, 3)];
    }

    /** MySQL boolean mode: every word required, as a prefix; the index's own word breaks (so "ada@client.com" is three words) */
    private static function fullText(array $words): string
    {
        $terms = [];
        foreach ($words as $word) {
            foreach (preg_split('/[^\p{L}\p{N}_]+/u', $word) ?: [] as $token) {
                if (mb_strlen($token) >= 3) {
                    $terms[] = '+' . $token . '*';
                }
            }
        }

        return implode(' ', array_unique($terms));
    }

    private static function like(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private static function page(User $user, string $accountId, $messages, string $filter, int $page, bool $search): array
    {
        $agg = (clone $messages)->whereNotNull('thread_id')->groupBy('thread_id')->selectRaw(
            'thread_id, SUM(CASE WHEN is_seen = 0 THEN 1 ELSE 0 END) AS row_unread, MAX(is_flagged) AS row_flagged, '
            . 'MAX(received_at) AS row_last_at, COUNT(*) AS row_count, MAX(has_attachments) AS row_attach'
            . ($search ? ', GROUP_CONCAT(DISTINCT folder_id) AS row_folders' : '')
        );
        $agg->getQuery()->orders = null;

        $query = MailThread::query()
            ->joinSub($agg->toBase(), 'agg', 'agg.thread_id', '=', 'mail_threads.id')
            ->where('mail_threads.account_id', $accountId)
            ->select('mail_threads.*', 'agg.row_unread', 'agg.row_flagged', 'agg.row_last_at', 'agg.row_count', 'agg.row_attach');
        if ($search) {
            $query->addSelect('agg.row_folders');
        }
        self::filter($query, $user, $filter);

        $total = (clone $query)->count();
        $page = max(1, min($page, (int) ceil(max($total, 1) / self::PER_PAGE)));
        $rows = $query->with('assignee:id,first_name,last_name')
            ->orderByDesc('agg.row_last_at')->orderByDesc('mail_threads.id')
            ->offset(($page - 1) * self::PER_PAGE)->limit(self::PER_PAGE)->get();

        return ['rows' => $rows, 'total' => $total, 'page' => $page];
    }

    private static function filter(Builder $query, User $user, string $filter): void
    {
        match ($filter) {
            'unread' => $query->where('agg.row_unread', '>', 0),
            'mine' => $query->where('mail_threads.assigned_to', $user->id),
            'open' => $query->where('mail_threads.status', 'open'),
            'done' => $query->where('mail_threads.status', 'done'),
            default => null,
        };
    }
}
