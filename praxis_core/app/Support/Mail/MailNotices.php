<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bell notifications about mailboxes themselves (PR 1). New-mail notifications are PR 4's
 * (a MailReceived listener). Rows go into `notifications`, so the HQ app pushes them like any other.
 */
final class MailNotices
{
    public static function needsPassword(MailAccount $account): void
    {
        self::send(
            MailAccess::managers($account),
            'Mail needs a password',
            "{$account->email} stopped updating in HQ: the mail server refused its password. Enter the current password on Mailboxes.",
            route('hq.mail.mailboxes', ['box' => $account->id], false),
        );
    }

    public static function memberAdded(MailAccount $account, User $person, string $level, ?User $by): void
    {
        $what = MailAccess::RANK[$level] >= MailAccess::RANK['send'] ? 'read and send from' : 'read';
        if ($level === 'manage') {
            $what = 'read, send from and manage';
        }
        self::send(
            [$person->id],
            'You have a shared mailbox',
            ($by ? $by->name . ' gave you' : 'You now have') . " access to {$account->email}: you can {$what} it in HQ Mail.",
            route('hq.mail.inbox', ['box' => $account->id], false),
        );
    }

    /** @param  list<string>  $userIds */
    public static function send(array $userIds, string $title, string $message, ?string $link): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (! $userIds) {
            return;
        }
        $now = now();
        DB::table('notifications')->insert(array_map(fn ($id) => [
            'id' => (string) Str::uuid(),
            'user_id' => $id,
            'title' => mb_substr($title, 0, 150),
            'message' => $message,
            'link_url' => $link !== null ? mb_substr($link, 0, 255) : null,
            'type' => 'Message',
            'is_read' => 0,
            'created_at' => $now,
        ], $userIds));
    }
}
