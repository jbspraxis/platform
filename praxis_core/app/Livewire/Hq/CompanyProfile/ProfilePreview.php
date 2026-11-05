<?php

namespace App\Livewire\Hq\CompanyProfile;

use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\Profile;
use App\Support\CompanyProfile\Sections;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ › Company profile › Preview (docs/COMPANY_PROFILE.md section 6): any version, in either language,
 * exactly as the renderers will read it — unheld credentials and switched-off facts already gone, so what is
 * on this screen is what goes out.
 *
 * It is deliberately plain. The designed pages are PR 2's renderer; this is the proof of the content.
 */
#[Layout('components.layouts.hq')]
class ProfilePreview extends Component
{
    #[Locked]
    public string $versionId = '';

    #[Url(as: 'lang', except: 'en')]
    public string $locale = 'en';

    public function mount(ProfileVersion $version): void
    {
        $this->versionId = $version->id;

        if (! Sections::isLocale($this->locale)) {
            $this->locale = 'en';
        }
    }

    public function render()
    {
        $version = ProfileVersion::findOrFail($this->versionId);

        return view('livewire.hq.company-profile.preview', [
            'version' => $version,
            'data' => Profile::dataFor($version, $this->locale),
            'changes' => Profile::changes($version),
            'sections' => Sections::all(),
        ])->title('Company profile preview');
    }
}
