<?php

namespace App\Livewire\Hq\Intelligence;

use App\Models\Organization;
use App\Services\Site\BookingService;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadImportSchema;
use App\Support\Leads\LeadNumbers;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\LeadStatus;
use App\Support\Leads\Sources;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ › Leads (docs/LEADS.md sections 7 and 9): every lead and client for everyone with crm.view (owner's decision 8).
 * Each row opens the lead's page (hq.crm.lead); "Assign to me" takes a lead nobody owns; "Book free audit" asks Free
 * audits for one; crm.export downloads the list (as filtered).
 *
 * PR 3: the filters (owner, with Mine on by default; status; next step; source; country; a search), the summary
 * strip above the list (LeadNumbers: new leads, by status, conversion, open opportunity value, overdue follow-ups;
 * a period and a per-owner view), the Excel import (<livewire:hq.import.import-dialog schema="leads" />) and the
 * way to My follow-ups. Filters live in the address, so a link can open the list filtered.
 */
#[Layout('components.layouts.hq')]
class LeadsRegister extends Component
{
    /** The filters ('' = any). owner: mine, all, none (nobody) or a users.id */
    #[Url(except: 'mine')]
    public string $owner = 'mine';

    #[Url(except: '')]
    public string $status = '';

    /** Next step: overdue, today, week (due within 7 days), none (open leads without one) */
    #[Url(except: '')]
    public string $step = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $country = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** The summary strip's period (LeadNumbers::PERIODS) and its per-owner view */
    #[Url(except: 'month')]
    public string $period = 'month';

    public bool $byOwner = false;

    /** Rows shown; "Show more" adds 100 */
    public int $limit = 100;

    /** Goes up when this page changes leads (Assign to me, an import), so the strip is counted again (see side()) */
    #[Locked]
    public int $changes = 0;

    /** The filters from the address are checked once a request */
    private bool $sanitised = false;

    public const STEPS = ['overdue' => 'Overdue', 'today' => 'Due today', 'week' => 'Due in the next 7 days', 'none' => 'No next step'];

    /** Status filter: one status, or every open one */
    public const STATUS_FILTERS = ['open' => 'Open (new, contacted, qualified)'] + LeadStatus::LABELS;

    // Book a free audit from HQ: the date and time are entered in Lagos time (WAT), like website bookings
    public $showBookingModal = false;
    #[Locked]
    public $bookingOrgId = null;
    #[Locked]
    public $bookingCompany = '';
    public $bookingDate = '', $bookingTime = '';

    // Field names in validation messages (sentence case, docs/BRAND.md section 12)
    protected $validationAttributes = ['bookingDate' => 'date', 'bookingTime' => 'time'];

    /** The leads and clients the filters let through, newest activity first */
    private function query()
    {
        $this->sanitise();
        $me = Auth::user();
        $today = LeadNumbers::today();
        $open = fn ($q) => $q->whereIn('organizations.lead_status', LeadStatus::OPEN);

        return DB::table('organizations')
            ->select('organizations.*', 'owner.first_name as owner_first', 'owner.last_name as owner_last', 'owner.id as owner_id', 'owner.avatar_path as owner_avatar')
            ->leftJoin('users as owner', 'organizations.assigned_to', '=', 'owner.id')
            ->whereIn('organizations.status', LeadAccess::STATUSES)->whereNull('organizations.deleted_at')
            ->when($this->owner === 'mine', fn ($q) => $q->where('organizations.assigned_to', $me->id))
            ->when($this->owner === 'none', fn ($q) => $q->whereNull('organizations.assigned_to'))
            ->when(! in_array($this->owner, ['mine', 'all', 'none'], true), fn ($q) => $q->where('organizations.assigned_to', $this->owner))
            ->when($this->status === 'open', $open)
            ->when($this->status !== '' && $this->status !== 'open', fn ($q) => $q->where('organizations.lead_status', $this->status))
            ->when($this->step === 'overdue', fn ($q) => $open($q)->where('organizations.next_step_due', '<', $today))
            ->when($this->step === 'today', fn ($q) => $open($q)->where('organizations.next_step_due', $today))
            ->when($this->step === 'week', fn ($q) => $open($q)->whereBetween('organizations.next_step_due', [$today, Carbon::now(\App\Support\Leads\NextStep::TZ)->addDays(7)->format('Y-m-d')]))
            ->when($this->step === 'none', fn ($q) => $open($q)->where(fn ($w) => $w->whereNull('organizations.next_step')->orWhere('organizations.next_step', '')))
            ->when($this->source !== '', fn ($q) => $q->where('organizations.source', $this->source))
            ->when($this->country !== '', fn ($q) => $q->where('organizations.country', $this->country))
            ->when(trim($this->search) !== '', function ($q) {
                $like = '%' . addcslashes(trim($this->search), '%_\\') . '%';
                $q->where(fn ($w) => $w->where('organizations.name', 'like', $like)->orWhere('organizations.email', 'like', $like)->orWhere('organizations.phone', 'like', $like)
                    ->orWhere('organizations.industry', 'like', $like)->orWhere('organizations.city', 'like', $like)
                    ->orWhereExists(fn ($c) => $c->select(DB::raw(1))->from('client_users')->whereColumn('client_users.organization_id', 'organizations.id')->whereNull('client_users.deleted_at')
                        ->where(fn ($m) => $m->whereRaw("CONCAT(client_users.first_name, ' ', client_users.last_name) LIKE ?", [$like])
                            ->orWhere('client_users.email', 'like', $like)->orWhere('client_users.phone', 'like', $like))));
            })
            ->orderByRaw('COALESCE(organizations.last_activity_at, organizations.created_at) DESC');
    }

    /** Filters from the address that aren't one of the choices fall back to their defaults */
    private function sanitise(): void
    {
        if ($this->sanitised) {
            return;
        }
        $this->sanitised = true;
        if (! in_array($this->owner, ['mine', 'all', 'none'], true) && ! LeadOwner::activeStaff($this->owner) && ! DB::table('organizations')->where('assigned_to', $this->owner)->exists()) {
            $this->owner = 'mine';
        }
        $this->status = isset(self::STATUS_FILTERS[$this->status]) ? $this->status : '';
        $this->step = isset(self::STEPS[$this->step]) ? $this->step : '';
        $this->source = isset(Sources::LABELS[$this->source]) ? $this->source : '';
        $this->period = isset(LeadNumbers::PERIODS[$this->period]) ? $this->period : 'month';
        $this->search = mb_substr($this->search, 0, 100);
    }

    /** Leads and clients with their owner, main contact and latest cost estimate ($limit rows; null: all of them) */
    private function leads(?int $limit = null): Collection
    {
        $leads = $this->query()->when($limit, fn ($q) => $q->limit($limit))->get();
        $ids = $leads->pluck('id');

        $contacts = DB::table('client_users')->whereIn('organization_id', $ids)->whereNull('deleted_at')
            ->orderByDesc('is_main_contact')->orderBy('created_at')->get(['organization_id', 'first_name', 'last_name', 'email', 'phone'])
            ->groupBy('organization_id')->map->first();
        $copq = DB::table('copq_assessments')->whereIn('organization_id', $ids)->whereNull('deleted_at')->orderByDesc('created_at')
            ->get(['organization_id', 'calculated_leakage', 'copq_currency', 'lead_tier', 'executive_name', 'corporate_email'])
            ->groupBy('organization_id')->map->first();

        return $leads->map(function ($lead) use ($contacts, $copq) {
            $contact = $contacts[$lead->id] ?? null;
            $estimate = $copq[$lead->id] ?? null;
            $lead->contact_name = $contact ? trim($contact->first_name . ' ' . $contact->last_name) : ($estimate->executive_name ?? null);
            $lead->contact_email = $contact->email ?? ($estimate->corporate_email ?? null);
            $lead->contact_phone = $contact->phone ?? null;
            $lead->calculated_leakage = $estimate->calculated_leakage ?? null;
            $lead->copq_currency = $estimate->copq_currency ?? null;
            $lead->lead_tier = $estimate->lead_tier ?? null;

            return $lead;
        });
    }

    public function claimLead($orgId)
    {
        abort_unless(LeadAccess::canEdit(Auth::user()), 403);
        $lead = Organization::query()->whereKey($orgId)->whereIn('status', LeadAccess::STATUSES)->first();
        // Only a lead nobody owns yet ("Assign to me" is offered only for those): an owned lead can't be taken over
        if ($lead) {
            LeadOwner::claim($lead, Auth::user());
            $this->changes++;
        }
    }

    // --- AD-HOC CONSULTATION PIPELINE ---
    // Opens the booking dialog, suggesting 15 minutes from now on the next 5 minutes (Lagos time)
    public function requestConsultation($orgId)
    {
        abort_unless(Auth::user()->can('crm.create'), 403);

        $org = DB::table('organizations')->where('id', $orgId)->first();
        if (! $org) {
            return;
        }

        $suggested = Carbon::now(BookingService::TIMEZONE)->addMinutes(15)->second(0);
        $suggested->addMinutes((5 - $suggested->minute % 5) % 5);

        $this->bookingOrgId = $org->id;
        $this->bookingCompany = $org->name;
        $this->bookingDate = $suggested->format('Y-m-d');
        $this->bookingTime = $suggested->format('H:i');
        $this->resetErrorBag(['bookingDate', 'bookingTime']);
        $this->showBookingModal = true;
    }

    public function closeBookingModal()
    {
        $this->showBookingModal = false;
        $this->reset(['bookingOrgId', 'bookingCompany', 'bookingDate', 'bookingTime']);
    }

    /**
     * Books the free audit as a request in the Consultation hub, where it is approved with the meeting link.
     * The time is entered and stored as Lagos wall-clock time (WAT), the same way as website bookings
     * (BookingService; the app itself runs in UTC), so the hub, My workspace, the website's free times and
     * the client's emails all show the same time.
     */
    public function bookConsultation()
    {
        abort_unless(Auth::user()->can('crm.create'), 403);
        if (! $this->bookingOrgId) {
            return;
        }

        $this->validate([
            'bookingDate' => 'required|date_format:Y-m-d',
            'bookingTime' => 'required|date_format:H:i',
        ]);

        $start = Carbon::createFromFormat('Y-m-d H:i', $this->bookingDate . ' ' . $this->bookingTime, BookingService::TIMEZONE)->second(0);
        if ($start->lt(Carbon::now(BookingService::TIMEZONE)->subMinutes(5))) {
            $this->addError('bookingTime', 'That time has passed. Choose a time that is still ahead (Lagos time).');
            return;
        }

        // The hour must not overlap a free audit that is waiting or approved (a meeting ending at the start is fine)
        $end = $start->copy()->addMinutes(60);
        $clash = DB::table('appointments')->whereIn('status', ['Requested', 'Scheduled'])->whereNull('deleted_at')
            ->where('start_time', '<', $end->format('Y-m-d H:i:s'))
            ->where('end_time', '>', $start->format('Y-m-d H:i:s'))
            ->orderBy('start_time')->first();
        if ($clash) {
            $this->addError('bookingTime', 'That time overlaps another free audit (' . Carbon::parse($clash->start_time)->format('H:i')
                . ' to ' . Carbon::parse($clash->end_time)->format('H:i') . ' WAT). Choose another time.');
            return;
        }

        $appointmentId = (string) Str::uuid();
        DB::table('appointments')->insert([
            'id' => $appointmentId,
            'organization_id' => $this->bookingOrgId,
            'title' => 'Free audit (booked from HQ)',
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
            'status' => 'Requested',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        \App\Support\Leads\LeadTimeline::record($this->bookingOrgId, 'free_audit_booked', "Free audit booked from HQ for {$start->format('D j M, H:i')} (WAT).", [
            'actor' => Auth::user(), 'related_type' => 'appointment', 'related_id' => $appointmentId,
        ]);

        session()->flash('success', "Free audit booked for {$this->bookingCompany} on {$start->format('D j M, H:i')} WAT. Approve it with the meeting link to email the client.");

        // Deep-link to the Consultation hub
        return $this->redirect(route('hq.crm.consult'), navigate: true);
    }

    // --- CSV export (crm.export) ---
    public function exportCsv()
    {
        abort_unless(Auth::user()->can('crm.export'), 403);

        $filename = "JBS_Praxis_leads_" . now()->format('Ymd_His') . ".csv";
        $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
        $columns = ['Organisation', 'Country', 'Industry', 'Main contact', 'Email', 'Phone', 'Status', 'Source', 'Owner', 'Next step', 'Next step by',
            'Cost of poor quality', 'Currency', 'Tier', 'Date added'];
        $leads = $this->leads();

        $callback = function () use ($columns, $leads) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($leads as $lead) {
                fputcsv($file, [
                    $lead->name, $lead->country, $lead->industry, $lead->contact_name, $lead->contact_email ?: $lead->email, $lead->contact_phone ?: $lead->phone,
                    LeadStatus::label($lead->lead_status), Sources::label($lead->source),
                    $lead->owner_first ? $lead->owner_first . ' ' . $lead->owner_last : 'Not assigned',
                    $lead->next_step, $lead->next_step_due,
                    $lead->calculated_leakage, $lead->copq_currency, $lead->lead_tier, $lead->created_at,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // --- Filters, the summary strip and the import (PR 3) ---

    /** Any filter changed: start the list from the top again */
    public function updated(string $name): void
    {
        if (in_array($name, ['owner', 'status', 'step', 'source', 'country', 'search'], true)) {
            $this->limit = 100;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['status', 'step', 'source', 'country', 'search', 'limit']);
    }

    public function showMore(): void
    {
        $this->limit += 100;
    }

    /** The summary strip's links: a status, the overdue follow-ups, or an owner's row in the per-owner view */
    public function filterBy(string $what, string $value): void
    {
        match ($what) {
            'status' => $this->status = $value,
            'step' => $this->step = $value,
            'owner' => $this->owner = $value,
            default => null,
        };
        $this->limit = 100;
    }

    /** The import dialog finished: the list redraws with the new leads */
    #[On('import-finished')]
    public function imported(): void
    {
        $this->limit = 100;
        $this->changes++;
    }

    /** The owner filter's people: whoever owns a lead (Mine, Everyone and No owner come first) */
    private function owners(): Collection
    {
        return DB::table('organizations')->join('users', 'users.id', '=', 'organizations.assigned_to')
            ->whereIn('organizations.status', LeadAccess::STATUSES)->whereNull('organizations.deleted_at')
            ->where('users.id', '!=', Auth::id())
            ->groupBy('users.id', 'users.first_name', 'users.last_name')->orderBy('users.first_name')->orderBy('users.last_name')
            ->get(['users.id', DB::raw("TRIM(CONCAT(users.first_name, ' ', users.last_name)) as name")]);
    }

    /**
     * The strip, the owner and country choices and the follow-ups badge. They don't follow the search or the other
     * filters, so they are read once for this owner, period and view and kept a minute while the page is open (each
     * visit has its own copy; Assign to me and an import count again). Kept as plain arrays.
     */
    private function side(): array
    {
        $me = Auth::user();
        $key = 'leads-list:' . $this->getId() . ':' . $this->changes . ':' . $me->id . ':' . md5($this->owner . '|' . $this->period . '|' . (int) $this->byOwner);
        $side = Cache::remember($key, 60, fn () => [
            'owners' => $this->owners()->map(fn ($o) => (array) $o)->all(),
            'countries' => DB::table('organizations')->whereIn('status', LeadAccess::STATUSES)->whereNull('deleted_at')->whereNotNull('country')->where('country', '!=', '')
                ->distinct()->orderBy('country')->pluck('country')->all(),
            'numbers' => LeadNumbers::summary($this->owner, $this->period, $me),
            'perOwner' => $this->byOwner ? LeadNumbers::byOwner($this->period, $me)->map(fn ($o) => (array) $o)->all() : [],
            'myOverdue' => LeadNumbers::open('mine', $me)->where('organizations.next_step_due', '<', LeadNumbers::today())->count(),
        ]);

        return [
            'owners' => collect($side['owners'])->map(fn ($o) => (object) $o),
            'countries' => collect($side['countries']),
            'numbers' => $side['numbers'],
            'perOwner' => collect($side['perOwner'])->map(fn ($o) => (object) $o),
            'myOverdue' => $side['myOverdue'],
        ];
    }

    public function render()
    {
        $me = Auth::user();
        $query = $this->query();
        $total = (clone $query)->count();
        $side = $this->side();
        $owners = $side['owners'];
        $scope = $this->owner;

        return view('livewire.hq.intelligence.leads-register', [
            'leads' => $this->leads($this->limit),
            'total' => $total,
            'canSetDefault' => LeadOwner::canSetDefault($me),
            'canImport' => (new LeadImportSchema())->canImport($me),
            'owners' => $owners,
            'ownerName' => match ($scope) { 'mine' => 'you', 'all' => 'everyone', 'none' => 'no one', default => $owners->firstWhere('id', $scope)?->name ?? 'this person' },
            'countries' => $side['countries'],
            'numbers' => $side['numbers'],
            'perOwner' => $side['perOwner'],
            'filtered' => $this->status !== '' || $this->step !== '' || $this->source !== '' || $this->country !== '' || trim($this->search) !== '',
            'myOverdue' => $side['myOverdue'],
        ]);
    }
}
