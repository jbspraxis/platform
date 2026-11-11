<?php

namespace App\Support\Projects;

use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MilestonePlan;
use App\Models\Opportunity;
use App\Models\PlanSuggestion;
use App\Models\StatementOfWork;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\OpportunityContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Plan milestones (PR 5b; the owner's decisions of 1 Nov 2026, docs/PROJECTS.md section 9). On the AI service, in the
 * background, with no direct Gemini call:
 *
 *  - A project with milestones (MyCity: M0–M11, 148 tasks) is planned **one milestone at a time** (purpose
 *    plan_milestone). The AI reads the scope box (assembled from the opportunity: the signed SOW first, then the approved
 *    proposal, the questionnaire's answers, the meetings' notes and the approved change reports, as much as the person
 *    may see) and the adopted plan (every milestone, its tasks numbered, their status and estimates). For the chosen
 *    milestone it proposes new tasks and edits to its tasks (title, detail, estimate; never a lead), and notes on tasks
 *    anywhere that look duplicated or outside the scope. It never removes a task. When something is unclear it may ask
 *    for the transcript of one meeting (only if the person may read transcripts): a second pass then reads that one
 *    transcript and its answer is filed. The lead ticks what to keep; saving writes only the ticked ones, through
 *    Tasks::create/update, with a revision check (a page that is behind saves nothing), an audit line and one summary
 *    bell per person.
 *  - A project with no milestones keeps PR 5's route: its plan comes from the project draft on its opportunity. One
 *    without a project draft (no opportunity, or signed before PR 5) gets a whole plan (purpose plan_project), saved
 *    through PlanWriter as before.
 *
 * Rights: ProjectRules::canManage (projects.view and ProjectAccess, plus projects.create or being the project's lead),
 * checked by the screen on every request and again here where it writes.
 */
final class Planner
{
    public const MAX_NEW = 30;

    public const MAX_EDITS = 40;

    public const MAX_NOTES = 30;

    public const MAX_SCOPE = 60000;

    /** Characters of one transcript the second pass reads (an hour of talk is about 50,000) */
    public const MAX_TRANSCRIPT = 120000;

    /** SOW sections about money, left out for someone who can't open the opportunity */
    private const MONEY = '/fee|pric|payment|invoice|cost|budget|commercial|vat|tax|honorair|paiement|factur|prix|coût|tarif|tva/iu';

    public static function canPlan(string $projectId): bool
    {
        return ProjectRules::canManage($projectId);
    }

    public static function opportunity(string $projectId): ?Opportunity
    {
        $linked = DB::table('projects')->where('id', $projectId)->value('opportunity_id');

        return Opportunity::query()->where(fn ($q) => $q->where('project_id', $projectId)->when($linked, fn ($q) => $q->orWhere('id', $linked)))
            ->orderByDesc('updated_at')->first();
    }

    /** The opportunity whose project draft (PR 5) still holds this project's plan, if any */
    public static function pendingDraftOpportunity(string $projectId): ?string
    {
        return DB::table('project_drafts')->join('statements_of_work', 'statements_of_work.id', '=', 'project_drafts.sow_id')
            ->where('statements_of_work.project_id', $projectId)->where('project_drafts.status', '!=', 'created')->whereNull('project_drafts.deleted_at')
            ->value('project_drafts.opportunity_id');
    }

    /** draft_pending (the plan waits in the project draft), milestone (plan one at a time) or whole (a new plan) */
    public static function mode(string $projectId): string
    {
        return match (true) {
            self::pendingDraftOpportunity($projectId) !== null => 'draft_pending',
            DB::table('project_phases')->where('project_id', $projectId)->whereNull('deleted_at')->exists() => 'milestone',
            default => 'whole',
        };
    }

    /**
     * The scope box, assembled from the project's opportunity in the owner's order: the signed SOW first, then the
     * approved proposal, the questionnaire's answers, the meetings' notes (the AI's summaries, never transcripts) and
     * the approved change reports. Someone who can't open the opportunity gets only what the project's screens show:
     * the organisation's name and sector, the signed SOW without its fees, and the change reports (projectScope). A
     * project without an opportunity starts from its organisation's latest
     * signed legacy proposal, else empty for pasting. Returns [text, sources] (sources: short labels for "Sources used").
     *
     * @return array{string, list<string>}
     */
    public static function scope(string $projectId, ?User $user): array
    {
        $o = self::opportunity($projectId);
        $blocks = [];
        $sources = [];
        if ($o && OpportunityAccess::canView($user, $o)) {
            foreach (['profile', 'sow', 'proposal', 'questionnaire', 'notes'] as $part) {
                $text = $part === 'notes'
                    ? OpportunityContext::for($o, include: ['meetings', 'internal', 'notes'])
                    : OpportunityContext::for($o, include: [$part]);
                if (trim($text) === '') {
                    continue;
                }
                $blocks[] = $text;
                if ($part !== 'profile') {
                    $sources[] = self::sourceLabel($part, $o);
                }
            }
        } elseif ($o) {
            [$text, $hasSow] = self::projectScope($o);
            $blocks[] = $text;
            if ($hasSow) {
                $sources[] = self::sourceLabel('sow', $o);
            }
        } else {
            $proposal = DB::table('proposals')->where('organization_id', DB::table('projects')->where('id', $projectId)->value('organization_id'))
                ->whereIn('status', ['Authorized', 'Converted'])->orderByDesc('created_at')->first();
            if ($proposal) {
                $narrative = DB::table('proposal_ai_narratives')->where('proposal_id', $proposal->id)->first();
                $blocks[] = trim('EXECUTIVE SUMMARY' . "\n" . ($proposal->executive_summary ?: '') . "\n\n" . ($narrative?->ai_operational_strategy ? "APPROACH\n" . $narrative->ai_operational_strategy : ''));
                $sources[] = __('planner.sources.legacy', ['ref' => $proposal->proposal_ref ?: '—']);
            }
        }
        if ($reports = self::changeReports($projectId)) {
            $blocks[] = $reports[0];
            $sources[] = trans_choice('planner.sources.change_reports', $reports[1], ['count' => $reports[1]]);
        }

        return [Str::limit(implode("\n\n", $blocks), self::MAX_SCOPE, ''), $sources];
    }

    /**
     * For someone who may plan the project but not open its opportunity (the owner's "same rights as the screens"):
     * the organisation's name and sector, and the signed SOW's sections and milestones without its fees. No proposal,
     * line items, prices, weights, turnover or budget. Returns [text, whether a signed SOW was found].
     *
     * @return array{string, bool}
     */
    private static function projectScope(Opportunity $o): array
    {
        $org = $o->organization;
        $out = ['ORGANISATION', 'Name: ' . ($org?->name ?: '—')];
        if (filled($org?->industry)) {
            $out[] = 'Sector: ' . $org->industry;
        }
        $sow = StatementOfWork::where('opportunity_id', $o->id)->where('status', 'signed')->orderByDesc('version')->first();
        if (! $sow) {
            return [implode("\n", $out), false];
        }
        $out[] = '';
        $out[] = 'STATEMENT OF WORK' . ($sow->reference ? " {$sow->reference}" : '') . ' (signed; its fees are left out)';
        foreach ((array) $sow->sections as $section) {
            $heading = (string) ($section['title'] ?? $section['key'] ?? '');
            if (preg_match(self::MONEY, ($section['key'] ?? '') . ' ' . $heading)) {
                continue;
            }
            $out[] = '';
            $out[] = mb_strtoupper($heading);
            $out[] = trim((string) ($section['body'] ?? ''));
        }
        if (is_array($sow->milestones) && $sow->milestones) {
            $out[] = '';
            $out[] = 'MILESTONES';
            foreach (array_values($sow->milestones) as $i => $m) {
                $out[] = sprintf('%d. %s (%d weeks): %s', $i + 1, $m['title'] ?? '', (int) ($m['weeks'] ?? 0), trim((string) ($m['description'] ?? '')));
            }
        }

        return [implode("\n", $out), true];
    }

    /** A record for the AI between BEGIN and END lines; a marker line inside it is dropped, so the text can't close it */
    private static function fence(string $label, string $text): string
    {
        $text = preg_replace('/^[ \t]*(BEGIN|END) (SCOPE|ADOPTED PLAN|TRANSCRIPT)\b.*$/m', '', $text);

        return "BEGIN {$label}\n" . trim((string) $text) . "\nEND {$label}";
    }

    private static function sourceLabel(string $part, Opportunity $o): string
    {
        return match ($part) {
            'sow' => __('planner.sources.sow', ['ref' => DB::table('statements_of_work')->where('opportunity_id', $o->id)->where('status', 'signed')->value('reference') ?? '—']),
            'proposal' => __('planner.sources.proposal'),
            'questionnaire' => __('planner.sources.questionnaire'),
            default => trans_choice('planner.sources.notes', $n = $o->meetings()->where('status', '!=', 'cancelled')->count(), ['count' => $n]),
        };
    }

    /** The approved change reports as text, and how many: [text, count] or null */
    private static function changeReports(string $projectId): ?array
    {
        $reports = DB::table('change_reports')->where('project_id', $projectId)->where('status', 'approved')->whereNull('deleted_at')->orderBy('number')->get();
        if ($reports->isEmpty()) {
            return null;
        }
        $out = ['APPROVED CHANGE REPORTS (part of the scope)'];
        foreach ($reports as $r) {
            $out[] = sprintf('- CR-%02d %s: %s', $r->number, $r->title, trim((string) $r->description));
        }

        return [implode("\n", $out), $reports->count()];
    }

    /**
     * The adopted plan for the AI, fenced as a record: every milestone (code, title, weight, dates, status) and its
     * tasks numbered T1… (status, estimate, the start of the detail; the planned milestone's own tasks with their detail
     * in full, lines kept, so an edit never cuts it), the one to plan marked. Returns [text, task ids by number, ids of
     * the planned milestone's tasks].
     *
     * @return array{string, array<int, string>, list<string>}
     */
    public static function adopted(string $projectId, ?string $phaseId): array
    {
        $phases = DB::table('project_phases')->where('project_id', $projectId)->whereNull('deleted_at')->orderBy('phase_number')->get();
        $tasks = DB::table('tasks')->whereIn('phase_id', $phases->pluck('id'))->whereNull('deleted_at')->orderBy('created_at')->orderBy('id')->get()->groupBy('phase_id');
        $out = ['Every milestone with its tasks; the one TO PLAN gives each task’s detail in full. Use the T-numbers for edits and notes.'];
        $ids = [];
        $own = [];
        $n = 0;
        foreach ($phases as $phase) {
            $out[] = '';
            $out[] = sprintf('%s · %s (%s%%, %s to %s, %s)%s', ProjectPlan::code($phase), ProjectPlan::title($phase), rtrim(rtrim(number_format((float) $phase->weight_percentage, 2, '.', ''), '0'), '.'),
                $phase->start_date ?: '?', $phase->due_date ?: '?', $phase->status, $phase->id === $phaseId ? ' — TO PLAN' : '');
            if (filled($phase->goal)) {
                $out[] = '  Goal: ' . $phase->goal;
            }
            foreach ($tasks->get($phase->id, collect()) as $task) {
                $ids[++$n] = $task->id;
                $head = sprintf('  T%d [%s%s] %s', $n, $task->status, $task->estimated_hours !== null ? ', ' . rtrim(rtrim((string) $task->estimated_hours, '0'), '.') . 'h' : '', $task->title);
                if ($phase->id === $phaseId) {
                    $own[] = $task->id;
                    $out[] = $head;
                    $detail = trim(str_replace("\r", '', (string) $task->description));
                    foreach ($detail !== '' ? explode("\n", $detail) : [] as $line) {
                        $out[] = '      | ' . $line;
                    }
                } else {
                    $detail = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $task->description))), 120);
                    $out[] = $head . ($detail !== '' ? ' — ' . $detail : '');
                }
            }
        }

        return [self::fence('ADOPTED PLAN', implode("\n", $out)), $ids, $own];
    }

    /** The meetings whose transcript the AI may ask for (only for someone who may read transcripts), in date order */
    public static function meetings(?Opportunity $o, ?User $user): Collection
    {
        if (! $o || ! OpportunityAccess::canReadTranscripts($user, $o)) {
            return collect();
        }

        return Meeting::where('opportunity_id', $o->id)->where('status', '!=', 'cancelled')->whereHas('transcripts')->orderBy('starts_at')->get();
    }

    /** The project's plan being drafted or waiting for the lead (not saved), if any */
    public static function current(string $projectId): ?MilestonePlan
    {
        return MilestonePlan::where('project_id', $projectId)->whereIn('status', ['drafting', 'ready', 'failed'])->latest()->first();
    }

    /**
     * Asks the AI for a plan: of one milestone ($phaseId) when the project has milestones, or a whole plan (null) when it
     * has none and no project draft. One at a time per project: a plan already being drafted is returned as it is.
     * Throws AiUnavailable (no key, over the budget) before anything is spent; nothing is kept then.
     */
    public static function start(string $projectId, ?string $phaseId, string $scope, User $by): MilestonePlan
    {
        if (! self::canPlan($projectId)) {
            throw new AuthorizationException(__('planner.errors.rights'));
        }
        $mode = self::mode($projectId);
        if ($mode === 'draft_pending' || ($phaseId === null) !== ($mode === 'whole')
            || ($phaseId && ! DB::table('project_phases')->where('project_id', $projectId)->where('id', $phaseId)->whereNull('deleted_at')->exists())) {
            throw new \LogicException('This project can’t be planned this way now');
        }
        $plan = DB::transaction(function () use ($projectId, $phaseId, $scope, $by) {
            DB::table('projects')->where('id', $projectId)->lockForUpdate()->first();
            if ($running = MilestonePlan::where('project_id', $projectId)->where('status', 'drafting')->first()) {
                return $running;
            }
            // Earlier plans not saved are set aside: one review at a time
            MilestonePlan::where('project_id', $projectId)->whereIn('status', ['ready', 'failed'])->each(fn ($p) => $p->forceFill(['deleted_by' => $by->id])->save() && $p->delete());

            return MilestonePlan::create([
                'project_id' => $projectId,
                'phase_id' => $phaseId,
                'scope' => Str::limit(trim(str_replace("\r", '', $scope)), self::MAX_SCOPE, ''),
                'sources' => [],
                'status' => 'drafting',
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);
        });
        if (! $plan->wasRecentlyCreated) {
            return $plan;
        }
        $plan->forceFill(['sources' => self::scope($projectId, $by)[1]])->save();
        try {
            $run = self::queue($plan, $by, null);
        } catch (AiUnavailable $e) {
            $plan->forceDelete();
            throw $e;
        }
        $plan->forceFill(['ai_run_id' => $run->id])->save();
        ProjectRules::log('PLAN_REQUESTED', $phaseId ? 'Asked the AI to plan a milestone.' : 'Asked the AI to plan the project.', 'projects', $projectId, [], ['plan_id' => $plan->id, 'phase_id' => $phaseId]);
        self::sync($plan);

        return $plan->fresh();
    }

    /**
     * The job: CONTEXT = the scope box; MATERIAL = the adopted plan, the meetings the AI may ask about (numbered; only
     * when the person may read transcripts), and on a second pass the one transcript it asked for. The scope, the plan
     * and the transcript are fenced as records. What isn't sent stays on the run's input to read the answer back: the
     * pass, task ids by number, the planned milestone's own tasks, the meetings by number.
     */
    private static function queue(MilestonePlan $plan, ?User $by, ?Meeting $transcriptOf, ?string $why = null): AiRun
    {
        $o = self::opportunity($plan->project_id);
        $language = $o?->language === 'fr' ? 'fr' : 'en';
        $context = self::fence('SCOPE', (string) $plan->scope);
        if ($plan->phase_id === null) {
            return Ai::queue('plan_project', $plan, ['context' => $context, 'language' => $language, 'plan_id' => $plan->id, 'pass' => $plan->pass]);
        }
        [$text, $ids, $own] = self::adopted($plan->project_id, $plan->phase_id);
        $meetings = $transcriptOf ? collect() : self::meetings($o, $by ?? User::find($plan->created_by));
        $material = [$text];
        if ($meetings->isNotEmpty()) {
            $material[] = '';
            $material[] = 'MEETINGS (ask for the transcript of one of them by its number, only if it would settle something essential):';
            foreach ($meetings->values() as $i => $m) {
                $material[] = sprintf('%d. %s, %s (%s)', $i + 1, $m->title, Carbon::parse($m->starts_at)->setTimezone('Africa/Lagos')->format('j F Y'), $m->kind);
            }
        }
        if ($transcriptOf) {
            $transcript = $transcriptOf->transcripts()->orderByDesc('collected_at')->first();
            $material[] = '';
            $material[] = sprintf('THE TRANSCRIPT OF “%s” (%s), AS YOU ASKED%s. This is the second pass: read_transcript must be 0.', $transcriptOf->title,
                Carbon::parse($transcriptOf->starts_at)->setTimezone('Africa/Lagos')->format('j F Y'), $why ? ' (' . Str::limit($why, 300) . ')' : '');
            $material[] = self::fence('TRANSCRIPT', Str::limit((string) $transcript?->text, self::MAX_TRANSCRIPT, ' […]'));
        }

        return Ai::queue('plan_milestone', $plan, [
            'context' => $context,
            'material' => implode("\n", $material),
            'language' => $language,
            'plan_id' => $plan->id,
            'pass' => $plan->pass,
            'task_ids' => $ids,
            'own_task_ids' => $own,
            'meeting_ids' => $meetings->pluck('id')->values()->all(),
        ]);
    }

    /** Files a finished job that the listener missed (the page calls it on load and while it polls) */
    public static function sync(MilestonePlan $plan): void
    {
        if ($plan->status !== 'drafting' || ! $plan->ai_run_id) {
            return;
        }
        // Only the run's status until it has finished: its input (the scope and the adopted plan) and output are large
        $run = AiRun::query()->select(['id', 'status'])->find($plan->ai_run_id);
        if ($run?->isFinished() && ($run = AiRun::find($plan->ai_run_id))) {
            self::fileAiOutput($run);
        }
    }

    /**
     * What the planner page shows of a plan being drafted (its pass, its run's status and progress), as one string; null
     * when the project has no plan drafting, or when its run has finished (the page draws then, and sync() files it).
     * The page polls this while the AI works and draws itself again only when it changes, from the plan and run
     * columns alone (not their scope, input or output). Same plan as current().
     */
    public static function draftingState(string $projectId): ?string
    {
        $plan = MilestonePlan::where('project_id', $projectId)->whereIn('status', ['drafting', 'ready', 'failed'])->latest()
            ->first(['id', 'status', 'pass', 'ai_run_id', 'transcript_meeting_id', 'transcript_reason']);
        if (! $plan || $plan->status !== 'drafting') {
            return null;
        }
        $run = $plan->ai_run_id ? AiRun::query()->select(['id', 'status', 'progress'])->find($plan->ai_run_id) : null;

        return $run?->isFinished() ? null : self::stateOf($plan, $run);
    }

    /** draftingState's string for a plan and its run already in hand */
    public static function stateOf(MilestonePlan $plan, ?AiRun $run): string
    {
        return implode('|', [$plan->id, $plan->status, $plan->pass, $plan->ai_run_id, $plan->transcript_meeting_id, sha1((string) $plan->transcript_reason),
            $run?->status, (int) $run?->progress]);
    }

    /**
     * A planning job finished (FilePlanAi on AiRunFinished, or sync): filed once, under the plan's lock, and only while
     * the plan waits for that run on that pass (a first pass is never filed once the second has started). A first pass
     * that asks for one meeting's transcript (one of the meetings it was offered) starts the second pass instead; any
     * other answer becomes the plan's suggestions (or its drafted plan), ready for the lead.
     */
    public static function fileAiOutput(AiRun $run): void
    {
        if (! in_array($run->purpose, ['plan_milestone', 'plan_project'], true) || $run->subject_type !== 'milestone_plan' || ! $run->isFinished()) {
            return;
        }
        $second = DB::transaction(function () use ($run) {
            $plan = MilestonePlan::whereKey($run->subject_id)->lockForUpdate()->first();
            if (! $plan || $plan->ai_run_id !== $run->id || $plan->status !== 'drafting' || (int) ($run->input['pass'] ?? 1) !== $plan->pass) {
                return null;
            }
            if ($run->status !== 'done') {
                $plan->forceFill(['status' => 'failed', 'revision' => $plan->revision + 1])->save();

                return null;
            }
            $output = (array) $run->output;
            if ($run->purpose === 'plan_milestone' && $plan->pass === 1) {
                $number = (int) ($output['read_transcript']['meeting_number'] ?? 0);
                $meetingId = ((array) ($run->input['meeting_ids'] ?? []))[$number - 1] ?? null;
                if ($number > 0 && is_string($meetingId)) {
                    $plan->forceFill(['pass' => 2, 'transcript_meeting_id' => $meetingId,
                        'transcript_reason' => Str::limit(trim((string) ($output['read_transcript']['why'] ?? '')), 500, '')])->save();

                    return $plan;
                }
            }
            $run->purpose === 'plan_project' ? self::fileWhole($plan, $output) : self::fileSuggestions($plan, $run, $output);

            return null;
        });
        if (! $second) {
            return;
        }
        // The second pass, outside the lock: the same plan with that one transcript. Until its run is recorded below,
        // nothing files the first pass (its input says pass 1) and the second run's own filing waits for sync().
        try {
            $next = self::queue($second, null, Meeting::find($second->transcript_meeting_id), $second->transcript_reason);
        } catch (AiUnavailable) {
            // Over the budget meanwhile: the first pass's answer is filed instead, if the plan still waits for it
            DB::transaction(function () use ($second, $run) {
                $plan = MilestonePlan::whereKey($second->id)->lockForUpdate()->first();
                if ($plan && $plan->status === 'drafting' && $plan->pass === 2 && $plan->ai_run_id === $run->id) {
                    $plan->forceFill(['pass' => 1, 'transcript_meeting_id' => null, 'transcript_reason' => null]);
                    self::fileSuggestions($plan, $run, (array) $run->output);
                }
            });

            return;
        }
        $waiting = DB::transaction(function () use ($second, $run, $next) {
            $plan = MilestonePlan::whereKey($second->id)->lockForUpdate()->first();
            if (! $plan || $plan->status !== 'drafting' || $plan->pass !== 2 || $plan->ai_run_id !== $run->id) {
                return null;
            }
            $plan->forceFill(['ai_run_id' => $next->id])->save();

            return $plan;
        });
        if ($waiting) {
            self::sync($waiting);
        }
    }

    /** The AI's answer for one milestone becomes suggestions: new tasks, edits to that milestone's tasks, notes */
    private static function fileSuggestions(MilestonePlan $plan, AiRun $run, array $output): void
    {
        $ids = array_map('strval', (array) ($run->input['task_ids'] ?? []));
        $own = array_flip(array_map('strval', (array) ($run->input['own_task_ids'] ?? [])));
        $tasks = DB::table('tasks')->whereIn('id', array_values($ids))->whereNull('deleted_at')->get()->keyBy('id');
        $known = $tasks->map(fn ($t) => mb_strtolower(trim((string) $t->title)))->flip();
        $hours = fn ($v) => is_numeric($v) && (float) $v > 0 ? max(0.5, min(999, round((float) $v, 1))) : null;
        $position = 0;
        $rows = [];

        foreach (array_slice(array_filter((array) ($output['new_tasks'] ?? []), 'is_array'), 0, self::MAX_NEW) as $t) {
            $title = Str::limit(trim((string) ($t['title'] ?? '')), 255, '');
            // Never a task the plan already has
            if ($title === '' || $known->has(mb_strtolower($title))) {
                continue;
            }
            $known->put(mb_strtolower($title), true);
            $rows[] = ['kind' => 'new', 'task_id' => null, 'title' => $title, 'detail' => Str::limit(trim((string) ($t['detail'] ?? '')), 5000, ''), 'estimate_hours' => $hours($t['estimate_hours'] ?? null), 'why' => null];
        }
        $edited = [];
        foreach (array_slice(array_filter((array) ($output['edits'] ?? []), 'is_array'), 0, self::MAX_EDITS) as $e) {
            $taskId = $ids[(int) ($e['task_number'] ?? 0)] ?? null;
            $task = $taskId ? $tasks->get($taskId) : null;
            // Only the planned milestone's own tasks, one edit each
            if (! $task || ! isset($own[$taskId]) || isset($edited[$taskId])) {
                continue;
            }
            // Only what the AI changes: "" and 0 keep a field, and so does the same value
            $base = self::base($task);
            $title = Str::limit(trim((string) ($e['title'] ?? '')), 255, '');
            $detail = Str::limit(trim(str_replace("\r", '', (string) ($e['detail'] ?? ''))), 5000, '');
            $estimate = $hours($e['estimate_hours'] ?? null);
            $title = $title !== '' && $title !== $base['title'] ? $title : null;
            $detail = $detail !== '' && $detail !== trim(str_replace("\r", '', $base['description'])) ? $detail : null;
            $estimate = $estimate !== null && $estimate !== $base['estimated_hours'] ? $estimate : null;
            if ($title === null && $detail === null && $estimate === null) {
                continue;
            }
            $edited[$taskId] = true;
            $rows[] = ['kind' => 'edit', 'task_id' => $taskId, 'title' => $title, 'detail' => $detail, 'estimate_hours' => $estimate,
                'why' => Str::limit(trim((string) ($e['why'] ?? '')), 1000, '') ?: null, 'base' => $base];
        }
        foreach (array_slice(array_filter((array) ($output['notes'] ?? []), 'is_array'), 0, self::MAX_NOTES) as $n) {
            $taskId = $ids[(int) ($n['task_number'] ?? 0)] ?? null;
            $note = Str::limit(trim((string) ($n['note'] ?? '')), 1000, '');
            if ($taskId && $tasks->has($taskId) && $note !== '') {
                $rows[] = ['kind' => 'note', 'task_id' => $taskId, 'title' => null, 'detail' => null, 'estimate_hours' => null, 'why' => $note];
            }
        }

        foreach ($rows as $row) {
            PlanSuggestion::create($row + [
                'plan_id' => $plan->id,
                'phase_id' => $row['kind'] === 'note' ? $tasks->get($row['task_id'])->phase_id : $plan->phase_id,
                'position' => $position++,
                'status' => 'pending',
            ]);
        }
        $plan->forceFill(['status' => 'ready', 'revision' => $plan->revision + 1])->save();
    }

    /**
     * A task as an edit's base: what the AI read, kept on the suggestion; saving refuses the edit if any of it changed
     *
     * @return array{title: string, description: string, estimated_hours: ?float, phase_id: ?string}
     */
    private static function base(object $task): array
    {
        return ['title' => (string) $task->title, 'description' => (string) $task->description,
            'estimated_hours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null, 'phase_id' => $task->phase_id];
    }

    /** Whether a task is still as an edit's base says (same title, detail, estimate and milestone): the review and save use it */
    public static function stillAsRead(object $task, ?array $base): bool
    {
        $now = self::base($task);

        return $base !== null && $now['title'] === (string) ($base['title'] ?? '') && $now['description'] === (string) ($base['description'] ?? '')
            && $now['phase_id'] === ($base['phase_id'] ?? null)
            && $now['estimated_hours'] === (isset($base['estimated_hours']) ? (float) $base['estimated_hours'] : null);
    }

    /** The AI's whole plan, kept on the plan for the lead to check */
    private static function fileWhole(MilestonePlan $plan, array $output): void
    {
        $hours = fn ($v) => is_numeric($v) && (float) $v > 0 ? max(0.5, min(999, round((float) $v, 1))) : null;
        $milestones = [];
        foreach (array_slice(array_filter((array) ($output['milestones'] ?? []), 'is_array'), 0, 30) as $m) {
            $title = Str::limit(trim((string) ($m['title'] ?? '')), 140, '');
            if ($title === '') {
                continue;
            }
            $milestones[] = [
                'title' => $title,
                'goal' => Str::limit(trim((string) ($m['goal'] ?? '')), 500, ''),
                'tasks' => array_values(array_filter(array_map(fn ($t) => [
                    'title' => Str::limit(trim((string) ($t['title'] ?? '')), 255, ''),
                    'detail' => Str::limit(trim((string) ($t['detail'] ?? '')), 5000, ''),
                    'estimate_hours' => $hours($t['estimate_hours'] ?? null),
                ], array_slice(array_filter((array) ($m['tasks'] ?? []), 'is_array'), 0, 60)), fn ($t) => $t['title'] !== '')),
            ];
        }
        $plan->forceFill(['output' => $milestones, 'status' => $milestones ? 'ready' : 'failed', 'revision' => $plan->revision + 1])->save();
    }

    /**
     * Saves a milestone's review: the ticked suggestions (new tasks and edits) are written through Tasks::create and
     * Tasks::update, the others are set aside; notes are only read. An edit writes only the fields the AI changed, and
     * only if its task is still as the AI read it (title, detail, estimate, milestone; the task's row locked): else it
     * is refused and named. Under the plan's lock, once, and only from a page at the plan's revision. Nothing is ever
     * removed and no milestone is created. The people whose tasks changed get one bell each. Returns why not (rights,
     * state, stale), or what was done: added, changed, and the titles of the refused edits.
     *
     * @param  list<string>  $ticked
     * @return string|array{added: int, changed: int, refused: list<string>}
     */
    public static function save(MilestonePlan $plan, array $ticked, User $by, int $revision): string|array
    {
        if (! self::canPlan($plan->project_id)) {
            return 'rights';
        }
        $ticked = array_flip(array_map('strval', $ticked));
        $result = DB::transaction(function () use ($plan, $ticked, $by, $revision) {
            $locked = MilestonePlan::whereKey($plan->id)->lockForUpdate()->first();
            // Saved, set aside or changed since the page showed it
            if (! $locked || $locked->revision !== $revision) {
                return 'stale';
            }
            if ($locked->status !== 'ready' || $locked->phase_id === null) {
                return 'state';
            }
            $added = 0;
            $changed = 0;
            $refused = [];
            $leads = [];
            foreach ($locked->suggestions()->where('status', 'pending')->whereIn('kind', ['new', 'edit'])->get() as $s) {
                $status = 'rejected';
                if (isset($ticked[$s->id]) && $s->kind === 'new') {
                    if ($s->phase_id && DB::table('project_phases')->where('id', $s->phase_id)->where('project_id', $locked->project_id)->whereNull('deleted_at')->exists()) {
                        Tasks::create($locked->project_id, array_filter(['phase_id' => $s->phase_id, 'title' => $s->title, 'description' => (string) $s->detail,
                            'estimated_hours' => $s->estimate_hours !== null ? (float) $s->estimate_hours : null], fn ($v) => $v !== null), notify: false);
                        $added++;
                        $status = 'accepted';
                    }
                } elseif (isset($ticked[$s->id]) && $s->kind === 'edit') {
                    DB::table('tasks')->where('id', $s->task_id)->lockForUpdate()->first();
                    $task = Tasks::find($locked->project_id, $s->task_id);
                    if ($task && self::stillAsRead($task, $s->base)) {
                        Tasks::update($locked->project_id, $task, array_filter(['title' => $s->title, 'description' => $s->detail,
                            'estimated_hours' => $s->estimate_hours !== null ? (float) $s->estimate_hours : null], fn ($v) => $v !== null));
                        $changed++;
                        $status = 'accepted';
                        if ($task->assigned_to) {
                            $leads[] = $task->assigned_to;
                        }
                    } else {
                        // Changed, moved to another milestone or deleted after the AI read it
                        $refused[] = (string) ($task->title ?? $s->base['title'] ?? $s->title);
                        $status = 'refused';
                    }
                }
                $s->forceFill(['status' => $status, 'decided_by' => $by->id, 'decided_at' => now()])->save();
            }
            $locked->forceFill(['status' => 'saved', 'revision' => $locked->revision + 1, 'updated_by' => $by->id])->save();
            ProjectRules::log('PLAN_SAVED', sprintf('Saved the milestone plan (%d added, %d changed, %d refused because the task changed).', $added, $changed, count($refused)),
                'projects', $locked->project_id, [], ['plan_id' => $locked->id, 'phase_id' => $locked->phase_id, 'added' => $added, 'changed' => $changed, 'refused' => count($refused)]);

            return ['added' => $added, 'changed' => $changed, 'refused' => $refused, 'leads' => $leads];
        });
        if (is_string($result)) {
            return $result;
        }
        self::tellLeads($plan->project_id, $result['leads'], $by);
        unset($result['leads']);

        return $result;
    }

    /** One bell per person whose tasks the plan changed (never the person who saved it), in their language */
    private static function tellLeads(string $projectId, array $leadIds, User $by): void
    {
        $counts = array_count_values(array_filter($leadIds, fn ($id) => is_string($id) && $id !== $by->id));
        $title = (string) DB::table('projects')->where('id', $projectId)->value('title');
        foreach (DB::table('users')->whereIn('id', array_keys($counts))->where('status', 'Active')->whereNull('deleted_at')->pluck('id') as $userId) {
            $locale = Tasks::localeFor($userId);
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $userId, 'type' => 'Project',
                'title' => __('planner.bell.title', [], $locale),
                'message' => trans_choice('planner.bell.changed', $counts[$userId], ['count' => $counts[$userId], 'project' => $title], $locale),
                'link_url' => route('hq.ops.project', ['project' => $projectId, 'tab' => 'backlog', 'lead' => $userId]),
                'created_at' => now(),
            ]);
        }
    }

    /** Removes one drafted task (or a whole drafted milestone with $t null) from a whole plan, at the plan's revision */
    public static function removeDrafted(MilestonePlan $plan, int $m, ?int $t, int $revision): bool
    {
        return DB::transaction(function () use ($plan, $m, $t, $revision) {
            $locked = MilestonePlan::whereKey($plan->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'ready' || $locked->phase_id !== null || $locked->revision !== $revision) {
                return false;
            }
            $output = (array) $locked->output;
            if ($t === null) {
                unset($output[$m]);
            } else {
                unset($output[$m]['tasks'][$t]);
                if (isset($output[$m])) {
                    $output[$m]['tasks'] = array_values($output[$m]['tasks']);
                }
            }
            $locked->forceFill(['output' => array_values($output), 'revision' => $locked->revision + 1])->save();

            return true;
        });
    }

    /**
     * Saves a whole plan through PlanWriter, as Plan milestones always did (two weeks each from today, the weights
     * shared out), only while the project still has no milestones and no project draft. Returns null, or why not:
     * rights, state, stale, plan_exists, empty.
     */
    public static function saveWhole(MilestonePlan $plan, User $by, int $revision): ?string
    {
        if (! self::canPlan($plan->project_id)) {
            return 'rights';
        }

        return DB::transaction(function () use ($plan, $by, $revision) {
            DB::table('projects')->where('id', $plan->project_id)->lockForUpdate()->first();
            $locked = MilestonePlan::whereKey($plan->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'ready' || $locked->phase_id !== null) {
                return 'state';
            }
            if ($locked->revision !== $revision) {
                return 'stale';
            }
            if (self::mode($locked->project_id) !== 'whole') {
                return 'plan_exists';
            }
            $milestones = (array) $locked->output;
            if (! $milestones) {
                return 'empty';
            }
            $total = (float) DB::table('projects')->where('id', $locked->project_id)->value('total_budget');
            $weight = round(100 / count($milestones), 2);
            $start = Carbon::now('Africa/Lagos')->startOfDay();
            $plan = [];
            foreach ($milestones as $m) {
                $due = $start->copy()->addDays(13);
                $plan[] = ['title' => $m['title'], 'goal' => $m['goal'], 'weight_percentage' => $weight, 'amount' => round($total * $weight / 100, 2),
                    'start_date' => $start->toDateString(), 'due_date' => $due->toDateString(),
                    'tasks' => array_map(fn ($t) => ['title' => $t['title'], 'description' => $t['detail'], 'estimated_hours' => $t['estimate_hours']], (array) $m['tasks'])];
                $start = $due->copy()->addDay();
            }
            PlanWriter::write($locked->project_id, $plan, notify: false);
            PlanWriter::announce($locked->project_id, (string) DB::table('projects')->where('id', $locked->project_id)->value('title'));
            $locked->forceFill(['status' => 'saved', 'revision' => $locked->revision + 1, 'updated_by' => $by->id])->save();

            return null;
        });
    }
}
