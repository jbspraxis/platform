<?php

namespace App\Livewire\Hq\Leads;

use App\Support\Leads\Contacts;
use App\Support\Leads\Copq;
use App\Support\Leads\Countries;
use App\Support\Leads\Duplicates;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\Leads;
use App\Support\Leads\NextStep;
use App\Support\Leads\Sources;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Add a lead (owner's decisions 1–4, docs/LEADS.md section 7): the organisation (name, country and industry required),
 * one contact block with "Add another contact", where it came from, the next step, and "More details" (closed at first:
 * the language for emails and the cost of poor quality figures). At least one phone or email is needed, on the
 * organisation or a contact. Leads and clients that match the name, an email or a phone show as you type, with
 * "Open it"; saving anyway is allowed. The lead belongs to whoever adds it.
 */
#[Layout('components.layouts.hq')]
#[Title('Add a lead')]
class AddLead extends Component
{
    public const MAX_CONTACTS = 20;

    /** The select's value for a country not in the list (the name is then typed in otherCountry) */
    public const OTHER_COUNTRY = '__other';

    public array $lead = [];

    public string $otherCountry = '';

    public array $contacts = [];

    public array $copq = [];

    public bool $moreOpen = false;

    /** Whether the language was chosen by hand (else it follows the country) */
    public bool $languageChosen = false;

    public function mount(): void
    {
        abort_unless(LeadAccess::canCreate(Auth::user()), 403);
        $step = NextStep::suggestion();
        $this->lead = [
            'name' => '', 'country' => 'Nigeria', 'city' => '', 'industry' => '', 'phone' => '', 'email' => '', 'address' => '',
            'preferred_language' => 'EN', 'source' => 'manual', 'source_detail' => '', 'next_step' => $step['what'], 'next_step_due' => $step['due'],
        ];
        $this->contacts = [self::blankContact(true)];
        $this->copq = ['tool' => '', 'bottleneck' => '', 'currency' => 'NGN'] + array_fill_keys(array_keys(Copq::FIGURES), '');
    }

    private static function blankContact(bool $main = false): array
    {
        return ['name' => '', 'job_title' => '', 'email' => '', 'phone' => '', 'deal_role' => '', 'main' => $main];
    }

    public function addContact(): void
    {
        if (count($this->contacts) < self::MAX_CONTACTS) {
            $this->contacts[] = self::blankContact();
        }
    }

    public function removeContact(int $index): void
    {
        if (count($this->contacts) <= 1 || ! isset($this->contacts[$index])) {
            return;
        }
        $wasMain = ! empty($this->contacts[$index]['main']);
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
        if ($wasMain) {
            $this->contacts[0]['main'] = true;
        }
        $this->resetErrorBag();
    }

    public function makeMain(int $index): void
    {
        foreach ($this->contacts as $i => $c) {
            $this->contacts[$i]['main'] = $i === $index;
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'lead.country' && ! $this->languageChosen) {
            $this->lead['preferred_language'] = Countries::language($this->lead['country']);
        }
        if ($property === 'lead.preferred_language') {
            $this->languageChosen = true;
        }
        // An error clears as soon as the field is fixed (docs/BRAND.md section 12)
        if ($this->getErrorBag()->has($property)) {
            $this->resetErrorBag($property);
            $rules = array_intersect_key($this->rules(), [$property => true]);
            if ($rules) {
                $this->validateOnly($property, $rules, $this->messages());
            }
        }
        if (in_array($property, ['lead.phone', 'lead.email'], true) || preg_match('/^contacts\.\d+\.(phone|email)$/', $property)) {
            $this->resetErrorBag('lead.reach');
        }
    }

    /** The rules for what is on the form: the lead's, each filled contact block's, and the estimate's once a figure is in */
    protected function rules(): array
    {
        $rules = Leads::rules('lead.') + ['otherCountry' => ['nullable', 'string', 'max:100']];
        foreach ($this->contacts as $i => $c) {
            if (! Contacts::isBlank($c)) {
                $rules += Contacts::rules("contacts.{$i}.");
            }
        }
        if (Copq::started($this->copq)) {
            $rules += Copq::rules('copq.');
        }

        return $rules;
    }

    protected function messages(): array
    {
        $messages = Leads::messages('lead.') + Copq::messages('copq.');
        foreach (array_keys($this->contacts) as $i) {
            $messages += Contacts::messages("contacts.{$i}.");
        }

        return $messages;
    }

    public function save()
    {
        abort_unless(LeadAccess::canCreate(Auth::user()), 403);
        $this->resetErrorBag();

        try {
            $this->validate($this->rules(), $this->messages());
        } catch (ValidationException $e) {
            $this->openMoreIfNeeded(array_keys($e->errors()));
            throw $e;
        }
        if ($this->lead['country'] === self::OTHER_COUNTRY && trim($this->otherCountry) === '') {
            $this->addError('otherCountry', 'Type the country’s name.');

            return;
        }
        $lead = $this->lead;
        if ($lead['country'] === self::OTHER_COUNTRY) {
            $lead['country'] = trim($this->otherCountry);
        }
        if (! Leads::reachable($lead, $this->contacts)) {
            $this->addError('lead.reach', Leads::UNREACHABLE);

            return;
        }

        try {
            $org = Leads::create($lead, $this->contacts, Auth::user(), [
                'source' => in_array($lead['source'], Sources::BY_HAND, true) ? $lead['source'] : 'manual',
                'copq' => Copq::started($this->copq) ? $this->copq : null,
            ]);
        } catch (ValidationException $e) {
            // Leads::create's keys: lead.<field> and contacts.<n>.<field> over the filled blocks only
            $filled = array_keys(array_filter($this->contacts, fn ($c) => ! Contacts::isBlank($c)));
            foreach ($e->errors() as $key => $messages) {
                $key = preg_replace_callback('/^contacts\.(\d+)\./', fn ($m) => 'contacts.' . ($filled[(int) $m[1]] ?? $m[1]) . '.', $key);
                $this->addError($key === 'lead.phone' && in_array(Leads::UNREACHABLE, $messages, true) ? 'lead.reach' : $key, $messages[0]);
            }

            return;
        } catch (LeadRuleBroken $e) {
            $this->addError('lead.reach', $e->getMessage());

            return;
        }

        session()->flash('success', "{$org->name} was added to your leads.");

        return $this->redirectRoute('hq.crm.lead', ['organization' => $org->id], navigate: true);
    }

    private function openMoreIfNeeded(array $keys): void
    {
        if (collect($keys)->contains(fn ($k) => str_starts_with($k, 'copq.') || $k === 'lead.preferred_language')) {
            $this->moreOpen = true;
        }
    }

    public function render()
    {
        $emails = collect($this->contacts)->pluck('email')->push($this->lead['email'] ?? '')->filter(fn ($e) => filter_var(trim((string) $e), FILTER_VALIDATE_EMAIL))->all();
        $phones = collect($this->contacts)->pluck('phone')->push($this->lead['phone'] ?? '')->filter()->all();
        $name = trim((string) ($this->lead['name'] ?? ''));

        return view('livewire.hq.leads.add-lead', [
            'matches' => mb_strlen($name) >= 3 || $emails || $phones ? Duplicates::find(mb_strlen($name) >= 3 ? $name : null, $emails, $phones) : collect(),
            'industries' => Leads::industries(),
            'countries' => Countries::NAMES,
            'sources' => array_intersect_key(Sources::LABELS, array_flip(Sources::BY_HAND)),
            'dealRoles' => Contacts::DEAL_ROLES,
            'choices' => $this->moreOpen ? Copq::choices() : ['systems' => [], 'bottlenecks' => [], 'currencies' => []],
            'figures' => Copq::FIGURES,
        ]);
    }
}
