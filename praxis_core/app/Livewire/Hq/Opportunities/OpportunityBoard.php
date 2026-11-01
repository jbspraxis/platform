<?php

namespace App\Livewire\Hq\Opportunities;

use App\Livewire\Hq\Opportunities\Concerns\PicksStaff;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\Opportunities\OpportunityAccess;
use App\Support\Opportunities\Organisations;
use App\Support\Opportunities\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ › Leads and proposals › Opportunities (docs/OPPORTUNITIES.md section 2): every piece of work with a client, as
 * a board by stage or a table, and "Open an opportunity" for an organisation. opportunities.view opens it (someone
 * on an opportunity's team without it sees only theirs); opportunities.create opens new ones. The form picks the
 * organisation (or adds one to Master data on the spot), the lead, the team and the language (which follows the
 * organisation's preferred language unless changed).
 */
#[Layout('components.layouts.hq')]
class OpportunityBoard extends Component
{
    use PicksStaff;

    #[Url(except: 'board')]
    public string $view = 'board';

    #[Url(except: '')]
    public string $search = '';

    /** '' everyone, 'mine' (led by me, or with me on the team), or a staff id */
    #[Url(except: '')]
    public string $lead = '';

    /** Show won and lost opportunities too */
    #[Url(except: false)]
    public bool $closed = false;

    // "Open an opportunity"
    public bool $creating = false;

    public string $orgSearch = '';

    public string $organizationId = '';

    public string $title = '';

    public string $language = 'en';

    // A new organisation, added from the form (Organisations)
    public bool $newOrg = false;

    public bool $newOrgMore = false;

    public string $newOrgName = '';

    public string $newOrgCountry = 'Nigeria';

    public string $newOrgCity = '';

    public string $newOrgLanguage = 'EN';

    public string $newOrgIndustry = '';

    public string $newOrgStage = '';

    public string $newOrgSystems = '';

    public string $newOrgGovernance = '';

    public string $newOrgTurnover = '';

    public string $newOrgBudget = '';

    public string $newOrgBillingAddress = '';

    public string $newOrgTaxId = '';

    public string $newOrgTimezone = 'Africa/Lagos';

    /** An organisation already named like the new one, offered instead of a duplicate */
    public array $existingOrg = [];

    public string $orgAdded = '';

    #[Locked]
    public bool $canCreate = false;

    public function mount(): void
    {
        abort_unless(OpportunityAccess::canOpenBoard(Auth::user()), 403);
        $this->canCreate = OpportunityAccess::canCreate(Auth::user());

        // ?new=1&organization=… opens the form for that organisation (from Leads, a booking, a profile)
        if ($this->canCreate && request()->boolean('new')) {
            $this->openCreate((string) request()->query('organization', ''));
        }
    }

    protected function authorizePicking(): void
    {
        abort_unless($this->canCreate, 403);
    }

    public function openCreate(string $organizationId = ''): void
    {
        abort_unless($this->canCreate, 403);
        $this->resetErrorBag();
        $this->reset('orgSearch', 'organizationId', 'title', 'language', 'leadSearch', 'teamSearch', 'teamUserIds', 'orgAdded');
        $this->resetNewOrg();
        $this->creating = true;
        $this->leadUserId = (string) Auth::id();
        if ($organizationId !== '') {
            $this->chooseOrganisation($organizationId);
        }
    }

    public function closeCreate(): void
    {
        $this->creating = false;
        $this->newOrg = false;
        $this->resetErrorBag();
    }

    public function chooseOrganisation(string $organizationId): void
    {
        $this->authorizePicking();
        if ($this->organisationQuery()->where('id', $organizationId)->exists()) {
            $this->organizationId = $organizationId;
            // The language follows the organisation's preferred language
            $preferred = DB::table('organizations')->where('id', $organizationId)->value('preferred_language');
            $this->language = str_starts_with(strtolower(trim((string) $preferred)), 'fr') ? 'fr' : 'en';
        }
        $this->orgSearch = '';
        $this->resetErrorBag('organizationId');
    }

    public function clearOrganisation(): void
    {
        $this->authorizePicking();
        $this->organizationId = '';
        $this->orgSearch = '';
        $this->orgAdded = '';
    }

    // --- A new organisation, without leaving the form ---

    public function startNewOrganisation(): void
    {
        $this->authorizePicking();
        $this->resetNewOrg();
        $this->newOrgName = trim($this->orgSearch);
        $this->newOrgLanguage = $this->language === 'fr' ? 'FR' : 'EN';
        $this->newOrg = true;
        $this->resetErrorBag();
    }

    public function cancelNewOrganisation(): void
    {
        $this->newOrg = false;
        $this->resetErrorBag();
    }

    public function toggleNewOrgMore(): void
    {
        $this->newOrgMore = ! $this->newOrgMore;
    }

    public function saveNewOrganisation(): void
    {
        $this->authorizePicking();
        $this->existingOrg = [];
        foreach (['newOrgName', 'newOrgCountry', 'newOrgCity', 'newOrgIndustry', 'newOrgStage', 'newOrgGovernance', 'newOrgTurnover', 'newOrgBudget', 'newOrgBillingAddress', 'newOrgTaxId', 'newOrgTimezone'] as $field) {
            $this->$field = trim($this->$field);
        }
        try {
            $this->validate(Organisations::rules(), Organisations::messages());
        } catch (ValidationException $e) {
            // An error in the optional details opens them, so it can be seen
            $advanced = [...array_diff(array_values(Organisations::PROFILE), ['newOrgIndustry']), 'newOrgTimezone'];
            $this->newOrgMore = $this->newOrgMore || array_intersect(array_keys($e->errors()), $advanced) !== [];
            throw $e;
        }

        if ($existing = Organisations::existing($this->newOrgName)) {
            $this->existingOrg = ['id' => $existing->id, 'name' => $existing->name, 'usable' => $existing->status !== 'Internal'];
            $this->addError('newOrgName', __('opportunities.create.new_org.exists', ['name' => $existing->name]));

            return;
        }

        $values = ['name' => $this->newOrgName, 'country' => $this->newOrgCountry, 'city' => $this->newOrgCity,
            'preferred_language' => $this->newOrgLanguage, 'timezone' => $this->newOrgTimezone];
        foreach (Organisations::PROFILE as $column => $property) {
            $values[$column] = $this->$property;
        }
        $org = Organisations::create($values, Auth::id());

        $this->newOrg = false;
        $this->resetNewOrg();
        $this->chooseOrganisation($org->id);
        $this->orgAdded = __('opportunities.create.new_org.added', ['name' => $org->name]);
    }

    /** The organisation that already has that name, instead of a duplicate */
    public function useExistingOrganisation(): void
    {
        $this->authorizePicking();
        if (! empty($this->existingOrg['id']) && ! empty($this->existingOrg['usable'])) {
            $id = $this->existingOrg['id'];
            $this->newOrg = false;
            $this->resetNewOrg();
            $this->chooseOrganisation($id);
        }
    }

    private function resetNewOrg(): void
    {
        $this->reset('newOrgMore', 'newOrgName', 'newOrgCountry', 'newOrgCity', 'newOrgLanguage', 'newOrgIndustry', 'newOrgStage', 'newOrgSystems',
            'newOrgGovernance', 'newOrgTurnover', 'newOrgBudget', 'newOrgBillingAddress', 'newOrgTaxId', 'newOrgTimezone', 'existingOrg');
    }

    // --- Open it ---

    protected function rules(): array
    {
        return [
            'organizationId' => ['required', Rule::exists('organizations', 'id')->whereNull('deleted_at')->where(fn ($q) => $q->where('status', '!=', 'Internal'))],
            'title' => ['required', 'string', 'max:200'],
            'leadUserId' => ['required', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'teamUserIds' => ['array', 'max:20'],
            'teamUserIds.*' => ['string', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
            'language' => ['required', Rule::in(Opportunity::LANGUAGES)],
        ];
    }

    protected function messages(): array
    {
        return [
            'organizationId.required' => __('opportunities.create.errors.organisation'),
            'organizationId.exists' => __('opportunities.create.errors.organisation'),
            'title.required' => __('opportunities.create.errors.title'),
            'title.max' => __('opportunities.create.errors.title_long'),
            'leadUserId.required' => __('opportunities.create.errors.lead'),
            'leadUserId.exists' => __('opportunities.create.errors.lead'),
            'teamUserIds.max' => __('opportunities.create.errors.team'),
            'teamUserIds.*.exists' => __('opportunities.create.errors.team'),
            'teamUserIds.*.string' => __('opportunities.create.errors.team'),
            'language.required' => __('opportunities.create.errors.language'),
            'language.in' => __('opportunities.create.errors.language'),
        ];
    }

    public function create()
    {
        abort_unless(OpportunityAccess::canCreate(Auth::user()), 403);
        $this->title = trim($this->title);
        $this->newOrg = false;
        $this->validate();

        $opportunity = Opportunity::create([
            'organization_id' => $this->organizationId,
            'title' => $this->title,
            'stage' => 'discovery',
            'lead_user_id' => $this->leadUserId,
            'language' => $this->language,
            'source' => 'manual',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
        $org = DB::table('organizations')->where('id', $this->organizationId)->value('name');
        OpportunityAccess::log('OPPORTUNITY_OPENED', "Opened the opportunity “{$opportunity->title}” for {$org}.", 'opportunities', $opportunity->id,
            [], $opportunity->only(['organization_id', 'title', 'stage', 'lead_user_id', 'language', 'source']));
        Team::sync($opportunity, $this->teamUserIds, Auth::id());

        session()->flash('success', __('opportunities.create.done', ['title' => $opportunity->title]));

        return $this->redirectRoute('hq.crm.opportunity', ['opportunity' => $opportunity->id], navigate: true);
    }

    /** Organisations an opportunity can be opened for: clients and leads, not JBS Praxis itself */
    private function organisationQuery()
    {
        return DB::table('organizations')->whereNull('deleted_at')->where('status', '!=', 'Internal');
    }

    /** What the organisation picker shows for the typed term, and whether the term could be a new organisation */
    private function organisationPicker(): array
    {
        $term = trim($this->orgSearch);
        $found = $this->organisationQuery()
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%' . addcslashes($term, '%_\\') . '%'))
            ->orderBy('name')->limit(50)->get(['id', 'name', 'status', 'city', 'country']);
        $status = fn ($s) => Lang::has('opportunities.org_status.' . $s) ? __('opportunities.org_status.' . $s) : $s;
        $options = $found->map(fn ($o) => [
            'id' => $o->id, 'label' => $o->name, 'meta' => implode(' · ', array_filter([$status($o->status), $o->city ?: $o->country])),
        ])->values();
        $selected = $this->organizationId !== '' ? $this->organisationQuery()->where('id', $this->organizationId)->first(['id', 'name', 'status', 'city', 'country']) : null;
        $exact = $term !== '' && $found->contains(fn ($o) => mb_strtolower(trim($o->name)) === mb_strtolower($term));

        return [
            'organisations' => $options,
            'organisationSelected' => $selected ? ['id' => $selected->id, 'label' => $selected->name, 'meta' => implode(' · ', array_filter([$status($selected->status), $selected->city ?: $selected->country]))] : null,
            'canAddOrganisation' => $this->canCreate && mb_strlen($term) >= 2 && ! $exact,
        ];
    }

    private function staff()
    {
        return User::where('status', 'Active')->whereNull('deleted_at')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);
    }

    public function render()
    {
        $user = Auth::user();
        $term = trim($this->search);
        $query = Opportunity::query()
            ->visibleTo($user)
            ->with(['organization:id,name', 'lead:id,first_name,last_name'])
            ->withCount(['meetings', 'proposals', 'members'])
            ->when(! $this->closed, fn ($q) => $q->whereNotIn('stage', Opportunity::CLOSED))
            ->when($this->lead === 'mine', fn ($q) => $q->involving($user))
            ->when($this->lead !== '' && $this->lead !== 'mine', fn ($q) => $q->where('lead_user_id', $this->lead))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhereHas('organization', fn ($o) => $o->where('name', 'like', $like)));
            })
            ->orderByDesc('updated_at');

        $opportunities = $query->limit(500)->get();
        $stages = $this->closed ? Opportunity::STAGES : array_values(array_diff(Opportunity::STAGES, Opportunity::CLOSED));

        return view('livewire.hq.opportunities.board', [
            'opportunities' => $opportunities,
            'stages' => $stages,
            'byStage' => $opportunities->groupBy('stage'),
            'staff' => $this->staff(),
            'teamOnly' => ! OpportunityAccess::canView($user),
            'industries' => $this->newOrg ? Organisations::industries() : [],
            'systems' => $this->newOrg ? Organisations::systems() : [],
            'timeZones' => Organisations::TIME_ZONES,
        ] + ($this->creating ? $this->organisationPicker() + $this->staffPickers() : []))->title(__('opportunities.title'));
    }
}
