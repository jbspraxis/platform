<?php

namespace App\Support\Mail\Team;

use App\Models\Mail\MailThread;
use App\Models\User;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\DB;

/**
 * Who has a conversation on screen, and who is writing a reply to it (the "someone is replying" warning,
 * owner decision 7). Rows in mail_presence last config('mail_team.presence_seconds'); the screen refreshes
 * them while it is open, and mail:prune clears the expired ones.
 */
final class MailPresence
{
    /** 'viewing' keeps an unexpired 'replying': the composer says when it stops */
    public static function touch(User $user, MailThread $thread, string $state = 'viewing'): void
    {
        if (! in_array($state, ['viewing', 'replying'], true) || ! MailAccess::canThread($user, $thread, 'read')) {
            return;
        }

        $expires = now()->addSeconds((int) config('mail_team.presence_seconds', 60));
        if ($state === 'viewing') {
            $replying = DB::table('mail_presence')->where('thread_id', $thread->id)->where('user_id', $user->id)
                ->where('state', 'replying')->where('expires_at', '>', now())->exists();
            if ($replying) {
                $state = 'replying';
            }
        }

        DB::table('mail_presence')->upsert(
            [['thread_id' => $thread->id, 'user_id' => $user->id, 'state' => $state, 'expires_at' => $expires]],
            ['thread_id', 'user_id'],
            ['state', 'expires_at'],
        );
    }

    /** The person stopped replying: back to viewing */
    public static function stopReplying(User $user, MailThread $thread): void
    {
        DB::table('mail_presence')->where('thread_id', $thread->id)->where('user_id', $user->id)->update(['state' => 'viewing']);
    }

    public static function leave(User $user, int $threadId): void
    {
        DB::table('mail_presence')->where('thread_id', $threadId)->where('user_id', $user->id)->delete();
    }

    /**
     * Everyone else on this conversation now, those replying first.
     *
     * @return list<array{user_id: string, name: string, state: string}>
     */
    public static function others(User $user, MailThread $thread): array
    {
        if (! MailAccess::canThread($user, $thread, 'read')) {
            return [];
        }

        $rows = DB::table('mail_presence')->where('thread_id', $thread->id)->where('user_id', '!=', $user->id)
            ->where('expires_at', '>', now())->get();
        $names = User::whereIn('id', $rows->pluck('user_id'))->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name]);

        return $rows->filter(fn ($r) => isset($names[$r->user_id]))
            ->sortBy(fn ($r) => ($r->state === 'replying' ? '0' : '1') . $names[$r->user_id])
            ->map(fn ($r) => ['user_id' => $r->user_id, 'name' => $names[$r->user_id], 'state' => $r->state])
            ->values()->all();
    }
}
