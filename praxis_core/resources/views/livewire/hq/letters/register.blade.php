@php
    use App\Support\Letters\LetterTypes;
    $day = fn ($at) => $at ? \Carbon\Carbon::parse($at)->timezone('Africa/Lagos')->format('j M Y') : '—';
    $tabs = ['all' => 'All', 'drafts' => 'Drafts', 'issued' => 'Issued', 'signing' => 'Out for signature', 'signed' => 'Fully signed', 'revoked' => 'Revoked', 'replaced' => 'Replaced'];
    $badge = ['draft' => 'Draft', 'issued' => 'Issued', 'sent_for_signature' => 'Out for signature', 'fully_signed' => 'Fully signed', 'revoked' => 'Revoked', 'replaced' => 'Replaced'];
@endphp
<div class="docs">
    <style>
        /* HQ › Documents (docs/DOCUMENTS.md): only what the shared component classes don't cover. Brand tokens only. */
        .docs-tools { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 16px; margin-bottom: 16px; }
        .docs-tools .field { flex: 1 1 240px; max-width: 360px; margin: 0; }
        .docs-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .docs-ref { font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 600; color: var(--color-ink); }
        .docs-state { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .docs-state.is-draft { background: var(--color-mist); color: var(--color-muted); }
        .docs-state.is-issued { background: #E7F4EC; color: #1A6E47; }
        .docs-state.is-revoked { background: #FBE9E7; color: #B3261E; }
        .docs-state.is-replaced { background: #FBF1DF; color: #8A5700; }
        .docs-state.is-sent_for_signature { background: #E8EEFB; color: #2A4B9B; }
        .docs-state.is-fully_signed { background: #E7F4EC; color: #14603C; }
        .docs-blank { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; }
        .docs-blank + .docs-blank { margin-top: 10px; }
        .docs-blank strong { min-width: 190px; color: var(--color-ink); }
        .docs-types { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 0 0 16px; }
        .docs-type { display: block; text-align: left; padding: 12px 14px; border: 1px solid var(--color-line); border-radius: 10px; background: var(--color-paper); cursor: pointer; }
        .docs-type.is-on { border-color: var(--color-deep-teal); box-shadow: inset 0 0 0 1px var(--color-deep-teal); }
        .docs-type strong { display: block; color: var(--color-ink); }
        .docs-type span { display: block; margin-top: 2px; font-size: 0.8125rem; color: var(--color-muted); }
        .docs-modal { max-width: 640px; }
        @media (max-width: 600px) {
            .docs-types { grid-template-columns: 1fr; }
            .docs-blank strong { min-width: 0; width: 100%; }
            .docs .modal-actions { flex-direction: column-reverse; }
            .docs .modal-actions .btn { width: 100%; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Documents</h1>
            <p class="page-subtitle">Formal documents on the company letterhead: board resolutions, letters and authorities. Every one gets a reference and is kept here with the PDF that went out.</p>
        </div>
        @if ($canCreate)
            <button type="button" class="btn btn-primary" wire:click="choose"><i data-lucide="plus"></i> New document</button>
        @endif
    </div>


    <div class="card mb-6">
        <h2 class="card-title">A blank letterhead</h2>
        <p class="help">For a document you would rather type yourself. It is built from Master data › Legal entities each time, so it is never out of date.</p>
        @foreach ($companies as $company)
            <div class="docs-blank" wire:key="blank-{{ $company['id'] }}">
                <strong>{{ $company['legal_name'] }}</strong>
                <a class="btn btn-secondary btn-sm" href="{{ route('hq.letters.blank-pdf', ['entity' => $company['id'], 'download' => 1]) }}"><i data-lucide="file-text"></i> PDF</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('hq.letters.blank-docx', ['entity' => $company['id']]) }}"><i data-lucide="file-type"></i> Word</a>
            </div>
        @endforeach
    </div>

    <nav class="tabs" aria-label="Documents">
        @foreach ($tabs as $key => $label)
            <button type="button" class="tab {{ $show === $key ? 'is-active' : '' }}" aria-current="{{ $show === $key ? 'page' : 'false' }}" wire:click="$set('show', '{{ $key }}')">{{ $label }}</button>
        @endforeach
    </nav>

    <div class="docs-tools">
        <div class="field">
            <label class="label" for="docs-search">Search</label>
            <input id="docs-search" type="search" class="input" wire:model.live.debounce.400ms="search" placeholder="A reference, a title or who it went to">
        </div>
    </div>

    <div class="card" style="padding: 0">
        @if ($documents->isEmpty())
            <div class="empty-state">
                <p>No documents yet. The first one is usually a board resolution: a bank asks for one before it opens a corporate account.</p>
                @if ($canCreate)<button type="button" class="btn btn-primary mt-3" wire:click="choose"><i data-lucide="plus"></i> New document</button>@endif
            </div>
        @else
            <div class="table-wrap">
                <table class="table docs-table">
                    <thead>
                        <tr>
                            <th scope="col">Reference</th>
                            <th scope="col">Document</th>
                            <th scope="col">Date</th>
                            <th scope="col">Signed by</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $row)
                            <tr wire:key="d-{{ $row->id }}">
                                <td><a class="docs-ref" href="{{ route('hq.letters.document', ['document' => $row->id]) }}" wire:navigate>{{ $row->reference }}</a></td>
                                <td>
                                    {{ $row->title ?: 'Untitled' }}
                                    <small>{{ LetterTypes::label($row->type, $row->subtype) }}@if ($row->addressed_to) · to {{ $row->addressed_to }}@endif</small>
                                </td>
                                <td>{{ $day($row->document_date) }}<small>{{ $row->issued_at ? 'Issued ' . $day($row->issued_at) : 'Not issued' }}</small></td>
                                <td>
                                    @forelse ($row->signatures as $signature)
                                        {{ $signature->signatory_name }}<small>{{ $signature->signatory_title }}</small>
                                    @empty
                                        <span style="color: var(--color-muted)">—</span>
                                    @endforelse
                                </td>
                                <td>
                                    <span class="docs-state is-{{ $row->status }}">{{ $badge[$row->status] ?? $row->status }}</span>
                                    @if ($row->status === 'replaced' && $row->replacedBy)<small>by {{ $row->replacedBy->reference }}</small>@endif
                                    @if ($row->status === 'revoked')<small>{{ $day($row->revoked_at) }}</small>@endif
                                    @if ($row->show_qr)<small>Carries a QR code</small>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $documents->links() }}</div>
        @endif
    </div>

    @if ($choosing)
        <div class="modal-backdrop" wire:key="choose">
            <div class="modal docs-modal" role="dialog" aria-modal="true" aria-labelledby="docs-new-title">
                <h2 class="modal-title" id="docs-new-title">New document</h2>
                <p class="help">It will be <strong>{{ $nextReference }}</strong> once it is issued.</p>

                <div class="docs-types">
                    @foreach ($types as $key => $type)
                        <button type="button" class="docs-type {{ $newType === $key ? 'is-on' : '' }}" wire:click="$set('newType', '{{ $key }}')" aria-pressed="{{ $newType === $key ? 'true' : 'false' }}">
                            <strong>{{ $type['label'] }}</strong>
                            <span>{{ $type['summary'] }}</span>
                        </button>
                    @endforeach
                </div>

                @if ($subtypes = LetterTypes::subtypes($newType))
                    <div class="field">
                        <label class="label" for="docs-subtype">What it is for</label>
                        <select id="docs-subtype" class="input" wire:model="newSubtype">
                            @foreach ($subtypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="field">
                    <label class="label" for="docs-company">Whose letterhead</label>
                    <select id="docs-company" class="input" wire:model="newCompany">
                        @foreach ($companies as $company)
                            <option value="{{ $company['id'] }}">{{ $company['legal_name'] }} ({{ $company['region_code'] }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="$set('choosing', false)">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="create" wire:loading.attr="disabled">Start the draft</button>
                </div>
            </div>
        </div>
    @endif
</div>
