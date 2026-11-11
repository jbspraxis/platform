<?php

namespace App\Livewire\Hq\Mdm;

use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Support\CompanyDocuments\Companies;
use App\Support\CompanyDocuments\Documents;
use App\Support\CompanyDocuments\Reader;
use App\Support\CompanyDocuments\Suggestions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One company document (Master data › Company documents, docs/COMPANY_DOCUMENTS.md): its details, its file in every
 * version, and, for the kinds Claude reads, the "Suggested details" panel: HQ's value next to the document's, each
 * editable with a tick box. Nothing changes until the person applies; where they disagree, HQ keeps its value
 * unless the row is ticked. When Claude can't read the file, the person types the details into the same panel.
 *
 * Who: mdm.view sees and downloads; mdm.edit edits, replaces and applies; mdm.delete archives and restores.
 */
#[Layout('components.layouts.hq')]
class CompanyDocumentPage extends Component
{
    use WithFileUploads;

    public CompanyDocument $document;

    // Edit the details
    public bool $editing = false;

    public array $form = [];

    // Replace the file
    public bool $replaceOpen = false;

    public $replacement = null;

    public bool $confirmArchive = false;

    // The suggested details panel: values and ticks keyed by Suggestions::FIELDS key with '.' as '__' (wire:model paths)
    public array $values = [];

    public array $ticks = [];

    public array $suggestedDeadlines = [];

    /** The person types the details (Claude couldn't read the file) */
    public bool $typing = false;

    /** Show the panel again for a version whose suggestions were already applied or set aside */
    public bool $reviewAgain = false;

    /** What the panel's inputs were filled from: version id, reading state, typing */
    public string $panelFor = '';

    /** After Apply: ['applied' => lines, 'kept' => lines] */
    public ?array $result = null;

    public string $flash = '';

    public function mount(CompanyDocument $document): void
    {
        $this->document = $document;
        $this->syncPanel();
    }

    private function version(): ?CompanyDocumentVersion
    {
        return $this->document->current_version_id ? CompanyDocumentVersion::find($this->document->current_version_id) : null;
    }

    /** Fills the panel's inputs from Claude's reading (or blank, when typing), once per version and state */
    private function syncPanel(bool $force = false): void
    {
        $version = $this->version();
        if (! $version || ! Reader::reads($this->document)) {
            return;
        }
        $reading = Reader::state($version);
        $key = $version->id . ':' . $reading['state'] . ':' . ($this->typing ? 'typing' : 'read');
        if (! $force && $key === $this->panelFor) {
            return;
        }
        $this->panelFor = $key;
        if ($reading['state'] !== 'read' && ! $this->typing) {
            return;
        }
        $built = Suggestions::build($version, Auth::user(), $this->typing ? null : $reading['output']);
        $this->values = [];
        $this->ticks = [];
        foreach ($built['rows'] as $row) {
            $wire = self::wireKey($row['key']);
            $this->values[$wire] = $row['found'] !== '' ? $row['found'] : '';
            $this->ticks[$wire] = $row['tick'];
        }
        $this->suggestedDeadlines = array_map(fn ($d) => ['title' => $d['title'], 'due_on' => $d['due_on'], 'repeats' => $d['repeats'], 'tick' => $d['tick']], $built['deadlines']);
        $this->resetErrorBag();
    }

    private static function wireKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** The poll while Claude reads: fills the panel once the reading is in */
    public function checkReading(): void
    {
        $this->document->refresh();
        $before = $this->panelFor;
        $this->syncPanel();
        // Claude is still reading the same version, as three seconds ago: nothing on the page changes, so it isn't sent again
        $version = (string) $this->document->current_version_id;
        if ($this->panelFor === $before && $version !== '' && str_starts_with($before, $version . ':reading:') && Reader::reads($this->document)) {
            $this->skipRender();
        }
    }

    public function readAgain(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $version = $this->version();
        if (! $version) {
            return;
        }
        Reader::start($version);
        $this->typing = false;
        $this->result = null;
        $this->syncPanel(true);
    }

    public function typeDetails(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $this->typing = true;
        $this->result = null;
        $this->syncPanel(true);
    }

    public function startReview(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $this->reviewAgain = true;
        $this->result = null;
        $this->syncPanel(true);
    }

    public function applySuggestions(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $version = $this->version();
        if (! $version) {
            return;
        }
        $this->resetErrorBag();
        $reading = Reader::state($version);
        $values = [];
        $ticks = [];
        foreach (Suggestions::FIELDS as $key => $field) {
            $values[$key] = (string) ($this->values[self::wireKey($key)] ?? '');
            $ticks[$key] = (bool) ($this->ticks[self::wireKey($key)] ?? false);
        }

        try {
            $this->result = Suggestions::apply($version, Auth::user(), $this->typing ? null : $reading['output'], $values, $ticks, $this->suggestedDeadlines);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(str_starts_with($field, 'values.') ? 'values.' . self::wireKey(substr($field, 7)) : $field, $messages[0]);
            }

            return;
        }
        $this->typing = false;
        $this->reviewAgain = false;
        $this->document->refresh();
    }

    public function setAside(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $version = $this->version();
        if (! $version) {
            return;
        }
        $reading = Reader::state($version);
        Suggestions::setAside($version, Auth::user(), $this->typing ? null : $reading['output']);
        $this->typing = false;
        $this->reviewAgain = false;
        $this->result = null;
        $this->flash = 'HQ kept its details as they are. Nothing was applied from this document.';
    }

    public function startEdit(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $d = $this->document;
        $this->form = ['company' => Companies::keyFor($d->legal_entity_id), 'kind' => $d->kind, 'title' => $d->title,
            'issued_on' => $d->issued_on?->format('Y-m-d') ?? '', 'expires_on' => $d->expires_on?->format('Y-m-d') ?? '',
            'remind_days' => $d->remind_days === null ? '' : (string) $d->remind_days, 'notes' => (string) $d->notes];
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->resetErrorBag();
    }

    public function saveDetails(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $this->resetErrorBag();
        try {
            $changed = Documents::update($this->document, $this->form, Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("form.{$field}", $messages[0]);
            }

            return;
        }
        $this->editing = false;
        $this->document->refresh();
        $this->flash = $changed ? 'Details saved.' : 'Nothing had changed.';
        $this->syncPanel(true);
    }

    public function openReplace(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $this->replacement = null;
        $this->resetErrorBag();
        $this->replaceOpen = true;
    }

    public function closeReplace(): void
    {
        $this->replaceOpen = false;
        $this->replacement = null;
        $this->resetErrorBag();
    }

    public function replaceFile(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $this->validate(['replacement' => Documents::fileRules()], [
            'replacement.required' => 'Choose the new file.',
            'replacement.mimes' => 'Upload a PDF, JPG, PNG or Word (.docx) file.',
            'replacement.max' => 'The file is over 20 MB. Upload a smaller scan or a compressed PDF.',
        ], ['replacement' => 'file']);
        try {
            $version = Documents::replace($this->document, $this->replacement, Auth::user());
        } catch (ValidationException $e) {
            $this->addError('replacement', collect($e->errors())->flatten()->first());

            return;
        }
        $this->replaceOpen = false;
        $this->replacement = null;
        $this->document->refresh();
        $this->typing = false;
        $this->reviewAgain = false;
        $this->result = null;
        $this->flash = "Version {$version->number} uploaded. The earlier versions are kept below.";
        $this->syncPanel(true);
    }

    public function askArchive(): void
    {
        abort_unless(Auth::user()->can('mdm.delete'), 403);
        $this->confirmArchive = true;
    }

    public function cancelArchive(): void
    {
        $this->confirmArchive = false;
    }

    public function archiveDocument(): void
    {
        abort_unless(Auth::user()->can('mdm.delete'), 403);
        Documents::archive($this->document, Auth::user());
        $this->confirmArchive = false;
        $this->document->refresh();
        $this->flash = 'Document archived. It and all its versions are kept; restore it here at any time.';
    }

    public function restoreDocument(): void
    {
        abort_unless(Auth::user()->can('mdm.delete'), 403);
        Documents::restore($this->document, Auth::user());
        $this->document->refresh();
        $this->flash = 'Document restored to the list.';
    }

    public function render()
    {
        $doc = $this->document;
        $version = $this->version();
        $reading = $version && Reader::reads($doc) ? Reader::state($version) : null;
        $reviewed = $version?->reviewed_at !== null;
        $panelOpen = $reading && (! $reviewed || $this->reviewAgain) && $this->result === null;
        $built = $panelOpen && ($reading['state'] === 'read' || $this->typing)
            ? Suggestions::build($version, Auth::user(), $this->typing ? null : $reading['output']) : null;

        $versions = CompanyDocumentVersion::where('document_id', $doc->id)->orderByDesc('number')->get();
        $people = DB::table('users')->whereIn('id', $versions->pluck('uploaded_by')->merge($versions->pluck('reviewed_by'))->push($doc->archived_by)->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);

        return view('livewire.hq.mdm.company-document-page', [
            'doc' => $doc,
            'version' => $version,
            'versions' => $versions,
            'people' => $people,
            'status' => Documents::status($doc),
            'companyLabel' => Companies::label($doc->legal_entity_id, $companies = Companies::all()),
            'companies' => $companies,
            'kinds' => Documents::KINDS,
            'reading' => $reading,
            'reviewed' => $reviewed,
            'panelOpen' => $panelOpen,
            'built' => $built,
            'canEdit' => Auth::user()->can('mdm.edit'),
            'canDelete' => Auth::user()->can('mdm.delete'),
        ])->title($doc->title . ' · Company documents');
    }
}
