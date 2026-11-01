<?php

namespace App\Support\Opportunities;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The team of an opportunity (opportunity_members): who is on it, and changing it in one place so the audit
 * log and the bell notifications are always written. The lead is never a member row: being the lead already
 * grants everything a member gets.
 */
final class Team
{
    /** Active staff on the team, lead first, then members by name */
    public static function of(Opportunity $opportunity): Collection
    {
        $members = User::query()
            ->join('opportunity_members as m', 'm.user_id', '=', 'users.id')
            ->where('m.opportunity_id', $opportunity->id)
            ->where('users.status', 'Active')->whereNull('users.deleted_at')
            ->orderBy('users.first_name')->orderBy('users.last_name')
            ->get(['users.id', 'users.first_name', 'users.last_name', 'users.avatar_path', 'users.job_title']);

        $lead = $opportunity->lead_user_id
            ? User::where('id', $opportunity->lead_user_id)->where('status', 'Active')->whereNull('deleted_at')->first(['id', 'first_name', 'last_name', 'avatar_path', 'job_title'])
            : null;

        return $lead ? $members->prepend($lead) : $members;
    }

    /** Member ids (without the lead) */
    public static function memberIds(Opportunity $opportunity): array
    {
        return DB::table('opportunity_members')->where('opportunity_id', $opportunity->id)->pluck('user_id')->all();
    }

    /**
     * Make the team exactly $userIds (the lead is dropped from it), log the change and tell the people who
     * joined. Returns ['added' => [...ids], 'removed' => [...ids]].
     */
    public static function sync(Opportunity $opportunity, array $userIds, ?string $byUserId): array
    {
        $wanted = array_values(array_unique(array_filter(array_map('strval', $userIds), fn ($id) => $id !== '' && $id !== $opportunity->lead_user_id)));
        $wanted = User::whereIn('id', $wanted)->where('status', 'Active')->whereNull('deleted_at')->pluck('id')->all();
        $current = self::memberIds($opportunity);

        $added = array_values(array_diff($wanted, $current));
        $removed = array_values(array_diff($current, $wanted));
        if (! $added && ! $removed) {
            return ['added' => [], 'removed' => []];
        }

        if ($removed) {
            DB::table('opportunity_members')->where('opportunity_id', $opportunity->id)->whereIn('user_id', $removed)->delete();
        }
        foreach ($added as $userId) {
            DB::table('opportunity_members')->insert([
                'id' => (string) Str::uuid(), 'opportunity_id' => $opportunity->id, 'user_id' => $userId, 'added_by' => $byUserId, 'created_at' => now(),
            ]);
        }

        $names = fn (array $ids) => User::whereIn('id', $ids)->get(['first_name', 'last_name'])->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name))->sort()->implode(', ');
        $parts = [];
        if ($added) {
            $parts[] = 'Added ' . $names($added) . ' to the team';
        }
        if ($removed) {
            $parts[] = ($parts ? 'removed ' : 'Removed ') . $names($removed) . ' from the team';
        }
        OpportunityAccess::log('OPPORTUNITY_TEAM_CHANGED', implode('; ', $parts) . " of “{$opportunity->title}”.", 'opportunities', $opportunity->id,
            ['members' => $current], ['members' => $wanted]);

        if ($added) {
            $org = (string) DB::table('organizations')->where('id', $opportunity->organization_id)->value('name');
            $by = $byUserId ? User::where('id', $byUserId)->first(['first_name', 'last_name']) : null;
            $byName = $by ? trim($by->first_name . ' ' . $by->last_name) : 'HQ';
            foreach ($added as $userId) {
                if ($userId === $byUserId) {
                    continue;
                }
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'type' => 'System',
                    'title' => Str::limit(__('opportunities.notify.joined_title'), 150, '…'),
                    'message' => __('opportunities.notify.joined', ['by' => $byName, 'title' => $opportunity->title, 'organisation' => $org]),
                    'link_url' => route('hq.crm.opportunity', ['opportunity' => $opportunity->id]),
                    'is_read' => 0,
                    'created_at' => now(),
                ]);
            }
        }

        return ['added' => $added, 'removed' => $removed];
    }
}
