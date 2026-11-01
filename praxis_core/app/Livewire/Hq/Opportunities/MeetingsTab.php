<?php

namespace App\Livewire\Hq\Opportunities;

use App\Models\Meeting;
use App\Models\MeetingPoint;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Ai\AiUnavailable;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Opportunities\Handovers;
use App\Support\Opportunities\MeetingChange;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\PortalLinks;
use App\Support\Opportunities\Transcripts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

/**
 * The opportunity's Meetings tab (docs/OPPORTUNITIES.md section 4, row 2): schedule, change and cancel client and
 * internal meetings through Google (Meetings), add a transcript by hand when Google has none, read the AI's notes,
 * and edit and send the client minutes. "Transcripts" lists every transcript of the opportunity together.
 * opportunities.view reads it; opportunities.manage acts; full transcripts need canReadTranscripts.
 * A sprint's handover meeting (PR 6, Handovers) is scheduled here from the sprint board (?handover=<sprint id>) and
 * lists the points raised in it; the lead or the owner (Handovers::canReview) edits and confirms them as QA tickets.
 */
class MeetingsTab extends Component
{
    #[Locked]
    public string $opportunityId;

    #[Locked]
    public bool $canManage = false;

    #[Locked]
    public bool $canReadTranscripts = false;

    /** Edit and confirm a handover meeting's points (Handovers::canReview) */
    #[Locked]
    public bool $canReview = false;

    /** From the sprint board: open the form for that sprint's handover meeting, then forget it */
    #[Url(as: 'handover', except: '')]
    public string $handover = '';

    /** The meeting whose details are open (from a notification link: ?meeting=…) */
    #[Url(as: 'meeting', except: '')]
    public string $open = '';

    /** meetings | transcripts */
    public string $view = 'meetings';

    // Schedule or change a meeting
    public bool $formOpen = false;

    #[Locked]
    public string $editingId = '';

    /** A new meeting that hands over this sprint (checked again on save) */
    #[Locked]
    public string $sprintId = '';

    public string $kind = 'client';

    public string $title = '';

    public string $date = '';

    public string $time = '';

    public int $duration = 60;

    public array $staff = [];

    public array $contacts = [];

    public string $guests = '';

    public string $agenda = '';

    // Cancel a meeting, or decline a client's request
    #[Locked]
    public string $cancellingId = '';

    // Add a transcript by hand
    #[Locked]
    public string $transcriptFor = '';

    public string $transcriptText = '';

    public string $transcriptSource = 'paste';

    /** Minutes being edited, by transcript id; $minutesSeen is what HQ held when each was loaded */
    public array $minutes = [];

    #[Locked]
    public array $minutesSeen = [];

    /** A handover meeting's draft points being edited, by point id: title, detail, raised_by, severity, task_id */
    public array $points = [];

    // Add a point by hand to a handover meeting
    #[Locked]
    public string $addingPointTo = '';

    public string $newPointTitle = '';

    public string $newPointSeverity = 'Medium';

    /** The last action's outcome, shown above the list */
    public ?string $notice = null;

    public string $noticeTone = 'success';

    public function mount(string $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);
        abort_unless(OpportunityAccess::canView(Auth::user(), $opportunity), 403);
        $this->opportunityId = $opportunity->id;
        $this->canManage = OpportunityAccess::canManage(Auth::user(), $opportunity);
        $this->canReadTranscripts = OpportunityAccess::canReadTranscripts(Auth::user(), $opportunity);
        $this->canReview = Handovers::canReview(Auth::user(), $opportunity);
        if ($this->open !== '' && ! Meeting::where('opportunity_id', $opportunity->id)->whereKey($this->open)->exists()) {
            $this->open = '';
        }
        if ($this->handover !== '') {
            $this->canManage ? $this->openHandover($this->handover) : $this->say(__('handover.errors.cannot_schedule'), 'warning');
            $this->handover = '';
        }
    }

    private function opportunity(): Opportunity
    {
        return Opportunity::with(['organization', 'lead'])->findOrFail($this->opportunityId);
    }

    private function meeting(string $id): Meeting
    {
        return Meeting::where('opportunity_id', $this->opportunityId)->with('attendees')->findOrFail($id);
    }

    private function transcript(string $id): MeetingTranscript
    {
        return MeetingTranscript::whereHas('meeting', fn ($q) => $q->where('opportunity_id', $this->opportunityId))->with('meeting')->findOrFail($id);
    }

    public function show(string $view): void
    {
        $this->view = $view === 'transcripts' ? 'transcripts' : 'meetings';
    }

    public function toggle(string $id): void
    {
        $this->open = $this->open === $id ? '' : $this->meeting($id)->id;
    }

    // ---- Schedule or change ------------------------------------------------------------------------------------

    public function openSchedule(string $kind = 'client'): void
    {
        $this->authorizeManage();
        $o = $this->opportunity();
        $kind = in_array($kind, Meetings::KINDS, true) ? $kind : 'client';
        $primary = DB::table('client_users')->where('organization_id', $o->organization_id)->whereNull('deleted_at')
            ->orderByDesc('is_primary_client')->orderBy('created_at')->value('id');

        $this->resetForm();
        $this->fill([
            'kind' => $kind,
            'title' => $this->defaultTitle($o, $kind),
            'date' => $this->nextWorkingDay(),
            'time' => '10:00',
            'duration' => 60,
            'staff' => array_values(array_unique(array_filter([$o->lead_user_id, Auth::id()]))),
            'contacts' => $primary ? [$primary] : [],
            'agenda' => $kind === 'internal' ? $this->agendaFromLastClientMeeting($o) : '',
            'formOpen' => true,
        ]);
    }

    /** The form for a sprint's handover: a client meeting, titled after the sprint, its agenda what the sprint delivered */
    public function openHandover(string $sprintId): void
    {
        $this->authorizeManage();
        $o = $this->opportunity();
        $sprint = Handovers::sprintFor($o, $sprintId);
        if (! $sprint) {
            $this->say(__('handover.errors.sprint'), 'warning');

            return;
        }
        $this->openSchedule('client');
        $this->fill(['sprintId' => $sprint->id, 'title' => Handovers::defaultTitle($o, $sprint), 'agenda' => Handovers::defaultAgenda($o, $sprint)]);
    }

    public function updatedKind(string $kind): void
    {
        if ($this->sprintId !== '') {
            $this->kind = 'client'; // a handover is always with the client
        }
        if ($this->editingId !== '' || $this->sprintId !== '') {
            return;
        }
        $o = $this->opportunity();
        $other = $kind === 'internal' ? 'client' : 'internal';
        // Swap the suggested title and agenda, never what someone typed
        if ($this->title === '' || $this->title === $this->defaultTitle($o, $other)) {
            $this->title = $this->defaultTitle($o, $kind);
        }
        if ($kind === 'internal' && trim($this->agenda) === '') {
            $this->agenda = $this->agendaFromLastClientMeeting($o);
        } elseif ($kind === 'client' && $this->agenda === $this->agendaFromLastClientMeeting($o)) {
            $this->agenda = '';
        }
    }

    public function openEdit(string $id): void
    {
        $this->authorizeManage();
        $m = $this->meeting($id);
        if (! Meetings::canChange($m)) {
            $this->say(__('meetings.errors.past'), 'warning');

            return;
        }
        $starts = $m->starts_at->copy()->setTimezone($m->time_zone ?: Meetings::TIME_ZONE);
        $this->resetForm();
        $this->fill([
            'editingId' => $m->id,
            'kind' => $m->kind,
            'title' => $m->title,
            'date' => $starts->format('Y-m-d'),
            'time' => $starts->format('H:i'),
            'duration' => max(15, (int) $m->starts_at->diffInMinutes($m->ends_at)),
            'staff' => $m->attendees->where('role', 'staff')->pluck('user_id')->filter()->values()->all(),
            'contacts' => $m->attendees->where('role', 'client')->pluck('client_user_id')->filter()->values()->all(),
            'guests' => $m->attendees->filter(fn ($a) => $a->role === 'guest' || ($a->role === 'client' && ! $a->client_user_id) || ($a->role === 'staff' && ! $a->user_id))
                ->pluck('email')->implode("\n"),
            'agenda' => (string) $m->agenda,
            'formOpen' => true,
        ]);
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeManage();
        $o = $this->opportunity();
        $editing = $this->editingId !== '' ? $this->meeting($this->editingId) : null;
        if ($editing && ! Meetings::canChange($editing)) {
            $this->resetForm();
            $this->say(__('meetings.errors.past'), 'warning');

            return;
        }
        $kind = $editing?->kind ?? ($this->sprintId !== '' ? 'client' : $this->kind);
        $sprint = null;
        if (! $editing && $this->sprintId !== '' && ! ($sprint = Handovers::sprintFor($o, $this->sprintId))) {
            $this->addError('sprint', __('handover.errors.sprint'));

            return;
        }
        $wasRequested = $editing?->status === 'requested';
        $this->title = trim($this->title);
        $this->agenda = trim($this->agenda);
        $durations = array_unique([...Meetings::DURATIONS, ...($editing ? [(int) $editing->starts_at->diffInMinutes($editing->ends_at)] : [])]);
        [$guests, $bad] = $this->parseGuests($this->guests);
        $contacts = $kind === 'client' ? $this->contacts : [];

        // Every problem at once: the rules below, then the time, the people and the guests' emails
        $this->withValidator(fn ($validator) => $validator->after(function ($v) use ($kind, $guests, $bad, $contacts) {
            if (! $v->errors()->hasAny(['date', 'time'])) {
                $starts = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$this->date} {$this->time}", Meetings::TIME_ZONE) ?: null;
                if (! $starts || $starts->lessThanOrEqualTo(now())) {
                    $v->errors()->add('date', __('meetings.errors.past_time'));
                }
            }
            if ($bad) {
                $v->errors()->add('guests', trans_choice('meetings.errors.guests', count($bad), ['emails' => implode(', ', array_slice($bad, 0, 3))]));
            } elseif (count($guests) > 30) {
                $v->errors()->add('guests', __('meetings.errors.guests_many'));
            }
            if (! $this->staff) {
                $v->errors()->add('staff', __('meetings.errors.staff'));
            }
            if ($kind === 'client' && ! $contacts && ! $guests) {
                $v->errors()->add('contacts', __('meetings.errors.client'));
            }
        }))->validate([
            'kind' => ['required', Rule::in(Meetings::KINDS)],
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', Rule::in($durations)],
            'staff' => ['array', 'max:30'],
            'staff.*' => ['string', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'contacts' => ['array', 'max:30'],
            'contacts.*' => ['string', Rule::exists('client_users', 'id')->where('organization_id', $o->organization_id)->whereNull('deleted_at')],
            'agenda' => ['nullable', 'string', 'max:5000'],
        ], [
            'kind.*' => __('meetings.errors.kind'),
            'title.required' => __('meetings.errors.title'),
            'title.max' => __('meetings.errors.title_long'),
            'date.*' => __('meetings.errors.date'),
            'time.*' => __('meetings.errors.time'),
            'duration.*' => __('meetings.errors.duration'),
            'staff.max' => __('meetings.errors.staff_many'),
            'staff.*.exists' => __('meetings.errors.staff_gone'),
            'contacts.max' => __('meetings.errors.contacts_many'),
            'contacts.*.exists' => __('meetings.errors.contact_gone'),
            'agenda.max' => __('meetings.errors.agenda_long'),
        ]);
        $starts = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$this->date} {$this->time}", Meetings::TIME_ZONE);

        $data = [
            'kind' => $kind,
            'title' => $this->title,
            'agenda' => $this->agenda,
            'starts_at' => $starts,
            'ends_at' => $starts->addMinutes($this->duration),
            'staff' => $this->staff,
            'contacts' => $contacts,
            'guests' => array_map(fn ($e) => ['email' => $e], $guests),
            'sprint_id' => $sprint?->id,
        ];
        try {
            $change = $editing ? Meetings::update($editing, $data) : Meetings::schedule($o, $data);
        } catch (GoogleUnavailable $e) {
            $this->addError('google', $e->getMessage());

            return;
        }

        $this->resetForm();
        $this->open = $change->meeting->id;
        $this->reported($change, $editing ? ($wasRequested ? 'confirmed' : 'changed') : 'scheduled');
        $this->dispatch('meetings-changed');
    }

    // ---- Cancel ------------------------------------------------------------------------------------------------

    public function confirmCancel(string $id): void
    {
        $this->authorizeManage();
        $this->cancellingId = $this->meeting($id)->id;
    }

    public function keepMeeting(): void
    {
        $this->cancellingId = '';
    }

    public function cancelMeeting(): void
    {
        $this->authorizeManage();
        $m = $this->meeting($this->cancellingId);
        $this->cancellingId = '';
        if (! Meetings::canChange($m)) {
            $this->say(__('meetings.errors.past'), 'warning');

            return;
        }
        $wasRequested = $m->status === 'requested';
        try {
            $change = Meetings::cancel($m);
        } catch (GoogleUnavailable $e) {
            $this->say($e->getMessage(), 'error');

            return;
        }
        $this->reported($change, $wasRequested ? 'declined' : 'cancelled');
        $this->dispatch('meetings-changed');
    }

    // ---- Transcripts, notes and minutes -------------------------------------------------------------------------

    public function openTranscript(string $id): void
    {
        $this->authorizeManage();
        $m = $this->meeting($id);
        if (! in_array($m->status, ['scheduled', 'held'], true) || $m->starts_at->isFuture()) {
            $this->say(__('meetings.errors.not_started'), 'warning');

            return;
        }
        $this->resetErrorBag();
        $this->fill(['transcriptFor' => $m->id, 'transcriptText' => '', 'transcriptSource' => 'paste']);
    }

    public function closeTranscript(): void
    {
        $this->fill(['transcriptFor' => '', 'transcriptText' => '', 'transcriptSource' => 'paste']);
        $this->resetErrorBag();
    }

    public function saveTranscript(): void
    {
        $this->authorizeManage();
        $m = $this->meeting($this->transcriptFor);
        $this->validate([
            'transcriptText' => ['required', 'string', 'max:' . Transcripts::MAX_CHARS],
            'transcriptSource' => ['required', Rule::in(['upload', 'paste'])],
        ], [
            'transcriptText.required' => __('meetings.errors.transcript'),
            'transcriptText.max' => __('meetings.errors.transcript_long'),
            'transcriptSource.*' => __('meetings.errors.transcript'),
        ]);
        if (trim($this->transcriptText) === '') {
            $this->addError('transcriptText', __('meetings.errors.transcript'));

            return;
        }

        Transcripts::fromText($m, $this->transcriptText, $this->transcriptSource);
        $this->closeTranscript();
        $this->open = $m->id;
        $this->say(__('meetings.done.transcript'));
        $this->dispatch('meetings-changed');
    }

    public function draftNotes(string $transcriptId): void
    {
        $this->draft($transcriptId, 'meeting_notes');
    }

    public function draftMinutes(string $transcriptId): void
    {
        $this->draft($transcriptId, 'client_minutes');
    }

    public function saveMinutes(string $transcriptId): void
    {
        $this->authorizeManage();
        $t = $this->transcript($transcriptId);
        if (! $this->validMinutes($t)) {
            return;
        }
        $t->forceFill(['minutes' => trim($this->minutes[$t->id]), 'updated_by' => Auth::id()])->save();
        $this->minutesSeen[$t->id] = $t->minutes;
        $this->say(__('meetings.done.minutes_saved'));
    }

    public function sendMinutes(string $transcriptId): void
    {
        $this->authorizeManage();
        $t = $this->transcript($transcriptId);
        if (! $this->validMinutes($t)) {
            return;
        }
        $recipients = $t->meeting->attendees()->whereIn('role', Meetings::EMAILED_ROLES)->count();
        if ($recipients === 0) {
            $this->addError("minutes.{$t->id}", __('meetings.errors.no_recipients'));

            return;
        }

        try {
            $unsent = Transcripts::sendMinutes($t, $this->minutes[$t->id]);
        } catch (LogicException) {
            // Someone else sent them a moment ago (or a second click got here first)
            $this->say(__('meetings.errors.minutes_sent'), 'warning');

            return;
        }
        $sent = $recipients - count($unsent);
        if ($sent === 0) {
            $this->say(__('meetings.done.minutes_failed'), 'error');
        } elseif ($unsent) {
            $this->say(__('meetings.done.minutes_partly', ['count' => $sent, 'emails' => implode(', ', $unsent)]), 'warning');
        } else {
            $this->say(trans_choice('meetings.done.minutes_sent', $sent, ['count' => $sent]));
        }
        unset($this->minutes[$t->id], $this->minutesSeen[$t->id]);
    }

    /** Sent minutes that didn't reach everyone: try only the addresses that failed */
    public function resendMinutes(string $transcriptId): void
    {
        $this->authorizeManage();
        $t = $this->transcript($transcriptId);
        $tried = count((array) $t->minutes_unsent);
        if ($t->minutes_sent_at === null || $tried === 0) {
            return;
        }
        $unsent = Transcripts::resendMinutes($t);
        $sent = $tried - count($unsent);
        if ($unsent) {
            $this->say(__('meetings.done.minutes_partly', ['count' => $sent, 'emails' => implode(', ', $unsent)]), 'warning');
        } else {
            $this->say(trans_choice('meetings.done.minutes_sent', $sent, ['count' => $sent]));
        }
    }

    private function validMinutes(MeetingTranscript $t): bool
    {
        $this->resetErrorBag();
        if ($t->meeting->kind !== 'client' || $t->minutes_sent_at !== null) {
            $this->say(__('meetings.errors.minutes_sent'), 'warning');

            return false;
        }
        $text = trim((string) ($this->minutes[$t->id] ?? ''));
        if ($text === '') {
            $this->addError("minutes.{$t->id}", __('meetings.errors.minutes'));
        } elseif (mb_strlen($text) > 10000) {
            $this->addError("minutes.{$t->id}", __('meetings.errors.minutes_long'));
        }

        return $this->getErrorBag()->isEmpty();
    }

    private function draft(string $transcriptId, string $purpose): void
    {
        $this->authorizeManage();
        // The AI's notes and minutes retell the transcript, so drafting them needs the right to read it
        abort_unless(OpportunityAccess::canReadTranscripts(Auth::user(), $this->opportunity()), 403);
        $t = $this->transcript($transcriptId);
        $running = Transcripts::latestRuns([$t->id])[$t->id][$purpose] ?? null;
        if ($running && ! $running->isFinished()) {
            return; // one draft per click: this one is still being written
        }
        try {
            $purpose === 'meeting_notes' ? Transcripts::draftNotes($t) : Transcripts::draftMinutes($t);
        } catch (AiUnavailable $e) {
            $this->say($e->getMessage(), 'error');

            return;
        }
        if ($purpose === 'client_minutes') {
            // The new draft replaces what is in the box
            unset($this->minutes[$t->id], $this->minutesSeen[$t->id]);
        }
        $this->notice = null;
    }

    // ---- A sprint's handover: the points raised, confirmed as QA tickets ------------------------------------------

    /** Ask the AI for the points raised (again): it reads the transcript, so it needs the right to read it */
    public function draftPoints(string $transcriptId): void
    {
        $this->authorizeManage();
        abort_unless(OpportunityAccess::canReadTranscripts(Auth::user(), $this->opportunity()), 403);
        $t = $this->transcript($transcriptId);
        $running = Handovers::latestRuns([$t->id])[$t->id] ?? null;
        if (! $t->meeting->isHandover() || ($running && ! $running->isFinished())) {
            return; // one draft per click: this one is still being written
        }
        try {
            Handovers::draftPoints($t);
        } catch (AiUnavailable $e) {
            $this->say($e->getMessage(), 'error');
        } catch (LogicException) {
            $this->say(__('handover.errors.sprint'), 'warning');
        }
    }

    /** A draft point's field, saved as soon as it is left (wire:model.blur) */
    public function updatedPoints(mixed $value, string $key): void
    {
        [$id, $field] = array_pad(explode('.', $key, 2), 2, '');
        if (! in_array($field, ['title', 'detail', 'raised_by', 'severity', 'task_id'], true)) {
            return;
        }
        $this->authorizeReview();
        $point = $this->point($id);
        if ($error = $this->pointError($point, $field, $value)) {
            $this->addError("points.{$id}.{$field}", $error);

            return;
        }
        if (! Handovers::updatePoint($point, $field, $value)) {
            unset($this->points[$id]);
            $this->say(__('handover.errors.decided'), 'warning');
        }
    }

    /** The lead confirms a point: a QA ticket on the project, linked to its task */
    public function confirmPoint(string $pointId): void
    {
        $this->authorizeReview();
        $point = $this->point($pointId);
        if (! $this->saveBuffered($point)) {
            return;
        }
        try {
            $ticket = Handovers::confirm($point->fresh());
        } catch (LogicException) {
            $this->say(__('handover.errors.no_project'), 'error');

            return;
        }
        unset($this->points[$point->id]);
        $ticket ? $this->say(__('handover.done.confirmed', ['title' => $point->fresh()->title])) : $this->say(__('handover.errors.decided'), 'warning');
    }

    /** Every draft point of the meeting, as it stands on screen */
    public function confirmAll(string $meetingId): void
    {
        $this->authorizeReview();
        $m = $this->meeting($meetingId);
        $created = 0;
        foreach ($m->points()->where('status', 'draft')->get() as $point) {
            if (! $this->saveBuffered($point)) {
                return; // that point's errors show; the ones before it are created
            }
            try {
                $created += Handovers::confirm($point->fresh()) ? 1 : 0;
            } catch (LogicException) {
                $this->say(__('handover.errors.no_project'), 'error');

                return;
            }
            unset($this->points[$point->id]);
        }
        $this->say(trans_choice('handover.done.confirmed_all', $created, ['count' => $created]));
    }

    public function dismissPoint(string $pointId): void
    {
        $this->authorizeReview();
        $point = $this->point($pointId);
        Handovers::dismiss($point) ? $this->say(__('handover.done.dismissed', ['title' => $point->title])) : $this->say(__('handover.errors.decided'), 'warning');
        unset($this->points[$point->id]);
    }

    public function restorePoint(string $pointId): void
    {
        $this->authorizeReview();
        Handovers::dismiss($this->point($pointId), dismiss: false);
    }

    public function openAddPoint(string $meetingId): void
    {
        $this->authorizeReview();
        $this->fill(['addingPointTo' => $this->meeting($meetingId)->id, 'newPointTitle' => '', 'newPointSeverity' => 'Medium']);
    }

    public function closeAddPoint(): void
    {
        $this->fill(['addingPointTo' => '', 'newPointTitle' => '', 'newPointSeverity' => 'Medium']);
        $this->resetErrorBag();
    }

    public function addPoint(): void
    {
        $this->authorizeReview();
        $m = $this->meeting($this->addingPointTo);
        if (! $m->isHandover()) {
            $this->closeAddPoint();

            return;
        }
        $this->validate([
            'newPointTitle' => ['required', 'string', 'max:' . Handovers::MAX_TITLE],
            'newPointSeverity' => ['required', Rule::in(Handovers::SEVERITIES)],
        ], [
            'newPointTitle.required' => __('handover.errors.title'),
            'newPointTitle.max' => __('handover.errors.title_long'),
            'newPointSeverity.*' => __('handover.errors.severity'),
        ]);
        try {
            Handovers::addPoint($m, ['title' => $this->newPointTitle, 'severity' => $this->newPointSeverity]);
        } catch (LogicException) {
            $this->addError('newPointTitle', __('handover.errors.full', ['max' => Handovers::MAX_POINTS]));

            return;
        }
        $this->closeAddPoint();
        $this->open = $m->id;
    }

    private function point(string $id): MeetingPoint
    {
        return MeetingPoint::whereHas('meeting', fn ($q) => $q->where('opportunity_id', $this->opportunityId))->findOrFail($id);
    }

    /** What is wrong with a value for a point's field, or null */
    private function pointError(MeetingPoint $point, string $field, mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            return __('handover.errors.invalid');
        }
        $value = trim((string) $value);

        return match ($field) {
            'title' => $value === '' ? __('handover.errors.title') : (mb_strlen($value) > Handovers::MAX_TITLE ? __('handover.errors.title_long') : null),
            'detail' => mb_strlen($value) > Handovers::MAX_DETAIL ? __('handover.errors.detail_long') : null,
            'raised_by' => mb_strlen($value) > Handovers::MAX_RAISED_BY ? __('handover.errors.raised_by_long') : null,
            'severity' => in_array($value, Handovers::SEVERITIES, true) ? null : __('handover.errors.severity'),
            'task_id' => $value === '' || Handovers::taskOnProject($value, (string) $this->opportunity()->project_id) ? null : __('handover.errors.task'),
            default => __('handover.errors.invalid'),
        };
    }

    /** What the lead typed on a draft point and hasn't left yet goes in first; false (with the errors) when it can't */
    private function saveBuffered(MeetingPoint $point): bool
    {
        foreach ((array) ($this->points[$point->id] ?? []) as $field => $value) {
            if (! in_array($field, ['title', 'detail', 'raised_by', 'severity', 'task_id'], true)
                || ((is_string($value) || $value === null) && (string) $value === (string) $point->{$field})) {
                continue;
            }
            if ($error = $this->pointError($point, $field, $value)) {
                $this->addError("points.{$point->id}.{$field}", $error);
            } else {
                Handovers::updatePoint($point, $field, $value);
            }
        }

        return $this->getErrorBag()->isEmpty();
    }

    // ---- Helpers -------------------------------------------------------------------------------------------------

    private function authorizeReview(): void
    {
        abort_unless(Handovers::canReview(Auth::user(), $this->opportunity()), 403);
        $this->resetErrorBag();
        $this->notice = null;
    }

    private function authorizeManage(): void
    {
        abort_unless(OpportunityAccess::canManage(Auth::user(), $this->opportunity()), 403);
        $this->resetErrorBag();
        $this->notice = null;
    }

    private function resetForm(): void
    {
        $this->reset(['formOpen', 'editingId', 'sprintId', 'kind', 'title', 'date', 'time', 'duration', 'staff', 'contacts', 'guests', 'agenda']);
        $this->resetErrorBag();
    }

    private function say(string $message, string $tone = 'success'): void
    {
        $this->notice = $message;
        $this->noticeTone = $tone;
    }

    /** "Meeting scheduled…", with the client emails that couldn't be sent */
    private function reported(MeetingChange $change, string $what): void
    {
        $m = $change->meeting;
        $message = __("meetings.done.{$what}.{$m->kind}", ['title' => $m->title]);
        if ($change->unsent) {
            $this->say($message . ' ' . __('meetings.done.unsent', ['emails' => implode(', ', $change->unsent)]), 'warning');
        } else {
            $this->say($message);
        }
    }

    /**
     * Emails typed or pasted one per line or separated by commas, with or without a name ("Ada Obi <ada@x.com>").
     *
     * @return array{0: list<string>, 1: list<string>} valid emails (once each), and the entries that aren't one
     */
    private function parseGuests(string $text): array
    {
        $good = [];
        $bad = [];
        foreach (preg_split('/[\n,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $found = preg_match_all('/[^\s<>"\'(),;]+@[^\s<>"\'(),;]+/u', $entry, $m) ? $m[0] : [];
            if (! $found) {
                $bad[] = $entry;
            }
            foreach ($found as $email) {
                $email = mb_strtolower(rtrim($email, '.'));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $good[$email] = $email;
                } else {
                    $bad[] = $email;
                }
            }
        }

        return [array_values($good), $bad];
    }

    private function defaultTitle(Opportunity $o, string $kind): string
    {
        if ($kind === 'internal') {
            return __('meetings.defaults.internal', ['title' => $o->title]);
        }
        $first = ! Meeting::where('opportunity_id', $o->id)->where('kind', 'client')->where('status', '!=', 'cancelled')->exists();

        // The client sees this title in the invitation: the opportunity's language
        return __($first ? 'meetings.defaults.discovery' : 'meetings.defaults.follow_up', ['title' => $o->title], $o->language);
    }

    private function nextWorkingDay(): string
    {
        $day = CarbonImmutable::now(Meetings::TIME_ZONE)->addDay();
        while ($day->isWeekend()) {
            $day = $day->addDay();
        }

        return $day->format('Y-m-d');
    }

    /** Internal meetings start from what the last client meeting left open (flow step 4) */
    private function agendaFromLastClientMeeting(Opportunity $o): string
    {
        $last = Meeting::where('opportunity_id', $o->id)->where('kind', 'client')->where('status', 'held')
            ->whereHas('transcripts', fn ($q) => $q->whereNotNull('notes'))->orderByDesc('starts_at')->with('transcripts')->first();
        $notes = $last?->transcripts->whereNotNull('notes')->last()?->notes;
        if (! $last || ! is_array($notes)) {
            return '';
        }
        $blocks = [__('meetings.agenda.after', ['title' => $last->title, 'date' => $last->starts_at->copy()->setTimezone($last->time_zone)->format('j F Y')])];
        foreach (['decisions', 'open_questions'] as $key) {
            $items = array_filter((array) ($notes[$key] ?? []));
            if ($items) {
                $blocks[] = __("meetings.notes.{$key}") . "\n" . implode("\n", array_map(fn ($i) => "- {$i}", $items));
            }
        }
        $steps = array_filter(array_map(fn ($s) => trim((string) ($s['action'] ?? '')), (array) ($notes['next_steps'] ?? [])));
        if ($steps) {
            $blocks[] = __('meetings.notes.next_steps') . "\n" . implode("\n", array_map(fn ($i) => "- {$i}", $steps));
        }

        return Str::limit(implode("\n\n", $blocks), 5000, '');
    }

    public function render()
    {
        $o = $this->opportunity();
        $meetings = Meeting::where('opportunity_id', $o->id)
            ->with(['attendees' => fn ($q) => $q->orderByRaw("FIELD(role, 'staff', 'client', 'guest')")->orderBy('name'), 'transcripts'])
            ->orderBy('starts_at')->get();
        $now = now();
        // A client's request stays on top until the lead schedules or declines it, even once its first time has passed
        [$upcoming, $past] = $meetings->partition(fn ($m) => $m->status === 'requested' || ($m->status === 'scheduled' && $m->ends_at->greaterThanOrEqualTo($now)));
        $transcripts = $meetings->flatMap->transcripts;
        $runs = Transcripts::latestRuns($transcripts->pluck('id')->all());

        // Minutes boxes start from what HQ holds, and follow a new draft unless someone has edited them
        foreach ($transcripts as $t) {
            if ($t->minutes_sent_at === null && filled($t->minutes)
                && (! array_key_exists($t->id, $this->minutes) || ($this->minutes[$t->id] === ($this->minutesSeen[$t->id] ?? null) && $t->minutes !== $this->minutesSeen[$t->id]))) {
                $this->minutes[$t->id] = $t->minutes;
                $this->minutesSeen[$t->id] = $t->minutes;
            }
        }
        $pending = collect($runs)->flatten()->contains(fn ($r) => ! $r->isFinished());

        // Sprint handovers: which sprint, how many points each, and the open one's points with their tickets
        $handovers = $meetings->filter->isHandover();
        $sprints = $handovers->isEmpty() ? collect() : DB::table('sprints')->whereIn('id', $handovers->pluck('sprint_id')->unique()->all())
            ->get(['id', 'title', 'start_date', 'end_date'])->keyBy('id');
        $pointCounts = $handovers->isEmpty() ? collect() : MeetingPoint::whereIn('meeting_id', $handovers->pluck('id')->all())
            ->selectRaw('meeting_id, status, count(*) as total')->groupBy('meeting_id', 'status')->get()->groupBy('meeting_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n));
        $pointRuns = Handovers::latestRuns($handovers->flatMap->transcripts->pluck('id')->all());
        $pending = $pending || collect($pointRuns)->contains(fn ($r) => ! $r->isFinished());
        $shown = $this->open !== '' ? $handovers->firstWhere('id', $this->open) : null;
        $pointList = $shown ? Handovers::points($shown) : collect();
        foreach ($pointList->where('status', 'draft') as $p) {
            // Edit boxes start from what HQ holds; what someone is typing stays
            $this->points[$p->id] ??= ['title' => $p->title, 'detail' => (string) $p->detail, 'raised_by' => (string) $p->raised_by, 'severity' => $p->severity, 'task_id' => (string) $p->task_id];
        }
        $confirmed = $pointList->where('status', 'confirmed');

        return view('livewire.hq.opportunities.meetings', [
            'o' => $o,
            'upcoming' => $upcoming->values(),
            'past' => $past->sortByDesc('starts_at')->values(),
            'transcriptCount' => $transcripts->count(),
            'runs' => $runs,
            'pending' => $pending,
            'googleConnected' => app(GoogleWorkspace::class)->isConnected(),
            'canOpenIntegrations' => (bool) Auth::user()?->can('integrations.manage'),
            'bookingLink' => $this->canManage ? PortalLinks::meetings($o) : null,
            'staffList' => $this->formOpen ? User::where('status', 'Active')->whereNull('deleted_at')->whereNotNull('email')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'job_title']) : collect(),
            'contactList' => $this->formOpen ? DB::table('client_users')->where('organization_id', $o->organization_id)->whereNull('deleted_at')
                ->orderByDesc('is_primary_client')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'job_title']) : collect(),
            'editing' => $this->editingId !== '' ? $meetings->firstWhere('id', $this->editingId) : null,
            'cancelling' => $this->cancellingId !== '' ? $meetings->firstWhere('id', $this->cancellingId) : null,
            'addingTo' => $this->transcriptFor !== '' ? $meetings->firstWhere('id', $this->transcriptFor) : null,
            'sprints' => $sprints,
            'pointCounts' => $pointCounts,
            'pointRuns' => $pointRuns,
            'pointList' => $pointList,
            'closingNotes' => Handovers::closingNotes($confirmed->pluck('qa_ticket_id')->filter()->values()->all()),
            'signoff' => $shown && $confirmed->isNotEmpty() ? Handovers::signoffText($shown) : null,
            'taskOptions' => $shown && $this->canReview && $o->project_id ? Handovers::sprintTasks($o->project_id, $shown->sprint_id) : collect(),
            'formSprint' => $this->formOpen && $this->sprintId !== '' ? Handovers::sprintFor($o, $this->sprintId) : null,
            'canQa' => (bool) Auth::user()?->can('qa.view'),
        ]);
    }
}
