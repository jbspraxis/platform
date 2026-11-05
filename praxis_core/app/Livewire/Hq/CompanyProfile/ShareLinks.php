<?php

namespace App\Livewire\Hq\CompanyProfile;

use App\Models\CompanyProfile\ProfileShareLink;
use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\Sections;
use App\Support\CompanyProfile\Shares;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * HQ › Company profile › Share links (docs/COMPANY_PROFILE.md section 14). A link per prospect: it opens the
 * profile PDF on jbspraxis.com with no sign-in, every open is counted and rings the bell, and it can be
 * revoked or left to expire.
 *
 * Making a link needs company_profile.view, which is what the owner asked for: anyone who may read the
 * profile may send it. Revoking is for the person who made it, or anyone with company_profile.manage.
 */
#[Layout('components.layouts.hq')]
class ShareLinks extends Component
{
    public bool $making = false;

    public string $locale = 'en';

    /** '' follows whatever is published when the link is opened */
    public string $versionId = '';

    public string $recipientName = '';

    public string $recipientCompany = '';

    /** Days from today, or '' for a link that does not expire */
    public string $expiresInDays = '30';

    /** The link just made, shown once so it can be copied */
    #[Locked]
    public string $madeToken = '';

    #[Locked]
    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorize('company_profile.view');
    }

    public function make(): void
    {
        $this->authorize('company_profile.view');
        $this->madeToken = '';
        $this->making = true;
    }

    public function cancel(): void
    {
        $this->reset(['making', 'recipientName', 'recipientCompany', 'versionId', 'expiresInDays', 'locale']);
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->authorize('company_profile.view');
        $this->validate([
            'recipientName' => ['nullable', 'string', 'max:150'],
            'recipientCompany' => ['nullable', 'string', 'max:200'],
            'expiresInDays' => ['nullable', 'integer', 'min:1', 'max:365'],
        ], [], ['expiresInDays' => 'expiry']);

        try {
            $link = Shares::create([
                'locale' => $this->locale,
                'version_id' => $this->versionId ?: null,
                'recipient_name' => $this->recipientName,
                'recipient_company' => $this->recipientCompany,
                'expires_at' => $this->expiresInDays === '' ? null : now()->addDays((int) $this->expiresInDays),
            ], Auth::user());
        } catch (Throwable $e) {
            $this->addError('versionId', $e->getMessage());

            return;
        }

        // Shown once, in full, so it can be copied into an email
        $this->madeToken = $link->token;
        $this->flash = 'The link is ready. Copy it now; it is shown here in full only this once.';
        $this->reset(['making', 'recipientName', 'recipientCompany', 'versionId', 'expiresInDays']);
    }

    public function revoke(string $id): void
    {
        $link = ProfileShareLink::findOrFail($id);
        abort_unless($this->mayRevoke($link), 403);

        Shares::revoke($link, Auth::user());
        $this->madeToken = '';
        $this->flash = 'That link is revoked. Anyone who opens it now sees the ended page, never the profile.';
    }

    private function mayRevoke(ProfileShareLink $link): bool
    {
        $user = Auth::user();

        return $user !== null && ($link->created_by === $user->id || $user->can('company_profile.manage'));
    }

    public function render()
    {
        $links = ProfileShareLink::query()->with('creator', 'version')->latest('created_at')->get();

        return view('livewire.hq.company-profile.shares', [
            'links' => $links,
            'mayRevoke' => fn (ProfileShareLink $link) => $this->mayRevoke($link),
            'versions' => ProfileVersion::query()->orderByDesc('number')->get(),
            'locales' => Sections::LOCALES,
            'madeUrl' => $this->madeToken === '' ? null : Shares::url($this->madeToken),
        ])->title('Share links');
    }
}
