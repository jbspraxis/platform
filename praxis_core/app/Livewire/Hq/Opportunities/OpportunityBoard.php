<?php

namespace App\Livewire\Hq\Opportunities;

use App\Models\Opportunity;
use App\Models\User;
use App\Support\Opportunities\OpportunityAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ › Leads and proposals › Opportunities (docs/OPPORTUNITIES.md section 2): every piece of work with a client, as
 * a board by stage or a table, and "Open an opportunity" for an organisation. opportunities.view opens it;
 * opportunities.create opens new ones (the lead and the language are set here; the language follows the
 * organisation's preferred language unless changed).
 */
#[Layout('components.layouts.hq')]
class OpportunityBoard extends Component
{
    #[Url(except: 'board')]
    public string $view = 'board';

    #[Url(except: '')]
    public string $search = '';

    /** '' everyone, 'mine', or a staff id */
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

    public string $leadUserId = '';

    public string $language = 'en';

    #[Locked]
    public bool $canCreate = false;

    public function mount(): void
    {
        abort_unless(OpportunityAccess::canView(Auth::user()), 403);
        $this->canCreate = OpportunityAccess::canCreate(Auth::user());

        // ?new=1&organization=… opens the form for that organisation (from Leads, a booking, a profile)
        if ($this->canCreate && request()->boolean('new')) {
            $this->openCreate((string) request()->query('organization', ''));
        }
    }

    public function openCreate(string $organizationId = ''): void
    {
        abort_unless($this->canCreate, 403);
        $this->resetErrorBag();
        $this->creating = true;
        $this->orgSearch = '';
        $this->title = '';
        $this->leadUserId = (string) Auth::id();
        $this->language = 'en';
        $this->organizationId = '';
        if ($organizationId !== '' && DB::table('organizations')->where('id', $organizationId)->whereNull('deleted_at')->where('status', '!=', 'Internal')->exists()) {
            $this->organizationId = $organizationId;
            $this->updatedOrganizationId();
        }
    }

    public function closeCreate(): void
    {
        $this->creating = false;
        $this->resetErrorBag();
    }

    /** The language follows the organisation's preferred language */
    public function updatedOrganizationId(): void
    {
        $preferred = DB::table('organizations')->where('id', $this->organizationId)->value('preferred_language');
        $this->language = str_starts_with(strtolower(trim((string) $preferred)), 'fr') ? 'fr' : 'en';
    }

    protected function rules(): array
    {
        return [
            'organizationId' => ['required', Rule::exists('organizations', 'id')->whereNull('deleted_at')->where(fn ($q) => $q->where('status', '!=', 'Internal'))],
            'title' => ['required', 'string', 'max:200'],
            'leadUserId' => ['required', Rule::exists('users', 'id')->where('status', 'Active')->whereNull('deleted_at')],
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
            'language.required' => __('opportunities.create.errors.language'),
            'language.in' => __('opportunities.create.errors.language'),
        ];
    }

    public function create()
    {
        abort_unless(OpportunityAccess::canCreate(Auth::user()), 403);
        $this->title = trim($this->title);
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

        session()->flash('success', __('opportunities.create.done', ['title' => $opportunity->title]));

        return $this->redirectRoute('hq.crm.opportunity', ['opportunity' => $opportunity->id], navigate: true);
    }

    /** Organisations an opportunity can be opened for: clients and leads, not JBS Praxis itself */
    private function organisations()
    {
        $term = trim($this->orgSearch);
        $base = fn () => DB::table('organizations')->whereNull('deleted_at')->where('status', '!=', 'Internal');

        $found = $base()->when($term !== '', fn ($q) => $q->where('name', 'like', '%' . addcslashes($term, '%_\\') . '%'))
            ->orderBy('name')->limit(50)->get(['id', 'name', 'status']);
        // The chosen one stays in the list whatever the search says
        if ($this->organizationId !== '' && ! $found->contains('id', $this->organizationId)
            && ($chosen = $base()->where('id', $this->organizationId)->first(['id', 'name', 'status']))) {
            $found->prepend($chosen);
        }

        return $found;
    }

    private function staff()
    {
        return User::where('status', 'Active')->whereNull('deleted_at')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);
    }

    public function render()
    {
        $term = trim($this->search);
        $query = Opportunity::query()
            ->with(['organization:id,name', 'lead:id,first_name,last_name'])
            ->withCount(['meetings', 'proposals'])
            ->when(! $this->closed, fn ($q) => $q->whereNotIn('stage', Opportunity::CLOSED))
            ->when($this->lead === 'mine', fn ($q) => $q->where('lead_user_id', Auth::id()))
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
            'organisations' => $this->creating ? $this->organisations() : collect(),
        ])->title(__('opportunities.title'));
    }
}
