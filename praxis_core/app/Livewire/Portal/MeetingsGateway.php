<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\WithGatewayLanguage;
use App\Models\Meeting;
use App\Models\Opportunity;
use App\Support\Google\GoogleUnavailable;
use App\Support\Opportunities\FreeTimes;
use App\Support\Opportunities\Meetings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The client's meetings with JBS Praxis for one opportunity (docs/OPPORTUNITIES.md decisions 4 and 12), opened from
 * the signed link in invitations and minutes (PortalLinks::meetings): upcoming meetings with their Meet link, the
 * minutes they were sent, and a follow-up: pick a free time (HQ creates the meeting, Meet link and invites at once)
 * or propose times for the lead to confirm. Internal meetings, transcripts and notes never appear here.
 */
#[Layout('components.layouts.portal')]
class MeetingsGateway extends Component
{
    use WithGatewayLanguage;

    /** Follow-ups a link can book or request in a day, so a forwarded link can't fill the calendar */
    public const MAX_PER_DAY = 3;

    public const PROPOSED = 3;

    #[Locked]
    public string $opportunityId;

    #[Locked]
    public ?string $contactId = null;

    /** '' | pick | propose */
    public string $mode = '';

    /** Free starts (UTC ISO) by Lagos date, read once when "pick a time" opens; null: Google couldn't be asked */
    #[Locked]
    public ?array $free = null;

    public bool $allDays = false;

    public string $chosen = '';

    public string $name = '';

    public string $email = '';

    public string $note = '';

    public array $proposed = [];

    /** booked | requested, once done */
    #[Locked]
    public string $done = '';

    public function mount(string $opportunity): void
    {
        $o = Opportunity::with('organization')->find($opportunity);
        abort_unless($o, 404);
        $this->useGatewayLanguage($o->language);
        $this->opportunityId = $o->id;
        $this->proposed = array_fill(0, self::PROPOSED, ['date' => '', 'time' => '']);

        $contact = request()->query('c');
        $contact = is_string($contact) && Str::isUuid($contact)
            ? DB::table('client_users')->where('id', $contact)->where('organization_id', $o->organization_id)->whereNull('deleted_at')->first(['id', 'first_name', 'last_name', 'email'])
            : null;
        if ($contact) {
            $this->contactId = $contact->id;
            $this->name = trim($contact->first_name . ' ' . $contact->last_name);
            $this->email = (string) $contact->email;
        }
    }

    private function opportunity(): Opportunity
    {
        return Opportunity::with(['organization', 'lead'])->findOrFail($this->opportunityId);
    }

    public function choose(string $mode): void
    {
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['pick', 'propose'], true) ? $mode : '';
        $this->chosen = '';
        if ($this->mode === 'pick') {
            $free = FreeTimes::for($this->opportunity());
            $this->free = $free === null ? null : array_map(fn ($day) => array_map(fn ($s) => $s->toIso8601String(), $day), $free);
            if ($this->free === null || $this->free === []) {
                $this->mode = 'propose';
                $this->addError('mode', $this->free === null ? __('meetings.portal.errors.no_calendar') : __('meetings.portal.errors.no_free'));
            }
        }
    }

    public function pick(string $slot): void
    {
        $this->chosen = collect($this->free ?? [])->flatten()->contains($slot) ? $slot : '';
    }

    public function book(): void
    {
        $o = $this->opportunity();
        if (! $this->open($o) || ! $this->person()) {
            return;
        }
        $start = collect($this->free ?? [])->flatten()->contains($this->chosen) ? CarbonImmutable::parse($this->chosen)->utc() : null;
        if (! $start) {
            $this->addError('chosen', __('meetings.portal.errors.slot'));

            return;
        }
        if (! FreeTimes::isFree($o, $start)) {
            $this->choose('pick');
            $this->addError('chosen', __('meetings.portal.errors.taken'));

            return;
        }
        if (! $this->allowed($o)) {
            return;
        }

        try {
            $change = Meetings::schedule($o, $this->meetingData($o) + ['starts_at' => $start, 'ends_at' => $start->addMinutes(FreeTimes::MINUTES)]);
        } catch (GoogleUnavailable) {
            $this->mode = 'propose';
            $this->addError('mode', __('meetings.portal.errors.google'));

            return;
        }
        Meetings::tellLead($o, __('meetings.notify.booked_title', [], config('app.locale')),
            __('meetings.notify.booked', ['name' => $this->name, 'title' => $o->title, 'date' => $start->setTimezone(Meetings::TIME_ZONE)->format('j F Y, H:i')], config('app.locale')), $change->meeting->id);
        $this->done = 'booked';
    }

    public function propose(): void
    {
        $o = $this->opportunity();
        if (! $this->open($o) || ! $this->person()) {
            return;
        }
        $times = [];
        foreach ($this->proposed as $i => $p) {
            $date = trim((string) ($p['date'] ?? ''));
            $time = trim((string) ($p['time'] ?? ''));
            if ($date === '' && $time === '') {
                continue;
            }
            try {
                $at = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$time}", Meetings::TIME_ZONE);
            } catch (Throwable) {
                $at = null;
            }
            if (! $at || $at->format('Y-m-d H:i') !== "{$date} {$time}") {
                $this->addError("proposed.{$i}", __('meetings.portal.errors.time'));
            } elseif ($at->lessThan(now()->addHours(2))) {
                $this->addError("proposed.{$i}", __('meetings.portal.errors.too_soon'));
            } else {
                $times[] = $at;
            }
        }
        if (! $times && $this->getErrorBag()->isEmpty()) {
            $this->addError('proposed', __('meetings.portal.errors.no_times'));
        }
        if ($this->getErrorBag()->isNotEmpty() || ! $this->allowed($o)) {
            return;
        }

        $lines = array_map(fn ($t) => '- ' . $t->format('l j F Y, H:i') . ' WAT', $times);
        $agenda = __('meetings.portal.proposed_agenda', ['name' => $this->name], $o->language) . "\n" . implode("\n", $lines)
            . (trim($this->note) !== '' ? "\n\n" . trim($this->note) : '');
        $meeting = Meetings::request($o, ['agenda' => $agenda] + $this->meetingData($o), $times);
        Meetings::tellLead($o, __('meetings.notify.requested_title', [], config('app.locale')),
            __('meetings.notify.requested', ['name' => $this->name, 'title' => $o->title], config('app.locale')), $meeting->id);
        $this->done = 'requested';
    }

    /** Name and email of whoever books */
    private function person(): bool
    {
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->note = trim($this->note);
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => __('meetings.portal.errors.name'),
            'name.max' => __('meetings.portal.errors.name_long'),
            'email.required' => __('meetings.portal.errors.email'),
            'email.email' => __('meetings.portal.errors.email'),
            'email.max' => __('meetings.portal.errors.email'),
            'note.max' => __('meetings.portal.errors.note_long'),
        ]);

        return true;
    }

    /** The opportunity takes follow-ups (not lost), and this link hasn't booked too many today */
    private function open(Opportunity $o): bool
    {
        $this->resetErrorBag();
        if ($o->stage === 'lost') {
            $this->addError('mode', __('meetings.portal.errors.closed'));

            return false;
        }

        return true;
    }

    private function allowed(Opportunity $o): bool
    {
        $key = 'portal-meetings:' . $o->id;
        $today = Meeting::where('opportunity_id', $o->id)->whereNull('created_by')->where('created_at', '>=', now()->subDay())->count();
        if ($today >= self::MAX_PER_DAY || RateLimiter::tooManyAttempts($key, self::MAX_PER_DAY)) {
            $this->addError('mode', __('meetings.portal.errors.too_many'));

            return false;
        }
        RateLimiter::hit($key, 86400);

        return true;
    }

    /** The follow-up's title (in the opportunity's language), the lead, and who books: a known contact or a guest */
    private function meetingData(Opportunity $o): array
    {
        $contact = DB::table('client_users')->where('organization_id', $o->organization_id)->whereNull('deleted_at')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($this->email)])->value('id');

        return [
            'kind' => 'client',
            'title' => __('meetings.defaults.follow_up', ['title' => $o->title], $o->language),
            'agenda' => $this->note,
            'staff' => array_filter([$o->lead_user_id]),
            'contacts' => $contact ? [$contact] : [],
            'guests' => $contact ? [] : [['email' => $this->email, 'name' => $this->name]],
            'minutes' => FreeTimes::MINUTES,
        ];
    }

    public function render()
    {
        $o = $this->opportunity();
        $meetings = Meeting::where('opportunity_id', $o->id)->where('kind', 'client')->where('status', '!=', 'cancelled')
            ->with(['transcripts' => fn ($q) => $q->whereNotNull('minutes_sent_at')->orderBy('minutes_sent_at')])->orderBy('starts_at')->get();
        $now = now();

        return view('livewire.portal.meetings-gateway', [
            'o' => $o,
            'upcoming' => $meetings->filter(fn ($m) => $m->status === 'scheduled' && $m->ends_at->greaterThan($now))->values(),
            'requested' => $meetings->where('status', 'requested')->values(),
            'minutes' => $meetings->filter(fn ($m) => $m->transcripts->isNotEmpty())->sortByDesc('starts_at')->values(),
            'times' => array_map(fn ($day) => array_map(fn ($s) => CarbonImmutable::parse($s), $day), $this->free ?? []),
        ])->title(__('meetings.portal.title'));
    }
}
