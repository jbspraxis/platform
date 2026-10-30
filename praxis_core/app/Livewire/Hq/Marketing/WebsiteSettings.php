<?php

namespace App\Livewire\Hq\Marketing;

use App\Support\WebsiteSettings as Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * HQ → Marketing → Website settings: what jbspraxis.com shows about the company (company details,
 * registrations, offices, contact, social links, leadership). Saving goes through App\Support\WebsiteSettings,
 * which validates, writes the audit log and clears the cache the site API reads (docs/SITE_API.md section 4).
 * website.view opens the screen (fields read-only); website.edit saves.
 */
#[Layout('components.layouts.hq')]
#[Title('Website settings')]
class WebsiteSettings extends Component
{
    /** The whole settings array, in the shape WebsiteSettings::all() returns */
    public array $settings = [];

    #[Locked]
    public bool $canEdit = false;
    #[Locked]
    public bool $unsaved = false;
    #[Locked]
    public ?string $flash = null;
    #[Locked]
    public ?string $updatedAt = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('website.view'), 403);
        $this->canEdit = Auth::user()->can('website.edit');
        $this->loadSettings();
    }

    protected function rules(): array
    {
        return Settings::rules('settings.');
    }

    protected function messages(): array
    {
        return Settings::messages('settings.');
    }

    protected function validationAttributes(): array
    {
        return Settings::attributes('settings.');
    }

    /** Check a field when the person leaves it (wire:model.blur), so mistakes show before saving. */
    public function updated(string $property): void
    {
        if (! $this->canEdit || ! str_starts_with($property, 'settings.')) {
            return;
        }
        $this->unsaved = true;
        $this->flash = null;

        // A registration set back to "in progress" no longer needs its number
        if (preg_match('/^settings\.company\.registrations\.(\d+)\.status$/', $property, $m)) {
            $this->resetErrorBag("settings.company.registrations.{$m[1]}.number");
        }
        if (array_key_exists($property, $this->rules()) || $this->matchesListRule($property)) {
            $this->validateOnly($property);
        }
    }

    public function addRow(string $list): void
    {
        abort_unless($this->canEdit && Auth::user()->can('website.edit'), 403);
        if (! isset(Settings::LISTS[$list])) {
            return;
        }
        [$section, $field] = explode('.', $list);
        $rows = $this->settings[$section][$field] ?? [];
        if (count($rows) >= Settings::LISTS[$list][1]) {
            return;
        }
        $rows[] = Settings::emptyRow($list);
        $this->settings[$section][$field] = $rows;
        $this->unsaved = true;
        $this->flash = null;
    }

    public function removeRow(string $list, int $index): void
    {
        abort_unless($this->canEdit && Auth::user()->can('website.edit'), 403);
        if (! isset(Settings::LISTS[$list])) {
            return;
        }
        [$section, $field] = explode('.', $list);
        $rows = $this->settings[$section][$field] ?? [];
        unset($rows[$index]);
        $this->settings[$section][$field] = array_values($rows);
        // Row numbers move up, so messages about later rows would point at the wrong row
        $this->resetValidation();
        $this->unsaved = true;
        $this->flash = null;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->can('website.edit'), 403);
        $this->flash = null;

        // Exactly what will be saved: trimmed, empty rows removed. Then the same rules set() uses.
        $this->settings = Settings::shape($this->settings);
        $this->validate();

        try {
            Settings::set($this->settings, Auth::id());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('settings.' . $key, $message);
                }
            }

            return;
        }

        $this->loadSettings();
        $this->unsaved = false;
        $this->flash = 'Website settings saved. The site shows them within 5 minutes.';
    }

    public function render()
    {
        return view('livewire.hq.marketing.website-settings', [
            'countries' => Settings::COUNTRIES,
            'statuses' => Settings::STATUSES,
            'limits' => array_map(fn ($l) => $l[1], Settings::LISTS),
            'lastSaved' => $this->updatedAt
                ? Carbon::parse($this->updatedAt)->timezone('Africa/Lagos')->format('j F Y, H:i') . ' (WAT)'
                : null,
        ]);
    }

    private function loadSettings(): void
    {
        $state = Settings::load();
        $this->settings = $state['values'];
        $this->updatedAt = $state['updated_at'];
    }

    /** 'settings.company.offices.0.city' matches the rule 'settings.company.offices.*.city' */
    private function matchesListRule(string $property): bool
    {
        return array_key_exists(preg_replace('/\.\d+\./', '.*.', $property), $this->rules());
    }
}
