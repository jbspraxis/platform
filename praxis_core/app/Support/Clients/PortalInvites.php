<?php

namespace App\Support\Clients;

use App\Mail\UserInvitation;
use App\Models\ClientUser;
use App\Models\User;
use App\Support\Access;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\LeadTimeline;
use App\Support\MailLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Giving a client's contact a Client Portal account from HQ › Clients (owner, 13 Nov 2026: "create accounts for their
 * portal directly there"). The contact's own client_users row becomes the account: no second row, so the email stays
 * one person's. It gets a setup link (invitation_token, lifecycle Onboarding, no password), the same email Settings and
 * access › Accounts sends, in the organisation's language, and signs in once the password is chosen.
 *
 * The organisation's first portal account is its primary contact (Client Admin), as on Accounts; the others get the
 * client role chosen (Client Member unless another is picked). Removing or suspending an account stays on Accounts.
 */
final class PortalInvites
{
    /** none: no account; invited: setup link sent, no password yet; active: has signed in; suspended */
    public static function state(ClientUser $contact): string
    {
        if ($contact->password === null && $contact->invitation_token === null) {
            return 'none';
        }
        if ($contact->status !== 'Active') {
            return 'suspended';
        }

        return $contact->password === null ? 'invited' : 'active';
    }

    /** The client roles a new team member may get, by id */
    public static function roles(): Collection
    {
        return DB::table('roles')->where('audience', 'client')->whereNull('deleted_at')->orderBy('id')->pluck('name', 'id');
    }

    /** Whether the organisation has no primary contact yet: the next account invited becomes it */
    public static function nextIsPrimary(string $organizationId): bool
    {
        return DB::table('client_users')->where('organization_id', $organizationId)->where('is_primary_client', 1)->whereNull('deleted_at')->doesntExist();
    }

    /**
     * Send the contact a setup link for the Client Portal. $roleId: a client role (ignored for the primary contact).
     *
     * @throws LeadRuleBroken when the contact has no email or already has an account
     */
    public static function invite(ClientUser $contact, User $by, ?int $roleId = null): void
    {
        if (trim((string) $contact->email) === '') {
            throw new LeadRuleBroken(__('clients.portal.needs_email'), 'invite');
        }
        if (self::state($contact) !== 'none') {
            throw new LeadRuleBroken(__('clients.portal.already'), 'invite');
        }

        $token = Str::random(60);
        DB::transaction(function () use ($contact, $by, $roleId, $token) {
            $primary = self::nextIsPrimary($contact->organization_id);
            $role = $primary ? Access::clientAdminRoleId() : self::validRole($roleId);
            DB::table('client_users')->where('id', $contact->id)->update([
                'invitation_token' => $token,
                'lifecycle_stage' => 'Onboarding',
                'status' => 'Active',
                'is_primary_client' => $primary ? 1 : 0,
                'role_id' => $role,
                'updated_at' => now(),
            ]);
            $roleName = DB::table('roles')->where('id', $role)->value('name');
            LeadTimeline::record($contact->organization_id, 'portal_invited', "Invited {$contact->full_name} to the Client Portal ({$roleName}).", [
                'actor' => $by, 'client_user_id' => $contact->id,
            ]);
            self::audit('CLIENT_ACCOUNT_INVITED', "Invited {$contact->full_name} ({$contact->email}) to the Client Portal as {$roleName}.", $contact, $by,
                ['role_id' => $role, 'primary' => $primary]);
        });
        self::send($contact, $token);
        $contact->refresh();
    }

    /**
     * A new setup link for someone invited who has not chosen a password yet; the earlier link stops working.
     *
     * @throws LeadRuleBroken when the contact is not waiting on an invitation
     */
    public static function resend(ClientUser $contact, User $by): void
    {
        if (self::state($contact) !== 'invited') {
            throw new LeadRuleBroken(__('clients.portal.not_invited'), 'invite');
        }
        $token = Str::random(60);
        DB::table('client_users')->where('id', $contact->id)->update(['invitation_token' => $token, 'lifecycle_stage' => 'Onboarding', 'updated_at' => now()]);
        self::audit('CLIENT_INVITATION_RESENT', "Sent {$contact->full_name} ({$contact->email}) a new Client Portal invitation.", $contact, $by);
        self::send($contact, $token);
        $contact->refresh();
    }

    private static function validRole(?int $roleId): ?int
    {
        if ($roleId && DB::table('roles')->where('id', $roleId)->where('audience', 'client')->whereNull('deleted_at')->exists()) {
            return $roleId;
        }

        return DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id');
    }

    private static function send(ClientUser $contact, string $token): void
    {
        $language = DB::table('organizations')->where('id', $contact->organization_id)->value('preferred_language');
        try {
            Mail::to($contact->email)->locale(MailLocale::forRecipient($language))->send(new UserInvitation($contact->first_name, $token, true));
        } catch (\Throwable $e) {
            // Never the link itself in the log: whoever reads it could take the account. "Send again" makes a new one.
            Log::warning('Client Portal invitation email failed', ['client_user_id' => $contact->id, 'message' => $e->getMessage()]);

            // field "mail": the account is ready, only the email failed
            throw new LeadRuleBroken(__('clients.portal.mail_failed'), 'mail');
        }
    }

    private static function audit(string $action, string $description, ClientUser $contact, User $by, array $new = []): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => $by->id,
            'action' => $action,
            'description' => mb_substr($description, 0, 2000),
            'target_table' => 'client_users',
            'target_id' => $contact->id,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
