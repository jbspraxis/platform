<?php

namespace App\Support\Opportunities;

use App\Models\Opportunity;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Who may do what with opportunities (docs/OPPORTUNITIES.md 8.1). Permissions come from roles
 * (config/access.php); the opportunity's own team (its lead and the members chosen on the form) can open it
 * and work on it whatever their roles say, and the lead also approves its documents and reads its transcripts.
 * Every change to an opportunity is written to system_audit_logs through log().
 */
final class OpportunityAccess
{
    /** Open an opportunity: the permission, or being on its team */
    public static function canView(?User $user, ?Opportunity $opportunity = null): bool
    {
        return Access::allows($user, 'opportunities.view') || ($opportunity !== null && self::isOnTeam($user, $opportunity));
    }

    /** Open the board: the permission, or at least one opportunity the person is on (the board then shows only those) */
    public static function canOpenBoard(?User $user): bool
    {
        return Access::allows($user, 'opportunities.view') || self::hasTeamWork($user);
    }

    public static function canCreate(?User $user): bool
    {
        return Access::allows($user, 'opportunities.create');
    }

    /** Edit, meetings, questionnaire and drafts: the permission, or being on the opportunity's team */
    public static function canManage(?User $user, ?Opportunity $opportunity = null): bool
    {
        return Access::allows($user, 'opportunities.manage') || ($opportunity !== null && self::isOnTeam($user, $opportunity));
    }

    /** Approve a proposal or SOW before it goes out: the owner's permission, or the opportunity's own lead */
    public static function canApprove(?User $user, Opportunity $opportunity): bool
    {
        return Access::allows($user, 'opportunities.approve') || self::isLead($user, $opportunity);
    }

    /** Full meeting transcripts (personal data): the permission, or the opportunity's own lead */
    public static function canReadTranscripts(?User $user, Opportunity $opportunity): bool
    {
        return Access::allows($user, 'opportunities.transcripts') || self::isLead($user, $opportunity);
    }

    public static function isLead(?User $user, Opportunity $opportunity): bool
    {
        return $user !== null && $user->status === 'Active' && $opportunity->lead_user_id !== null && $opportunity->lead_user_id === $user->id;
    }

    /** A team member chosen on the form (opportunity_members), not the lead */
    public static function isMember(?User $user, Opportunity $opportunity): bool
    {
        return $user !== null && $user->status === 'Active'
            && DB::table('opportunity_members')->where('opportunity_id', $opportunity->id)->where('user_id', $user->id)->exists();
    }

    public static function isOnTeam(?User $user, Opportunity $opportunity): bool
    {
        return self::isLead($user, $opportunity) || self::isMember($user, $opportunity);
    }

    /** Leads or is a member of at least one opportunity */
    public static function hasTeamWork(?User $user): bool
    {
        if ($user === null || $user->status !== 'Active') {
            return false;
        }

        return DB::table('opportunities')->whereNull('deleted_at')->where('lead_user_id', $user->id)->exists()
            || DB::table('opportunity_members')->where('user_id', $user->id)->exists();
    }

    /** One line in the audit log for a change to an opportunity or one of its records */
    public static function log(string $action, string $description, string $table, string $id, array $old = [], array $new = []): void
    {
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => mb_substr($description, 0, 2000),
            'target_table' => $table,
            'target_id' => $id,
            'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
