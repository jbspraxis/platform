<?php

namespace App\Livewire\Hq\Leads;

use App\Support\CompanyDocuments\CannotRead;
use App\Support\Leads\Contacts;
use App\Support\Leads\Copq;
use App\Support\Leads\Countries;
use App\Support\Leads\Duplicates;
use App\Support\Leads\LeadAccess;
use App\Support\Leads\LeadIntel;
use App\Support\Leads\LeadRuleBroken;
use App\Support\Leads\Leads;
use App\Support\Leads\NextStep;
use App\Support\Leads\Sources;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Add a lead (owner's decisions 1–4, docs/LEADS.md section 7): the organisation (name, country and industry required),
 * one contact block with "Add another contact", where it came from, the next step, and "More details" (closed at first:
 * the language for emails and the cost of poor quality figures). At least one phone or email is needed, on the
 * organisation or a contact. Leads and clients that match the name, an email or a phone show as you type, with
 * "Open it"; saving anyway is allowed. The lead belongs to whoever adds it.
 *
 * It starts by asking what the person has (docs/LEADS.md section 13): an intel report (upload or paste it; "Fill the empty
 * fields from the report" lets Claude fill the form, never over what was typed, and the report is read in full after
 * saving), a cost of poor quality discussion (the figures open at once) or a blank lead (the basics only). Each path
 * is the same form with the sections it needs; the rest can be added from the lead's page.
 */
#[Layout('components.layouts.hq')]
#[Title('Add a lead')]
class AddLead extends Component
{
    use WithFileUploads;

    public const MAX_CONTACTS = 20;

    /** The three ways in */
    public const PATHS = ['intel', 'copq', 'blank'];

    /** The select's value for a country not in the list (the name is then typed in otherCountry) */
    public const OTHER_COUNTRY = '__other';

    public array $lead = [];

    public string $otherCountry = '';

    public array $contacts = [];

    public array $copq = [];

    public bool $moreOpen = false;

    /** Whether the language was chosen by hand (else it follows the country) */
    public bool $languageChosen = false;

    /** Whether the country was chosen by hand (else "Fill from the report" may set it) */
    public bool $countryChosen = false;

    /** What the person has: '' (the start screen), 'intel', 'copq' or 'blank' */
    #[Url(as: 'start', except: '')]
    public string $path = '';

    /** The intel report (docs/LEADS.md section 13): a file or pasted text, read in full once the lead is saved */
    public $intelFile = null;

    public string $intelText = '';

    public string $intelTitle = '';

    /** The chosen file once it is on the private disk (an inbox until the lead exists): only the server sets it */
    #[Locked]
    public array $intelStored = [];

    /** The fields "Fill from the report" filled and nobody has changed since ('lead.name', 'contacts.0.email'…) */
    public array $fromReport = [];

    public ?string $fillNotice = null;

    public ?string $fillWarning = null;

    public function mount(): void
    {
        abort_unless(LeadAccess::canCreate(Auth::user()), 403);
        if (! in_array($this->path, self::PATHS, true) || ($this->path === 'intel' && ! LeadIntel::canUse(Auth::user()))) {
            $this->path = '';
        }
        $this->moreOpen = $this->path === 'copq';
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

    // --- What do you have? (docs/LEADS.md section 13) ---

    public function choose(string $path): void
    {
        abort_unless(LeadAccess::canCreate(Auth::user()), 403);
        if (! in_array($path, self::PATHS, true) || ($path === 'intel' && ! LeadIntel::canUse(Auth::user()))) {
            return;
        }
        $this->path = $path;
        if ($path === 'copq') {
            $this->moreOpen = true;
        }
        $this->resetErrorBag();
    }

    public function changePath(): void
    {
        $this->path = '';
        $this->resetErrorBag();
    }

    /** A new file replaces the one already on the private disk; nothing is read until "Fill" or Add lead */
    public function updatedIntelFile(): void
    {
        $this->dropStoredIntel();
        $this->fillNotice = $this->fillWarning = null;
        if ($this->intelFile) {
            $this->validateOnly('intelFile', $this->rules(), $this->messages());
        }
    }

    private function dropStoredIntel(): void
    {
        if ($this->intelStored) {
            Storage::disk('local')->delete($this->intelStored['path']);
            $this->intelStored = [];
        }
    }

    /**
     * The report as a source for Claude: the file (put on the private disk once) or the text, else null (and an error when
     * the file is no good)
     */
    private function intelSource(): ?array
    {
        if ($this->intelStored) {
            return $this->intelStored;
        }
        if ($this->intelFile) {
            try {
                $this->intelStored = LeadIntel::storeUpload($this->intelFile);
            } catch (CannotRead $e) {
                $this->addError('intelFile', $e->getMessage());

                return null;
            }
            // Storing moves Livewire's temporary file, so the property can't be asked about it again (the next validation
            // would find nothing there); the stored copy is what is used from here on, until another file is chosen
            $this->intelFile = null;

            return $this->intelStored;
        }

        return mb_strlen(trim($this->intelText)) >= 30 ? LeadIntel::textSource($this->intelText) : null;
    }

    /**
     * "Fill the empty fields from the report": Claude reads the report while the person waits and the empty fields of
     * the form are filled (never one that has something typed in it). Every field it filled says so until it is changed;
     * the person checks the form, then adds the lead.
     */
    public function fillFromReport(): void
    {
        abort_unless(LeadAccess::canCreate(Auth::user()) && LeadIntel::canUse(Auth::user()), 403);
        $this->resetErrorBag();
        $this->fillNotice = $this->fillWarning = null;

        $this->validate(array_intersect_key($this->rules(), array_flip(['intelFile', 'intelText'])), $this->messages());
        $source = $this->intelSource();
        if ($source === null) {
            if (! $this->getErrorBag()->has('intelFile')) {
                $this->addError('intelText', 'Upload a report or paste some notes first, then fill the form from it.');
            }

            return;
        }

        $result = LeadIntel::prefill($source);
        if (! $result['ok']) {
            $this->fillWarning = 'Claude could not read the report just now: ' . $result['message'] . ' You can still type the details and add the lead; the report is read after it is added.';

            return;
        }
        $this->applyFields($result['fields']);
    }

    /** Puts what the report states into the empty fields only; contacts go into empty blocks, then new ones */
    private function applyFields(array $f): void
    {
        $blank = fn ($v) => trim((string) $v) === '';
        $clean = fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);
        $email = fn ($v) => filter_var(trim((string) $v), FILTER_VALIDATE_EMAIL) && mb_strlen(trim((string) $v)) <= 100 ? trim((string) $v) : '';
        $filled = [];
        $lead = $this->lead;

        foreach (['name' => ['company_name', 150], 'city' => ['city', 100], 'phone' => ['phone', 30]] as $key => [$from, $max]) {
            if ($blank($lead[$key]) && ! $blank($f[$from] ?? '')) {
                $lead[$key] = $clean($f[$from], $max);
                $filled[] = "lead.{$key}";
            }
        }
        if ($blank($lead['email']) && $email($f['email'] ?? '') !== '') {
            $lead['email'] = $email($f['email']);
            $filled[] = 'lead.email';
        }
        if (! $this->countryChosen) {
            $country = collect(Countries::NAMES)->first(fn ($c) => mb_strtolower($c) === mb_strtolower(trim((string) ($f['country'] ?? ''))));
            if ($country && $country !== $lead['country']) {
                $lead['country'] = $country;
                $filled[] = 'lead.country';
                if (! $this->languageChosen) {
                    $lead['preferred_language'] = Countries::language($country);
                }
            }
        }
        if ($blank($lead['industry'])) {
            $industry = collect(Leads::industries())->first(fn ($i) => mb_strtolower($i) === mb_strtolower(trim((string) ($f['industry'] ?? ''))));
            if ($industry) {
                $lead['industry'] = $industry;
                $filled[] = 'lead.industry';
            }
        }

        // Decision makers first; people already on the form (same email or name) are not added twice
        $order = ['decision_maker' => 0, 'finance' => 1, 'technical' => 2, 'user' => 3];
        $people = collect($f['contacts'] ?? [])->filter(fn ($p) => ! $blank($p['name'] ?? ''))
            ->sortBy(fn ($p) => $order[$p['deal_role'] ?? ''] ?? 9)->take(10)->values();
        $contacts = $this->contacts;
        $added = 0;
        foreach ($people as $p) {
            $known = collect($contacts)->contains(fn ($c) => mb_strtolower(trim((string) $c['name'])) === mb_strtolower(trim((string) $p['name']))
                || ($email($p['email'] ?? '') !== '' && mb_strtolower(trim((string) $c['email'])) === mb_strtolower($email($p['email']))));
            if ($known) {
                continue;
            }
            $slot = collect($contacts)->search(fn ($c) => Contacts::isBlank($c));
            if ($slot === false) {
                if (count($contacts) >= self::MAX_CONTACTS) {
                    break;
                }
                $contacts[] = self::blankContact();
                $slot = array_key_last($contacts);
            }
            $contacts[$slot] = [
                'name' => $clean($p['name'], 100), 'job_title' => $clean($p['job_title'] ?? '', 100), 'email' => $email($p['email'] ?? ''),
                'phone' => $clean($p['phone'] ?? '', 30),
                'deal_role' => isset(Contacts::DEAL_ROLES[$p['deal_role'] ?? '']) ? $p['deal_role'] : '',
                'main' => $contacts[$slot]['main'] ?? false,
            ];
            foreach (['name', 'job_title', 'email', 'phone', 'deal_role'] as $k) {
                if (! $blank($contacts[$slot][$k])) {
                    $filled[] = "contacts.{$slot}.{$k}";
                }
            }
            $added++;
        }

        $this->lead = $lead;
        $this->contacts = $contacts;
        $this->fromReport = array_values(array_unique(array_merge($this->fromReport, $filled)));

        $leadFields = collect($filled)->filter(fn ($k) => str_starts_with($k, 'lead.'))
            ->map(fn ($k) => ['lead.name' => 'the name', 'lead.country' => 'the country', 'lead.city' => 'the city', 'lead.industry' => 'the industry', 'lead.phone' => 'the phone', 'lead.email' => 'the email'][$k])->all();
        $parts = $leadFields;
        if ($added > 0) {
            $parts[] = $added === 1 ? 'one contact' : "{$added} contacts";
        }
        $this->fillNotice = $parts
            ? 'Filled from the report: ' . implode(', ', $parts) . '. Check each one before you add the lead; anything you had typed was left alone.'
            : 'The report had nothing new for the empty fields. The report itself is still read in full once the lead is added.';
    }

    public function updated(string $property): void
    {
        if ($property === 'lead.country') {
            $this->countryChosen = true;
            if (! $this->languageChosen) {
                $this->lead['preferred_language'] = Countries::language($this->lead['country']);
            }
        }
        if ($property === 'lead.preferred_language') {
            $this->languageChosen = true;
        }
        // A field someone changes is theirs from then on
        $this->fromReport = array_values(array_diff($this->fromReport, [$property]));
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
        if ($this->path === 'intel') {
            $rules += [
                'intelFile' => ['nullable', 'file', 'max:' . LeadIntel::MAX_KB, 'extensions:' . implode(',', array_keys(LeadIntel::FILES))],
                'intelText' => ['nullable', 'string', 'min:30', 'max:' . (LeadIntel::MAX_TEXT * 2)],
                'intelTitle' => ['nullable', 'string', 'max:200'],
            ];
        }

        return $rules;
    }

    protected function messages(): array
    {
        $messages = Leads::messages('lead.') + Copq::messages('copq.') + [
            'intelFile.max' => 'That file is over ' . (LeadIntel::MAX_KB / 1024) . ' MB. Split it, or paste the text instead.',
            'intelFile.extensions' => 'Upload a PDF, a Word file (.docx), a text file or a Markdown file.',
            'intelText.min' => 'Add a little more: at least a couple of sentences.',
            'intelText.max' => 'That is too long to read in one go. Split it into two reports once the lead is added.',
            'intelTitle.max' => 'Keep the title under 200 characters.',
        ];
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
        // The intel report is checked before anything is created, so a bad file never leaves a lead without it
        $intelSource = $this->path === 'intel' && LeadIntel::canUse(Auth::user()) ? $this->intelSource() : null;
        if ($this->getErrorBag()->isNotEmpty()) {
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

        // The report goes on the lead and Claude reads it in the background; the lead is already saved, so a problem here
        // is said, never lost
        $message = "{$org->name} was added to your leads.";
        $intel = null;
        if ($intelSource) {
            try {
                $intel = LeadIntel::add($org, $intelSource, Auth::user(), $this->intelTitle);
                $state = LeadIntel::state($intel);
                $message .= $state['state'] === 'failed'
                    ? ' The intel report is saved, but Claude could not read it yet: ' . $state['message']
                    : ' Claude is reading the intel report; it shows on the Intel tab within a minute or two.';
            } catch (\Throwable $e) {
                report($e);
                $message .= ' The intel report could not be saved: add it again from the lead’s Intel tab.';
            }
        }
        session()->flash('success', $message);

        return $this->redirectRoute('hq.crm.lead', ['organization' => $org->id] + ($intel ? ['tab' => 'intel'] : []), navigate: true);
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
            'canIntel' => LeadIntel::canUse(Auth::user()),
            'maxMb' => LeadIntel::MAX_KB / 1024,
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
