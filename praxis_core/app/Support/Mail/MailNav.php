<?php

namespace App\Support\Mail;

use App\Models\Mail\MailFolder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** The unread count next to Mail in the rail: unread Inbox mail in every mailbox the person can open (cached 30 s). */
final class MailNav
{
    public static function unread(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) Cache::remember('mail-nav-unread:' . $user->id, 30, function () use ($user) {
            $ids = MailAccess::accountIds($user);

            return $ids ? (int) MailFolder::whereIn('account_id', $ids)->where('role', 'inbox')->sum('unread_count') : 0;
        });
    }

    public static function forget(string $userId): void
    {
        Cache::forget('mail-nav-unread:' . $userId);
    }
}
