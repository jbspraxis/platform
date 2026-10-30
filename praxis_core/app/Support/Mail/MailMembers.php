<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAccountMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who shares a shared mailbox and at what level (owner decision 3: Read, Read and send, Manager). Only its
 * managers (and Owner accounts) change this. A mailbox always keeps at least one manager of its own, so it
 * is never left to the Owner accounts alone by accident. Every change is audited and the person is told.
 */
final class MailMembers
{
    public static function add(User $by, MailAccount $account, User $person, string $level): MailAccountMember
    {
        self::guard($by, $account, $level);
        if ($person->status !== 'Active' || ! $person->can('mail.view')) {
            throw ValidationException::withMessages(['invite_user' => "{$person->name} can't use Mail. Give their role the Mail permission first (Settings and access, Roles and permissions)."]);
        }
        if (MailAccountMember::where('account_id', $account->id)->where('user_id', $person->id)->exists()) {
            throw ValidationException::withMessages(['invite_user' => "{$person->name} already has access. Change their level in the list instead."]);
        }

        $member = MailAccountMember::create(['account_id' => $account->id, 'user_id' => $person->id, 'level' => $level, 'added_by' => $by->id]);
        MailConnector::audit($by, 'MAIL_MEMBER_ADDED', "Gave {$person->name} " . MailAccountMember::LEVEL_LABELS[$level] . " access to {$account->email}.", $account, null, ['user_id' => $person->id, 'level' => $level]);
        MailNotices::memberAdded($account, $person, $level, $by);
        MailAccess::flush();

        return $member;
    }

    public static function change(User $by, MailAccount $account, MailAccountMember $member, string $level): void
    {
        self::guard($by, $account, $level);
        abort_unless($member->account_id === $account->id, 404);
        if ($member->level === $level) {
            return;
        }
        if ($member->level === 'manage' && $level !== 'manage') {
            self::keepAManager($account, $member);
        }

        $old = $member->level;
        $member->update(['level' => $level]);
        $name = $member->user?->name ?? 'A person';
        MailConnector::audit($by, 'MAIL_MEMBER_CHANGED', "Changed {$name}'s access to {$account->email} from " . MailAccountMember::LEVEL_LABELS[$old] . ' to ' . MailAccountMember::LEVEL_LABELS[$level] . '.', $account, ['level' => $old], ['level' => $level]);
        MailAccess::flush();
    }

    public static function remove(User $by, MailAccount $account, MailAccountMember $member): void
    {
        MailAccess::authorize($by, $account, 'manage');
        abort_unless($account->isShared() && $member->account_id === $account->id, 404);
        if ($member->level === 'manage') {
            self::keepAManager($account, $member);
        }

        $name = $member->user?->name ?? 'A person';
        DB::transaction(function () use ($member, $account) {
            // Their claims on conversations go with them
            DB::table('mail_presence')->where('user_id', $member->user_id)
                ->whereIn('thread_id', DB::table('mail_threads')->where('account_id', $account->id)->select('id'))->delete();
            $member->delete();
        });
        MailConnector::audit($by, 'MAIL_MEMBER_REMOVED', "Removed {$name}'s access to {$account->email}.", $account, ['user_id' => $member->user_id, 'level' => $member->level]);
        MailAccess::flush();
    }

    /** Staff who can be invited: active, can use Mail, not yet members */
    public static function invitable(MailAccount $account): \Illuminate\Support\Collection
    {
        $members = MailAccountMember::where('account_id', $account->id)->pluck('user_id')->all();

        return User::where('status', 'Active')
            ->whereNotIn('id', $members)
            ->whereIn('id', DB::table('staff_roles')->select('user_id'))
            ->where('id', '!=', User::EDITORIAL_ID)
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'status'])
            ->filter(fn (User $u) => $u->can('mail.view'))
            ->values();
    }

    private static function guard(User $by, MailAccount $account, string $level): void
    {
        MailAccess::authorize($by, $account, 'manage');
        abort_unless($account->isShared(), 404);
        if (! in_array($level, MailAccountMember::LEVELS, true)) {
            throw ValidationException::withMessages(['invite_level' => 'Choose Read, Read and send, or Manager.']);
        }
    }

    private static function keepAManager(MailAccount $account, MailAccountMember $leaving): void
    {
        $others = MailAccountMember::where('account_id', $account->id)->where('level', 'manage')->where('id', '!=', $leaving->id)->count();
        if ($others === 0) {
            throw ValidationException::withMessages(['members' => 'A shared mailbox needs at least one manager. Make someone else a manager first.']);
        }
    }
}
