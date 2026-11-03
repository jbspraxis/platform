<?php

namespace App\Livewire\Hq\Leads;

use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadNumbers;
use App\Support\Leads\LeadOwner;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\NextStep;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Leads › My follow-ups (owner's decision 17, docs/LEADS.md section 9): every open lead's next step, overdue ones first
 * and in red, then today, the next 7 days and later; open leads with no next step are flagged at the end. The same
 * owner filter as the list (Mine by default). Someone with crm.edit changes a next step here (NextStep::set). The
 * morning bell (leads:morning-bell) opens this page.
 */
#[Layout('components.layouts.hq')]
class FollowUps extends Component
{
    #[Url(except: 'mine')]
    public string $owner = 'mine';

    /** The lead whose next step is being changed, and the two fields */
    #[Locked]
    public ?string $editing = null;

    public string $stepWhat = '';

    public string $stepDue = '';

    public function edit(string $orgId): void
    {
        abort_unless(LeadAccess::canEdit(Auth::user()), 403);
        $lead = LeadAccess::find($orgId);
        if (! $lead) {
            return;
        }
        $this->resetErrorBag();
        $this->editing = $lead->id;
        $suggested = NextStep::suggestion();
        $this->stepWhat = (string) ($lead->next_step ?? '');
        $this->stepDue = $lead->next_step_due?->format('Y-m-d') ?? $suggested['due'];
        if ($lead->next_step_due && $lead->next_step_due->format('Y-m-d') < LeadNumbers::today()) {
            $this->stepDue = $suggested['due']; // an overdue step usually moves on: start from the next working day
        }
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'stepWhat', 'stepDue']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $by = Auth::user();
        abort_unless(LeadAccess::canEdit($by), 403);
        $lead = LeadAccess::find($this->editing);
        if (! $lead) {
            $this->cancel();

            return;
        }
        $this->validate(['stepWhat' => NextStep::rules()['next_step'], 'stepDue' => ['required', 'date_format:Y-m-d']], [
            'stepWhat.max' => 'Keep the next step under 255 characters.',
            'stepDue.required' => 'Choose by when.', 'stepDue.date_format' => 'Choose a date.',
        ]);
        if (trim($this->stepWhat) === '') {
            $this->addError('stepWhat', 'Say what happens next, e.g. Call Ada about the proposal.');

            return;
        }
        try {
            NextStep::set($lead, $this->stepWhat, $this->stepDue, $by);
        } catch (LeadRuleBroken $e) {
            $this->addError('stepDue', $e->getMessage());

            return;
        }
        session()->flash('success', "Next step saved for {$lead->name}.");
        $this->cancel();
    }

    public function render()
    {
        $me = Auth::user();
        abort_unless(LeadAccess::canView($me), 403);
        if (! in_array($this->owner, ['mine', 'all', 'none'], true) && ! LeadOwner::activeStaff($this->owner)) {
            $this->owner = 'mine';
        }
        $owners = DB::table('organizations')->join('users', 'users.id', '=', 'organizations.assigned_to')
            ->whereIn('organizations.status', LeadAccess::STATUSES)->whereNull('organizations.deleted_at')->where('users.id', '!=', $me->id)
            ->groupBy('users.id', 'users.first_name', 'users.last_name')->orderBy('users.first_name')
            ->get(['users.id', DB::raw("TRIM(CONCAT(users.first_name, ' ', users.last_name)) as name")]);
        $who = match ($this->owner) { 'mine' => null, 'all' => 'Everyone’s', 'none' => 'Unowned leads’', default => ($owners->firstWhere('id', $this->owner)?->name ?? 'Their') . '’s' };

        return view('livewire.hq.leads.follow-ups', [
            'groups' => LeadNumbers::followUps($this->owner, $me),
            'owners' => $owners,
            'title' => $who ? "{$who} follow-ups" : 'My follow-ups',
            'canEdit' => LeadAccess::canEdit($me),
            'today' => LeadNumbers::today(),
        ])->title($who ? "{$who} follow-ups" : 'My follow-ups');
    }
}
