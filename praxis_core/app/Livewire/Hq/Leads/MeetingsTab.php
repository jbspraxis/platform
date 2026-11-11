<?php

namespace App\Livewire\Hq\Leads;

use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Support\Ai\AiUnavailable;
use App\Support\Google\GoogleUnavailable;
use App\Support\Google\GoogleWorkspace;
use App\Support\Leads\Contacts;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadMeetings;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\NextStep;
use App\Support\Opportunities\MeetingChange;
use App\Support\Opportunities\Meetings;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\Transcripts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

/**
 * A lead's Meetings tab (docs/LEADS.md section 9, owner's decisions 11 and 13–16), inside LeadPage:
 *
 *   - Schedule a meeting: a Google invite with a Meet link, or in person at an address (LeadMeetings::schedule);
 *   - Log a meeting that already happened: who was there, the team's typed notes, the transcript if there is one;
 *   - each meeting keeps its transcript (collected from Google, or added by hand), the AI's notes with the next steps,
 *     the typed notes and a download, as an opportunity's meetings do (the same Meetings and Transcripts code);
 *   - Open an opportunity from a meeting: the AI suggests the title from the transcript; the meeting, its notes and
 *     its people go with it;
 *   - a free audit booked on the website waits here as requested until Free audits approves it.
 *
 * Meetings of the lead's opportunities are listed too, read only, with a link to the opportunity. crm.view reads;
 * crm.edit acts; full transcripts need LeadMeetings::canReadTranscripts; opening an opportunity needs
 * opportunities.create. Every change tells the page (lead-changed) so the tab counts and the timeline follow.
 */
class MeetingsTab extends Component
{
    #[Locked]
    public string $leadId;

    #[Locked]
    public bool $canManage = false;

    #[Locked]
    public bool $canReadTranscripts = false;

    #[Locked]
    public bool $canOpenOpportunity = false;

    /** The meeting whose details are open (?meeting=…, from a bell) */
    #[Url(as: 'meeting', except: '')]
    public string $open = '';

    // Schedule, log or change a meeting: form is '' (closed), schedule, log or edit
    #[Locked]
    public string $form = '';

    #[Locked]
    public string $editingId = '';

    public string $title = '';

    public string $date = '';

    public string $time = '';

    public int $duration = 60;

    /** meet (Google Meet; a logged one: a call or video) or in_person (an address) */
    public string $where = 'meet';

    public string $location = '';

    /** A logged meeting's call link, if any */
    public string $meetLink = '';

    public array $staff = [];

    public array $contacts = [];

    public string $guests = '';

    public string $agenda = '';

    /** A logged meeting: what the team typed, and the transcript if there is one */
    public string $typedNotes = '';

    public string $transcriptText = '';

    public string $transcriptSource = 'paste';

    // Cancel
    #[Locked]
    public string $cancellingId = '';

    // Add a transcript to a meeting
    #[Locked]
    public string $transcriptFor = '';

    // Typed notes of a meeting
    #[Locked]
    public string $notesFor = '';

    public string $notesText = '';

    // Open an opportunity from a meeting
    #[Locked]
    public string $opportunityFrom = '';

    public string $oppTitle = '';

    public string $oppLead = '';

    public string $oppLanguage = 'en';

    #[Locked]
    public bool $titleAsked = false;

    #[Locked]
    public ?string $titleNote = null;

    #[Locked]
    public string $titleTone = 'info';

    #[Locked]
    public string $titleSuggested = '';

    /** What the AI's runs looked like when the tab was last drawn (see check()) */
    #[Locked]
    public string $polled = '';

    public ?string $notice = null;

    public string $noticeTone = 'success';

    public function mount(string $leadId): void
    {
        $user = Auth::user();
        abort_unless(LeadAccess::canView($user), 403);
        $lead = $this->lead($leadId);
        $this->leadId = $lead->id;
        $this->canManage = LeadMeetings::canManage($user);
        $this->canReadTranscripts = LeadMeetings::canReadTranscripts($user, $lead);
        $this->canOpenOpportunity = $this->canManage && OpportunityAccess::canCreate($user);
        if ($this->open !== '' && ! Meeting::where('organization_id', $lead->id)->whereKey($this->open)->exists()) {
            $this->open = '';
        }
    }

    private function lead(?string $id = null): Organization
    {
        $lead = LeadAccess::find($id ?? $this->leadId);
        abort_unless($lead, 404);

        return $lead;
    }

    /** One of this lead's own meetings (an opportunity's is changed there) */
    private function meeting(string $id): Meeting
    {
        $m = LeadMeetings::find($this->leadId, $id);
        abort_unless($m, 404);

        return $m;
    }

    private function transcript(string $id): MeetingTranscript
    {
        return MeetingTranscript::whereHas('meeting', fn ($q) => $q->where('organization_id', $this->leadId)->whereNull('opportunity_id'))
            ->with('meeting')->findOrFail($id);
    }

    public function toggle(string $id): void
    {
        $this->open = $this->open === $id ? '' : Meeting::where('organization_id', $this->leadId)->findOrFail($id)->id;
    }

    // ---- Schedule, log or change -----------------------------------------------------------------------------------

    public function openSchedule(): void
    {
        $this->authorizeManage();
        $lead = $this->lead();
        $this->resetForm();
        $main = Contacts::of($lead->id)->first(fn ($c) => filled($c->email));
        $this->fill([
            'form' => 'schedule',
            'title' => 'Introduction: ' . $lead->name,
            'date' => NextStep::suggestion()['due'],
            'time' => '10:00',
            'duration' => 60,
            'where' => 'meet',
            'location' => (string) $lead->billing_address,
            'staff' => array_values(array_unique(array_filter([$lead->assigned_to, Auth::id()]))),
            'contacts' => $main ? [$main->id] : [],
        ]);
    }

    public function openLog(): void
    {
        $this->authorizeManage();
        $lead = $this->lead();
        $this->resetForm();
        $main = Contacts::of($lead->id)->first(fn ($c) => filled($c->email));
        $now = CarbonImmutable::now(Meetings::TIME_ZONE);
        $this->fill([
            'form' => 'log',
            'title' => 'Meeting with ' . $lead->name,
            'date' => $now->format('Y-m-d'),
            'time' => $now->subHour()->format('H') . ':00',
            'duration' => 60,
            'where' => 'meet',
            'location' => (string) $lead->billing_address,
            'staff' => [Auth::id()],
            'contacts' => $main ? [$main->id] : [],
        ]);
    }

    public function openEdit(string $id): void
    {
        $this->authorizeManage();
        $m = $this->meeting($id);
        if (! Meetings::canChange($m) || $m->status === 'requested') {
            $this->say($m->status === 'requested' ? 'A free audit request is approved in Free audits.' : 'This meeting has started, so it can’t be changed.', 'warning');

            return;
        }
        $starts = $m->starts_at->copy()->setTimezone($m->time_zone ?: Meetings::TIME_ZONE);
        $this->resetForm();
        $this->fill([
            'form' => 'edit',
            'editingId' => $m->id,
            'title' => $m->title,
            'date' => $starts->format('Y-m-d'),
            'time' => $starts->format('H:i'),
            'duration' => max(15, (int) $m->starts_at->diffInMinutes($m->ends_at)),
            'where' => $m->location ? 'in_person' : 'meet',
            'location' => (string) $m->location,
            'staff' => $m->attendees->where('role', 'staff')->pluck('user_id')->filter()->values()->all(),
            'contacts' => $m->attendees->where('role', 'client')->pluck('client_user_id')->filter()->values()->all(),
            'guests' => $m->attendees->filter(fn ($a) => $a->role === 'guest' || ($a->role === 'client' && ! $a->client_user_id) || ($a->role === 'staff' && ! $a->user_id))
                ->pluck('email')->implode("\n"),
            'agenda' => (string) $m->agenda,
        ]);
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeManage();
        $lead = $this->lead();
        if (! in_array($this->form, ['schedule', 'log', 'edit'], true)) {
            return;
        }
        $editing = $this->form === 'edit' ? $this->meeting($this->editingId) : null;
        if ($editing && ! Meetings::canChange($editing)) {
            $this->resetForm();
            $this->say('This meeting has started, so it can’t be changed.', 'warning');

            return;
        }
        $logging = $this->form === 'log';
        // A meeting in person keeps that way when changed (its invite has no Meet link to add)
        $where = $editing ? ($editing->location ? 'in_person' : 'meet') : $this->where;
        foreach (['title', 'location', 'meetLink', 'agenda', 'typedNotes'] as $field) {
            $this->{$field} = trim($this->{$field});
        }
        [$guests, $bad] = $this->parseGuests($this->guests);
        $durations = array_unique([...LeadMeetings::DURATIONS, 15, ...($editing ? [(int) $editing->starts_at->diffInMinutes($editing->ends_at)] : [])]);

        $this->withValidator(fn ($validator) => $validator->after(function ($v) use ($logging, $guests, $bad, $where) {
            if (! $v->errors()->hasAny(['date', 'time'])) {
                $starts = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$this->date} {$this->time}", Meetings::TIME_ZONE) ?: null;
                if (! $starts) {
                    $v->errors()->add('date', 'Choose a date and a time.');
                } elseif ($logging && $starts->isFuture()) {
                    $v->errors()->add('date', 'A meeting you log has already happened: choose a time in the past, or schedule it instead.');
                } elseif (! $logging && $starts->lessThanOrEqualTo(now())) {
                    $v->errors()->add('date', 'Choose a time that hasn’t passed yet, or log the meeting instead.');
                }
            }
            if ($bad) {
                $v->errors()->add('guests', 'These aren’t email addresses: ' . implode(', ', array_slice($bad, 0, 3)) . '. Write one address per line.');
            } elseif (count($guests) > 30) {
                $v->errors()->add('guests', 'Invite 30 guests at most.');
            }
            if (! $this->staff) {
                $v->errors()->add('staff', $logging ? 'Tick who from JBS Praxis was there.' : 'Tick who from JBS Praxis takes part.');
            }
            if (! $logging && ! $this->contacts && ! $guests) {
                $v->errors()->add('contacts', 'Tick at least one of their contacts, or add a guest’s email, so someone from the lead is invited.');
            }
            if ($where === 'in_person' && $this->location === '') {
                $v->errors()->add('location', $logging ? 'Give the address where you met, or choose a call or video.' : 'Give the address, or choose Google Meet.');
            }
        }))->validate([
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', Rule::in($durations)],
            'where' => ['required', Rule::in(['meet', 'in_person'])],
            'location' => ['nullable', 'string', 'max:255'],
            'meetLink' => ['nullable', 'string', 'max:255'],
            'staff' => ['array', 'max:30'],
            'staff.*' => ['string', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'contacts' => ['array', 'max:30'],
            'contacts.*' => ['string', Rule::exists('client_users', 'id')->where('organization_id', $lead->id)->whereNull('deleted_at')],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'typedNotes' => ['nullable', 'string', 'max:' . LeadMeetings::MAX_NOTES],
            'transcriptText' => ['nullable', 'string', 'max:' . Transcripts::MAX_CHARS],
            'transcriptSource' => ['required', Rule::in(['upload', 'paste'])],
        ], [
            'title.required' => 'Give the meeting a title.',
            'title.max' => 'Keep the title under 200 characters.',
            'date.*' => 'Choose a date.',
            'time.*' => 'Choose a time.',
            'duration.*' => 'Choose how long it lasts.',
            'where.*' => 'Choose Google Meet or in person.',
            'location.max' => 'Keep the address under 255 characters.',
            'meetLink.max' => 'Keep the link under 255 characters.',
            'staff.max' => 'Tick 30 people at most.',
            'staff.*.exists' => 'One of the people ticked no longer works here. Untick them.',
            'contacts.max' => 'Tick 30 contacts at most.',
            'contacts.*.exists' => 'One of the contacts ticked is no longer this lead’s. Untick them.',
            'agenda.max' => 'Keep the agenda under 5,000 characters.',
            'typedNotes.max' => 'Keep your notes under 20,000 characters.',
            'transcriptText.max' => 'This transcript is too long. Add the first part, or split it in two.',
            'transcriptSource.*' => 'Paste the transcript again.',
        ]);
        $starts = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$this->date} {$this->time}", Meetings::TIME_ZONE);
        $data = [
            'title' => $this->title,
            'agenda' => $this->agenda,
            'starts_at' => $starts,
            'ends_at' => $starts->addMinutes($this->duration),
            'staff' => $this->staff,
            'contacts' => $this->contacts,
            'guests' => array_map(fn ($e) => ['email' => $e], $guests),
            'location' => $where === 'in_person' ? $this->location : null,
        ];

        if ($logging) {
            $m = LeadMeetings::logPast($lead, $data + [
                'meet_link' => $where === 'meet' ? $this->meetLink : null,
                'typed_notes' => $this->typedNotes,
                'transcript' => trim($this->transcriptText),
                'transcript_source' => $this->transcriptSource,
            ], Auth::user());
            $this->resetForm();
            $this->open = $m->id;
            $this->say($m->transcripts->isNotEmpty() ? 'Meeting logged with its transcript. The AI is writing the notes.' : 'Meeting logged.');
            $this->dispatch('lead-changed');

            return;
        }

        try {
            $change = $editing ? LeadMeetings::update($editing, $data, Auth::user()) : LeadMeetings::schedule($lead, $data, Auth::user());
        } catch (GoogleUnavailable $e) {
            $this->addError('google', $e->getMessage());

            return;
        }
        $this->resetForm();
        $this->open = $change->meeting->id;
        $this->reported($change, $editing ? 'Meeting changed. Google has told everyone invited.' : 'Meeting scheduled. Google has sent the invitations.');
        $this->dispatch('lead-changed');
    }

    // ---- Cancel -------------------------------------------------------------------------------------------------

    public function confirmCancel(string $id): void
    {
        $this->authorizeManage();
        $m = $this->meeting($id);
        if ($m->status === 'requested') {
            $this->say('A free audit request is declined in Free audits.', 'warning');

            return;
        }
        $this->cancellingId = $m->id;
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
        if (! Meetings::canChange($m) || $m->status === 'requested') {
            $this->say('This meeting has started, so it can’t be cancelled.', 'warning');

            return;
        }
        try {
            $change = LeadMeetings::cancel($m, Auth::user());
        } catch (GoogleUnavailable $e) {
            $this->say($e->getMessage(), 'error');

            return;
        }
        $this->reported($change, 'Meeting cancelled. Google has told everyone invited.');
        $this->dispatch('lead-changed');
    }

    // ---- Transcripts, the AI's notes, typed notes -------------------------------------------------------------------

    public function openTranscript(string $id): void
    {
        $this->authorizeManage();
        $m = $this->meeting($id);
        if (! in_array($m->status, ['scheduled', 'held'], true) || $m->starts_at->isFuture()) {
            $this->say('A transcript is added once the meeting has started.', 'warning');

            return;
        }
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
            'transcriptText.required' => 'Paste the transcript, or choose its file.',
            'transcriptText.max' => 'This transcript is too long. Add the first part, or split it in two.',
            'transcriptSource.*' => 'Paste the transcript again.',
        ]);
        if (trim($this->transcriptText) === '') {
            $this->addError('transcriptText', 'Paste the transcript, or choose its file.');

            return;
        }
        Transcripts::fromText($m, $this->transcriptText, $this->transcriptSource);
        $this->closeTranscript();
        $this->open = $m->id;
        $this->say('Transcript added. The AI is writing the notes.');
        $this->dispatch('lead-changed');
    }

    /** The AI's notes, when they weren't written by themselves (no key or over the budget then): one draft per click */
    public function draftNotes(string $transcriptId): void
    {
        $this->authorizeManage();
        // The notes retell the transcript, so drafting them needs the right to read it
        abort_unless($this->canReadTranscripts, 403);
        $t = $this->transcript($transcriptId);
        if (filled($t->notes)) {
            return;
        }
        $running = Transcripts::latestRuns([$t->id])[$t->id]['meeting_notes'] ?? null;
        if ($running && ! $running->isFinished()) {
            return;
        }
        try {
            Transcripts::draftNotes($t);
        } catch (AiUnavailable $e) {
            $this->say($e->getMessage(), 'error');

            return;
        }
        $this->notice = null;
    }

    public function editNotes(string $id): void
    {
        $this->authorizeManage();
        $m = $this->meeting($id);
        $this->fill(['notesFor' => $m->id, 'notesText' => (string) $m->typed_notes]);
    }

    public function closeNotes(): void
    {
        $this->fill(['notesFor' => '', 'notesText' => '']);
        $this->resetErrorBag();
    }

    public function saveNotes(): void
    {
        $this->authorizeManage();
        $m = $this->meeting($this->notesFor);
        $this->validate(['notesText' => ['nullable', 'string', 'max:' . LeadMeetings::MAX_NOTES]], ['notesText.max' => 'Keep your notes under 20,000 characters.']);
        $changed = LeadMeetings::saveTypedNotes($m, $this->notesText, Auth::user());
        $this->closeNotes();
        $this->open = $m->id;
        if ($changed) {
            $this->say('Notes saved.');
        }
    }

    /** The first next step of the AI's notes becomes the lead's next step, due the next working day (then editable on the Overview) */
    public function useNextStep(string $transcriptId): void
    {
        $this->authorizeManage();
        $t = $this->transcript($transcriptId);
        $action = trim((string) collect((array) ($t->notes['next_steps'] ?? []))->pluck('action')->first(fn ($a) => filled($a)));
        if ($action === '') {
            return;
        }
        try {
            NextStep::set($this->lead(), Str::limit($action, 255, ''), NextStep::suggestion()['due'], Auth::user());
        } catch (LeadRuleBroken $e) {
            $this->say($e->getMessage(), 'warning');

            return;
        }
        $this->say('Next step set: “' . Str::limit($action, 120) . '”, by ' . NextStep::day(NextStep::suggestion()['due']) . '. Change the date on the Overview.');
        $this->dispatch('lead-changed');
    }

    // ---- Open an opportunity from a meeting --------------------------------------------------------------------------

    public function openOpportunity(string $id): void
    {
        abort_unless($this->canOpenOpportunity && OpportunityAccess::canCreate(Auth::user()), 403);
        $this->resetErrorBag();
        $this->notice = null;
        $m = $this->meeting($id);
        if (! LeadMeetings::canOpenOpportunity($m)) {
            $this->say('An opportunity is opened from a meeting that is scheduled or held.', 'warning');

            return;
        }
        $lead = $this->lead();
        $owner = LeadOwner::activeStaff($lead->assigned_to);
        $this->fill([
            'opportunityFrom' => $m->id,
            'oppTitle' => LeadMeetings::defaultTitle($m),
            'oppLead' => $owner?->id ?? (string) Auth::id(),
            'oppLanguage' => LeadMeetings::language($lead),
            'titleAsked' => false,
            'titleNote' => null,
            'titleTone' => 'info',
            'titleSuggested' => '',
        ]);
    }

    /** Asked by the dialog as soon as it opens: the AI's title replaces the meeting's unless the person has typed one */
    public function suggestTitle(): void
    {
        abort_unless($this->canOpenOpportunity, 403);
        if ($this->opportunityFrom === '' || $this->titleAsked) {
            return;
        }
        $this->titleAsked = true;
        $m = $this->meeting($this->opportunityFrom);
        try {
            $title = LeadMeetings::suggestTitle($m);
        } catch (AiUnavailable $e) {
            [$this->titleNote, $this->titleTone] = ['No title from the AI: ' . $e->getMessage() . ' Write one yourself.', 'warning'];

            return;
        }
        if ($title === null) {
            [$this->titleNote, $this->titleTone] = ['Add the meeting’s transcript or your notes, and the AI suggests a title from them. For now it is the meeting’s.', 'info'];

            return;
        }
        if (trim($this->oppTitle) === LeadMeetings::defaultTitle($m) || trim($this->oppTitle) === '') {
            $this->oppTitle = $title;
        }
        $this->titleSuggested = $title;
        [$this->titleNote, $this->titleTone] = ['Suggested by the AI from the meeting’s ' . ($m->transcripts->isNotEmpty() ? 'transcript' : 'notes') . '. Change it as you like.', 'info'];
    }

    public function closeOpportunity(): void
    {
        $this->reset(['opportunityFrom', 'oppTitle', 'oppLead', 'oppLanguage', 'titleAsked', 'titleNote', 'titleTone', 'titleSuggested']);
        $this->resetErrorBag();
    }

    public function createOpportunity()
    {
        abort_unless($this->canOpenOpportunity && OpportunityAccess::canCreate(Auth::user()), 403);
        $m = $this->meeting($this->opportunityFrom);
        $this->oppTitle = trim($this->oppTitle);
        $this->validate([
            'oppTitle' => ['required', 'string', 'max:200'],
            'oppLead' => ['required', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'oppLanguage' => ['required', Rule::in(Opportunity::LANGUAGES)],
        ], [
            'oppTitle.required' => 'Give the opportunity a title: the piece of work you discussed.',
            'oppTitle.max' => 'Keep the title under 200 characters.',
            'oppLead.*' => 'Choose who leads it.',
            'oppLanguage.*' => 'Choose English or French.',
        ]);
        try {
            $opportunity = LeadMeetings::openOpportunity($m, ['title' => $this->oppTitle, 'lead_user_id' => $this->oppLead, 'language' => $this->oppLanguage], Auth::user());
        } catch (LogicException) {
            $this->closeOpportunity();
            $this->say('This meeting is already part of an opportunity.', 'warning');
            $this->dispatch('lead-changed');

            return null;
        }
        session()->flash('success', "Opportunity “{$opportunity->title}” opened. The meeting “{$m->title}”, its notes and its people came with it.");

        return $this->redirectRoute('hq.crm.opportunity', ['opportunity' => $opportunity->id, 'tab' => 'meetings', 'meeting' => $m->id], navigate: true);
    }

    // ---- Helpers ---------------------------------------------------------------------------------------------------

    private function authorizeManage(): void
    {
        abort_unless(LeadMeetings::canManage(Auth::user()), 403);
        $this->resetErrorBag();
        $this->notice = null;
    }

    private function resetForm(): void
    {
        $this->reset(['form', 'editingId', 'title', 'date', 'time', 'duration', 'where', 'location', 'meetLink', 'staff', 'contacts', 'guests', 'agenda',
            'typedNotes', 'transcriptText', 'transcriptSource']);
        $this->resetErrorBag();
    }

    private function say(string $message, string $tone = 'success'): void
    {
        $this->notice = $message;
        $this->noticeTone = $tone;
    }

    private function reported(MeetingChange $change, string $message): void
    {
        $change->unsent
            ? $this->say($message . ' The email to ' . implode(', ', $change->unsent) . ' couldn’t be sent: Google’s invitation still reached them.', 'warning')
            : $this->say($message);
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
                filter_var($email, FILTER_VALIDATE_EMAIL) ? $good[$email] = $email : $bad[] = $email;
            }
        }

        return [array_values($good), $bad];
    }

    /**
     * wire:poll while the AI writes notes: two small queries, and the tab is drawn again only when a run moved on, a
     * transcript came or its notes were written
     */
    public function check(): void
    {
        $notes = MeetingTranscript::whereHas('meeting', fn ($q) => $q->where('organization_id', $this->leadId)->whereNull('opportunity_id'))
            ->selectRaw('id, notes IS NOT NULL AS has_notes')->pluck('has_notes', 'id')->map(fn ($n) => (int) $n)->all();
        if ($this->stamp($notes, Transcripts::latestRuns(array_keys($notes))) === $this->polled) {
            $this->skipRender();
        }
    }

    /** The lead's transcripts (id => whether it has notes) and their latest runs (status and progress), as one string */
    private function stamp(array $notes, array $runs): string
    {
        ksort($notes);
        ksort($runs);

        return md5(json_encode([$notes, collect($runs)->map(fn ($byPurpose) => collect($byPurpose)->map(fn ($r) => [$r->id, $r->status, (int) $r->progress]))]));
    }

    /** The open meeting's transcripts in full, for those who may read them; every other transcript is listed without its text */
    private function withText(?Meeting $meeting): void
    {
        if (! $meeting || ! $this->canReadTranscripts || $meeting->transcripts->isEmpty()) {
            return;
        }
        $texts = MeetingTranscript::whereIn('id', $meeting->transcripts->pluck('id'))->pluck('text', 'id');
        $meeting->transcripts->each(fn (MeetingTranscript $t) => $t->setRawAttributes(['text' => (string) ($texts[$t->id] ?? '')] + $t->getAttributes(), true));
    }

    public function render()
    {
        $lead = $this->lead();
        $all = LeadMeetings::of($lead->id, transcriptText: false);
        [$own, $inOpportunities] = $all->partition(fn ($m) => $m->opportunity_id === null);
        $this->withText($this->open !== '' ? $own->firstWhere('id', $this->open) : null);
        $now = now();
        // A booking waiting for approval stays on top until Free audits approves or declines it
        [$upcoming, $past] = $own->partition(fn ($m) => $m->status === 'requested' || ($m->status === 'scheduled' && $m->ends_at->greaterThanOrEqualTo($now)));
        $transcripts = $own->flatMap->transcripts;
        $runs = Transcripts::latestRuns($transcripts->pluck('id')->all());
        $this->polled = $this->stamp($transcripts->mapWithKeys(fn ($t) => [$t->id => (int) ($t->getRawOriginal('notes') !== null)])->all(), $runs);
        $formOpen = $this->form !== '';
        // Which opportunities this person may open, for their meetings' links: one query for all of them
        $visible = LeadAccess::opportunitiesVisible(Auth::user(), $inOpportunities->pluck('opportunity'));

        return view('livewire.hq.leads.meetings-tab', [
            'lead' => $lead,
            'upcoming' => $upcoming->values(),
            'past' => $past->sortByDesc('starts_at')->values(),
            'inOpportunities' => $inOpportunities->sortByDesc('starts_at')->values(),
            'runs' => $runs,
            'pending' => collect($runs)->flatten()->contains(fn ($r) => ! $r->isFinished()),
            'googleConnected' => app(GoogleWorkspace::class)->isConnected(),
            'canOpenIntegrations' => (bool) Auth::user()?->can('integrations.manage'),
            'canSeeOpportunity' => fn ($o) => $o && ($visible[$o->id] ?? false),
            'staffList' => $formOpen ? User::where('status', 'Active')->whereNull('deleted_at')->whereNotNull('email')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'job_title']) : collect(),
            'contactList' => $formOpen ? Contacts::of($lead->id) : collect(),
            'editing' => $this->editingId !== '' ? $own->firstWhere('id', $this->editingId) : null,
            'cancelling' => $this->cancellingId !== '' ? $own->firstWhere('id', $this->cancellingId) : null,
            'addingTo' => $this->transcriptFor !== '' ? $own->firstWhere('id', $this->transcriptFor) : null,
            'opportunityMeeting' => $this->opportunityFrom !== '' ? $own->firstWhere('id', $this->opportunityFrom) : null,
            'leadOptions' => $this->opportunityFrom !== '' ? User::where('status', 'Active')->whereNull('deleted_at')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']) : collect(),
        ]);
    }
}
