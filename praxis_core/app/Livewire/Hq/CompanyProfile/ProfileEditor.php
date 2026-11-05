<?php

namespace App\Livewire\Hq\CompanyProfile;

use App\Models\CompanyProfile\ProfileSection;
use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\Editor;
use App\Support\CompanyProfile\Profile;
use App\Support\CompanyProfile\Sections;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ › Company profile (docs/COMPANY_PROFILE.md section 5): the section-by-section editor, English and French
 * side by side.
 *
 * It only ever edits a draft or a version in review. A published version opens read-only, with "Start a new
 * version" next to it: publishing never changes what has already gone out.
 */
#[Layout('components.layouts.hq')]
class ProfileEditor extends Component
{
    #[Url(as: 'section', except: 'cover')]
    public string $section = 'cover';

    /** en, fr or both */
    #[Url(as: 'lang', except: 'both')]
    public string $lang = 'both';

    #[Locked]
    public ?string $versionId = null;

    /** The section as it is being edited: what both languages share, and the words of each */
    public array $structure = [];

    public array $en = [];

    public array $fr = [];

    #[Locked]
    public bool $canEdit = false;

    #[Locked]
    public bool $canApprove = false;

    #[Locked]
    public bool $canManage = false;

    public string $note = '';

    public string $flash = '';

    public bool $confirmingDiscard = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->canEdit = (bool) $user?->can('company_profile.edit');
        $this->canApprove = (bool) $user?->can('company_profile.approve');
        $this->canManage = (bool) $user?->can('company_profile.manage');

        if (! in_array($this->section, Sections::KEYS, true)) {
            $this->section = 'cover';
        }
        if (! in_array($this->lang, ['en', 'fr', 'both'], true)) {
            $this->lang = 'both';
        }

        $version = Profile::working();
        $this->versionId = $version?->id;
        $this->load();
    }

    // ---------------------------------------------------------------- the version

    public function getVersionProperty(): ?ProfileVersion
    {
        return $this->versionId ? ProfileVersion::find($this->versionId) : null;
    }

    public function getEditableProperty(): bool
    {
        return $this->canEdit && (bool) $this->version?->isOpen();
    }

    public function getSpecProperty(): array
    {
        return Sections::get($this->section);
    }

    public function getChangeCountProperty(): int
    {
        return $this->version ? count(Profile::changes($this->version)) : 0;
    }

    private function load(): void
    {
        $version = $this->version;

        if (! $version) {
            $this->structure = Editor::blankStructure();
            $this->en = Editor::blankText();
            $this->fr = Editor::blankText();

            return;
        }

        $row = $this->row($version);
        $this->structure = $row->structure ?? Editor::blankStructure();
        $this->en = $row->text_en ?? Editor::blankText();
        $this->fr = $row->text_fr ?? Editor::blankText();

        Editor::reconcile($this->section, $this->structure, $this->en, $this->fr);
    }

    private function row(ProfileVersion $version): ProfileSection
    {
        return ProfileSection::firstOrCreate(
            ['version_id' => $version->id, 'key' => $this->section],
            ['structure' => Editor::blankStructure(), 'text_en' => Editor::blankText(), 'text_fr' => Editor::blankText()],
        );
    }

    /** Every word and every switch saves itself as it is changed; nobody loses a draft to a closed tab */
    public function updated(string $property): void
    {
        if (preg_match('/^(structure|en|fr)\./', $property)) {
            $this->save(quiet: true);
        }
    }

    public function updatedSection(string $value): void
    {
        $this->section = in_array($value, Sections::KEYS, true) ? $value : 'cover';
        $this->flash = '';
        $this->load();
    }

    public function openSection(string $key): void
    {
        $this->save(quiet: true);
        $this->updatedSection($key);
    }

    // ---------------------------------------------------------------- editing

    public function save(bool $quiet = false): void
    {
        $version = $this->version;

        if (! $this->editable || ! $version) {
            return;
        }

        Editor::reconcile($this->section, $this->structure, $this->en, $this->fr);

        $row = $this->row($version);
        $row->structure = $this->structure;
        $row->text_en = $this->en;
        $row->text_fr = $this->fr;
        $row->save();

        $version->touch();

        if (! $quiet) {
            $this->flash = Sections::label($this->section).' saved.';
            Profile::audit('COMPANY_PROFILE_EDITED', 'Edited '.Sections::label($this->section).' in '.$version->label(), $version);
        }
    }

    /** A path is "items" or "groups:<id>:items": the list, and the item each nested list sits inside */
    public function addItem(string $path): void
    {
        if (! $this->editable) {
            return;
        }

        Editor::add($this->structure, $this->en, $this->fr, $this->section, $this->path($path));
        $this->save(quiet: true);
    }

    public function removeItem(string $path, string $id): void
    {
        if (! $this->editable) {
            return;
        }

        Editor::remove($this->structure, $this->en, $this->fr, $this->section, $this->path($path), $id);
        $this->save(quiet: true);
    }

    /** Tick or untick every switch in one list at once (a team's credentials, the company facts) */
    public function setAll(string $path, bool $value): void
    {
        if (! $this->editable) {
            return;
        }

        $changed = Editor::setGate($this->structure, $this->section, $this->path($path), $value);
        $this->save(quiet: true);
        $this->flash = $changed === 0
            ? 'Nothing to change.'
            : $changed . ' ' . Str::plural('switch', $changed) . ' turned ' . ($value ? 'on' : 'off') . '.';
    }

    public function moveItem(string $path, string $id, int $delta): void
    {
        if (! $this->editable) {
            return;
        }

        Editor::move($this->structure, $this->section, $this->path($path), $id, $delta);
        $this->save(quiet: true);
    }

    private function path(string $path): array
    {
        return array_values(array_filter(explode(':', $path), fn ($part) => $part !== ''));
    }

    // ---------------------------------------------------------------- the workflow

    public function startDraft(): void
    {
        abort_unless($this->canEdit, 403);

        $version = Profile::startDraft();
        $this->versionId = $version->id;
        $this->load();
        $this->flash = 'Started '.$version->label().'. Nothing changes for anyone else until it is published.';
    }

    public function submitForReview(): void
    {
        abort_unless($this->canEdit, 403);

        $version = $this->version;
        if (! $version || $version->status !== ProfileVersion::DRAFT) {
            return;
        }

        $this->save(quiet: true);
        Profile::submit($version, $this->note !== '' ? $this->note : null);
        $this->note = '';
        $this->flash = $version->label().' is with the approver.';
    }

    public function publish(): void
    {
        abort_unless($this->canApprove, 403);

        $version = $this->version;
        if (! $version || ! $version->isOpen()) {
            return;
        }

        $this->save(quiet: true);
        Profile::publish($version);
        $this->flash = $version->label().' is published. It is what every download and share link now carries.';
    }

    public function sendBack(): void
    {
        abort_unless($this->canApprove, 403);

        $version = $this->version;
        if (! $version || $version->status !== ProfileVersion::IN_REVIEW) {
            return;
        }

        $this->validate(['note' => ['required', 'string', 'max:500']], [
            'note.required' => 'Say what needs changing before you send it back.',
        ]);

        Profile::returnToDraft($version, $this->note);
        $this->note = '';
        $this->flash = $version->label().' went back to its author.';
    }

    public function discardDraft(): void
    {
        abort_unless($this->canManage, 403);

        $version = $this->version;
        if (! $version || ! $version->isOpen()) {
            return;
        }

        Profile::discard($version);
        $this->confirmingDiscard = false;
        $this->versionId = Profile::working()?->id;
        $this->load();
        $this->flash = 'The draft is gone. The published version is untouched.';
    }

    public function render()
    {
        return view('livewire.hq.company-profile.editor', [
            'sections' => Sections::all(),
            'version' => $this->version,
            'spec' => $this->spec,
            'editable' => $this->editable,
            'changeCount' => $this->changeCount,
            'published' => Profile::published(),
        ])->title('Company profile');
    }
}
