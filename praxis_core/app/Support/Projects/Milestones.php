<?php

namespace App\Support\Projects;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Changes to milestones (project_phases) from the project page (docs/PROJECTS.md sections 2 and 3). A milestone is
 * also the unit of work: its dates, goal and tasks are its board (sprints were folded into milestones on 1 Nov 2026).
 * Only the project's lead and managers call these (ProjectRules::canManage). Every change is logged against its record.
 */
final class Milestones
{
    public const FIELDS = ['phase_number', 'title', 'description', 'weight_percentage', 'amount', 'status', 'start_date', 'due_date', 'accepted_at', 'accepted_by_name', 'goal', 'notes'];

    public static function find(string $projectId, ?string $phaseId): ?object
    {
        return $phaseId ? DB::table('project_phases')->where('project_id', $projectId)->where('id', $phaseId)->whereNull('deleted_at')->first() : null;
    }

    public static function nextNumber(string $projectId): int
    {
        return (int) DB::table('project_phases')->where('project_id', $projectId)->whereNull('deleted_at')->max('phase_number') + 1;
    }

    /**
     * A milestone's agreed amount (project_phases.amount): what its invoice is pre-filled with. Set from the weight
     * when the weight is set or changed; a change report keeps every amount as it is and only rescales the weights,
     * so amounts never drift with rounding. An older row without one falls back to weight × contract total.
     */
    public static function amount(object $phase, ?float $total = null): float
    {
        if ($phase->amount !== null) {
            return (float) $phase->amount;
        }
        $total ??= (float) DB::table('projects')->where('id', $phase->project_id)->value('total_budget');

        return round($total * (float) $phase->weight_percentage / 100, 2);
    }

    private static function amountFor(string $projectId, $weight): string
    {
        $total = (float) DB::table('projects')->where('id', $projectId)->value('total_budget');

        return number_format(round($total * (float) $weight / 100, 2), 2, '.', '');
    }

    public static function create(string $projectId, array $data): string
    {
        $id = (string) Str::uuid();
        $row = self::withAcceptance(array_intersect_key($data, array_flip(self::FIELDS)));
        $row['amount'] ??= self::amountFor($projectId, $row['weight_percentage'] ?? 0);
        DB::table('project_phases')->insert($row + [
            'id' => $id, 'project_id' => $projectId,
            'created_by' => Auth::id(), 'updated_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        ProjectRules::log('MILESTONE_CREATED', "Added the milestone M{$row['phase_number']} “{$row['title']}” at {$row['weight_percentage']}%.", 'project_phases', $id, [], $row);

        return $id;
    }

    public static function update(object $phase, array $data, string $action = 'MILESTONE_UPDATED'): void
    {
        [$before, $after] = ProjectRules::diff((array) $phase, self::withAcceptance(array_intersect_key($data, array_flip(self::FIELDS))));
        if (! $after) {
            return;
        }
        // A new weight sets a new agreed amount; anything else leaves the amount alone
        if (array_key_exists('weight_percentage', $after) && ! array_key_exists('amount', $after)) {
            $before['amount'] = $phase->amount;
            $after['amount'] = self::amountFor($phase->project_id, $after['weight_percentage']);
        }
        DB::table('project_phases')->where('id', $phase->id)->update($after + ['updated_by' => Auth::id(), 'updated_at' => now()]);
        ProjectRules::log($action, "Changed M{$phase->phase_number} “{$phase->title}”: " . implode(', ', array_keys($after)) . '.', 'project_phases', $phase->id, $before, $after);
    }

    /** A kanban move between the four columns its status decides (Accepted with who accepted it and when) */
    public static function move(object $phase, string $column, ?string $acceptedAt = null, ?string $acceptedBy = null): void
    {
        $data = ['status' => ProjectPlan::STATUS_FOR[$column]];
        if ($column === 'accepted') {
            $data += ['accepted_at' => $acceptedAt, 'accepted_by_name' => $acceptedBy];
        }
        self::update($phase, $data, 'MILESTONE_MOVED');
    }

    /** Only a milestone with no tasks and no invoice can go */
    public static function canDelete(object $phase): bool
    {
        return ! DB::table('tasks')->where('phase_id', $phase->id)->whereNull('deleted_at')->exists()
            && ! DB::table('invoices')->where('phase_id', $phase->id)->whereNull('deleted_at')->exists();
    }

    public static function delete(object $phase): bool
    {
        if (! self::canDelete($phase)) {
            return false;
        }
        DB::transaction(function () use ($phase) {
            DB::table('phase_dependencies')->where('phase_id', $phase->id)->orWhere('depends_on_phase_id', $phase->id)->delete();
            DB::table('project_phases')->where('id', $phase->id)->update(['deleted_at' => now(), 'deleted_by' => Auth::id()]);
        });
        ProjectRules::log('MILESTONE_DELETED', "Deleted the milestone M{$phase->phase_number} “{$phase->title}”.", 'project_phases', $phase->id);

        return true;
    }

    /** Accepted on and by belong to an accepted milestone only: leaving Accepted clears them */
    private static function withAcceptance(array $row): array
    {
        if (isset($row['status']) && $row['status'] !== 'Completed') {
            $row['accepted_at'] = null;
            $row['accepted_by_name'] = null;
        }

        return $row;
    }
}
