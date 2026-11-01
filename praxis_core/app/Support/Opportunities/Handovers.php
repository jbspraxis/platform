<?php

namespace App\Support\Opportunities;

use App\Models\AiRun;
use App\Models\Meeting;
use App\Models\MeetingPoint;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Ai\Ai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Sprint handovers (docs/OPPORTUNITIES.md section 12, PR 6). From a sprint of a project that has an opportunity
 * (opportunities.project_id), the lead schedules the client meeting that hands it over, through Meetings::schedule
 * (meetings.sprint_id). Its transcript is collected like any other; once filed, the AI drafts the points raised
 * (handover_points, Haiku) as meeting_points. The lead edits them and confirms each one, which creates a QA ticket on
 * the project, linked to the sprint task when known; nothing is created without that confirmation. The meeting then
 * shows every point with its ticket's status, and signoffText() lists them for the handover sign-off.
 *
 * Who (always for this opportunity: the permission, or being on its team, OpportunityAccess): anyone who can view it
 * sees the points; drafting them reads the transcript (canManage + canReadTranscripts); editing and confirming creates
 * tickets the client sees, so it is the lead's or the owner's (canReview: canManage + canApprove).
 */
final class Handovers
{
    public const SEVERITIES = ['Low', 'Medium', 'Critical'];

    /** Points one meeting can hold, drafted and added together */
    public const MAX_POINTS = 50;

    public const MAX_TITLE = 200;

    public const MAX_DETAIL = 2000;

    public const MAX_RAISED_BY = 150;

    /** Sprint tasks listed for the AI and the task picker */
    public const MAX_TASKS = 200;

    /** The opportunity that holds this project, or null: a project without one can't schedule a handover */
    public static function opportunityForProject(string $projectId): ?Opportunity
    {
        return Opportunity::where('project_id', $projectId)->orderByRaw("stage = 'won' desc")->orderByDesc('updated_at')->first();
    }

    /** The sprint, when it is one of the opportunity's project's sprints */
    public static function sprintFor(Opportunity $opportunity, ?string $sprintId): ?object
    {
        if (! $opportunity->project_id || ! $sprintId) {
            return null;
        }

        return DB::table('sprints')->where('id', $sprintId)->where('project_id', $opportunity->project_id)->whereNull('deleted_at')
            ->first(['id', 'project_id', 'title', 'start_date', 'end_date', 'status']);
    }

    /** The sprint's tasks, in the order the AI numbers them (1, 2, …) and the lead picks from */
    public static function sprintTasks(string $projectId, string $sprintId): Collection
    {
        return DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $projectId)->where('tasks.sprint_id', $sprintId)->whereNull('tasks.deleted_at')
            ->orderBy('project_phases.phase_number')->orderBy('tasks.created_at')->orderBy('tasks.id')
            ->limit(self::MAX_TASKS)->get(['tasks.id', 'tasks.title', 'tasks.status', 'project_phases.title as phase_title']);
    }

    public static function taskOnProject(string $taskId, string $projectId): bool
    {
        return DB::table('tasks')->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $projectId)->where('tasks.id', $taskId)->whereNull('tasks.deleted_at')->exists();
    }

    /** Edit the points and confirm them as QA tickets: managing opportunities, and the opportunity's lead or the owner */
    public static function canReview(?User $user, Opportunity $opportunity): bool
    {
        return OpportunityAccess::canManage($user, $opportunity) && OpportunityAccess::canApprove($user, $opportunity);
    }

    /** "Handover: Sprint 3", in the opportunity's language (the client sees it in the invitation) */
    public static function defaultTitle(Opportunity $opportunity, object $sprint): string
    {
        return Str::limit(__('handover.defaults.title', ['sprint' => $sprint->title], $opportunity->language), 200, '');
    }

    /** What the sprint delivered, for the invitation's agenda, in the opportunity's language */
    public static function defaultAgenda(Opportunity $opportunity, object $sprint): string
    {
        $language = $opportunity->language;
        $date = fn ($d) => Carbon::parse($d)->locale($language)->translatedFormat('j F Y');
        $tasks = self::sprintTasks($opportunity->project_id, $sprint->id);
        $lines = [__('handover.defaults.agenda', ['sprint' => $sprint->title, 'start' => $date($sprint->start_date), 'end' => $date($sprint->end_date)], $language)];
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
     * over budget) before anything is spent. Points already confirmed, left out or added by the lead go with it as
     * "already recorded", so a new draft doesn't repeat them.
     */
    public static function draftPoints(MeetingTranscript $transcript): AiRun
    {
        $meeting = $transcript->meeting()->with('attendees', 'opportunity.organization')->first();
        $sprint = $meeting?->isHandover() ? self::sprintFor($meeting->opportunity, $meeting->sprint_id) : null;
        if (! $sprint) {
            throw new LogicException('Only a handover meeting of a sprint on the opportunity’s project has points to draft');
        }
        $o = $meeting->opportunity;
        $tasks = self::sprintTasks($o->project_id, $sprint->id);
        $recorded = $meeting->points()->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhere('origin', 'lead'))->pluck('title');

        return Ai::queue('handover_points', $transcript, [
            // Who the client is, cached after the company brief; the sprint, its tasks and the transcript are this job's material
            'context' => OpportunityContext::for($o, include: ['profile']),
            'material' => self::material($meeting, $sprint, $tasks, $transcript),
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
            ->orderBy('created_at')->orderBy('id')->get(['id', 'purpose', 'subject_id', 'status', 'progress', 'error', 'output', 'created_at'])
            ->keyBy('subject_id')->all();
    }

    /**
     * AiRunFinished: a finished handover_points run becomes draft points on its meeting, once (meeting row locked). It
     * replaces the AI's points still waiting; the lead's own, confirmed and left-out points stay.
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
            MeetingPoint::where('meeting_id', $meeting->id)->where('status', 'draft')->where('origin', 'ai')
                ->update(['deleted_at' => now(), 'deleted_by' => $run->requested_by]);
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
     * its task when that task is still on the project). The point is claimed under a lock, so a double click or two
     * people at once create one ticket. Returns the ticket's id, or null when the point was no longer a draft.
     */
    public static function confirm(MeetingPoint $point): ?string
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
            $taskId = $locked->task_id && self::taskOnProject($locked->task_id, $projectId) ? $locked->task_id : null;
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

        OpportunityAccess::log('HANDOVER_POINT_CONFIRMED', sprintf('Confirmed “%s” from the handover meeting “%s” as a %s QA ticket.', $locked->title, $meeting->title, strtolower($locked->severity)),
            'opportunities', $meeting->opportunity_id, [], ['meeting_id' => $meeting->id, 'point_id' => $locked->id, 'qa_ticket_id' => $ticketId, 'task_id' => $taskId]);
        self::tellAssignee($taskId, $locked, $ticketId, $userId);

        return $ticketId;
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
            OpportunityAccess::log('HANDOVER_POINT_DISMISSED', sprintf('Left “%s” out of the QA tickets from the handover meeting “%s”.', $point->title, $meeting?->title),
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

    /** The person working on the task hears that a handover point now concerns it (bell, staff only) */
    private static function tellAssignee(?string $taskId, MeetingPoint $point, string $ticketId, string $by): void
    {
        $assignee = $taskId ? DB::table('tasks')->where('id', $taskId)->value('assigned_to') : null;
        if (! $assignee || $assignee === $by || ! User::where('id', $assignee)->where('status', 'Active')->exists()) {
            return;
        }
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $assignee,
            'type' => 'QA_Update',
            'title' => Str::limit(__('handover.notify.title'), 150, '…'),
            'message' => __('handover.notify.message', ['title' => $point->title]),
            'link_url' => route('hq.ops.qa', ['ticket' => $ticketId]),
            'is_read' => 0,
            'created_at' => now(),
        ]);
    }

    private static function material(Meeting $meeting, object $sprint, Collection $tasks, MeetingTranscript $transcript): string
    {
        $org = $meeting->opportunity->organization?->name ?: 'the client';
        $who = $meeting->attendees->map(fn ($a) => ($a->name ?: $a->email) . ' (' . match ($a->role) {
            'staff' => 'JBS Praxis', 'client' => $org, default => 'guest',
        } . ')')->implode('; ');
        $numbered = $tasks->values()->map(fn ($t, $i) => sprintf('%d. %s (%s)', $i + 1, $t->title, $t->phase_title))->implode("\n");

        return implode("\n", array_filter([
            sprintf('Handover meeting: %s, %s WAT', $meeting->title, Meetings::when($meeting)),
            sprintf('Sprint: %s, %s to %s', $sprint->title, $sprint->start_date, $sprint->end_date),
            $who !== '' ? "Attendees: {$who}" : null,
            filled($meeting->agenda) ? 'Agenda: ' . trim($meeting->agenda) : null,
            '',
            'Sprint tasks:',
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
