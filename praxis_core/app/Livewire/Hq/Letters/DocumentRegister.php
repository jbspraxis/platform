<?php

namespace App\Livewire\Hq\Letters;

use App\Models\Letters\LetterDocument;
use App\Support\Letters\Letterhead;
use App\Support\Letters\Letters;
use App\Support\Letters\LetterTypes;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HQ › Documents (docs/DOCUMENTS.md section 2): the register of every formal document the firm has issued, with
 * its reference, who signed it and whether it still stands, plus New document and the blank letterhead to
 * download. Only the Owner role can open it until the owner ticks the letters module for another role.
 */
#[Layout('components.layouts.hq')]
class DocumentRegister extends Component
{
    use WithPagination;

    /** all, drafts, issued, revoked, replaced */
    #[Url(as: 'show', except: 'all')]
    public string $show = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $choosing = false;

    public string $newType = 'board_resolution';

    public string $newSubtype = 'open_bank_account';

    public string $newCompany = '';

    #[Locked]
    public bool $canCreate = false;

    #[Locked]
    public array $companies = [];

    private const FILTERS = [
        'all' => null,
        'drafts' => [LetterDocument::DRAFT],
        'issued' => [LetterDocument::ISSUED],
        'signing' => [LetterDocument::SENT_FOR_SIGNATURE],
        'signed' => [LetterDocument::FULLY_SIGNED],
        'revoked' => [LetterDocument::REVOKED],
        'replaced' => [LetterDocument::REPLACED],
    ];

    public function mount(): void
    {
        $this->canCreate = (bool) Auth::user()?->can('letters.create');
        $this->companies = Letterhead::companies();
        $this->newCompany = (string) ($this->companies[0]['id'] ?? '');
        if (! array_key_exists($this->show, self::FILTERS)) {
            $this->show = 'all';
        }
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedNewType(string $value): void
    {
        $subtypes = LetterTypes::subtypes($value);
        $this->newSubtype = $subtypes ? (string) array_key_first($subtypes) : '';
    }

    public function choose(): void
    {
        $this->authorize('letters.create');
        $this->choosing = true;
    }

    public function create()
    {
        $this->authorize('letters.create');
        $company = Letterhead::company($this->newCompany) ?? Letterhead::defaultCompany();
        abort_if($company === null, 422, 'There is no company to issue a document for.');

        $subtypes = LetterTypes::subtypes($this->newType);
        $subtype = $subtypes && isset($subtypes[$this->newSubtype]) ? $this->newSubtype : null;
        $document = Letters::start($this->newType, $subtype, $company, Auth::user());

        return $this->redirectRoute('hq.letters.document', ['document' => $document->id], navigate: true);
    }

    public function render()
    {
        $statuses = self::FILTERS[$this->show] ?? null;
        $term = trim($this->search);

        $documents = LetterDocument::query()
            ->with('signatures')
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', '%' . $term . '%')
                ->orWhere('title', 'like', '%' . $term . '%')
                ->orWhere('addressed_to', 'like', '%' . $term . '%')))
            ->orderByRaw("FIELD(status, 'draft') DESC")
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.hq.letters.register', [
            'documents' => $documents,
            'types' => LetterTypes::TYPES,
            'nextReference' => Letters::nextReference(LetterTypes::code($this->newType), (int) Letters::today()->format('Y')),
        ])->title('Documents');
    }
}
