<?php

namespace App\Support\Opportunities;

use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingPoint;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Ai\Ai;
use App\Support\ProjectAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Milestone handovers (docs/OPPORTUNITIES.md section 12, PR 6). For a milestone (project_phases) of a project that has
 * an opportunity (opportunities.project_id), the lead schedules the client meeting that hands it over, through
 * Meetings::schedule (meetings.phase_id); forMilestone() is what a screen shows for one milestone (the milestone board,
 * docs/PROJECTS.md, shows it for the milestone it shows). Its transcript is collected like any other; once filed,
 * the AI drafts the points raised (handover_points, Haiku) as meeting_points. The lead edits them and confirms each
 * one, which creates a QA ticket on the project, linked to the milestone's task when known; nothing is created
 * without that confirmation. The meeting then shows every point with its ticket's status, and signoffText() lists
 * them for the handover sign-off.
 *
 * Who (always for this opportunity: the permission, or being on its team, OpportunityAccess): anyone who can view it
 * sees the points; drafting them reads the transcript (canManage + canReadTranscripts). The milestone's task titles
 * (agenda, task picker) and creating QA tickets on the project also need the project, as the milestone board and the QA
 * hub do (ProjectAccess::maySee); confirming creates tickets the client sees, so it is the lead's or the owner's
 * (canReview: canManage + canApprove + the project).
 */
final class Handovers
{
    public const SEVERITIES = ['Low', 'Medium', 'Critical'];

    /** The fields of a draft point the lead can change */
    public const FIELDS = ['title', 'detail', 'raised_by', 'severity', 'task_id'];

    /** Points one meeting can hold, drafted and added together */
    public const MAX_POINTS = 50;

    public const MAX_TITLE = 200;

    public const MAX_DETAIL = 2000;

    public const MAX_RAISED_BY = 150;

    /** Milestone tasks listed for the AI and the task picker */
    public const MAX_TASKS = 200;

    /** The opportunity that holds this project, or null: a project without one can't schedule a handover */
    public static function opportunityForProject(string $projectId): ?Opportunity
    {
        return Opportunity::where('project_id', $projectId)->orderByRaw("stage = 'won' desc")->orderByDesc('updated_at')->first();
    }

    /** The milestone (a project_phases row), when it is one of the opportunity's project's milestones */
    public static function milestoneFor(Opportunity $opportunity, ?string $phaseId): ?object
    {
        if (! $opportunity->project_id || ! $phaseId) {
            return null;
        }

        return DB::table('project_phases')->where('id', $phaseId)->where('project_id', $opportunity->project_id)->whereNull('deleted_at')
            ->first(['id', 'project_id', 'phase_number', 'title', 'description', 'status']);
    }

    /**
     * One milestone's handover as a screen shows it, for this person: the milestone, its latest client handover meeting
     * (not cancelled) and when, the points to review and the issues (open), and the links to schedule or open it; and
     * the team's own meetings about it (internal meetings linked to it, the latest three, for those who can open them)
     * with the link to schedule another. opportunityId is null when no opportunity holds the project, milestone null
     * when the milestone isn't on it.
     */
    public static function forMilestone(string $projectId, string $phaseId, ?User $user): array
    {
        $opportunity = self::opportunityForProject($projectId);
        $milestone = DB::table('project_phases')->where('id', $phaseId)->where('project_id', $projectId)->whereNull('deleted_at')->first(['id', 'phase_number', 'title']);
        $meeting = $opportunity && $milestone ? Meeting::where('opportunity_id', $opportunity->id)->where('phase_id', $milestone->id)->where('kind', 'client')
            ->where('status', '!=', 'cancelled')->orderByDesc('starts_at')->first() : null;
        $counts = $meeting ? MeetingPoint::where('meeting_id', $meeting->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect();
        $canSchedule = $opportunity !== null && $milestone !== null && OpportunityAccess::canManage($user, $opportunity) && ProjectAccess::maySee($projectId);
        $canView = $opportunity !== null && OpportunityAccess::canView($user, $opportunity);
        $link = fn (array $query) => route('hq.crm.opportunity', ['opportunity' => $opportunity->id, 'tab' => 'meetings'] + $query);
        $team = $canView && $milestone ? Meeting::where('opportunity_id', $opportunity->id)->where('phase_id', $milestone->id)->where('kind', 'internal')
            ->whereIn('status', ['scheduled', 'held'])->orderByDesc('starts_at')->limit(3)->get() : collect();

        return [
            'opportunityId' => $opportunity?->id,
            'milestone' => $milestone,
            'meeting' => $meeting,
            'when' => $meeting ? self::when($meeting, app()->getLocale()) : null,
            'review' => (int) ($counts['draft'] ?? 0),
            'issues' => (int) ($counts['confirmed'] ?? 0),
            'openIssues' => $meeting ? MeetingPoint::where('meeting_points.meeting_id', $meeting->id)->where('meeting_points.status', 'confirmed')
                ->join('qa_tickets', 'qa_tickets.id', '=', 'meeting_points.qa_ticket_id')->whereNull('qa_tickets.deleted_at')
                ->whereIn('qa_tickets.status', ['Open', 'Investigating'])->count() : 0,
            'canSchedule' => $canSchedule,
            'scheduleUrl' => $canSchedule && (! $meeting || $meeting->status === 'held') ? $link(['handover' => $milestone->id]) : null,
            'meetingUrl' => $meeting && $canView ? $link(['meeting' => $meeting->id]) : null,
            'teamMeetings' => $team->map(fn (Meeting $m) => ['meeting' => $m, 'when' => self::when($m, app()->getLocale()), 'url' => $link(['meeting' => $m->id])])->all(),
            'teamUrl' => $canSchedule ? $link(['team' => $milestone->id]) : null,
        ];
    }

    /** "9 November 2026, 10:00 WAT": the meeting's date, time and time zone, in that language */
    public static function when(Meeting $meeting, string $language): string
    {
        $at = $meeting->starts_at->copy()->setTimezone($meeting->time_zone ?: Meetings::TIME_ZONE)->locale($language);

        return __('handover.board.when', [
            'date' => $at->translatedFormat(__('opportunities.formats.date', [], $language)),
            'time' => $at->translatedFormat(__('opportunities.formats.time', [], $language)),
            'zone' => $at->format('T'),
        ], $language);
    }

    /** The milestone's tasks, in the order the AI numbers them (1, 2, …) and the lead picks from */
    public static function milestoneTasks(string $projectId, string $phaseId): Collection
    {
        return DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $projectId)->where('tasks.phase_id', $phaseId)->whereNull('tasks.deleted_at')
            ->orderBy('tasks.created_at')->orderBy('tasks.id')
            ->limit(self::MAX_TASKS)->get(['tasks.id', 'tasks.title', 'tasks.status']);
    }

    /** A point links only to a task of the meeting's own milestone */
    public static function taskOnMilestone(string $taskId, ?string $phaseId): bool
    {
        return $phaseId !== null && DB::table('tasks')->where('id', $taskId)->where('phase_id', $phaseId)->whereNull('deleted_at')->exists();
    }

    /** The signed-in person sees the opportunity's project (its milestone board and QA tickets) */
    public static function canSeeProject(Opportunity $opportunity): bool
    {
        return $opportunity->project_id !== null && ProjectAccess::maySee($opportunity->project_id);
    }

    /**
     * Edit the points and confirm them as QA tickets: managing the opportunity, being its lead or the owner, and seeing
     * its project (ProjectAccess works on the signed-in person, so $user is that person)
     */
    public static function canReview(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::canManage($user, $opportunity) && OpportunityAccess::canApprove($user, $opportunity)
            && $user !== null && $user->is(Auth::user()) && self::canSeeProject($opportunity);
    }

    /** "Milestone handover: M3 Reports", in the opportunity's language (the client sees it in the invitation) */
    public static function defaultTitle(Opportunity $opportunity, object $milestone): string
    {
        return Str::limit(__('handover.defaults.title', ['milestone' => $milestone->title], $opportunity->language), 200, '');
    }

    /** What the milestone delivered, for the invitation's agenda, in the opportunity's language */
    public static function defaultAgenda(Opportunity $opportunity, object $milestone): string
    {
        $language = $opportunity->language;
        $tasks = self::milestoneTasks($opportunity->project_id, $milestone->id);
        $lines = [__('handover.defaults.agenda', ['milestone' => $milestone->title], $language)];
        if ($tasks->isNotEmpty()) {
            $lines[] = '';
            $lines[] = __('handover.defaults.delivered', [], $language);
            foreach ($tasks->take(30) as $task) {
                $lines[] = '- ' . $task->title;
            }
            if ($tasks->count() > 30) {
                $lines[] = trans_choice('handover.defaults.more', $tasks->count() - 30, ['count' => $tasks->count() - 30], $language);
            }
        }
        $lines[] = '';
        $lines[] = __('handover.defaults.points', [], $language);

        return Str::limit(implode("\n", $lines), 5000, '');
    }

    /**
     * The AI drafts the points raised in a handover meeting from one of its transcripts. Throws AiUnavailable (no key,
     * over budget) before anything is spent. Every point it won't replace (confirmed, left out, added or edited by the
     * lead, or from another transcript) goes with it as "already recorded", so a new list doesn't repeat them.
     */
    public static function draftPoints(MeetingTranscript $transcript): AiRun
    {
        $meeting = $transcript->meeting()->with('attendees', 'opportunity.organization')->first();
        $milestone = $meeting?->isHandover() ? self::milestoneFor($meeting->opportunity, $meeting->phase_id) : null;
        if (! $milestone) {
            throw new LogicException('Only a handover meeting of a milestone on the opportunity’s project has points to draft');
        }
        $o = $meeting->opportunity;
        $tasks = self::milestoneTasks($o->project_id, $milestone->id);
        $replaced = self::replaceable($meeting->id, $transcript->id)->pluck('id')->all();
        $recorded = $meeting->points()->whereNotIn('id', $replaced)->pluck('title');

        return Ai::queue('handover_points', $transcript, [
            // Who the client is, cached after the company brief; the milestone, its tasks and the transcript are this job's material
            'context' => OpportunityContext::for($o, include: ['profile']),
            'material' => self::material($meeting, $milestone, $tasks, $transcript),
            'instructions' => $recorded->isNotEmpty() ? "Already recorded (do not list them again):\n" . $recorded->map(fn ($t) => "- {$t}")->implode("\n") : null,
            'language' => $o->language,
            // Not sent to the AI: which task each number in MATERIAL stands for, when the answer is filed
            'task_ids' => $tasks->pluck('id')->all(),
        ]);
    }

    /** The latest handover_points run of each transcript, keyed by transcript id */
    public static function latestRuns(array $transcriptIds): array
    {
        if (! $transcriptIds) {
            return [];
        }

        return AiRun::where('subject_type', 'meeting_transcript')->whereIn('subject_id', $transcriptIds)->where('purpose', 'handover_points')
            ->orderBy('created_at')->orderBy('id')->get(['id', 'purpose', 'subject_id', 'status', 'progress', 'error', 'output', 'created_at', 'finished_at'])
            ->keyBy('subject_id')->all();
    }

    /**
     * Still under way: queued or running, or done with points that aren't filed yet (Ai::run saves the run as done
     * before AiRunFinished files them; after a minute it counts as filed, so a meeting that is full can't wait forever)
     */
    public static function isPending(AiRun $run): bool
    {
        if (! $run->isFinished()) {
            return true;
        }
        if ($run->status !== 'done' || ! self::clean((array) ($run->output['points'] ?? []), [])) {
            return false;
        }

        return ($run->finished_at === null || $run->finished_at->greaterThan(now()->subMinute()))
            && ! MeetingPoint::withTrashed()->where('ai_run_id', $run->id)->exists();
    }

    /** A handover_points run of any of the meeting's transcripts that is still under way, or null */
    public static function pendingRun(Meeting $meeting): ?AiRun
    {
        $transcripts = $meeting->transcripts()->pluck('id')->all();

        return collect(self::latestRuns($transcripts))->first(fn (AiRun $run) => self::isPending($run));
    }

    /**
     * AiRunFinished: a finished handover_points run becomes draft points on its meeting, once (meeting row locked). It
     * replaces only the AI's points from the same transcript that are still to review and that nobody has edited; the
     * lead's own, edited, confirmed and left-out points, and those from other transcripts, stay.
     */
    public static function fileAiOutput(AiRun $run): void
    {
        if (! $run->succeeded() || $run->purpose !== 'handover_points' || $run->subject_type !== 'meeting_transcript') {
            return;
        }
        $transcript = MeetingTranscript::find($run->subject_id);
        if (! $transcript) {
            return;
        }
        $taskIds = array_values(array_filter((array) ($run->input['task_ids'] ?? []), 'is_string'));

        DB::transaction(function () use ($run, $transcript, $taskIds) {
            $meeting = Meeting::whereKey($transcript->meeting_id)->lockForUpdate()->first();
            if (! $meeting || MeetingPoint::withTrashed()->where('ai_run_id', $run->id)->exists()) {
                return;
            }
            self::replaceable($meeting->id, $transcript->id)->update(['deleted_at' => now(), 'deleted_by' => $run->requested_by]);
            $count = MeetingPoint::where('meeting_id', $meeting->id)->count();
            $position = (int) MeetingPoint::where('meeting_id', $meeting->id)->max('position');
            foreach (self::clean((array) ($run->output['points'] ?? []), $taskIds) as $point) {
                if ($count++ >= self::MAX_POINTS) {
                    break;
                }
                MeetingPoint::create($point + [
                    'meeting_id' => $meeting->id, 'transcript_id' => $transcript->id, 'ai_run_id' => $run->id, 'position' => ++$position,
                    'status' => 'draft', 'origin' => 'ai', 'created_by' => $run->requested_by,
                ]);
            }
        });
    }

    /**
     * The AI's points as rows: trimmed to the columns' lengths, severity one of SEVERITIES (else Medium), the task
     * number turned into that task's id (anything else: no task). Points without a title are dropped.
     *
     * @return list<array{title: string, detail: ?string, raised_by: ?string, severity: string, task_id: ?string}>
     */
    public static function clean(array $points, array $taskIds): array
    {
        $out = [];
        foreach ($points as $p) {
            $title = is_array($p) ? self::line($p['title'] ?? '', self::MAX_TITLE) : '';
            if ($title === '') {
                continue;
            }
            $number = is_int($p['task_number'] ?? null) ? $p['task_number'] : 0;
            $out[] = [
                'title' => $title,
                'detail' => self::text($p['asked'] ?? '', self::MAX_DETAIL) ?: null,
                'raised_by' => self::line($p['raised_by'] ?? '', self::MAX_RAISED_BY) ?: null,
                'severity' => in_array($p['severity'] ?? null, self::SEVERITIES, true) ? $p['severity'] : 'Medium',
                'task_id' => $number >= 1 && $number <= count($taskIds) ? $taskIds[$number - 1] : null,
            ];
        }

        return $out;
    }

    /** A point the lead adds by hand, as a draft (title, detail, raised_by, severity, task_id already checked) */
    public static function addPoint(Meeting $meeting, array $values): MeetingPoint
    {
        return DB::transaction(function () use ($meeting, $values) {
            Meeting::whereKey($meeting->id)->lockForUpdate()->first();
            if (MeetingPoint::where('meeting_id', $meeting->id)->count() >= self::MAX_POINTS) {
                throw new LogicException('This meeting already holds as many points as it can');
            }

            return MeetingPoint::create([
                'meeting_id' => $meeting->id,
                'position' => (int) MeetingPoint::where('meeting_id', $meeting->id)->max('position') + 1,
                'title' => self::line($values['title'] ?? '', self::MAX_TITLE),
                'detail' => self::text($values['detail'] ?? '', self::MAX_DETAIL) ?: null,
                'raised_by' => self::line($values['raised_by'] ?? '', self::MAX_RAISED_BY) ?: null,
                'severity' => in_array($values['severity'] ?? null, self::SEVERITIES, true) ? $values['severity'] : 'Medium',
                'task_id' => $values['task_id'] ?? null,
                'status' => 'draft',
                'origin' => 'lead',
                'created_by' => Auth::guard('web')->id(),
            ]);
        });
    }

    /** Change one field of a draft point (already checked); false when it is no longer a draft */
    public static function updatePoint(MeetingPoint $point, string $field, ?string $value): bool
    {
        $value = match ($field) {
            'title' => self::line($value ?? '', self::MAX_TITLE),
            'detail' => self::text($value ?? '', self::MAX_DETAIL) ?: null,
            'raised_by' => self::line($value ?? '', self::MAX_RAISED_BY) ?: null,
            'severity' => in_array($value, self::SEVERITIES, true) ? $value : throw new LogicException('Unknown severity'),
            'task_id' => $value ?: null,
            default => throw new LogicException("A point has no field '{$field}' to change"),
        };

        return MeetingPoint::whereKey($point->id)->where('status', 'draft')
            ->update([$field => $value, 'updated_by' => Auth::guard('web')->id(), 'updated_at' => now()]) === 1;
    }

    /**
     * The lead confirms a draft point: it becomes a QA ticket on the project (Open, the point's severity, linked to
     * its task when that task is still on the meeting's milestone). The point is claimed under a lock, so a double
     * click or two people at once create one ticket. Returns the ticket's id, or null when the point was no longer a
     * draft. $tell: the task's assignee gets a bell now; confirming several at once tells them once (tellAssignees()).
     */
    public static function confirm(MeetingPoint $point, bool $tell = true): ?string
    {
        $userId = Auth::guard('web')->id() ?? throw new LogicException('A point is confirmed by a member of staff');
        $done = DB::transaction(function () use ($point, $userId) {
            $locked = MeetingPoint::whereKey($point->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'draft') {
                return null;
            }
            $meeting = Meeting::with('opportunity')->find($locked->meeting_id);
            $projectId = $meeting?->opportunity?->project_id;
            if (! $projectId || ! $meeting->isHandover()) {
                throw new LogicException('Only a point of a handover meeting on a project becomes a QA ticket');
            }
            $taskId = $locked->task_id && self::taskOnMilestone($locked->task_id, $meeting->phase_id) ? $locked->task_id : null;
            $ticketId = (string) Str::uuid();
            DB::table('qa_tickets')->insert([
                'id' => $ticketId,
                'project_id' => $projectId,
                'task_id' => $taskId,
                'submitted_by' => $userId,
                'submitted_by_type' => 'staff',
                'title' => $locked->title,
                'description' => self::ticketDescription($locked, $meeting),
                'severity' => $locked->severity,
                'status' => 'Open',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $locked->forceFill(['status' => 'confirmed', 'qa_ticket_id' => $ticketId, 'task_id' => $taskId, 'decided_by' => $userId, 'decided_at' => now(), 'updated_by' => $userId])->save();

            return [$ticketId, $locked, $meeting, $taskId];
        });
        if (! $done) {
            return null;
        }
        [$ticketId, $locked, $meeting, $taskId] = $done;

        OpportunityAccess::log('HANDOVER_POINT_CONFIRMED', sprintf('Confirmed “%s” from the milestone handover meeting “%s” as a %s QA ticket.', $locked->title, $meeting->title, strtolower($locked->severity)),
            'opportunities', $meeting->opportunity_id, [], ['meeting_id' => $meeting->id, 'point_id' => $locked->id, 'qa_ticket_id' => $ticketId, 'task_id' => $taskId]);
        if ($tell) {
            self::tellAssignees([['task_id' => $taskId, 'title' => $locked->title, 'ticket_id' => $ticketId]]);
        }

        return $ticketId;
    }

    /**
     * The people working on the tasks hear that handover points now concern them: one bell each, however many points
     * (a bell becomes a phone push). $confirmed: [['task_id', 'title', 'ticket_id'], …]; whoever confirmed isn't told.
     */
    public static function tellAssignees(array $confirmed): void
    {
        $byTask = collect($confirmed)->filter(fn ($c) => $c['task_id'] ?? null);
        if ($byTask->isEmpty()) {
            return;
        }
        $assignees = DB::table('tasks')->whereIn('id', $byTask->pluck('task_id')->unique()->all())->whereNotNull('assigned_to')->pluck('assigned_to', 'id');
        $active = User::whereIn('id', $assignees->unique()->values()->all())->where('status', 'Active')->pluck('id')->all();
        foreach ($byTask->groupBy(fn ($c) => $assignees[$c['task_id']] ?? null) as $userId => $points) {
            if ($userId === '' || ! in_array($userId, $active, true) || $userId === Auth::guard('web')->id()) {
                continue;
            }
            $one = $points->count() === 1;
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'type' => 'QA_Update',
                'title' => Str::limit(__($one ? 'handover.notify.title' : 'handover.notify.title_many'), 150, '…'),
                'message' => $one ? __('handover.notify.message', ['title' => $points->first()['title']]) : __('handover.notify.many', ['count' => $points->count()]),
                'link_url' => $one ? route('hq.ops.qa', ['ticket' => $points->first()['ticket_id']]) : route('hq.ops.qa'),
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }
    }

    /** Leave a draft point out (no ticket), or bring a left-out point back as a draft; false when nothing changed */
    public static function dismiss(MeetingPoint $point, bool $dismiss = true): bool
    {
        $changed = MeetingPoint::whereKey($point->id)->where('status', $dismiss ? 'draft' : 'dismissed')->update([
            'status' => $dismiss ? 'dismissed' : 'draft',
            'decided_by' => $dismiss ? Auth::guard('web')->id() : null,
            'decided_at' => $dismiss ? now() : null,
            'updated_by' => Auth::guard('web')->id(),
            'updated_at' => now(),
        ]) === 1;
        if ($changed && $dismiss) {
            $meeting = Meeting::find($point->meeting_id);
            OpportunityAccess::log('HANDOVER_POINT_DISMISSED', sprintf('Left “%s” out of the QA tickets from the milestone handover meeting “%s”.', $point->title, $meeting?->title),
                'opportunities', (string) $meeting?->opportunity_id, [], ['meeting_id' => $point->meeting_id, 'point_id' => $point->id]);
        }

        return $changed;
    }

    /**
     * A meeting's points with their ticket (status, resolved_at, updated_at, deleted_at as ticket_*) and task title,
     * in order.
     */
    public static function points(Meeting $meeting): Collection
    {
        return MeetingPoint::where('meeting_points.meeting_id', $meeting->id)
            ->leftJoin('qa_tickets', 'qa_tickets.id', '=', 'meeting_points.qa_ticket_id')
            ->leftJoin('tasks', 'tasks.id', '=', 'meeting_points.task_id')
            ->orderBy('meeting_points.position')->orderBy('meeting_points.created_at')
            ->get(['meeting_points.*', 'qa_tickets.status as ticket_status', 'qa_tickets.resolved_at as ticket_resolved_at',
                'qa_tickets.updated_at as ticket_updated_at', 'qa_tickets.deleted_at as ticket_deleted_at', 'tasks.title as task_title']);
    }

    /** The last reply the client could see on each ticket (not an internal note): how it was closed */
    public static function closingNotes(array $ticketIds): array
    {
        if (! $ticketIds) {
            return [];
        }

        return DB::table('qa_comments')->whereIn('ticket_id', $ticketIds)->where('is_internal_only', 0)
            ->orderBy('created_at')->orderBy('id')->get(['ticket_id', 'comment_text'])
            ->mapWithKeys(fn ($c) => [$c->ticket_id => Str::limit(trim(preg_replace('/\s+/u', ' ', $c->comment_text)), 300)])->all();
    }

    /**
     * Every confirmed point and how its ticket stands, for the handover sign-off, in the opportunity's language:
     * "1. Title (Critical): Resolved on 12 November 2026. Reply: …".
     */
    public static function signoffText(Meeting $meeting): string
    {
        $meeting->loadMissing('opportunity');
        $language = $meeting->opportunity->language;
        $date = fn ($at) => Carbon::parse($at)->setTimezone($meeting->time_zone ?: Meetings::TIME_ZONE)->locale($language)->translatedFormat('j F Y');
        $points = self::points($meeting)->where('status', 'confirmed')->values();
        $notes = self::closingNotes($points->pluck('qa_ticket_id')->filter()->all());

        $lines = [__('handover.signoff.heading', ['title' => $meeting->title, 'date' => $date($meeting->starts_at)], $language)];
        foreach ($points as $i => $p) {
            $status = match (true) {
                $p->ticket_status === null || $p->ticket_deleted_at !== null => __('handover.signoff.removed', [], $language),
                in_array($p->ticket_status, ['Resolved', 'Closed'], true) => __('handover.signoff.closed_on', [
                    'status' => __('portal.status.issue.' . $p->ticket_status, [], $language), 'date' => $date($p->ticket_resolved_at ?? $p->ticket_updated_at)], $language),
                default => __('portal.status.issue.' . $p->ticket_status, [], $language),
            };
            $line = __('handover.signoff.line', ['number' => $i + 1, 'title' => $p->title, 'severity' => __('handover.severity.' . $p->severity, [], $language), 'status' => $status], $language);
            if (isset($notes[$p->qa_ticket_id])) {
                $line .= ' ' . __('handover.signoff.reply', ['reply' => $notes[$p->qa_ticket_id]], $language);
            }
            $lines[] = $line;
        }
        if ($points->isEmpty()) {
            $lines[] = __('handover.signoff.none', [], $language);
        }

        return implode("\n", $lines);
    }

    /** The AI's points of this transcript still to review that nobody has edited: what a new list of it replaces */
    private static function replaceable(string $meetingId, string $transcriptId)
    {
        return MeetingPoint::where('meeting_id', $meetingId)->where('transcript_id', $transcriptId)
            ->where('status', 'draft')->where('origin', 'ai')->whereNull('updated_by');
    }

    /** The ticket's text, which the client also reads in the portal: what was asked, then where it was raised */
    private static function ticketDescription(MeetingPoint $point, Meeting $meeting): string
    {
        $language = $meeting->opportunity->language;
        $when = $meeting->starts_at->copy()->setTimezone($meeting->time_zone ?: Meetings::TIME_ZONE)->locale($language)->translatedFormat('j F Y');
        $where = filled($point->raised_by)
            ? __('handover.ticket.raised_by', ['name' => $point->raised_by, 'meeting' => $meeting->title, 'date' => $when], $language)
            : __('handover.ticket.raised', ['meeting' => $meeting->title, 'date' => $when], $language);

        return trim(implode("\n\n", array_filter([trim((string) $point->detail), $where])));
    }

    private static function material(Meeting $meeting, object $milestone, Collection $tasks, MeetingTranscript $transcript): string
    {
        $org = $meeting->opportunity->organization?->name ?: 'the client';
        $who = $meeting->attendees->map(fn ($a) => ($a->name ?: $a->email) . ' (' . match ($a->role) {
            'staff' => 'JBS Praxis', 'client' => $org, default => 'guest',
        } . ')')->implode('; ');
        $numbered = $tasks->values()->map(fn ($t, $i) => sprintf('%d. %s', $i + 1, $t->title))->implode("\n");

        return implode("\n", array_filter([
            'Milestone handover meeting: ' . $meeting->title . ', ' . self::when($meeting, 'en'),
            'Milestone: ' . $milestone->title . (filled($milestone->description) ? ' (' . Str::limit(trim($milestone->description), 500) . ')' : ''),
            $who !== '' ? "Attendees: {$who}" : null,
            filled($meeting->agenda) ? 'Agenda: ' . trim($meeting->agenda) : null,
            '',
            'Milestone tasks:',
            $numbered !== '' ? $numbered : '(none listed)',
            '',
            'Transcript:',
            trim($transcript->text),
        ], fn ($line) => $line !== null));
    }

    /** One line: spaces collapsed, cut to $max characters */
    private static function line(mixed $value, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $value)), 0, $max);
    }

    /** Text with its line breaks, cut to $max characters */
    private static function text(mixed $value, int $max): string
    {
        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", (string) $value))), 0, $max);
    }
}
