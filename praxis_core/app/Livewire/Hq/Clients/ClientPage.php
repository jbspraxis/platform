<?php

namespace App\Livewire\Hq\Clients;

use App\Models\ClientUser;
use App\Models\Organization;
use App\Support\Clients\ClientAccess;
use App\Support\Clients\Clients;
use App\Support\Clients\PortalInvites;
use App\Support\Leads\Contacts;
use App\Support\Leads\Countries;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\Leads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One client (docs/CLIENTS.md): the details its invoices print ("Billed to"), its contacts and their Client Portal
 * accounts, its projects and, for Finance, what it still owes.
 *
 *   invoice details   ClientAccess::canEdit; saved by Leads::updateDetails (same rules, timeline and audit as the lead page)
 *   contacts          adding one needs canEdit; inviting one to the Client Portal needs canInvite (PortalInvites)
 *
 * Active and past clients both open here; an active client's lead page (Lead 360) keeps its meetings, emails and history.
 */
#[Layout('components.layouts.hq')]
class ClientPage extends Component
{
    /** The details form's fields, besides the industry when it is missing (Leads::DETAILS keys) */
    public const FIELDS = ['name', 'address', 'registration_number', 'tax_number', 'email', 'phone', 'country', 'city', 'preferred_language'];

    #[Locked]
    public string $clientId;

    #[Locked]
    public bool $canEdit = false;

    #[Locked]
    public bool $canInvite = false;

    #[Locked]
    public bool $canResend = false;

    #[Locked]
    public bool $canSeeMoney = false;

    // Invoice details
    public bool $editing = false;

    public array $details = [];

    /** The client has no industry yet: the form asks for it, since saving needs one */
    #[Locked]
    public bool $askIndustry = false;

    // Client Portal: the contact being invited, and the role they get
    public ?string $inviting = null;

    public string $inviteRole = '';

    // A new person
    public bool $adding = false;

    public array $person = [];

    public function mount(Organization $organization): void
    {
        $user = Auth::user();
        abort_unless(ClientAccess::canView($user), 403);
        $client = Clients::find($organization->id);
        abort_unless($client, 404);
        $this->clientId = $client->id;
        $this->canEdit = ClientAccess::canEdit($user);
        $this->canInvite = ClientAccess::canInvite($user);
        $this->canResend = ClientAccess::canResend($user);
        $this->canSeeMoney = ClientAccess::canSeeMoney($user);
    }

    private function client(): Organization
    {
        $client = Clients::find($this->clientId);
        abort_unless($client, 404);

        return $client;
    }

    private function contactOf(?string $id): ClientUser
    {
        return ClientUser::where('organization_id', $this->clientId)->findOrFail((string) $id);
    }

    private function defaultRole(): string
    {
        return (string) (DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id') ?? '');
    }

    // --- Invoice details ---

    public function editDetails(): void
    {
        abort_unless(ClientAccess::canEdit(Auth::user()), 403);
        $client = $this->client();
        $this->details = [];
        foreach (Leads::DETAILS as $column => $key) {
            $this->details[$key] = (string) $client->{$column};
        }
        $this->details['preferred_language'] = strtoupper($this->details['preferred_language'] ?: 'EN') === 'FR' ? 'FR' : 'EN';
        // Clients from before the Leads revamp may have no source: saving needs one, as on the lead page
        $this->details['source'] = $this->details['source'] ?: 'manual';
        $this->askIndustry = trim($this->details['industry']) === '';
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function cancelDetails(): void
    {
        $this->editing = false;
        $this->resetErrorBag();
    }

    public function saveDetails(): void
    {
        abort_unless(ClientAccess::canEdit(Auth::user()), 403);
        $this->resetErrorBag();
        try {
            $changed = Leads::updateDetails($this->client(), $this->details, Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError("details.{$key}", $messages[0]);
            }

            return;
        }
        $this->editing = false;
        session()->flash('success', $changed ? __('clients.details.saved') : __('clients.details.unchanged'));
    }

    // --- Client Portal ---

    public function startInvite(string $id): void
    {
        abort_unless(ClientAccess::canInvite(Auth::user()), 403);
        $contact = $this->contactOf($id);
        $this->resetErrorBag();
        $this->adding = false;
        $this->inviting = $contact->id;
        $this->inviteRole = $this->defaultRole();
    }

    public function cancelInvite(): void
    {
        $this->inviting = null;
        $this->resetErrorBag();
    }

    public function invite(): void
    {
        abort_unless(ClientAccess::canInvite(Auth::user()), 403);
        $contact = $this->contactOf($this->inviting);
        try {
            PortalInvites::invite($contact, Auth::user(), (int) $this->inviteRole ?: null);
        } catch (LeadRuleBroken $e) {
            if ($e->field === 'mail') {
                // Invited, but the email didn't go: the contact now shows "Send invitation again"
                $this->inviting = null;
                session()->flash('warning', $e->getMessage());
            } else {
                $this->addError('invite', $e->getMessage());
            }

            return;
        }
        $this->inviting = null;
        session()->flash('success', __('clients.portal.invited', ['name' => $contact->full_name, 'email' => $contact->email]));
    }

    public function resend(string $id): void
    {
        abort_unless(ClientAccess::canResend(Auth::user()), 403);
        $contact = $this->contactOf($id);
        try {
            PortalInvites::resend($contact, Auth::user());
        } catch (LeadRuleBroken $e) {
            session()->flash('warning', $e->getMessage());

            return;
        }
        session()->flash('success', __('clients.portal.resent', ['name' => $contact->full_name, 'email' => $contact->email]));
    }

    // --- A new person ---

    public function newPerson(): void
    {
        abort_unless(ClientAccess::canEdit(Auth::user()), 403);
        $this->resetErrorBag();
        $this->inviting = null;
        $this->person = ['name' => '', 'job_title' => '', 'email' => '', 'phone' => '', 'invite' => $this->canInvite];
        $this->inviteRole = $this->defaultRole();
        $this->adding = true;
    }

    public function cancelPerson(): void
    {
        $this->adding = false;
        $this->resetErrorBag();
    }

    public function addPerson(): void
    {
        $user = Auth::user();
        abort_unless(ClientAccess::canEdit($user), 403);
        $invite = ! empty($this->person['invite']) && ClientAccess::canInvite($user);
        $rules = Contacts::rules('person.');
        if ($invite) {
            $rules['person.email'] = array_merge(['required'], array_diff($rules['person.email'], ['nullable']));
        }
        $this->validate($rules, Contacts::messages('person.') + ['person.email.required' => __('clients.portal.needs_email')]);
        try {
            $contact = Contacts::add($this->client(), $this->person, $user);
        } catch (LeadRuleBroken $e) {
            $this->addError('person.' . $e->field, $e->getMessage());

            return;
        }
        $this->adding = false;
        if (! $invite) {
            session()->flash('success', __('clients.people.added', ['name' => $contact->full_name]));

            return;
        }
        try {
            PortalInvites::invite($contact, $user, (int) $this->inviteRole ?: null);
            session()->flash('success', __('clients.portal.added_and_invited', ['name' => $contact->full_name, 'email' => $contact->email]));
        } catch (LeadRuleBroken $e) {
            session()->flash('warning', __('clients.people.added', ['name' => $contact->full_name]) . ' ' . $e->getMessage());
        }
    }

    public function render()
    {
        $client = $this->client();
        $user = Auth::user();
        $contacts = Contacts::of($client->id);

        return view('livewire.hq.clients.page', [
            'client' => $client,
            'contacts' => $contacts,
            'states' => $contacts->mapWithKeys(fn (ClientUser $c) => [$c->id => PortalInvites::state($c)])->all(),
            'roles' => $this->canInvite || $this->adding ? PortalInvites::roles() : collect(),
            'nextIsPrimary' => PortalInvites::nextIsPrimary($client->id),
            'missing' => Clients::missing($client),
            'projects' => DB::table('projects')->where('organization_id', $client->id)->whereNull('deleted_at')
                ->orderByRaw("CASE WHEN status IN ('Completed', 'Cancelled') THEN 1 ELSE 0 END")->orderByDesc('created_at')
                ->get(['id', 'title', 'status', 'currency', 'total_budget']),
            'invoices' => $this->canSeeMoney ? Clients::unpaidInvoices([$client->id]) : collect(),
            // The lead page opens active clients only (LeadAccess::STATUSES)
            'leadUrl' => $user?->can('crm.view') && LeadAccess::find($client->id) ? route('hq.crm.lead', ['organization' => $client->id]) : null,
            'canOpenProjects' => (bool) $user?->can('projects.view'),
            'canOpenAccounts' => (bool) $user?->can('access.view'),
            'industries' => $this->editing && $this->askIndustry ? Leads::industries() : [],
            'countries' => Countries::NAMES,
        ])->title($client->name . ' · ' . __('clients.title'));
    }
}
