<?php

namespace App\Support\Opportunities;

use App\Events\ProjectCreatedFromOpportunity;
use App\Models\AiRun;
use App\Models\Opportunity;
use App\Models\ProjectDraft;
use App\Models\StatementOfWork;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\Projects\PlanWriter;
use App\Support\Projects\ProjectRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The project draft (docs/OPPORTUNITIES.md section 4, row 5, as the owner changed it on 1 Nov 2026): once the SOW is
 * signed, its milestones become the project's milestones (docs/PROJECTS.md decision 9: a milestone is the unit of
 * work, with its own dates and task board; there are no sprints), with dates from the SOW's weeks, and weights and
 * amounts from the SOW that add up to the contract total. The AI (purpose project_draft, Claude Sonnet) adds each
 * milestone's goal and tasks and the roles the work needs; the lead picks a person per role, edits everything, can
 * add a 0% milestone (such as "Later"), and clicks Create project, which writes the plan through PR 7's support
 * classes (PlanWriter → Milestones, Tasks) into the project made on signing, sets its lead (the opportunity's lead)
 * and dates, and links the opportunity to it (opportunities.project_id). Every task belongs to a milestone.
 *
 * payload: {project: {title, description, start_date}, currency, total, lead_user_id,
 *           milestones: [{key, sow_key, title, goal, description, weeks, weight, amount, start_date, due_date,
 *                         tasks: [{title, description, role_key, estimate_hours}]}],
 *           roles: [{key, title, responsibilities, user_id}], unavailable}
 */
final class ProjectDrafts
{
    public const MAX_TASKS = 300;

    public const MAX_TASKS_PER_MILESTONE = 60;

    public const MAX_ROLES = 15;

    /** Milestones the lead may add on top of the SOW's, each at 0% */
    public const MAX_EXTRA = 5;

    /** Edit the draft and create the project: the owner (opportunities.approve) or the opportunity's lead, once the work is won */
    public static function canEdit(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::canApprove($user, $opportunity) && $opportunity->stage === 'won';
    }

    public static function forOpportunity(Opportunity $opportunity): ?ProjectDraft
    {
        return ProjectDraft::where('opportunity_id', $opportunity->id)->latest()->first();
    }

    /**
     * On signing (Sows::sign): the draft from the signed SOW, then the AI's tasks in the background. Without a key or
     * over the budget, the draft is ready at once with the SOW's milestones and no tasks, and says so. Once per SOW.
     */
    public static function queue(StatementOfWork $sow): ProjectDraft
    {
        $draft = DB::transaction(function () use ($sow) {
            Opportunity::whereKey($sow->opportunity_id)->lockForUpdate()->first();
            if ($existing = ProjectDraft::where('sow_id', $sow->id)->first()) {
                return $existing;
            }

            return ProjectDraft::create([
                'opportunity_id' => $sow->opportunity_id,
                'sow_id' => $sow->id,
                'payload' => self::skeleton($sow),
                'status' => 'drafting',
                'created_by' => $sow->approved_by,
                'updated_by' => $sow->approved_by,
            ]);
        });
        if ($draft->wasRecentlyCreated) {
            self::askAi($draft, '');
        }

        return $draft->fresh();
    }

    /** Asks the AI again (the lead's instructions): it replaces the goals, tasks and roles, never the dates, weights or amounts */
    public static function draft(ProjectDraft $draft, string $instructions, User $by): bool
    {
        self::sync($draft);
        if ($draft->status === 'created' || Proposals::running($draft->ai_run_id)) {
            return false;
        }
        $draft->forceFill(['updated_by' => $by->id])->save();

        return self::askAi($draft, $instructions);
    }

    private static function askAi(ProjectDraft $draft, string $instructions): bool
    {
        $sow = $draft->sow;
        $opportunity = $draft->opportunity;
        try {
            $run = Ai::queue('project_draft', $draft, [
                // The approved proposal, the signed SOW, the answers and the meetings' notes; no full transcript (6.2, rule 4)
                'context' => OpportunityContext::for($opportunity),
                'material' => self::material($draft),
                'instructions' => Str::limit(trim($instructions), Sows::MAX_NOTE, ''),
                'language' => $opportunity->language,
            ]);
        } catch (AiUnavailable) {
            $draft->forceFill(['status' => 'ready', 'payload' => ['unavailable' => true] + (array) $draft->payload])->save();
            Sows::tellLead($opportunity, __('project_draft.notify.manual_title', ['title' => $opportunity->title], config('app.staff_locale', 'en')),
                __('project_draft.notify.manual', [], config('app.staff_locale', 'en')), 'project');

            return false;
        }
        $payload = (array) $draft->payload;
        unset($payload['unavailable']);
        $draft->forceFill(['status' => 'drafting', 'ai_run_id' => $run->id, 'payload' => $payload])->save();
        OpportunityAccess::log('PROJECT_DRAFT_REQUESTED', "Asked the AI to draft the project plan from statement of work {$sow?->reference}.", 'opportunities', $opportunity->id,
            [], ['project_draft_id' => $draft->id]);
        self::sync($draft);

        return true;
    }

    /**
     * The draft from the signed SOW: its milestones in order, each with its weeks, weight and amount (adding up to the
     * contract total), dated one after another from the first working day after signing (Lagos), no tasks yet.
     */
    public static function skeleton(StatementOfWork $sow): array
    {
        $total = Sows::contractTotal($sow);
        $milestones = Sows::cleanMilestones((array) $sow->milestones);
        $amounts = Sows::amounts($milestones, $total);
        $start = self::nextWorkingDay(Carbon::parse($sow->signed_at ?? now())->setTimezone('Africa/Lagos'));

        $out = [];
        $from = $start->copy();
        foreach ($milestones as $i => $m) {
            $due = $from->copy()->addDays($m['weeks'] * 7 - 1);
            $out[] = [
                'key' => 'm' . ($i + 1), 'sow_key' => $m['key'], 'title' => $m['title'], 'goal' => '', 'description' => $m['description'],
                'weeks' => $m['weeks'], 'weight' => $m['weight'], 'amount' => $amounts[$i],
                'start_date' => $from->toDateString(), 'due_date' => $due->toDateString(), 'tasks' => [],
            ];
            $from = $due->copy()->addDay();
        }

        return [
            'project' => ['title' => Str::limit((string) $sow->title, 255, ''), 'description' => '', 'start_date' => $start->toDateString()],
            'currency' => (string) ($sow->proposal?->currency ?? 'USD'),
            'total' => $total,
            'lead_user_id' => $sow->opportunity?->lead_user_id,
            'milestones' => $out,
            'roles' => [],
        ];
    }

    /** Applies a finished draft (row locked, once); a failed one leaves the SOW's milestones to plan by hand */
    public static function sync(ProjectDraft $draft): bool
    {
        $runId = $draft->ai_run_id;
        if (! $runId || ! AiRun::whereKey($runId)->whereIn('status', ['done', 'failed'])->exists()) {
            return false;
        }
        $applied = DB::transaction(function () use ($draft, $runId) {
            $locked = ProjectDraft::whereKey($draft->id)->lockForUpdate()->first();
            $run = AiRun::find($runId);
            if (! $locked || ! $run || $locked->ai_run_id !== $runId || $locked->status === 'created') {
                return false;
            }
            if ($run->status === 'failed') {
                // Kept linked so the tab says why, until dismissed; the lead can plan by hand meanwhile
                if ($locked->status !== 'ready') {
                    $locked->forceFill(['status' => 'ready'])->save();

                    return true;
                }

                return false;
            }

            return self::applyDraft($locked, $run);
        });
        if ($applied) {
            $draft->refresh();
        }

        return $applied;
    }

    /** The job finished (FileProjectDraftAi on AiRunFinished): filed at once, and the lead gets a bell */
    public static function fileAiOutput(AiRun $run): void
    {
        if ($run->purpose !== 'project_draft' || $run->subject_type !== 'project_draft' || ! $run->isFinished()) {
            return;
        }
        $draft = ProjectDraft::find($run->subject_id);
        if (! $draft || $draft->ai_run_id !== $run->id) {
            return;
        }
        self::sync($draft);
        $opportunity = $draft->opportunity;
        $locale = config('app.staff_locale', 'en');
        if ($draft->ai_run_id === null) {
            Sows::tellLead($opportunity, __('project_draft.notify.ready_title', ['title' => $opportunity->title], $locale), __('project_draft.notify.ready', [], $locale), 'project');
        } elseif (($run = $run->fresh())->status === 'failed') {
            Sows::tellLead($opportunity, __('project_draft.notify.failed_title', ['title' => $opportunity->title], $locale),
                __('project_draft.notify.failed', ['message' => $run->errorMessage()], $locale), 'project');
        }
    }

    public static function dismissFailure(ProjectDraft $draft): void
    {
        if ($draft->ai_run_id && ! Proposals::running($draft->ai_run_id)) {
            $draft->forceFill(['ai_run_id' => null, 'status' => $draft->status === 'created' ? 'created' : 'ready'])->save();
        }
    }

    /** Saves the lead's edits (cleaned: the SOW's weights and amounts can't change); false once created or while the AI works */
    public static function save(ProjectDraft $draft, array $payload, User $by): bool
    {
        return DB::transaction(function () use ($draft, $payload, $by) {
            $locked = ProjectDraft::whereKey($draft->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'ready' || Proposals::running($locked->ai_run_id)) {
                return false;
            }
            $locked->forceFill(['payload' => self::clean($payload, $locked), 'updated_by' => $by->id])->save();
            $draft->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * The payload as HQ keeps it. The SOW's milestones keep their order, weight and amount whatever the browser sent;
     * the lead's own milestones are 0%; tasks need a title; a role's person must be active staff; dates are real dates.
     */
    public static function clean(array $payload, ProjectDraft $draft): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : null;
        $skeleton = collect((array) ($draft->payload['milestones'] ?? []))->filter(fn ($m) => ! empty($m['sow_key']))->keyBy('sow_key');

        $roles = [];
        foreach (array_slice(array_values((array) ($payload['roles'] ?? [])), 0, self::MAX_ROLES) as $i => $r) {
            $title = Str::limit(trim((string) ($r['title'] ?? '')), 100, '');
            if ($title === '' || ! is_array($r)) {
                continue;
            }
            $key = Str::limit(preg_replace('/[^a-z0-9_]/', '', Str::snake(Str::ascii((string) ($r['key'] ?? '')))), 40, '') ?: 'role_' . ($i + 1);
            $userId = (string) ($r['user_id'] ?? '');
            $roles[$key] = [
                'key' => $key, 'title' => $title, 'responsibilities' => Str::limit(trim((string) ($r['responsibilities'] ?? '')), 1000, ''),
                'user_id' => $userId !== '' && DB::table('users')->where('id', $userId)->where('status', 'Active')->whereNull('deleted_at')->exists() ? $userId : null,
            ];
        }

        $milestones = [];
        $seen = [];
        $extra = 0;
        $tasks = 0;
        foreach ((array) ($payload['milestones'] ?? []) as $i => $m) {
            if (! is_array($m)) {
                continue;
            }
            $fromSow = $skeleton->get($m['sow_key'] ?? '');
            if ($fromSow && isset($seen[$fromSow['sow_key']])) {
                continue;
            }
            if (! $fromSow && ++$extra > self::MAX_EXTRA) {
                continue;
            }
            $list = [];
            foreach ((array) ($m['tasks'] ?? []) as $t) {
                $title = Str::limit(trim((string) ($t['title'] ?? '')), 255, '');
                if (! is_array($t) || count($list) >= self::MAX_TASKS_PER_MILESTONE || $tasks >= self::MAX_TASKS) {
                    continue;
                }
                $tasks++;
                $list[] = [
                    'title' => $title,
                    'description' => Str::limit(trim(str_replace("\r", '', (string) ($t['description'] ?? ''))), 5000, ''),
                    'role_key' => isset($roles[$t['role_key'] ?? '']) ? $t['role_key'] : '',
                    'estimate_hours' => is_numeric($t['estimate_hours'] ?? null) ? max(0, min(999, round((float) $t['estimate_hours'], 1))) : null,
                ];
            }
            $start = $date($m['start_date'] ?? null);
            $due = $date($m['due_date'] ?? null);
            $ownKey = (string) ($m['key'] ?? '');
            $milestones[] = [
                'key' => $fromSow ? $fromSow['key'] : (preg_match('/^x[a-z0-9]{1,20}$/', $ownKey) ? $ownKey : 'x' . substr(md5($ownKey . $i), 0, 8)),
                'sow_key' => $fromSow ? $fromSow['sow_key'] : null,
                'title' => Str::limit(trim((string) ($m['title'] ?? '')), 140, '') ?: ($fromSow['title'] ?? __('project_draft.later', [], $draft->opportunity?->language ?? 'en')),
                'goal' => Str::limit(trim((string) ($m['goal'] ?? '')), 500, ''),
                'description' => $fromSow ? $fromSow['description'] : Str::limit(trim((string) ($m['description'] ?? '')), 2000, ''),
                'weeks' => $fromSow ? $fromSow['weeks'] : max(1, min(104, (int) ($m['weeks'] ?? 2))),
                'weight' => $fromSow ? $fromSow['weight'] : 0,
                'amount' => $fromSow ? $fromSow['amount'] : 0,
                'start_date' => $start ?? ($fromSow['start_date'] ?? null),
                'due_date' => $due ?? ($fromSow['due_date'] ?? null),
                'tasks' => $list,
            ];
            if ($fromSow) {
                $seen[$fromSow['sow_key']] = true;
            }
        }
        // A SOW milestone is never dropped: it is part of the contract
        foreach ($skeleton as $key => $m) {
            if (! isset($seen[$key])) {
                $milestones[] = $m;
            }
        }

        $project = (array) ($payload['project'] ?? []);

        return [
            'project' => [
                'title' => Str::limit(trim((string) ($project['title'] ?? '')), 255, '') ?: (string) ($draft->payload['project']['title'] ?? ''),
                'description' => Str::limit(trim(str_replace("\r", '', (string) ($project['description'] ?? ''))), 5000, ''),
                'start_date' => $date($project['start_date'] ?? null) ?? ($draft->payload['project']['start_date'] ?? null),
            ],
            'currency' => $draft->payload['currency'] ?? 'USD',
            'total' => $draft->payload['total'] ?? 0,
            'lead_user_id' => $draft->payload['lead_user_id'] ?? null,
            'milestones' => $milestones,
            'roles' => array_values($roles),
        ];
    }

    /** The project starts on another day: every milestone moves by as many days, so the lead's own dates keep their gaps */
    public static function shift(array $payload, string $newStart): array
    {
        $old = $payload['project']['start_date'] ?? null;
        if (! $old || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $newStart)) {
            return $payload;
        }
        $days = (int) Carbon::parse($old)->diffInDays(Carbon::parse($newStart), false);
        foreach ((array) ($payload['milestones'] ?? []) as $i => $m) {
            foreach (['start_date', 'due_date'] as $field) {
                if (! empty($m[$field])) {
                    $payload['milestones'][$i][$field] = Carbon::parse($m[$field])->addDays($days)->toDateString();
                }
            }
        }
        $payload['project']['start_date'] = $newStart;

        return $payload;
    }

    /**
     * Checks the draft can become the plan. Null when it can, else why: created, running, start (no start date),
     * dates (a milestone without dates, or ending before it starts), weights (the SOW's don't add up to 100%), title,
     * project (the project made on signing is gone), lead (the opportunity's lead isn't active).
     */
    public static function problem(ProjectDraft $draft): ?string
    {
        $payload = (array) $draft->payload;
        $sow = $draft->sow;

        return match (true) {
            $draft->status === 'created' => 'created',
            Proposals::running($draft->ai_run_id) || $draft->status === 'drafting' => 'running',
            trim((string) ($payload['project']['title'] ?? '')) === '' => 'title',
            empty($payload['project']['start_date']) => 'start',
            collect((array) ($payload['milestones'] ?? []))->contains(fn ($m) => empty($m['start_date']) || empty($m['due_date']) || $m['due_date'] < $m['start_date']) => 'dates',
            abs(Sows::weightTotal((array) ($payload['milestones'] ?? [])) - 100) > 0.001 => 'weights',
            ! $sow?->project_id || ! DB::table('projects')->where('id', $sow->project_id)->whereNull('deleted_at')->exists() => 'project',
            ! ($payload['lead_user_id'] ?? null) || ! DB::table('users')->where('id', $payload['lead_user_id'])->where('status', 'Active')->whereNull('deleted_at')->exists() => 'lead',
            default => null,
        };
    }

    /**
     * Create project: in one transaction, claimed under the opportunity's lock so it happens once. The project made on
     * signing takes the draft's title, description, lead, start and end dates and becomes Active; its milestones and
     * tasks are written through PlanWriter (each task led by the person picked for its role, with its estimate); the
     * delivery team is told the plan is ready; the opportunity points at the project. Returns null when done, else
     * why not (problem()).
     */
    public static function create(ProjectDraft $draft, User $by): ?string
    {
        self::sync($draft);
        $why = DB::transaction(function () use ($draft, $by) {
            Opportunity::whereKey($draft->opportunity_id)->lockForUpdate()->first();
            $locked = ProjectDraft::whereKey($draft->id)->lockForUpdate()->first();
            if (! $locked) {
                return 'created';
            }
            if ($why = self::problem($locked)) {
                return $why;
            }
            $payload = (array) $locked->payload;
            $projectId = $locked->sow->project_id;
            $people = collect((array) $payload['roles'])->pluck('user_id', 'key');
            $milestones = (array) $payload['milestones'];
            $end = collect($milestones)->max('due_date');

            $before = (array) DB::table('projects')->where('id', $projectId)->first(['title', 'status', 'lead_user_id', 'start_date', 'target_end_date']);
            $after = [
                'title' => $payload['project']['title'],
                'description' => trim($payload['project']['description']) !== '' ? $payload['project']['description'] : DB::table('projects')->where('id', $projectId)->value('description'),
                'status' => 'Active',
                'lead_user_id' => $payload['lead_user_id'],
                'start_date' => $payload['project']['start_date'],
                'target_end_date' => $end,
            ];
            DB::table('projects')->where('id', $projectId)->update($after + ['updated_by' => $by->id, 'updated_at' => now()]);
            ProjectRules::log('PROJECT_CREATED_FROM_DRAFT', "Created the plan of “{$after['title']}” from the signed statement of work.", 'projects', $projectId, $before, array_diff_key($after, ['description' => 1]));

            PlanWriter::write($projectId, array_map(fn ($m) => [
                'title' => $m['title'],
                'goal' => $m['goal'],
                'description' => $m['description'],
                'weight_percentage' => $m['weight'],
                'amount' => $m['amount'],
                'start_date' => $m['start_date'],
                'due_date' => $m['due_date'],
                'tasks' => array_map(fn ($t) => [
                    'title' => $t['title'],
                    'description' => $t['description'],
                    'assigned_to' => $people[$t['role_key'] ?? ''] ?? null,
                    'estimated_hours' => $t['estimate_hours'],
                ], (array) $m['tasks']),
            ], $milestones));
            PlanWriter::announce($projectId, $after['title']);

            $locked->forceFill(['status' => 'created', 'created_project_id' => $projectId, 'updated_by' => $by->id])->save();
            Opportunity::whereKey($locked->opportunity_id)->update(['project_id' => $projectId, 'updated_by' => $by->id, 'updated_at' => now()]);
            OpportunityAccess::log('PROJECT_CREATED', "Created the project “{$after['title']}” from the project draft.", 'opportunities', $locked->opportunity_id,
                [], ['project_id' => $projectId, 'milestones' => count($milestones), 'tasks' => collect($milestones)->sum(fn ($m) => count($m['tasks']))]);

            return null;
        });
        if ($why === null) {
            $draft->refresh();
            ProjectCreatedFromOpportunity::dispatch($draft->created_project_id, $draft->opportunity_id, $by->id);
        }

        return $why;
    }

    /** The first working day (Monday to Friday) after $day */
    private static function nextWorkingDay(Carbon $day): Carbon
    {
        $next = $day->copy()->startOfDay()->addDay();
        while ($next->isWeekend()) {
            $next->addDay();
        }

        return $next;
    }

    /** The SOW's milestones, numbered for the AI, with what is fixed */
    private static function material(ProjectDraft $draft): string
    {
        $out = ['THE SIGNED STATEMENT OF WORK’S MILESTONES (HQ fixes their dates, weights and amounts; milestone_number is the number here):'];
        foreach (array_values((array) ($draft->payload['milestones'] ?? [])) as $i => $m) {
            if (! empty($m['sow_key'])) {
                $out[] = sprintf('%d. %s (%d weeks, %s to %s): %s', $i + 1, $m['title'], (int) $m['weeks'], $m['start_date'], $m['due_date'], trim((string) $m['description']));
            }
        }

        return implode("\n", $out);
    }

    /** The AI's goals, tasks and roles join the draft's milestones (by milestone_number); dates, weights and amounts stay */
    private static function applyDraft(ProjectDraft $locked, AiRun $run): bool
    {
        $payload = (array) $locked->payload;
        $roles = [];
        foreach (array_slice((array) ($run->output['roles'] ?? []), 0, self::MAX_ROLES) as $r) {
            $key = Str::limit(preg_replace('/[^a-z0-9_]/', '', Str::snake(Str::ascii((string) ($r['key'] ?? '')))), 40, '');
            $title = Str::limit(trim((string) ($r['title'] ?? '')), 100, '');
            if ($key !== '' && $title !== '' && ! isset($roles[$key])) {
                $old = collect((array) ($payload['roles'] ?? []))->firstWhere('key', $key);
                $roles[$key] = ['key' => $key, 'title' => $title, 'responsibilities' => Str::limit(trim((string) ($r['responsibilities'] ?? '')), 1000, ''), 'user_id' => $old['user_id'] ?? null];
            }
        }
        $byNumber = collect((array) ($run->output['milestones'] ?? []))->filter(fn ($m) => is_array($m))->keyBy(fn ($m) => (int) ($m['milestone_number'] ?? 0));
        $filed = 0;
        foreach ((array) ($payload['milestones'] ?? []) as $i => $m) {
            $ai = $byNumber->get($i + 1);
            if (! $ai || empty($m['sow_key'])) {
                continue;
            }
            $payload['milestones'][$i]['goal'] = Str::limit(trim((string) ($ai['goal'] ?? '')), 500, '');
            $payload['milestones'][$i]['tasks'] = array_values(array_filter(array_map(fn ($t) => [
                'title' => Str::limit(trim((string) ($t['title'] ?? '')), 255, ''),
                'description' => Str::limit(trim((string) ($t['description'] ?? '')), 5000, ''),
                'role_key' => isset($roles[$t['role_key'] ?? '']) ? $t['role_key'] : '',
                'estimate_hours' => is_numeric($t['estimate_hours'] ?? null) ? max(0, min(999, round((float) $t['estimate_hours'], 1))) : null,
            ], array_slice(array_filter((array) ($ai['tasks'] ?? []), 'is_array'), 0, self::MAX_TASKS_PER_MILESTONE)), fn ($t) => $t['title'] !== ''));
            $filed += count($payload['milestones'][$i]['tasks']);
        }
        if ($filed === 0) {
            $run->forceFill(['status' => 'failed', 'error' => 'ai.errors.invalid'])->save();
            $locked->forceFill(['status' => 'ready'])->save();

            return true;
        }
        $payload['roles'] = array_values($roles);
        unset($payload['unavailable']);
        $locked->forceFill(['payload' => $payload, 'status' => 'ready', 'ai_run_id' => null, 'updated_by' => $run->requested_by])->save();

        return true;
    }
}
