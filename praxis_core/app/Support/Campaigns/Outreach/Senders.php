<?php

namespace App\Support\Campaigns\Outreach;

use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Support\Access;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Each salesperson's outreach sending (owner's decisions 1 and 2, 3 Nov 2026). Emails go from their own jbspraxis.com
 * mailbox as connected in HQ Mail (a personal mail_accounts row), never from another domain. Their state is in
 * outreach_senders: when they started (the first weeks' lower limit runs from their first outreach email) and an
 * automatic pause:
 *
 *   bounces    more of that day's emails bounced than the limit allows (5% by default)
 *   complaint  someone marked one of their outreach emails as spam
 *   mailbox    their mailbox refused its password
 *
 * A pause rings the sender and everyone with outreach.approve (the owner); only outreach.approve resumes.
 */
final class Senders
{
    public const PAUSES = [
        'bounces' => 'Too many emails bounced today',
        'complaint' => 'Someone marked an email as spam',
        'mailbox' => 'The mailbox refused its password',
    ];

    /** Their own connected mailbox (personal), whatever its state */
    public static function mailbox(?string $userId): ?MailAccount
    {
        return $userId ? MailAccount::where('kind', 'personal')->where('owner_user_id', $userId)->orderBy('created_at')->first() : null;
    }

    /**
     * Why emails can't go from this person's mailbox now, in words for the screen (how to fix it); null when they can.
     */
    public static function mailboxProblem(?User $user): ?string
    {
        if (! $user || $user->status !== 'Active') {
            return 'This sender’s HQ account isn’t active, so nothing goes out. Choose another sender for the campaign.';
        }
        if (! MailAccess::canUse($user)) {
            return 'HQ Mail isn’t open to ' . self::firstName($user) . ' yet, so Outreach can’t send from their mailbox. The owner opens Mail to everyone in Mail › Mailboxes.';
        }
        $account = self::mailbox($user->id);
        if (! $account) {
            return 'Connect ' . (auth()->id() === $user->id ? 'your' : self::firstName($user) . '’s') . ' jbspraxis.com mailbox first: open Mail › Mailboxes, and under Your mailbox enter the address and its password. Outreach sends from it, so replies come back to the same inbox.';
        }
        if ($account->status !== 'active') {
            return 'The mailbox ' . $account->email . ' needs its password again: open Mail › Mailboxes and choose Reconnect. Nothing goes out until then.';
        }

        return null;
    }

    public static function state(string $userId): ?object
    {
        return DB::table('outreach_senders')->where('user_id', $userId)->first();
    }

    public static function isPaused(string $userId): bool
    {
        return DB::table('outreach_senders')->where('user_id', $userId)->whereNotNull('paused_at')->exists();
    }

    /** Their first outreach email starts the lower limit's weeks */
    public static function started(string $userId, Carbon $at): void
    {
        DB::table('outreach_senders')->insertOrIgnore(['user_id' => $userId, 'created_at' => $at, 'updated_at' => $at]);
        DB::table('outreach_senders')->where('user_id', $userId)->whereNull('first_sent_at')->update(['first_sent_at' => $at, 'updated_at' => $at]);
    }

    public static function firstSentAt(string $userId): ?Carbon
    {
        $at = DB::table('outreach_senders')->where('user_id', $userId)->value('first_sent_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * Stops every outreach email from this person until someone with outreach.approve resumes them. Rings them and the
     * owner once (a second reason while paused changes nothing). Returns whether it paused them now.
     */
    public static function pause(string $userId, string $reason, string $note): bool
    {
        $now = now();
        DB::table('outreach_senders')->insertOrIgnore(['user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        $paused = DB::table('outreach_senders')->where('user_id', $userId)->whereNull('paused_at')
            ->update(['paused_at' => $now, 'paused_reason' => $reason, 'paused_note' => mb_substr($note, 0, 255), 'updated_at' => $now]) > 0;
        if (! $paused) {
            return false;
        }

        $name = self::firstName(User::find($userId));
        $ids = collect(Access::staffWith('outreach.approve'))->push($userId)->unique()->all();
        self::bell($ids, 'Outreach paused for ' . $name, $note . ' Nothing more goes out from ' . $name . '’s mailbox until it is resumed in Outreach › Sending limits.');

        return true;
    }

    public static function resume(string $userId, User $by): bool
    {
        return DB::table('outreach_senders')->where('user_id', $userId)->whereNotNull('paused_at')
            ->update(['paused_at' => null, 'paused_reason' => null, 'paused_note' => null, 'resumed_by' => $by->id, 'resumed_at' => now(), 'updated_at' => now()]) > 0;
    }

    /** Emails the sender has tried today (Lagos day): sent, bounced, failed and the one going now. Follow-ups count. */
    public static function triedToday(string $userId, ?Carbon $at = null): int
    {
        return DB::table('outreach_emails')->where('sender_id', $userId)->where('tried_at', '>=', Limits::dayStart($at))
            ->whereIn('status', ['sending', 'sent', 'bounced', 'failed'])->count();
    }

    /** [bounced, tried] today */
    public static function bouncesToday(string $userId, ?Carbon $at = null): array
    {
        $row = DB::table('outreach_emails')->where('sender_id', $userId)->where('tried_at', '>=', Limits::dayStart($at))
            ->whereIn('status', ['sent', 'bounced'])->selectRaw("SUM(status = 'bounced') AS b, COUNT(*) AS n")->first();

        return [(int) ($row->b ?? 0), (int) ($row->n ?? 0)];
    }

    public static function lastTriedAt(string $userId): ?Carbon
    {
        $at = DB::table('outreach_emails')->where('sender_id', $userId)->max('tried_at');

        return $at ? Carbon::parse($at) : null;
    }

    public static function limitToday(string $userId, ?Carbon $at = null): int
    {
        return Limits::dailyLimit(self::firstSentAt($userId), $at);
    }

    /**
     * What the screens show about a sender: their mailbox, today's count and limit, a pause.
     *
     * @return array{mailbox: ?string, problem: ?string, tried: int, limit: int, paused: ?object}
     */
    public static function summary(?User $user): array
    {
        $state = $user ? self::state($user->id) : null;

        return [
            'mailbox' => $user ? self::mailbox($user->id)?->email : null,
            'problem' => self::mailboxProblem($user),
            'tried' => $user ? self::triedToday($user->id) : 0,
            'limit' => $user ? self::limitToday($user->id) : (int) Limits::get('first_weeks_limit'),
            'paused' => $state && $state->paused_at ? $state : null,
        ];
    }

    public static function firstName(?User $user): string
    {
        return $user ? (trim((string) $user->first_name) ?: (string) $user->name) : 'Someone';
    }

    /** Bell notifications (type System), opening Outreach */
    public static function bell(array $userIds, string $title, string $message, ?string $link = null): void
    {
        $ids = DB::table('users')->whereIn('id', array_filter($userIds))->where('status', 'Active')->whereNull('deleted_at')->pluck('id');
        $link ??= route('hq.campaigns.outreach', [], false);
        $rows = $ids->map(fn ($id) => [
            'id' => (string) Str::uuid(), 'user_id' => $id, 'title' => Str::limit($title, 150, '…'), 'message' => Str::limit($message, 500, '…'),
            'link_url' => $link, 'type' => 'System', 'is_read' => 0, 'created_at' => now(),
        ])->all();
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }
}
