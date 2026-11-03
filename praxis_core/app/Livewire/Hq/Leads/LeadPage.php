<?php

namespace App\Livewire\Hq\Leads;

use App\Models\ClientUser;
use App\Models\Organization;
use App\Models\User;
use App\Support\Leads\Contacts;
use App\Support\Leads\Copq;
use App\Support\Leads\Countries;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\Leads;
use App\Support\Leads\LeadStatus;
use App\Support\Leads\LeadTimeline;
use App\Support\Leads\NextStep;
use App\Support\Leads\Sources;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One lead (Lead 360, owner's decision 9, docs/LEADS.md section 7): a page with tabs and a link back to all leads.
 *
 *   overview       details (owner and source too), contacts, status (Lost with a reason, Mark as client), next step,
 *                  cost of poor quality (Calculate when missing; merging duplicate assessments), Log activity
 *   meetings       PR 2 (partial tabs/meetings)
 *   emails         PR 2 (partial tabs/emails)
 *   opportunities  the lead's opportunities, simply; PR 2 builds it out (partial tabs/opportunities)
 *   activity       the timeline (LeadTimeline)
 *
 * crm.view opens it (everyone with Leads access sees every lead: decision 8); crm.edit changes it; handing it to
 * someone else follows LeadOwner::canAssign. Every change goes through App\Support\Leads (timeline and audit log).
 */
#[Layout('components.layouts.hq')]
class LeadPage extends Component
{
    public const TABS = ['overview', 'meetings', 'emails', 'opportunities', 'activity'];

    #[Locked]
    public string $leadId;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    #[Url(as: 'type', except: '')]
    public string $activityFilter = '';

    #[Locked]
    public bool $canEdit = false;

    // Details
    public bool $editingDetails = false;

    public array $details = [];

    // Owner (x-hq.picker)
    public string $ownerSearch = '';

    public bool $ownerPicking = false;

    // Status
    public string $status = '';

    public string $lostReason = '';

    public string $lostNote = '';

    public bool $confirmingClient = false;

    // Next step
    public bool $editingNextStep = false;

    public string $nextStep = '';

    public string $nextStepDue = '';

    // Contacts: the one being edited ('new' for a new one)
    public ?string $contactEditing = null;

    public array $contact = [];

    // Cost of poor quality
    public bool $calculating = false;

    public array $copq = [];

    // Merge two assessments (from the old register): the records are set on the server only
    public bool $showDiffModal = false;

    #[Locked]
    public $diffOld = null;

    #[Locked]
    public $diffNew = null;

    public array $mergeSelections = [];

    // The only copq_assessments columns a merge may write, with their labels
    private const MERGE_FIELDS = [
        'company_name' => 'Company name',
        'executive_name' => 'Contact name',
        'copq_industry' => 'Industry',
        'primary_tool' => 'Systems in use',
        'bottleneck' => 'Main bottleneck',
        'copq_turnover' => 'Annual turnover',
        'headcount' => 'Headcount',
        'calculated_leakage' => 'Cost of poor quality (a year)',
    ];

    #[Locked]
    public array $diffFields = self::MERGE_FIELDS;

    // Log activity
    public string $activityType = 'call';

    public string $activityText = '';

    public string $activityContact = '';

    public function mount(Organization $organization): void
    {
        abort_unless(LeadAccess::canView(Auth::user()), 403);
        $lead = LeadAccess::find($organization->id);
        abort_unless($lead, 404);
        $this->leadId = $lead->id;
        $this->canEdit = LeadAccess::canEdit(Auth::user());
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }
        $this->syncStatus($lead);
    }

    private function lead(): Organization
    {
        $lead = LeadAccess::find($this->leadId);
        abort_unless($lead, 404);

        return $lead;
    }

    private function syncStatus(Organization $lead): void
    {
        $this->status = (string) ($lead->lead_status ?: 'new');
        $this->lostReason = (string) $lead->lost_reason;
        $this->lostNote = (string) $lead->lost_note;
    }

    private function authorizeEdit(): void
    {
        abort_unless(LeadAccess::canEdit(Auth::user()), 403);
        $this->resetErrorBag();
    }

    public function show(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    // --- Details ---

    public function editDetails(): void
    {
        $this->authorizeEdit();
        $lead = $this->lead();
        $this->details = [];
        foreach (Leads::DETAILS as $column => $key) {
            $this->details[$key] = (string) $lead->{$column};
        }
        $this->details['preferred_language'] = strtoupper($this->details['preferred_language'] ?: 'EN') === 'FR' ? 'FR' : 'EN';
        $this->details['source'] = $this->details['source'] ?: 'manual';
        $this->editingDetails = true;
    }

    public function cancelDetails(): void
    {
        $this->editingDetails = false;
        $this->resetErrorBag();
    }

    public function saveDetails(): void
    {
        $this->authorizeEdit();
        try {
            $changed = Leads::updateDetails($this->lead(), $this->details, Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError("details.{$key}", $messages[0]);
            }

            return;
        }
        $this->editingDetails = false;
        if ($changed) {
            session()->flash('success', 'Details saved.');
        }
    }

    // --- Owner ---

    public function startReassign(): void
    {
        abort_unless(LeadOwner::canAssign(Auth::user(), $this->lead()), 403);
        $this->ownerPicking = true;
        $this->ownerSearch = '';
    }

    public function cancelReassign(): void
    {
        $this->ownerPicking = false;
        $this->ownerSearch = '';
    }

    public function assignLead(string $userId): void
    {
        $lead = $this->lead();
        abort_unless(LeadOwner::canAssign(Auth::user(), $lead), 403);
        try {
            if (LeadOwner::assign($lead, $userId, Auth::user())) {
                session()->flash('success', $userId === Auth::id() ? 'This lead is now yours.' : 'Owner changed. They have a notification.');
            }
        } catch (LeadRuleBroken $e) {
            $this->addError('owner', $e->getMessage());
        }
        $this->cancelReassign();
    }

    public function claim(): void
    {
        abort_unless(LeadAccess::canEdit(Auth::user()), 403);
        if (LeadOwner::claim($this->lead(), Auth::user())) {
            session()->flash('success', 'This lead is now yours.');
        } else {
            session()->flash('warning', 'Someone took this lead a moment ago.');
        }
    }

    // --- Status ---

    public function saveStatus(): void
    {
        $this->authorizeEdit();
        $lead = $this->lead();
        if ($this->status === 'client') {
            $this->confirmingClient = true;

            return;
        }
        try {
            $changed = LeadStatus::change($lead, $this->status, Auth::user(), $this->lostReason ?: null, $this->lostNote);
        } catch (LeadRuleBroken $e) {
            $this->addError($e->field, $e->getMessage());

            return;
        }
        $this->syncStatus($lead->refresh());
        if ($changed) {
            session()->flash('success', 'Status saved: ' . LeadStatus::label($lead->lead_status) . '.');
        }
    }

    public function askMarkClient(): void
    {
        $this->authorizeEdit();
        $this->confirmingClient = true;
    }

    public function cancelMarkClient(): void
    {
        $this->confirmingClient = false;
        $this->syncStatus($this->lead());
    }

    public function markClient(): void
    {
        $this->authorizeEdit();
        $lead = $this->lead();
        LeadStatus::markClient($lead, Auth::user());
        $this->confirmingClient = false;
        $this->syncStatus($lead->refresh());
        session()->flash('success', "{$lead->name} is now a client.");
    }

    // --- Next step ---

    public function editNextStep(): void
    {
        $this->authorizeEdit();
        $lead = $this->lead();
        $suggestion = NextStep::suggestion();
        $this->nextStep = (string) $lead->next_step;
        $this->nextStepDue = $lead->next_step_due?->format('Y-m-d') ?? $suggestion['due'];
        $this->editingNextStep = true;
    }

    public function cancelNextStep(): void
    {
        $this->editingNextStep = false;
        $this->resetErrorBag();
    }

    public function saveNextStep(): void
    {
        $this->authorizeEdit();
        $this->validate([
            'nextStep' => ['nullable', 'string', 'max:255'],
            'nextStepDue' => ['nullable', 'date_format:Y-m-d', Rule::requiredIf(trim($this->nextStep) !== '')],
        ], [
            'nextStep.max' => 'Keep the next step under 255 characters.',
            'nextStepDue.required' => 'Choose by when.',
            'nextStepDue.date_format' => 'Choose a date.',
        ]);
        try {
            NextStep::set($this->lead(), $this->nextStep, $this->nextStepDue, Auth::user());
        } catch (LeadRuleBroken $e) {
            $this->addError('nextStepDue', $e->getMessage());

            return;
        }
        $this->editingNextStep = false;
    }

    // --- Contacts ---

    public function newContact(): void
    {
        $this->authorizeEdit();
        $this->contact = ['name' => '', 'job_title' => '', 'email' => '', 'phone' => '', 'deal_role' => '', 'main' => false];
        $this->contactEditing = 'new';
    }

    public function editContact(string $id): void
    {
        $this->authorizeEdit();
        $c = $this->contactOf($id);
        $this->contact = ['name' => $c->full_name, 'job_title' => (string) $c->job_title, 'email' => (string) $c->email, 'phone' => (string) $c->phone,
            'deal_role' => (string) $c->deal_role, 'main' => (bool) $c->is_main_contact];
        $this->contactEditing = $c->id;
    }

    public function cancelContact(): void
    {
        $this->contactEditing = null;
        $this->resetErrorBag();
    }

    public function saveContact(): void
    {
        $this->authorizeEdit();
        $portal = $this->contactEditing !== 'new' && $this->contactEditing && Contacts::isPortalAccount($this->contactOf($this->contactEditing));
        $this->validate($portal ? array_intersect_key(Contacts::rules('contact.'), array_flip(['contact.job_title', 'contact.deal_role'])) : Contacts::rules('contact.'),
            Contacts::messages('contact.'));
        try {
            if ($this->contactEditing === 'new') {
                Contacts::add($this->lead(), $this->contact, Auth::user());
            } elseif ($this->contactEditing) {
                $c = $this->contactOf($this->contactEditing);
                if ($portal) {
                    $this->contact = ['name' => $c->full_name, 'email' => $c->email, 'phone' => $c->phone] + $this->contact;
                }
                Contacts::update($c, $this->contact, Auth::user());
            }
        } catch (LeadRuleBroken $e) {
            $this->addError('contact.' . $e->field, $e->getMessage());

            return;
        }
        $this->contactEditing = null;
    }

    public function makeMainContact(string $id): void
    {
        $this->authorizeEdit();
        Contacts::makeMain($this->contactOf($id), Auth::user());
    }

    public function removeContact(string $id): void
    {
        $this->authorizeEdit();
        try {
            Contacts::remove($this->contactOf($id), Auth::user());
        } catch (LeadRuleBroken $e) {
            session()->flash('warning', $e->getMessage());
        }
        if ($this->contactEditing === $id) {
            $this->contactEditing = null;
        }
    }

    private function contactOf(string $id): ClientUser
    {
        return ClientUser::where('organization_id', $this->leadId)->findOrFail($id);
    }

    // --- Cost of poor quality ---

    public function startCalculate(): void
    {
        $this->authorizeEdit();
        $this->copq = ['tool' => '', 'bottleneck' => '', 'currency' => 'NGN'] + array_fill_keys(array_keys(Copq::FIGURES), '');
        $this->calculating = true;
    }

    public function cancelCalculate(): void
    {
        $this->calculating = false;
        $this->resetErrorBag();
    }

    public function saveCopq(): void
    {
        $this->authorizeEdit();
        $lead = $this->lead();
        if (! $lead->industry) {
            $this->addError('copq.industry', 'Add the lead’s industry in its details first: the estimate depends on it.');

            return;
        }
        $this->validate(Copq::rules('copq.'), Copq::messages('copq.'));
        $main = $lead->contacts()->first();
        $row = Copq::store($lead, $this->copq, Auth::user(), $main?->full_name, $main?->email);
        $this->calculating = false;
        session()->flash('success', 'Cost of poor quality estimated: ' . $row->copq_currency . ' ' . number_format($row->calculated_leakage) . ' a year.');
    }

    // --- Merge duplicate assessments (kept from the old register) ---

    public function openDiffViewer(): void
    {
        $this->authorizeEdit();
        $records = DB::table('copq_assessments')->where('organization_id', $this->leadId)->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')->limit(2)->get();
        if ($records->count() < 2) {
            DB::table('organizations')->where('id', $this->leadId)->update(['is_duplicate_suspect' => 0]);

            return;
        }
        $this->diffNew = $records[0];
        $this->diffOld = $records[1];
        foreach (array_keys(self::MERGE_FIELDS) as $field) {
            $this->mergeSelections[$field] = 'new';
        }
        $this->showDiffModal = true;
    }

    public function closeDiffViewer(): void
    {
        $this->showDiffModal = false;
        $this->diffOld = null;
        $this->diffNew = null;
    }

    public function executeMerge(): void
    {
        $this->authorizeEdit();
        if (! $this->diffOld || ! $this->diffNew) {
            return;
        }
        $old = (object) $this->diffOld;
        $new = (object) $this->diffNew;
        $finalData = [];
        // Only the whitelisted columns, whatever the component state says
        foreach (array_keys(self::MERGE_FIELDS) as $field) {
            $finalData[$field] = ($this->mergeSelections[$field] ?? 'new') === 'old' ? $old->$field : $new->$field;
        }
        $finalData['updated_at'] = now();
        DB::table('copq_assessments')->where('id', $new->id)->where('organization_id', $this->leadId)->update($finalData);
        DB::table('organizations')->where('id', $this->leadId)->update(['is_duplicate_suspect' => 0, 'updated_at' => now()]);

        $kept = array_keys(array_filter(array_intersect_key($this->mergeSelections, self::MERGE_FIELDS), fn ($pick) => $pick === 'old'));
        DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => 'LEAD_RECORDS_MERGED',
            'description' => 'Merged two assessments for ' . ($finalData['company_name'] ?: 'a lead') . '.',
            'target_table' => 'copq_assessments',
            'target_id' => $new->id,
            'old_values' => json_encode(array_intersect_key((array) $new, self::MERGE_FIELDS), JSON_UNESCAPED_UNICODE),
            'new_values' => json_encode(['merged_from' => $old->id, 'kept_from_older_record' => $kept]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
        LeadTimeline::record($this->leadId, 'records_merged', 'Two cost of poor quality assessments merged into one.', [
            'actor' => Auth::user(), 'related_type' => 'copq_assessment', 'related_id' => $new->id, 'payload' => ['merged_from' => $old->id, 'kept_from_older_record' => $kept],
        ]);
        $this->closeDiffViewer();
        session()->flash('success', 'Assessments merged.');
    }

    // --- Log activity ---

    public function logActivity(): void
    {
        $this->authorizeEdit();
        $this->activityText = trim($this->activityText);
        $this->validate([
            'activityType' => ['required', Rule::in(array_keys(LeadTimeline::LOGGABLE))],
            'activityText' => ['required', 'string', 'max:5000'],
            'activityContact' => ['nullable', Rule::exists('client_users', 'id')->where('organization_id', $this->leadId)->whereNull('deleted_at')],
        ], [
            'activityType.required' => 'Choose what happened.',
            'activityType.in' => 'Choose what happened.',
            'activityText.required' => 'Say in a few words what was said or agreed.',
            'activityText.max' => 'Keep it under 5,000 characters.',
            'activityContact.exists' => 'Choose one of this lead’s contacts.',
        ]);
        $contact = $this->activityContact ? ClientUser::find($this->activityContact) : null;
        $label = LeadTimeline::LOGGABLE[$this->activityType];
        $first = mb_substr(preg_replace('/\s+/u', ' ', $this->activityText), 0, 160);
        LeadTimeline::record($this->leadId, $this->activityType, $label . ($contact ? " with {$contact->full_name}" : '') . ": {$first}", [
            'actor' => Auth::user(), 'body' => $this->activityText, 'client_user_id' => $contact?->id,
        ]);
        $this->reset(['activityText', 'activityContact']);
        session()->flash('success', "{$label} logged.");
    }

    /** PR 2's tabs tell the page something changed (a meeting, an email, an opportunity): redraw the counts and the timeline */
    #[On('lead-changed')]
    public function leadChanged(): void {}

    public function render()
    {
        $lead = $this->lead()->load('owner');
        $user = Auth::user();
        $contacts = Contacts::of($lead->id);
        $opportunities = $lead->opportunities()->with('lead:id,first_name,last_name')->get(['id', 'organization_id', 'title', 'stage', 'lead_user_id', 'created_at', 'closed_at']);
        $canAssign = LeadOwner::canAssign($user, $lead);
        $owner = $lead->owner ? LeadOwner::option($lead->owner) : null;

        return view('livewire.hq.leads.page', [
            'lead' => $lead,
            'contacts' => $contacts,
            'opportunities' => $opportunities,
            'copqLatest' => Copq::latest($lead->id),
            'recent' => $this->tab === 'overview' ? LeadTimeline::for($lead->id, null, 5) : collect(),
            'timeline' => $this->tab === 'activity' ? LeadTimeline::for($lead->id, $this->activityFilter !== '' ? [$this->activityFilter] : null, 200) : collect(),
            'timelineTypes' => $this->tab === 'activity' ? DB::table('lead_timeline')->where('organization_id', $lead->id)->whereNull('deleted_at')->distinct()->pluck('type')->all() : [],
            'canAssign' => $canAssign,
            'ownerSelected' => $this->ownerPicking ? null : $owner,
            'ownerOptions' => $canAssign && ($this->ownerPicking || ! $owner) ? LeadOwner::options($this->ownerSearch, $lead->assigned_to) : collect(),
            'canOpenOpportunity' => (bool) $user?->can('opportunities.create'),
            'canSeeOpportunity' => fn ($o) => OpportunityAccess::canView($user, $o),
            'industries' => $this->editingDetails ? Leads::industries() : [],
            'countries' => Countries::NAMES,
            'sources' => Sources::LABELS,
            'choices' => $this->calculating ? Copq::choices() : ['systems' => [], 'bottlenecks' => [], 'currencies' => []],
            'overdue' => NextStep::isOverdue($lead),
        ])->title($lead->name . ' · Leads');
    }
}
