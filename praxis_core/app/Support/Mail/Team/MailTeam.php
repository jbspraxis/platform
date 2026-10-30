<?php

namespace App\Support\Mail\Team;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailThread;
use App\Models\Mail\MailThreadNote;
use App\Models\User;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailNotices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Teamwork on a conversation in a shared mailbox (owner decision 7, docs/MAIL.md): assignment, Open / Done,
 * internal notes and the activity timeline. Personal mailboxes have none of it.
 *
 * Levels: read can add notes (and delete their own); send can assign and change Open / Done; manage can also
 * delete anyone's note. Only people with send level can be assigned, since an assignee has to be able to reply.
 */
final class MailTeam
{
    public const NOTE_MAX = 5000;

    /** People a conversation in this mailbox can be assigned to, by name */
    public static function assignees(MailAccount $account): Collection
    {
        if (! $account->isShared()) {
            return collect();
        }

        return User::whereIn('id', MailAccess::readers($account))->get()
            ->filter(fn (User $u) => MailAccess::can($u, $account, 'send'))
            ->sortBy(fn (User $u) => mb_strtolower($u->name))
            ->values();
    }

    public static function assign(User $by, MailThread $thread, ?string $userId): void
    {
        $account = self::sharedAccount($by, $thread, 'send');
        $userId = $userId ?: null;
        if ($userId === $thread->assigned_to) {
            return;
        }
        if ($userId !== null) {
            abort_unless(self::assignees($account)->contains('id', $userId), 422, 'That person can’t reply from this mailbox.');
        }

        $before = $thread->assigned_to;
        $thread->forceFill([
            'assigned_to' => $userId,
            'assigned_by' => $userId ? $by->id : null,
            'assigned_at' => $userId ? now() : null,
        ])->save();

        self::log($thread->id, $by->id, $userId ? 'assigned' : 'unassigned', array_filter(['to' => $userId, 'from' => $before]));

        if ($userId && $userId !== $by->id) {
            MailNotices::send(
                [$userId],
                'Conversation assigned to you',
                "{$by->name} assigned you “" . self::subject($thread) . "” in {$account->email}.",
                self::link($thread),
            );
        }
    }

    /** 'open' or 'done' */
    public static function setStatus(User $by, MailThread $thread, string $status): void
    {
        self::sharedAccount($by, $thread, 'send');
        abort_unless(in_array($status, ['open', 'done'], true), 422);
        if ($thread->status === $status) {
            return;
        }

        $thread->forceFill($status === 'done'
            ? ['status' => 'done', 'done_by' => $by->id, 'done_at' => now()]
            : ['status' => 'open', 'done_by' => null, 'done_at' => null])->save();

        self::log($thread->id, $by->id, $status === 'done' ? 'done' : 'reopened');
    }

    /** A client wrote again: a Done conversation is open again (owner decision 7). Called by sync's listener. */
    public static function reopenOnReply(MailThread $thread, int $messageId): bool
    {
        if ($thread->status !== 'done') {
            return false;
        }
        $thread->forceFill(['status' => 'open', 'done_by' => null, 'done_at' => null])->save();
        self::log($thread->id, null, 'reopened', ['reason' => 'reply', 'message_id' => $messageId]);

        return true;
    }

    public static function addNote(User $by, MailThread $thread, string $body): MailThreadNote
    {
        self::sharedAccount($by, $thread, 'read');
        $body = trim($body);
        abort_if($body === '' || mb_strlen($body) > self::NOTE_MAX, 422);

        $note = MailThreadNote::create(['thread_id' => $thread->id, 'user_id' => $by->id, 'body' => $body]);
        self::log($thread->id, $by->id, 'note', ['note_id' => $note->id]);

        return $note;
    }

    public static function deleteNote(User $by, MailThreadNote $note): void
    {
        $thread = MailThread::findOrFail($note->thread_id);
        $account = self::sharedAccount($by, $thread, 'read');
        abort_unless($note->user_id === $by->id || MailAccess::can($by, $account, 'manage'), 403);

        $note->delete();
        self::log($thread->id, $by->id, 'note_deleted', ['note_id' => $note->id]);
    }

    /** @return Collection<int, MailThreadNote> newest last, with ->user */
    public static function notes(User $user, MailThread $thread): Collection
    {
        self::sharedAccount($user, $thread, 'read');

        return MailThreadNote::with('user')->where('thread_id', $thread->id)->orderBy('created_at')->orderBy('id')->get();
    }

    /**
     * The timeline, newest first, each with a readable sentence.
     *
     * @return list<array{type: string, text: string, at: Carbon}>
     */
    public static function activity(User $user, MailThread $thread, int $limit = 30): array
    {
        self::sharedAccount($user, $thread, 'read');

        $rows = DB::table('mail_thread_activity')->where('thread_id', $thread->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
        $ids = $rows->pluck('user_id')->merge($rows->map(fn ($r) => json_decode((string) $r->data, true)['to'] ?? null))->filter()->unique();
        $names = User::whereIn('id', $ids)->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name]);

        return $rows->map(function ($r) use ($names, $user) {
            $data = json_decode((string) $r->data, true) ?: [];
            $who = $r->user_id ? ($r->user_id === $user->id ? 'You' : ($names[$r->user_id] ?? 'Someone')) : null;
            $to = isset($data['to']) ? ($data['to'] === $user->id ? 'you' : ($names[$data['to']] ?? 'someone')) : null;
            $text = match ($r->type) {
                'assigned' => $r->user_id === ($data['to'] ?? null) ? "{$who} took this conversation" : "{$who} assigned this to {$to}",
                'unassigned' => "{$who} removed the assignment",
                'done' => "{$who} marked this done",
                'reopened' => ($data['reason'] ?? null) === 'reply' ? 'Opened again: a new reply came in' : "{$who} opened this again",
                'note' => "{$who} added a note",
                'note_deleted' => "{$who} deleted a note",
                'merged' => 'Joined with another conversation about the same mail',
                'sent' => ($who ?? 'Someone') . ' replied',
                default => ($who ? "{$who}: " : '') . str_replace('_', ' ', $r->type),
            };

            return ['type' => $r->type, 'text' => $text, 'at' => Carbon::parse($r->created_at)];
        })->values()->all();
    }

    public static function log(int $threadId, ?string $userId, string $type, array $data = []): void
    {
        DB::table('mail_thread_activity')->insert([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'type' => $type,
            'data' => $data ? json_encode($data) : null,
            'created_at' => now(),
        ]);
    }

    public static function link(MailThread $thread): string
    {
        return route('hq.mail.inbox', ['box' => $thread->account_id, 'thread' => $thread->id], false);
    }

    public static function subject(MailThread $thread): string
    {
        return mb_strimwidth($thread->subject ?: '(no subject)', 0, 80, '…');
    }

    /** The thread's mailbox, after checking it is shared and the person has this level on it (else 403) */
    private static function sharedAccount(User $user, MailThread $thread, string $ability): MailAccount
    {
        $account = $thread->relationLoaded('account') ? $thread->account : MailAccount::find($thread->account_id);
        abort_unless($account && $account->isShared(), 404);
        MailAccess::authorize($user, $account, $ability);

        return $account;
    }
}
