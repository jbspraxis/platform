<?php

namespace App\Support\Leads;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Who may do what with leads (docs/LEADS.md section 6). Everyone with crm.view sees every lead (decision 8);
 * crm.edit changes them; crm.create adds them; crm.export downloads the list. Handing a lead to someone else is
 * LeadOwner::canAssign; the default owner of website leads is LeadOwner::canSetDefault.
 */
final class LeadAccess
{
    /** The organisation statuses the Leads list shows, and the only ones its screens change */
    public const STATUSES = ['Lead', 'Active Client'];

    public static function canView(?User $user): bool
    {
        return (bool) $user?->can('crm.view');
    }

    public static function canEdit(?User $user): bool
    {
        return (bool) $user?->can('crm.edit');
    }

    public static function canCreate(?User $user): bool
    {
        return (bool) $user?->can('crm.create');
    }

    /** A lead the Leads screens show (a Lead or an Active Client, not deleted), else null */
    public static function find(?string $id): ?Organization
    {
        return $id ? Organization::query()->whereKey($id)->whereIn('status', self::STATUSES)->whereNull('deleted_at')->first() : null;
    }

    /**
     * Which of a lead's opportunities this person may open (OpportunityAccess::canView: the permission, or being on its
     * team), by opportunity id. The lead's tabs list many at once: the team is read in one query, not once per row.
     *
     * @param  iterable<Opportunity|null>  $opportunities
     * @return array<string, bool>
     */
    public static function opportunitiesVisible(?User $user, iterable $opportunities): array
    {
        $list = collect($opportunities)->filter()->unique('id');
        if ($list->isEmpty()) {
            return [];
        }
        // The permission alone (no opportunity given: no team asked)
        if (OpportunityAccess::canView($user)) {
            return $list->mapWithKeys(fn (Opportunity $o) => [$o->id => true])->all();
        }
        // Team members as OpportunityAccess::isMember counts them: active accounts only
        $member = $user !== null && $user->status === 'Active'
            ? DB::table('opportunity_members')->where('user_id', $user->id)->whereIn('opportunity_id', $list->pluck('id')->all())->pluck('opportunity_id')->flip()->all()
            : [];

        return $list->mapWithKeys(fn (Opportunity $o) => [$o->id => OpportunityAccess::isLead($user, $o) || isset($member[$o->id])])->all();
    }

    /** One line in system_audit_logs about the lead (target organizations), by whoever is signed in */
    public static function log(string $action, string $description, string $organizationId, array $old = [], array $new = []): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 2000),
            'target_table' => 'organizations',
            'target_id' => $organizationId,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
