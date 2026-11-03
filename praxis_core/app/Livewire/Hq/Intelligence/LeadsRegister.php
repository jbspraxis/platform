<?php

namespace App\Livewire\Hq\Intelligence;

use App\Models\Organization;
use App\Services\Site\BookingService;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\LeadStatus;
use App\Support\Leads\Sources;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * HQ › Leads (docs/LEADS.md section 7): every lead and client for everyone with crm.view (owner's decision 8). Each row
 * opens the lead's page (hq.crm.lead); "Assign to me" takes a lead nobody owns; "Book free audit" asks Free audits for
 * one; crm.export downloads the list. Filters, "Mine", the summary strip and the Excel import are PR 3's.
 */
#[Layout('components.layouts.hq')]
class LeadsRegister extends Component
{
    // Book a free audit from HQ: the date and time are entered in Lagos time (WAT), like website bookings
    public $showBookingModal = false;
    #[Locked]
    public $bookingOrgId = null;
    #[Locked]
    public $bookingCompany = '';
    public $bookingDate = '', $bookingTime = '';

    // Field names in validation messages (sentence case, docs/BRAND.md section 12)
    protected $validationAttributes = ['bookingDate' => 'date', 'bookingTime' => 'time'];

    /** Every lead and client, newest activity first, with its owner, main contact and latest cost estimate */
    private function leads(): Collection
    {
        $leads = DB::table('organizations')
            ->select('organizations.*', 'owner.first_name as owner_first', 'owner.last_name as owner_last', 'owner.id as owner_id', 'owner.avatar_path as owner_avatar')
            ->leftJoin('users as owner', 'organizations.assigned_to', '=', 'owner.id')
            ->whereIn('organizations.status', LeadAccess::STATUSES)->whereNull('organizations.deleted_at')
            ->orderByRaw('COALESCE(organizations.last_activity_at, organizations.created_at) DESC')
            ->get();
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

    public function render()
    {
        return view('livewire.hq.intelligence.leads-register', [
            'leads' => $this->leads(),
            'canSetDefault' => LeadOwner::canSetDefault(Auth::user()),
        ]);
    }
}
