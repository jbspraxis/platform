<?php

namespace App\Livewire\Hq\CompanyProfile;

use App\Models\CompanyProfile\ClientLogo;
use App\Support\CompanyProfile\LogoLibrary as Library;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * HQ > Company profile > Client logos (docs/COMPANY_PROFILE.md section 2). The one place a client logo is kept:
 * the company profile, the deck and the strip on jbspraxis.com all read this list, so a logo added here appears
 * on the website without anybody touching the code.
 *
 * The browser makes the three renditions before anything is sent (resources/company-profile/logos.js), because
 * no PHP here can convert an image: the live server has neither gd nor imagick.
 */
#[Layout('components.layouts.hq')]
class LogoLibrary extends Component
{
    /** The row being edited, or '' for the new one */
    public string $editing = '';

    public bool $adding = false;

    public string $name = '';

    public string $sector = '';

    public bool $onProfile = true;

    public bool $onDeck = true;

    public bool $onWebsite = true;

    /** data:… from the browser: the SVG or JPEG the PDF draws, the transparent PNG, and the WebP */
    public string $printUri = '';

    public string $pngUri = '';

    public string $webUri = '';

    public ?int $width = null;

    public ?int $height = null;

    /** What the file picker found, so the form can say so before it is saved */
    public string $chosen = '';

    public bool $showRetired = false;

    #[Locked]
    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorize('company_profile.view');
    }

    // ------------------------------------------------------------------ the form

    public function add(): void
    {
        $this->authorize('company_profile.edit');
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $id): void
    {
        $this->authorize('company_profile.edit');
        $logo = ClientLogo::findOrFail($id);
        $this->resetForm();
        $this->editing = $logo->id;
        $this->name = (string) $logo->name;
        $this->sector = (string) $logo->sector;
        $this->onProfile = (bool) $logo->on_profile;
        $this->onDeck = (bool) $logo->on_deck;
        $this->onWebsite = (bool) $logo->on_website;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    /** The file picker hands over all three renditions at once */
    public function setFiles(array $payload): void
    {
        $this->authorize('company_profile.edit');
        $this->printUri = (string) ($payload['print'] ?? '');
        $this->pngUri = (string) ($payload['png'] ?? '');
        $this->webUri = (string) ($payload['web'] ?? '');
        $this->width = (int) ($payload['width'] ?? 0) ?: null;
        $this->height = (int) ($payload['height'] ?? 0) ?: null;
        $this->chosen = (string) ($payload['filename'] ?? '');
        $this->resetErrorBag('printUri');
    }

    public function save(): void
    {
        $this->authorize('company_profile.edit');
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'sector' => ['nullable', 'string', 'max:80'],
        ]);

        $logo = $this->editing !== '' ? ClientLogo::findOrFail($this->editing) : null;

        try {
            Library::save([
                'name' => $this->name,
                'sector' => $this->sector,
                'on_profile' => $this->onProfile,
                'on_deck' => $this->onDeck,
                'on_website' => $this->onWebsite,
                'width' => $this->width,
                'height' => $this->height,
            ], [
                'print' => $this->printUri,
                'png' => $this->pngUri,
                'web' => $this->webUri,
            ], Auth::user(), $logo);
        } catch (Throwable $e) {
            $this->addError('printUri', $e->getMessage());

            return;
        }

        $this->flash = $logo ? 'Saved.' : 'Added.';
        $this->resetForm();
    }

    // ------------------------------------------------------------------ order and retiring

    public function moveUp(string $id): void
    {
        $this->authorize('company_profile.edit');
        Library::move(ClientLogo::findOrFail($id), -1, Auth::user());
    }

    public function moveDown(string $id): void
    {
        $this->authorize('company_profile.edit');
        Library::move(ClientLogo::findOrFail($id), 1, Auth::user());
    }

    public function retire(string $id): void
    {
        $this->authorize('company_profile.manage');
        $logo = Library::retire(ClientLogo::findOrFail($id), Auth::user());
        $this->flash = $logo->name . ' is retired. It stays on the profiles that already carried it.';
    }

    public function restore(string $id): void
    {
        $this->authorize('company_profile.manage');
        $logo = Library::restore(ClientLogo::findOrFail($id), Auth::user());
        $this->flash = $logo->name . ' is back in the library, at the end.';
    }

    private function resetForm(): void
    {
        $this->reset([
            'editing', 'adding', 'name', 'sector', 'printUri', 'pngUri', 'webUri',
            'width', 'height', 'chosen',
        ]);
        $this->onProfile = true;
        $this->onDeck = true;
        $this->onWebsite = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.hq.company-profile.logos', [
            'logos' => Library::live(),
            'retiredLogos' => $this->showRetired ? Library::retired() : collect(),
            'canEdit' => (bool) $user?->can('company_profile.edit'),
            'canManage' => (bool) $user?->can('company_profile.manage'),
        ])->title('Client logos');
    }
}
