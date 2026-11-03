<?php

namespace App\Livewire\Hq\Leads;

use App\Support\Leads\LeadOwner;
use App\Support\Leads\LeadRuleBroken;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Leads › Lead settings: who website leads go to (owner's decision 7, LeadOwner::DEFAULT_SETTING). Offered on the
 * Leads list to the owner and partners (opportunities.approve); the person chosen gets a bell for each new website lead.
 */
class LeadSettings extends Component
{
    public bool $open = false;

    public string $search = '';

    public bool $picking = false;

    public function mount(): void
    {
        abort_unless(LeadOwner::canSetDefault(Auth::user()), 403);
    }

    public function show(): void
    {
        abort_unless(LeadOwner::canSetDefault(Auth::user()), 403);
        $this->open = true;
        $this->picking = ! LeadOwner::defaultOwnerId();
        $this->search = '';
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    public function change(): void
    {
        $this->picking = true;
        $this->search = '';
    }

    public function choose(string $userId): void
    {
        try {
            LeadOwner::setDefault($userId, Auth::user());
        } catch (LeadRuleBroken $e) {
            $this->addError('defaultOwner', $e->getMessage());

            return;
        }
        $this->picking = false;
        session()->flash('success', 'Website leads now go to ' . LeadOwner::name(LeadOwner::activeStaff($userId)) . '.');
        $this->redirectRoute('hq.crm.leads', navigate: true);
    }

    public function clear(): void
    {
        LeadOwner::setDefault(null, Auth::user());
        session()->flash('success', 'Website leads now go to no one in particular: anyone can take them.');
        $this->redirectRoute('hq.crm.leads', navigate: true);
    }

    public function render()
    {
        $ownerId = LeadOwner::defaultOwnerId();
        $owner = LeadOwner::option(LeadOwner::activeStaff($ownerId));

        return view('livewire.hq.leads.lead-settings', [
            'selected' => $this->picking ? null : $owner,
            'options' => $this->open && $this->picking ? LeadOwner::options($this->search, $ownerId) : collect(),
            'hasOwner' => (bool) $owner,
        ]);
    }
}
