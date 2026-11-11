<?php

namespace App\Livewire\Hq\Mdm;

use App\Models\CompanyDeadline;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Support\CompanyDocuments\Calendar;
use App\Support\CompanyDocuments\Companies;
use App\Support\CompanyDocuments\Documents;
use App\Support\Finance\Vat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Master data › Company documents (owner's decisions, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md): each company's papers
 * with their expiry dates, and its compliance calendar. Filtered by company (Legal entities, plus the head office);
 * expired and due-soon items come first and are counted at the top. One document opens on its own page
 * (CompanyDocumentPage), where its versions and Claude's suggested details are.
 *
 * Who: mdm.view (the route) sees and downloads; mdm.create uploads and adds deadlines; mdm.edit edits deadlines and
 * marks them done; mdm.delete archives. Nothing is ever deleted.
 */
#[Layout('components.layouts.hq')]
#[Title('Company documents')]
class CompanyDocuments extends Component
{
    use WithFileUploads;

    /** A Companies key ('' = every company) */
    #[Url(except: '')]
    public string $company = '';

    /** documents | calendar */
    #[Url(except: 'documents')]
    public string $tab = 'documents';

    /** Show archived documents (and deadlines) instead of current ones */
    #[Url(except: false)]
    public bool $archived = false;

    // Upload a document
    public bool $uploadOpen = false;

    public array $newDoc = [];

    public $file = null;

    // Add or edit a deadline
    public bool $deadlineOpen = false;

    public ?string $deadlineId = null;

    public array $deadlineForm = [];

    // The deadline waiting for "Mark done" or "Archive" to be confirmed
    public ?string $confirmDoneId = null;

    public ?string $confirmArchiveId = null;

    public string $flash = '';

    /** The companies, read once per request (Companies::all() is up to three queries; not kept between requests) */
    private ?array $companies = null;

    private function companies(): array
    {
        return $this->companies ??= Companies::all();
    }

    public function mount(): void
    {
        if ($this->company !== '' && ! in_array($this->company, array_column($this->companies(), 'key'), true)) {
            $this->company = '';
        }
        if (! in_array($this->tab, ['documents', 'calendar'], true)) {
            $this->tab = 'documents';
        }
    }

    /** The company a new document or deadline starts with: the one filtered on, else Nigeria's */
    private function defaultCompany(): string
    {
        if ($this->company !== '') {
            return $this->company;
        }
        $home = collect($this->companies())->firstWhere('region', Vat::HOME);

        return $home['key'] ?? ($this->companies()[0]['key'] ?? Companies::GROUP);
    }

    public function openUpload(): void
    {
        abort_unless(Auth::user()->can('mdm.create'), 403);
        $this->resetErrorBag();
        $this->file = null;
        $this->newDoc = ['company' => $this->defaultCompany(), 'kind' => '', 'title' => '', 'issued_on' => '', 'expires_on' => '',
            'remind_days' => (string) Documents::DEFAULT_REMIND_DAYS, 'notes' => ''];
        $this->uploadOpen = true;
    }

    public function closeUpload(): void
    {
        $this->uploadOpen = false;
        $this->file = null;
        $this->resetErrorBag();
    }

    /** Choosing the kind names the document after it, until the person types a title of their own */
    public function updatedNewDoc($value, $key): void
    {
        if ($key === 'kind' && isset(Documents::KINDS[$value])
            && (trim((string) ($this->newDoc['title'] ?? '')) === '' || in_array($this->newDoc['title'], Documents::KINDS, true))) {
            $this->newDoc['title'] = $value === 'other' ? '' : Documents::KINDS[$value];
        }
    }

    public function saveUpload()
    {
        abort_unless(Auth::user()->can('mdm.create'), 403);
        $this->resetErrorBag();
        $this->validate(['file' => Documents::fileRules()], [
            'file.required' => 'Choose the file to upload.',
            'file.mimes' => 'Upload a PDF, JPG, PNG or Word (.docx) file.',
            'file.max' => 'The file is over 20 MB. Upload a smaller scan or a compressed PDF.',
        ], ['file' => 'file']);

        try {
            $doc = Documents::create($this->newDoc, $this->file, Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'file' ? 'file' : "newDoc.{$field}", $messages[0]);
            }

            return null;
        }
        $this->uploadOpen = false;
        $this->file = null;

        return $this->redirectRoute('hq.mdm.documents.show', ['document' => $doc->id], navigate: true);
    }

    public function newDeadline(): void
    {
        abort_unless(Auth::user()->can('mdm.create'), 403);
        $this->resetErrorBag();
        $this->deadlineId = null;
        $this->deadlineForm = ['company' => $this->defaultCompany(), 'title' => '', 'due_on' => '', 'repeats' => 'yearly',
            'remind_days' => (string) Documents::DEFAULT_REMIND_DAYS, 'notes' => ''];
        $this->deadlineOpen = true;
    }

    public function editDeadline(string $id): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $d = CompanyDeadline::findOrFail($id);
        $this->resetErrorBag();
        $this->deadlineId = $d->id;
        $this->deadlineForm = ['company' => Companies::keyFor($d->legal_entity_id), 'title' => $d->title, 'due_on' => $d->due_on->format('Y-m-d'),
            'repeats' => $d->repeats, 'remind_days' => $d->remind_days === null ? '' : (string) $d->remind_days, 'notes' => (string) $d->notes];
        $this->deadlineOpen = true;
    }

    public function closeDeadline(): void
    {
        $this->deadlineOpen = false;
        $this->deadlineId = null;
        $this->resetErrorBag();
    }

    public function saveDeadline(): void
    {
        abort_unless(Auth::user()->can($this->deadlineId ? 'mdm.edit' : 'mdm.create'), 403);
        $this->resetErrorBag();
        try {
            if ($this->deadlineId) {
                Calendar::update(CompanyDeadline::findOrFail($this->deadlineId), $this->deadlineForm, Auth::user());
                $this->flash = '‘' . trim($this->deadlineForm['title']) . '’ saved.';
            } else {
                $d = Calendar::add($this->deadlineForm, Auth::user());
                $this->flash = "‘{$d->title}’ added to the calendar, due " . Documents::day($d->due_on->format('Y-m-d')) . '.';
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("deadlineForm.{$field}", $messages[0]);
            }

            return;
        }
        $this->deadlineOpen = false;
        $this->deadlineId = null;
    }

    public function askDone(string $id): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $this->confirmArchiveId = null;
        $this->confirmDoneId = $id;
    }

    public function markDone(): void
    {
        abort_unless(Auth::user()->can('mdm.edit'), 403);
        $d = CompanyDeadline::find($this->confirmDoneId);
        $this->confirmDoneId = null;
        if (! $d) {
            return;
        }
        $next = Calendar::markDone($d, Auth::user());
        $this->flash = "‘{$d->title}’ marked done." . ($next ? ' Next year’s is in the calendar, due ' . Documents::day($next->due_on->format('Y-m-d')) . '.' : '');
    }

    public function askArchive(string $id): void
    {
        abort_unless(Auth::user()->can('mdm.delete'), 403);
        $this->confirmDoneId = null;
        $this->confirmArchiveId = $id;
    }

    public function archiveDeadline(): void
    {
        abort_unless(Auth::user()->can('mdm.delete'), 403);
        $d = CompanyDeadline::find($this->confirmArchiveId);
        $this->confirmArchiveId = null;
        if (! $d) {
            return;
        }
        Calendar::archive($d, Auth::user());
        $this->flash = "‘{$d->title}’ archived. It stays in the archive; nothing was deleted.";
    }

    public function cancelConfirm(): void
    {
        $this->confirmDoneId = null;
        $this->confirmArchiveId = null;
    }

    public function updatedCompany(): void
    {
        $this->flash = '';
    }

    /** Documents and deadlines filed under the company filtered on (all when none is) */
    private function scoped($query)
    {
        return $this->company === '' ? $query
            : ($this->company === Companies::GROUP ? $query->whereNull('legal_entity_id') : $query->where('legal_entity_id', $this->company));
    }

    public function render()
    {
        $today = Documents::today();
        $companies = $this->companies();
        $labels = collect($companies)->mapWithKeys(fn ($c) => [$c['key'] => $c['label']])->all();

        $order = ['expired' => 0, 'soon' => 1, 'valid' => 2, 'none' => 3, 'archived' => 4];
        $documents = $this->scoped(CompanyDocument::query())
            ->when($this->archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->orderBy('title')->get()
            ->map(function (CompanyDocument $d) use ($today) {
                $d->setAttribute('status', Documents::status($d, $today));

                return $d;
            })
            ->sortBy(fn ($d) => [$order[$d->status['key']], $d->expires_on?->format('Y-m-d') ?? '9999', $d->title])->values();
        $versions = CompanyDocumentVersion::whereIn('id', $documents->pluck('current_version_id')->filter())->get()->keyBy('id');

        // Every open deadline, and only the last ten done: the done ones pile up over the years
        $withStatus = function (CompanyDeadline $d) use ($today) {
            $d->setAttribute('status', Calendar::status($d, $today));

            return $d;
        };
        $open = $this->scoped(CompanyDeadline::query())->whereNull('archived_at')->whereNull('done_at')->orderBy('due_on')->get()
            ->map($withStatus)->values();
        $done = $this->scoped(CompanyDeadline::query())->whereNull('archived_at')->whereNotNull('done_at')
            ->orderByDesc('done_at')->orderBy('due_on')->limit(10)->get()->map($withStatus)->values();
        $people = DB::table('users')->whereIn('id', $done->pluck('done_by')->filter()->unique())->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);

        // What needs attention, for the company filtered on, whatever the tab
        $current = $this->archived ? $this->scoped(CompanyDocument::query())->whereNull('archived_at')->get()
            ->map(fn ($d) => $d->setAttribute('status', Documents::status($d, $today))) : $documents;
        $attention = [
            'expired' => $current->where('status.key', 'expired')->count(),
            'expiring' => $current->where('status.key', 'soon')->count(),
            'overdue' => $open->where('status.key', 'overdue')->count(),
            'due' => $open->where('status.key', 'soon')->count(),
        ];

        $counts = CompanyDocument::whereNull('archived_at')->selectRaw('legal_entity_id, COUNT(*) n')->groupBy('legal_entity_id')->pluck('n', 'legal_entity_id');

        return view('livewire.hq.mdm.company-documents', [
            'companies' => $companies,
            'labels' => $labels,
            'documents' => $documents,
            'versions' => $versions,
            'open' => $open,
            'done' => $done,
            'people' => $people,
            'attention' => $attention,
            'counts' => $counts,
            'archivedCount' => $this->scoped(CompanyDocument::query())->whereNotNull('archived_at')->count(),
            'kinds' => Documents::KINDS,
            'canCreate' => Auth::user()->can('mdm.create'),
            'canEdit' => Auth::user()->can('mdm.edit'),
            'canDelete' => Auth::user()->can('mdm.delete'),
            'confirmDone' => $this->confirmDoneId ? CompanyDeadline::find($this->confirmDoneId) : null,
            'confirmArchive' => $this->confirmArchiveId ? CompanyDeadline::find($this->confirmArchiveId) : null,
        ]);
    }
}
