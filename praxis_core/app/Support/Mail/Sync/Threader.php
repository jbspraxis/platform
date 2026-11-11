<?php

namespace App\Support\Mail\Sync;

use App\Events\Mail\MailThreadsMerged;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use Illuminate\Support\Facades\DB;

/**
 * Puts each new message in a conversation (docs/MAIL.md, "Threads"):
 *   1. a message it answers (In-Reply-To, References), or another copy of itself, is already in a thread → that thread;
 *      if those sit in several threads they are merged into the oldest (MailThreadsMerged lets PR 4 follow);
 *   2. a message already here answers it (it arrived after its reply) → that thread;
 *   3. a reply ("Re:", "Fwd:" or an unknown In-Reply-To) whose subject matches a thread from the last 30 days
 *      that shares a person with it → that thread;
 *   4. otherwise a new thread.
 */
final class Threader
{
    private const PREFIX = '/^\s*((re|fw|fwd|tr|aw|wg|sv|vs|rif|antw|réf|ref|r)\s*(\[\d+\]|\(\d+\))?\s*[:：]\s*)+/iu';

    public static function subjectKey(?string $subject): string
    {
        $key = mb_strtolower(trim((string) $subject));
        $key = preg_replace(self::PREFIX, '', $key) ?? $key;
        $key = trim(preg_replace('/\s+/u', ' ', $key) ?? $key);

        return mb_substr($key, 0, 191);
    }

    public static function isReplySubject(?string $subject): bool
    {
        return (bool) preg_match(self::PREFIX, (string) $subject);
    }

    public static function assign(MailMessage $message): int
    {
        $ownHash = MailMessage::hashId($message->message_id);
        $hashes = array_values(array_filter(array_unique(array_merge(
            array_map([MailMessage::class, 'hashId'], $message->referenceIds()),
            [$ownHash],
        ))));

        $threadIds = [];
        if ($hashes) {
            $threadIds = MailMessage::where('account_id', $message->account_id)
                ->whereIn('message_id_hash', $hashes)
                ->where('id', '!=', $message->id)
                ->whereNotNull('thread_id')
                ->distinct()->pluck('thread_id')->all();
        }
        if ($message->message_id) {
            $threadIds = array_merge($threadIds, MailMessage::where('account_id', $message->account_id)
                ->where('in_reply_to', trim($message->message_id, '<> '))
                ->where('id', '!=', $message->id)
                ->whereNotNull('thread_id')
                ->distinct()->pluck('thread_id')->all());
        }
        $threadIds = array_values(array_unique(array_map('intval', $threadIds)));

        if ($threadIds) {
            sort($threadIds);
            $threadId = $threadIds[0];
            if (count($threadIds) > 1) {
                self::merge(array_slice($threadIds, 1), $threadId);
            }
        } else {
            $threadId = self::bySubject($message) ?? self::create($message);
        }

        $message->forceFill(['thread_id' => $threadId])->save();
        self::note($threadId, $message);

        return $threadId;
    }

    /**
     * Keep the thread's people and dates current as each message joins, so later messages in the same sync
     * batch can match it (ThreadCounters redoes the full counts at the end of the batch).
     */
    private static function note(int $threadId, MailMessage $message): void
    {
        $thread = MailThread::find($threadId);
        if (! $thread) {
            return;
        }
        $people = [];
        foreach (array_merge($thread->participants ?? [], [['name' => $message->from_name, 'email' => $message->from_email]], $message->to ?? [], $message->cc ?? []) as $p) {
            $email = strtolower(trim((string) ($p['email'] ?? '')));
            if ($email !== '') {
                $people[$email] ??= ['name' => $p['name'] ?? null, 'email' => $email];
            }
        }
        $at = $message->received_at;
        $thread->forceFill([
            'participants' => array_slice(array_values($people), 0, 12),
            'first_message_at' => $at && (! $thread->first_message_at || $at->lt($thread->first_message_at)) ? $at : $thread->first_message_at,
            'last_message_at' => $at && (! $thread->last_message_at || $at->gt($thread->last_message_at)) ? $at : $thread->last_message_at,
        ])->save();
    }

    /** Move everything of $fromIds into $intoId and delete the emptied threads */
    public static function merge(array $fromIds, int $intoId): void
    {
        $fromIds = array_values(array_diff(array_map('intval', $fromIds), [$intoId]));
        if (! $fromIds) {
            return;
        }

        DB::transaction(function () use ($fromIds, $intoId) {
            $into = MailThread::lockForUpdate()->find($intoId);
            $sources = MailThread::whereIn('id', $fromIds)->orderBy('id')->get();

            // Keep the first assignment and the open state if any of them is still open
            foreach ($sources as $s) {
                if (! $into->assigned_to && $s->assigned_to) {
                    $into->fill(['assigned_to' => $s->assigned_to, 'assigned_by' => $s->assigned_by, 'assigned_at' => $s->assigned_at]);
                }
                if ($s->status === 'open') {
                    $into->fill(['status' => 'open', 'done_by' => null, 'done_at' => null]);
                }
            }
            $into->save();

            MailMessage::whereIn('thread_id', $fromIds)->update(['thread_id' => $intoId]);
            DB::table('mail_thread_notes')->whereIn('thread_id', $fromIds)->update(['thread_id' => $intoId]);
            DB::table('mail_thread_activity')->whereIn('thread_id', $fromIds)->update(['thread_id' => $intoId]);
            DB::table('mail_presence')->whereIn('thread_id', $fromIds)->delete();
            DB::table('mail_outbox')->whereIn('thread_id', $fromIds)->update(['thread_id' => $intoId]);
            MailThread::whereIn('id', $fromIds)->delete();
        });

        event(new MailThreadsMerged($fromIds, $intoId));
    }

    /**
     * Same subject and a person in common (not the mailbox itself, which is on every message), within 30 days.
     * A reply joins an earlier thread; an original joins a thread that so far holds only replies to it (a first
     * copy reads newest first, so replies often arrive before what they answer).
     */
    private static function bySubject(MailMessage $message): ?int
    {
        $key = self::subjectKey($message->subject);
        if (mb_strlen($key) < 4) {
            return null;
        }

        // The mailbox's own address, read once per mailbox (sync files messages one by one)
        static $emails = [];
        $own = $emails[$message->account_id] ??= strtolower((string) MailAccount::whereKey($message->account_id)->value('email'));
        $people = array_values(array_diff(self::emails($message), [$own]));
        if (! $people) {
            return null;
        }

        $isReply = self::isReplySubject($message->subject) || $message->in_reply_to;
        $when = $message->received_at ?? now();
        $days = (int) config('mail_client.thread_subject_days', 30);

        $query = MailThread::where('account_id', $message->account_id)->where('subject_key', $key);
        if ($isReply) {
            $query->where('last_message_at', '>=', $when->copy()->subDays($days))->orderByDesc('last_message_at');
        } else {
            $query->whereBetween('first_message_at', [$when, $when->copy()->addDays($days)])->orderBy('first_message_at');
        }

        foreach ($query->limit(10)->get() as $thread) {
            if (! $isReply && ! self::isReplySubject($thread->subject)) {
                continue; // two originals with the same subject are two conversations
            }
            $theirs = array_map(fn ($p) => strtolower($p['email'] ?? ''), $thread->participants ?? []);
            if (array_intersect($people, $theirs)) {
                return $thread->id;
            }
        }

        return null;
    }

    private static function create(MailMessage $message): int
    {
        return MailThread::create([
            'account_id' => $message->account_id,
            'subject' => $message->subject,
            'subject_key' => self::subjectKey($message->subject),
            'first_message_at' => $message->received_at,
            'last_message_at' => $message->received_at,
        ])->id;
    }

    private static function emails(MailMessage $message): array
    {
        $out = [strtolower((string) $message->from_email)];
        foreach (['to', 'cc'] as $field) {
            foreach ($message->{$field} ?? [] as $a) {
                $out[] = strtolower($a['email'] ?? '');
            }
        }

        return array_values(array_filter(array_unique($out)));
    }
}
