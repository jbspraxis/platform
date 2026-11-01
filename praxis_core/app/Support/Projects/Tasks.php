<?php

namespace App\Support\Projects;

use App\Support\Access;
use App\Support\Pwa\ClientNotify;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Changes to tasks from the project page and the sprint board (docs/PROJECTS.md section 3). Callers check
 * ProjectRules first. Every change is logged against the task (its history); the lead, the people sharing it and its
 * watchers get the bell when it moves to Client review or Done, or gets a comment, never the person who did it.
 * Nothing here emails anyone: the only client-facing effect is the sprint board's existing portal notice when a
 * task enters Client review.
 */
final class Tasks
{
    /** The fields a manager edits in the task drawer */
    public const FIELDS = ['title', 'description', 'phase_id', 'sprint_id', 'assigned_to', 'due_date', 'estimated_hours', 'is_client_visible'];

    public const MAX_STEPS = 25;

    /** A task on this project (task ids come from the browser), or null */
    public static function find(string $projectId, ?string $taskId): ?object
    {
        if (! $taskId) {
            return null;
        }

        return DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
            ->where('project_phases.project_id', $projectId)->whereNull('project_phases.deleted_at')
            ->where('tasks.id', $taskId)->whereNull('tasks.deleted_at')
            ->select('tasks.*')->first();
    }

    public static function create(string $projectId, array $data): string
    {
        $id = (string) Str::uuid();
        $row = array_intersect_key($data, array_flip(self::FIELDS));
        DB::table('tasks')->insert($row + [
            'id' => $id,
            'status' => 'To-Do',
            'is_client_visible' => 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ProjectRules::log('TASK_CREATED', "Added the task “{$row['title']}”.", 'tasks', $id, [], $row);
        if (! empty($row['assigned_to'])) {
            self::tellLead($projectId, $id, $row['title'], $row['assigned_to']);
        }

        return $id;
    }

    /** A manager's edit: only the fields that changed are written and logged */
    public static function update(string $projectId, object $task, array $data): void
    {
        $new = array_intersect_key($data, array_flip(self::FIELDS));
        [$before, $after] = ProjectRules::diff((array) $task, $new);
        if (! $after) {
            return;
        }
        // A due date set by the lead settles any proposal
        if (array_key_exists('due_date', $after)) {
            $after += ['proposed_due_date' => null, 'proposed_due_by' => null];
        }
        DB::table('tasks')->where('id', $task->id)->update($after + ['updated_by' => Auth::id(), 'updated_at' => now()]);
        ProjectRules::log('TASK_UPDATED', "Changed “{$task->title}”: " . implode(', ', array_keys($before)) . '.', 'tasks', $task->id, $before, $after);
        if (! empty($after['assigned_to'])) {
            self::tellLead($projectId, $task->id, $after['title'] ?? $task->title, $after['assigned_to']);
        }
    }

    /** "Assign to me": an unassigned task, or anyone's for a manager (the sprint board's checkout rule) */
    public static function assignToMe(object $task, bool $anyone): bool
    {
        $updated = DB::table('tasks')->where('id', $task->id)
            ->when(! $anyone, fn ($q) => $q->whereNull('assigned_to'))
            ->update(['assigned_to' => Auth::id(), 'updated_by' => Auth::id(), 'updated_at' => now()]);
        if ($updated) {
            ProjectRules::log('TASK_CHECKOUT', "Took the lead on “{$task->title}”.", 'tasks', $task->id, ['assigned_to' => $task->assigned_to], ['assigned_to' => Auth::id()]);
        }

        return $updated > 0;
    }

    /**
     * Move a task to another status, with the sprint board's rules unchanged (moved here from SprintBoard): entering
     * Client review starts the 5-hour client window and tells the QA approvers and the client's portal; leaving it
     * clears the window; entering In progress starts the mover's timer and leaving it stops the timer and books the hours.
     */
    public static function move(string $projectId, object $task, string $newStatus): void
    {
        DB::transaction(function () use ($task, $newStatus) {
            $oldStatus = $task->status;
            $updateData = ['status' => $newStatus, 'updated_at' => now()];

            // The 5-hour client QA window (Rule 6)
            if ($newStatus === 'QA_Review') {
                $updateData['qa_sla_expires_at'] = now()->addHours(5);
                self::notifyQaApprovers("‘{$task->title}’ is waiting for the client’s review. If the client doesn’t respond within 5 hours, it’s approved automatically.");
            }

            // Cleared when pulled out of client review
            if ($oldStatus === 'QA_Review' && $newStatus !== 'QA_Review' && $newStatus !== 'Done') {
                $updateData['qa_sla_expires_at'] = null;
            }

            DB::table('tasks')->where('id', $task->id)->update($updateData);

            // Timer starts: into In progress
            if ($newStatus === 'In-Progress' && $oldStatus !== 'In-Progress') {
                DB::table('task_time_logs')->insert([
                    'task_id' => $task->id,
                    'user_id' => Auth::id(),
                    'start_time' => now(),
                    'created_at' => now(),
                ]);
            }
            // Timer stops: out of In progress
            elseif ($oldStatus === 'In-Progress' && $newStatus !== 'In-Progress') {
                $openLog = DB::table('task_time_logs')
                    ->where('task_id', $task->id)
                    ->where('user_id', Auth::id())
                    ->whereNull('end_time')
                    ->orderBy('start_time', 'desc')
                    ->first();

                if ($openLog) {
                    $endTime = now();
                    $durationMinutes = Carbon::parse($openLog->start_time)->diffInMinutes($endTime);

                    // The engineer's hourly rate for the live margin (US$50 an hour when unset)
                    $rate = DB::table('compensation_history')
                        ->where('user_id', Auth::id())
                        ->orderBy('effective_from', 'desc')
                        ->value('hourly_rate') ?? 50.00;

                    DB::table('task_time_logs')->where('id', $openLog->id)->update([
                        'end_time' => $endTime,
                        'duration_minutes' => $durationMinutes,
                        'cost_calculated' => ($durationMinutes / 60) * $rate,
                    ]);

                    DB::table('tasks')->where('id', $task->id)->update([
                        'actual_hours_logged' => DB::raw('actual_hours_logged + ' . round($durationMinutes / 60, 2)),
                    ]);
                }
            }
        });

        // Waiting for the client's review: the project's client accounts hear about it (docs/PWA.md)
        if ($newStatus === 'QA_Review') {
            ClientNotify::task($task->id);
        }

        ProjectRules::log('TASK_MOVED', "Moved “{$task->title}” from {$task->status} to {$newStatus}.", 'tasks', $task->id, ['status' => $task->status], ['status' => $newStatus]);
        if (in_array($newStatus, ['QA_Review', 'Done'], true)) {
            self::tell($projectId, $task, __('projects.bell.moved_title'), __('projects.bell.moved', [
                'task' => $task->title, 'status' => __('projects.task_statuses.' . $newStatus),
            ]));
        }
    }

    /** The people sharing a task and its watchers, as a manager sets them (the lead is never one of them) */
    public static function setPeople(object $task, array $sharing, array $watchers): void
    {
        $sharing = array_values(array_unique(array_diff($sharing, [$task->assigned_to])));
        $watchers = array_values(array_unique(array_diff($watchers, $sharing, [$task->assigned_to])));
        $old = self::people($task->id);
        if ($old['sharing'] == collect($sharing)->sort()->values()->all() && $old['watcher'] == collect($watchers)->sort()->values()->all()) {
            return;
        }

        DB::transaction(function () use ($task, $sharing, $watchers) {
            DB::table('task_people')->where('task_id', $task->id)->delete();
            $rows = [];
            foreach (['sharing' => $sharing, 'watcher' => $watchers] as $role => $ids) {
                foreach ($ids as $userId) {
                    $rows[] = ['task_id' => $task->id, 'user_id' => $userId, 'role' => $role, 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()];
                }
            }
            if ($rows) {
                DB::table('task_people')->insert($rows);
            }
        });
        ProjectRules::log('TASK_PEOPLE_CHANGED', "Changed who shares and watches “{$task->title}”.", 'tasks', $task->id, $old, self::people($task->id));
    }

    /** ['sharing' => [user ids], 'watcher' => [user ids]], sorted */
    public static function people(string $taskId): array
    {
        $rows = DB::table('task_people')->where('task_id', $taskId)->orderBy('user_id')->get(['user_id', 'role']);

        return [
            'sharing' => $rows->where('role', 'sharing')->pluck('user_id')->values()->all(),
            'watcher' => $rows->where('role', 'watcher')->pluck('user_id')->values()->all(),
        ];
    }

    /** Anyone who can see the task may watch it, or stop; someone sharing it already hears everything */
    public static function watch(object $task, bool $on): void
    {
        $role = DB::table('task_people')->where('task_id', $task->id)->where('user_id', Auth::id())->value('role');
        if ($on && ! $role && $task->assigned_to !== Auth::id()) {
            DB::table('task_people')->insert(['task_id' => $task->id, 'user_id' => Auth::id(), 'role' => 'watcher', 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
            ProjectRules::log('TASK_WATCHED', "Started watching “{$task->title}”.", 'tasks', $task->id);
        } elseif (! $on && $role === 'watcher') {
            DB::table('task_people')->where('task_id', $task->id)->where('user_id', Auth::id())->delete();
            ProjectRules::log('TASK_UNWATCHED', "Stopped watching “{$task->title}”.", 'tasks', $task->id);
        }
    }

    public static function addStep(object $task, string $text): bool
    {
        if (DB::table('task_steps')->where('task_id', $task->id)->count() >= self::MAX_STEPS) {
            return false;
        }
        $position = (int) DB::table('task_steps')->where('task_id', $task->id)->max('position') + 1;
        DB::table('task_steps')->insert(['task_id' => $task->id, 'position' => $position, 'text' => $text, 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
        ProjectRules::log('TASK_STEP_ADDED', "Added the step “{$text}” to “{$task->title}”.", 'tasks', $task->id);

        return true;
    }

    public static function toggleStep(object $task, int $stepId): void
    {
        $step = DB::table('task_steps')->where('task_id', $task->id)->where('id', $stepId)->first();
        if (! $step) {
            return;
        }
        $done = $step->done_at === null;
        DB::table('task_steps')->where('id', $step->id)->update(['done_at' => $done ? now() : null, 'done_by' => $done ? Auth::id() : null, 'updated_at' => now()]);
        ProjectRules::log($done ? 'TASK_STEP_DONE' : 'TASK_STEP_REOPENED', ($done ? 'Ticked' : 'Unticked') . " the step “{$step->text}” on “{$task->title}”.", 'tasks', $task->id);
    }

    public static function removeStep(object $task, int $stepId): void
    {
        $step = DB::table('task_steps')->where('task_id', $task->id)->where('id', $stepId)->first();
        if ($step) {
            DB::table('task_steps')->where('id', $step->id)->delete();
            ProjectRules::log('TASK_STEP_REMOVED', "Removed the step “{$step->text}” from “{$task->title}”.", 'tasks', $task->id);
        }
    }

    /** A comment, with an optional file already stored on the private disk (task_files/…) */
    public static function comment(string $projectId, object $task, string $text, ?string $attachmentPath = null): void
    {
        DB::table('task_comments')->insert([
            'task_id' => $task->id,
            'user_id' => Auth::id(),
            'comment_text' => $text,
            'attachment_path' => $attachmentPath,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ProjectRules::log('TASK_COMMENTED', "Commented on “{$task->title}”.", 'tasks', $task->id);
        self::tell($projectId, $task, __('projects.bell.comment_title'), __('projects.bell.comment', [
            'name' => trim(Auth::user()->first_name . ' ' . Auth::user()->last_name), 'task' => $task->title, 'text' => Str::limit($text, 140),
        ]));
    }

    /** A team member proposes a due date; the project lead sees it in the drawer (and the bell) and accepts it or not */
    public static function proposeDueDate(string $projectId, object $task, string $date): void
    {
        DB::table('tasks')->where('id', $task->id)->update(['proposed_due_date' => $date, 'proposed_due_by' => Auth::id(), 'updated_at' => now()]);
        ProjectRules::log('TASK_DUE_PROPOSED', "Proposed {$date} as the due date of “{$task->title}”.", 'tasks', $task->id, ['due_date' => $task->due_date], ['proposed_due_date' => $date]);

        $lead = DB::table('projects')->where('id', $projectId)->value('lead_user_id');
        if ($lead && $lead !== Auth::id()) {
            self::bell([$lead], $projectId, $task->id, __('projects.bell.proposal_title'), __('projects.bell.proposal', [
                'name' => trim(Auth::user()->first_name . ' ' . Auth::user()->last_name), 'task' => $task->title, 'date' => ProjectPlan::day($date),
            ]));
        }
    }

    public static function decideProposal(object $task, bool $accept): void
    {
        if (! $task->proposed_due_date) {
            return;
        }
        $change = ['proposed_due_date' => null, 'proposed_due_by' => null] + ($accept ? ['due_date' => $task->proposed_due_date] : []);
        DB::table('tasks')->where('id', $task->id)->update($change + ['updated_by' => Auth::id(), 'updated_at' => now()]);
        ProjectRules::log($accept ? 'TASK_DUE_ACCEPTED' : 'TASK_DUE_DECLINED', ($accept ? 'Accepted' : 'Declined') . " {$task->proposed_due_date} as the due date of “{$task->title}”.",
            'tasks', $task->id, ['due_date' => $task->due_date], ['due_date' => $accept ? $task->proposed_due_date : $task->due_date]);
    }

    /** The bell for the task's lead, the people sharing it and its watchers, except whoever made the change */
    private static function tell(string $projectId, object $task, string $title, string $message): void
    {
        $fresh = DB::table('tasks')->where('id', $task->id)->value('assigned_to');
        $people = DB::table('task_people')->where('task_id', $task->id)->pluck('user_id')->push($fresh);
        self::bell($people->filter()->unique()->reject(fn ($id) => $id === Auth::id())->values()->all(), $projectId, $task->id, $title, $message);
    }

    private static function tellLead(string $projectId, string $taskId, string $title, string $leadId): void
    {
        if ($leadId !== Auth::id()) {
            self::bell([$leadId], $projectId, $taskId, __('projects.bell.lead_title'), __('projects.bell.lead', ['task' => $title]));
        }
    }

    /** Bell rows (and so a phone push) for active staff, opening the task on the project page */
    private static function bell(array $userIds, string $projectId, string $taskId, string $title, string $message): void
    {
        $active = DB::table('users')->whereIn('id', $userIds)->where('status', 'Active')->whereNull('deleted_at')->pluck('id');
        $link = route('hq.ops.project', ['project' => $projectId, 'tab' => 'backlog', 'task' => $taskId]);
        $rows = $active->map(fn ($id) => [
            'id' => (string) Str::uuid(), 'user_id' => $id, 'title' => Str::limit($title, 150, '…'), 'message' => $message,
            'link_url' => $link, 'type' => 'Project', 'is_read' => 0, 'created_at' => now(),
        ])->all();
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }

    /** QA approvers follow what lands in client review (from SprintBoard) */
    private static function notifyQaApprovers(string $message): void
    {
        $inserts = [];
        foreach (Access::staffWith('qa.approve') as $adminId) {
            $inserts[] = [
                'id' => (string) Str::uuid(), 'user_id' => $adminId,
                'title' => 'Task ready for client review', 'message' => $message,
                'type' => 'Project', 'created_at' => now(),
            ];
        }
        if (! empty($inserts)) {
            DB::table('notifications')->insert($inserts);
        }
    }
}
