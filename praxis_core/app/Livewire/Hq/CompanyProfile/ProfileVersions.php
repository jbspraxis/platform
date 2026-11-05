<?php

namespace App\Livewire\Hq\CompanyProfile;

use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\Profile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * HQ › Company profile › Versions (docs/COMPANY_PROFILE.md section 6): every version there has ever been,
 * which one is published, who wrote it and who approved it, and what has changed since.
 */
#[Layout('components.layouts.hq')]
class ProfileVersions extends Component
{
    #[Locked]
    public bool $canEdit = false;

    #[Locked]
    public bool $canApprove = false;

    public string $flash = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->canEdit = (bool) $user?->can('company_profile.edit');
        $this->canApprove = (bool) $user?->can('company_profile.approve');
    }

    public function startDraft(): void
    {
        abort_unless($this->canEdit, 403);

        Profile::startDraft();
        $this->redirectRoute('hq.company-profile.editor', navigate: true);
    }

    public function publish(string $id): void
    {
        abort_unless($this->canApprove, 403);

        $version = ProfileVersion::findOrFail($id);
        if (! $version->isOpen()) {
            return;
        }

        Profile::publish($version);
        $this->flash = $version->label().' is published.';
    }

    public function render()
    {
        $versions = ProfileVersion::with(['creator', 'submitter', 'reviewer'])->orderByDesc('number')->get();
        $published = $versions->firstWhere('status', ProfileVersion::PUBLISHED);
        $open = $versions->first(fn ($v) => $v->isOpen());

        return view('livewire.hq.company-profile.versions', [
            'versions' => $versions,
            'published' => $published,
            'open' => $open,
            'changes' => $open && $published ? Profile::changes($open, $published) : [],
        ])->title('Company profile versions');
    }
}
