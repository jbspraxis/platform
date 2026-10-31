<?php

namespace App\Support\Opportunities;

use App\Models\Opportunity;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Who may do what with opportunities (docs/OPPORTUNITIES.md 8.1). Permissions come from roles
 * (config/access.php); being the opportunity's lead adds approving its documents and reading its transcripts.
 * Every change to an opportunity is written to system_audit_logs through log().
 */
final class OpportunityAccess
{
    public static function canView(?User $user): bool
    {
        return Access::allows($user, 'opportunities.view');
    }

    public static function canCreate(?User $user): bool
    {
        return Access::allows($user, 'opportunities.create');
    }

    /** Edit, meetings, questionnaire and drafts */
    public static function canManage(?User $user, ?Opportunity $opportunity = null): bool
    {
        return Access::allows($user, 'opportunities.manage');
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
