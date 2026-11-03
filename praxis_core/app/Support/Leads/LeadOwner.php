<?php

namespace App\Support\Leads;

use App\Models\Organization;
use App\Models\User;
use App\Support\Integrations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who follows a lead up (organizations.assigned_to, owner's decision 7, docs/LEADS.md section 6).
 *
 *   - Website leads go to the default owner (a setting: Leads › Lead settings), who gets a bell.
 *   - A lead added by hand belongs to whoever adds it.
 *   - Anyone with crm.edit may take or hand on a lead nobody owns; once owned, its owner may hand it on, and so may a
 *     partner or the firm's owner (opportunities.approve: the one Leads-and-proposals permission Sales doesn't hold).
 *   - The default owner is set by the same people (opportunities.approve).
 */
final class LeadOwner
{
    /** integration_settings key (App\Support\Integrations): users.id of the default owner of website leads */
    public const DEFAULT_SETTING = 'leads.default_owner_id';

    public static function canAssign(?User $user, object $lead): bool
    {
        if (! $user || ! LeadAccess::canEdit($user)) {
            return false;
        }

        return ! $lead->assigned_to || $lead->assigned_to === $user->id || $user->can('opportunities.approve');
    }

    /** Give the lead to $userId (an active staff member): audit, timeline and a bell for them. False when nothing changed. */
    public static function assign(Organization $lead, string $userId, User $by): bool
    {
        $person = self::activeStaff($userId);
        if (! $person) {
            throw new LeadRuleBroken('Choose someone on the staff whose account is active.', 'owner');
        }

        return DB::transaction(function () use ($lead, $person, $by) {
            $row = DB::table('organizations')->where('id', $lead->id)->whereIn('status', LeadAccess::STATUSES)->lockForUpdate()->first();
            if (! $row) {
                throw new LeadRuleBroken('This lead is no longer in the Leads list.', 'owner');
            }
            if (! self::canAssign($by, $row)) {
                abort(403);
            }
            if ($row->assigned_to === $person->id) {
                return false;
            }
            DB::table('organizations')->where('id', $row->id)->update(['assigned_to' => $person->id, 'updated_by' => $by->id, 'updated_at' => now()]);
            $name = self::name($person);
            $before = $row->assigned_to ? self::name(User::find($row->assigned_to)) : null;
            $self = $person->id === $by->id;
            LeadAccess::log('LEAD_ASSIGNED', $self ? "Took ownership of the lead {$row->name}." : "Assigned the lead {$row->name} to {$name}.", $row->id,
                ['assigned_to' => $row->assigned_to], ['assigned_to' => $person->id]);
            LeadTimeline::record($row->id, 'owner_changed', $before ? "Owner changed from {$before} to {$name}." : "{$name} now owns this lead.", [
                'actor' => $by, 'payload' => ['from' => $row->assigned_to, 'to' => $person->id],
            ]);
            if (! $self) {
                self::bell($person->id, 'A lead was assigned to you', self::name($by) . " assigned you the lead {$row->name}.", $row->id);
            }
            $lead->refresh();

            return true;
        });
    }

    /** "Assign to me" on a lead nobody owns yet: an owned lead can't be taken over this way. False when it wasn't free. */
    public static function claim(Organization $lead, User $by): bool
    {
        abort_unless(LeadAccess::canEdit($by), 403);
        $claimed = DB::table('organizations')->where('id', $lead->id)->whereIn('status', LeadAccess::STATUSES)->whereNull('assigned_to')
            ->update(['assigned_to' => $by->id, 'updated_by' => $by->id, 'updated_at' => now()]);
        if (! $claimed) {
            return false;
        }
        LeadAccess::log('LEAD_CLAIMED', "Took ownership of the lead {$lead->name}.", $lead->id, ['assigned_to' => null], ['assigned_to' => $by->id]);
        LeadTimeline::record($lead->id, 'owner_changed', self::name($by) . ' took this lead.', ['actor' => $by, 'payload' => ['from' => null, 'to' => $by->id]]);
        $lead->refresh();

        return true;
    }

    /** The default owner of website leads, while their account is active; else null (website leads stay unowned) */
    public static function defaultOwnerId(): ?string
    {
        $id = Integrations::get(self::DEFAULT_SETTING);

        return $id && self::activeStaff($id) ? $id : null;
    }

    public static function canSetDefault(?User $user): bool
    {
        return (bool) $user?->can('opportunities.approve') && LeadAccess::canView($user);
    }

    /** Set (or clear, with null) the default owner, with an audit line */
    public static function setDefault(?string $userId, User $by): void
    {
        abort_unless(self::canSetDefault($by), 403);
        if ($userId !== null && ! self::activeStaff($userId)) {
            throw new LeadRuleBroken('Choose someone on the staff whose account is active.', 'defaultOwner');
        }
        $before = Integrations::get(self::DEFAULT_SETTING);
        if ($before === $userId) {
            return;
        }
        Integrations::set(self::DEFAULT_SETTING, $userId, $by->id);
        $name = $userId ? self::name(User::find($userId)) : null;
        DB::table('system_audit_logs')->insert([
            'user_id' => $by->id, 'action' => 'LEADS_DEFAULT_OWNER_CHANGED',
            'description' => $name ? "Website leads now go to {$name}." : 'Website leads now go to no one in particular.',
            'target_table' => 'integration_settings', 'target_id' => self::DEFAULT_SETTING,
            'old_values' => json_encode([self::DEFAULT_SETTING => $before]), 'new_values' => json_encode([self::DEFAULT_SETTING => $userId]),
            'ip_address' => request()?->ip(), 'created_at' => now(),
        ]);
    }

    /** The picker's list (x-hq.picker): active staff matching the typed name or email, $except left out */
    public static function options(string $term, ?string $except = null): Collection
    {
        $term = trim($term);
        $like = '%' . addcslashes($term, '%_\\') . '%';

        return User::query()->where('status', 'Active')->whereNull('deleted_at')
            ->when($except, fn ($q, $id) => $q->where('id', '!=', $id))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])->orWhere('email', 'like', $like)))
            ->orderBy('first_name')->orderBy('last_name')->limit(30)
            ->get(['id', 'first_name', 'last_name', 'avatar_path', 'job_title'])
            ->map(fn ($u) => self::option($u))->values();
    }

    /** One person as a picker option */
    public static function option(?User $u): ?array
    {
        return $u ? ['id' => $u->id, 'label' => self::name($u), 'meta' => $u->job_title, 'user' => $u] : null;
    }

    /** A bell (and so a phone push) for $userId about the lead, opening its page */
    public static function bell(string $userId, string $title, string $message, string $organizationId): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $userId, 'type' => 'System',
            'title' => Str::limit($title, 150, ''), 'message' => $message,
            'link_url' => route('hq.crm.lead', ['organization' => $organizationId], false),
            'is_read' => 0, 'created_at' => now(),
        ]);
    }

    public static function activeStaff(?string $id): ?User
    {
        return $id ? User::where('id', $id)->where('status', 'Active')->whereNull('deleted_at')->first() : null;
    }

    public static function name(?User $u): string
    {
        return $u ? trim($u->first_name . ' ' . $u->last_name) : 'Someone';
    }
}
