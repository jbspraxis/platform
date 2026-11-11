<?php

namespace App\Support\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAccountMember;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who may do what in HQ Mail (docs/MAIL.md, "Access"). Every Mail screen, action and query goes through here.
 *
 * Using Mail at all: the mail.view permission, and Mail must be open to the person: until the owner opens it to
 * everyone (Mailboxes page), only Owner accounts can use it (owner decision 1).
 *
 * Levels on a mailbox, lowest first:
 *   read    sees every message (opening one marks it read for everyone, as on the server)
 *   send    also replies, sends, flags, files, archives and deletes
 *   manage  also invites people, changes their level, updates the password, renames and removes the mailbox
 *   owner   the person's own (personal) mailbox: everything; nobody else sees it, not even the Owner role
 * A shared mailbox's levels come from mail_account_members; Owner accounts manage every shared mailbox.
 *
 * Abilities for can(): read, send, organise (flag, move, delete; same as send), manage.
 */
final class MailAccess
{
    public const RANK = ['read' => 1, 'send' => 2, 'manage' => 3, 'owner' => 4];

    private const ABILITY_RANK = ['read' => 1, 'send' => 2, 'organise' => 2, 'manage' => 3];

    private static array $cache = [];

    public static function canUse(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Asked for every mailbox, thread and message checked in a request: once per person (flush() forgets it)
        return self::$cache['use:' . $user->id] ??= $user->can('mail.view') && (self::isOpenToEveryone() || self::isOwner($user));
    }

    public static function isOpenToEveryone(): bool
    {
        return MailSettings::get('open_to', 'owner') === 'everyone';
    }

    /** Add shared mailboxes (the 'Shared mailboxes: Create' permission) */
    public static function canAddShared(?User $user): bool
    {
        return self::canUse($user) && $user->can('mailboxes.create');
    }

    /** 'owner', 'manage', 'send', 'read' or null */
    public static function level(?User $user, MailAccount $account): ?string
    {
        if (! self::canUse($user)) {
            return null;
        }

        $key = $user->id . ':' . $account->id;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        if (! $account->isShared()) {
            return self::$cache[$key] = $account->owner_user_id === $user->id ? 'owner' : null;
        }

        if (self::isOwner($user)) {
            return self::$cache[$key] = 'manage';
        }

        $level = MailAccountMember::where('account_id', $account->id)->where('user_id', $user->id)->value('level');

        return self::$cache[$key] = in_array($level, MailAccountMember::LEVELS, true) ? $level : null;
    }

    public static function can(?User $user, MailAccount $account, string $ability): bool
    {
        $level = self::level($user, $account);
        if ($level === null) {
            return false;
        }

        return self::RANK[$level] >= (self::ABILITY_RANK[$ability] ?? PHP_INT_MAX);
    }

    public static function authorize(?User $user, MailAccount $account, string $ability): void
    {
        abort_unless(self::can($user, $account, $ability), 403);
    }

    public static function canThread(?User $user, MailThread $thread, string $ability): bool
    {
        $account = $thread->relationLoaded('account') ? $thread->account : self::account($thread->account_id);

        return $account !== null && self::can($user, $account, $ability);
    }

    public static function canMessage(?User $user, MailMessage $message, string $ability): bool
    {
        $account = $message->relationLoaded('account') ? $message->account : self::account($message->account_id);

        return $account !== null && self::can($user, $account, $ability);
    }

    /** A mailbox by id, read once per request for the checks above (flush() forgets it) */
    private static function account(?string $id): ?MailAccount
    {
        if (! $id) {
            return null;
        }
        $key = 'account:' . $id;

        return array_key_exists($key, self::$cache) ? self::$cache[$key] : (self::$cache[$key] = MailAccount::find($id));
    }

    /**
     * The mailboxes this person can open: their own first, then shared ones by address. Each has ->access_level.
     *
     * @return Collection<int, MailAccount>
     */
    public static function accounts(?User $user, string $ability = 'read'): Collection
    {
        if (! self::canUse($user)) {
            return collect();
        }

        $query = MailAccount::query()->where(function ($q) use ($user) {
            $q->where(fn ($p) => $p->where('kind', 'personal')->where('owner_user_id', $user->id));
            if (self::isOwner($user)) {
                $q->orWhere('kind', 'shared');
            } else {
                $q->orWhere(fn ($s) => $s->where('kind', 'shared')
                    ->whereIn('id', MailAccountMember::where('user_id', $user->id)->select('account_id')));
            }
        });

        return $query->get()
            ->each(fn (MailAccount $a) => $a->setAttribute('access_level', self::level($user, $a)))
            ->filter(fn (MailAccount $a) => self::can($user, $a, $ability))
            ->sortBy(fn (MailAccount $a) => ($a->isShared() ? '1' : '0') . $a->email)
            ->values();
    }

    /** @return list<string> */
    public static function accountIds(?User $user, string $ability = 'read'): array
    {
        return self::accounts($user, $ability)->pluck('id')->all();
    }

    /**
     * Ids of the active people who can read a mailbox now: for a personal one its person; for a shared one its
     * members and the Owner accounts. Used for new-mail notifications (PR 4).
     *
     * @return list<string>
     */
    public static function readers(MailAccount $account): array
    {
        $ids = $account->isShared()
            ? array_merge(
                MailAccountMember::where('account_id', $account->id)->pluck('user_id')->all(),
                self::ownerIds(),
            )
            : [$account->owner_user_id];

        return User::whereIn('id', array_values(array_unique(array_filter($ids))))->where('status', 'Active')->get()
            ->filter(fn (User $u) => self::can($u, $account, 'read'))
            ->pluck('id')->values()->all();
    }

    /** Managers of a mailbox (who to tell when it needs its password again) */
    public static function managers(MailAccount $account): array
    {
        if (! $account->isShared()) {
            return array_values(array_filter([$account->owner_user_id]));
        }
        $ids = array_merge(
            MailAccountMember::where('account_id', $account->id)->where('level', 'manage')->pluck('user_id')->all(),
            self::ownerIds(),
        );

        return User::whereIn('id', array_values(array_unique($ids)))->where('status', 'Active')->pluck('id')->all();
    }

    public static function isOwner(User $user): bool
    {
        return self::$cache['owner:' . $user->id] ??= Access::isOwner($user);
    }

    public static function flush(): void
    {
        self::$cache = [];
        MailSettings::flush();
    }

    private static function ownerIds(): array
    {
        return DB::table('staff_roles')->join('roles', 'roles.id', '=', 'staff_roles.role_id')
            ->where('roles.is_protected', 1)->where('roles.audience', 'staff')->pluck('staff_roles.user_id')->all();
    }
}
