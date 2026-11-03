<?php

namespace App\Livewire\Hq\Leads;

use App\Models\ClientUser;
use App\Support\Leads\Contacts;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadEmails;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A lead's Emails tab (docs/LEADS.md section 9, owner's decision 12), inside LeadPage: HQ Mail to and from the lead's
 * contacts, matched by address both ways (LeadEmails), from the mailboxes this person may read (their own and the
 * shared ones they are on). *Email them* opens HQ Mail's composer here, with the contact filled in. Opening the tab
 * also files the lead's past mail on its timeline (a contact added since leads:file-emails read it).
 */
class EmailsTab extends Component
{
    #[Locked]
    public string $leadId;

    #[Locked]
    public bool $canUseMail = false;

    #[Locked]
    public bool $canSend = false;

    public ?string $notice = null;

    public function mount(string $leadId): void
    {
        $user = Auth::user();
        abort_unless(LeadAccess::canView($user), 403);
        $lead = LeadAccess::find($leadId);
        abort_unless($lead, 404);
        $this->leadId = $lead->id;
        $this->canUseMail = MailAccess::canUse($user);
        $this->canSend = $this->canUseMail && MailAccess::accounts($user, 'send')->isNotEmpty();
        if (LeadEmails::fileFor($lead->id) > 0) {
            $this->dispatch('lead-changed');
        }
    }

    /** The composer, with this contact in To (from the first mailbox the person sends from; they can change it there) */
    public function emailThem(string $contactId): void
    {
        abort_unless($this->canSend && MailAccess::canUse(Auth::user()), 403);
        $c = ClientUser::where('organization_id', $this->leadId)->whereNotNull('email')->where('email', '!=', '')->find($contactId);
        abort_unless($c, 404);
        $name = str_replace(['"', '<', '>', ',', ';'], '', trim($c->first_name . ' ' . $c->last_name));
        $this->notice = null;
        // From the browser, as the Mail page does: a named `to:` argument of dispatch() would be read as a target component
        $this->js('Livewire.dispatch("mail-compose", ' . Js::from(['mode' => 'new', 'to' => $name !== '' ? "\"{$name}\" <{$c->email}>" : $c->email]) . ')');
    }

    /** The composer queued or sent a message: say where it will show */
    #[On('mail-sent')]
    public function sent(): void
    {
        $this->notice = 'Sent. It shows here once HQ Mail has copied it from Sent, within a minute or two.';
    }

    public function render()
    {
        $user = Auth::user();
        $contacts = Contacts::of($this->leadId);

        return view('livewire.hq.leads.emails-tab', [
            'lead' => LeadAccess::find($this->leadId),
            'emails' => $this->canUseMail ? LeadEmails::for($user, $this->leadId) : collect(),
            'contacts' => $contacts,
            'reachable' => $contacts->filter(fn ($c) => filled($c->email))->values(),
            'limit' => LeadEmails::LIMIT,
        ]);
    }
}
