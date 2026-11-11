<div class="cpv">
    @assets
    <style>
        /* HQ › Company profile › Versions (docs/COMPANY_PROFILE.md section 6) */
        .cpv-row { border-top: 1px solid var(--color-line); padding: 14px 0; display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px 20px; }
        .cpv-row:first-of-type { border-top: 0; padding-top: 2px; }
        .cpv-name { font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); margin: 0; }
        .cpv-meta { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); margin: 2px 0 0; }
        .cpv-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 2px 10px; font-size: 0.75rem; line-height: 1.25rem; font-weight: 600; background: var(--color-mist); color: var(--color-slate); }
        .cpv-pill[data-status="published"] { background: color-mix(in srgb, var(--color-success) 14%, transparent); color: var(--color-success); }
        .cpv-pill[data-status="in_review"] { background: color-mix(in srgb, var(--color-warning) 18%, transparent); color: var(--color-warning); }
        .cpv-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .cpv-changes { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
        .cpv-change { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-slate); }
        .cpv-change strong { color: var(--color-ink); font-weight: 600; }
        .cpv-was { color: var(--color-muted); text-decoration: line-through; overflow-wrap: anywhere; }
        .cpv-now { color: var(--color-ink); overflow-wrap: anywhere; }
    </style>
    @endassets

    <div class="page-header">
        <div>
            <h1 class="page-title">Versions</h1>
            <p class="page-subtitle">Every version of the company profile there has ever been. The published one is what sales send; the rest are kept so any profile that went out can be read again exactly as it was.</p>
        </div>
        <div class="cpv-tools">
            @if ($canEdit && ! $open)
                <button type="button" class="btn btn-primary" wire:click="startDraft"><i data-lucide="plus"></i> Start a new version</button>
            @endif
            <a class="btn btn-quiet" href="{{ route('hq.company-profile.editor') }}" wire:navigate><i data-lucide="pencil"></i> Sections</a>
        </div>
    </div>

    @if ($flash)<div class="alert alert-success mb-6" role="status">{{ $flash }}</div>@endif

    @if ($open && $published && $changes)
        <div class="card mb-6">
            <h2 class="card-title">Changed since {{ $published->label() }}</h2>
            <ul class="cpv-changes">
                @foreach (collect($changes)->take(40) as $change)
                    <li class="cpv-change">
                        <strong>{{ $change['label'] }}</strong> · {{ strtoupper($change['locale']) }} ·
                        <span class="cpv-was">{{ \Illuminate\Support\Str::limit($change['was'], 90) ?: '—' }}</span>
                        <i data-lucide="arrow-right" style="width:12px;height:12px;vertical-align:-1px"></i>
                        <span class="cpv-now">{{ \Illuminate\Support\Str::limit($change['now'], 90) ?: '—' }}</span>
                    </li>
                @endforeach
            </ul>
            @if (count($changes) > 40)
                <p class="cpv-meta">and {{ count($changes) - 40 }} more.</p>
            @endif
        </div>
    @elseif ($open && $published)
        <div class="alert alert-info mb-6" role="status">{{ $open->label() }} says exactly what {{ $published->label() }} says. Nothing has changed yet.</div>
    @endif

    <div class="card">
        @forelse ($versions as $v)
            <div class="cpv-row" wire:key="version-{{ $v->id }}">
                <div>
                    <p class="cpv-name">{{ $v->label() }} <span class="cpv-pill" data-status="{{ $v->status }}">{{ $v->statusLabel() }}</span></p>
                    <p class="cpv-meta">
                        {{ $v->reference }}
                        · started {{ $v->created_at?->timezone('Africa/Lagos')->format('j M Y') }}@if ($v->creator) by {{ $v->creator->name }}@endif
                        @if ($v->submitted_at) · sent for review {{ $v->submitted_at->timezone('Africa/Lagos')->format('j M Y') }}@if ($v->submitter) by {{ $v->submitter->name }}@endif @endif
                        @if ($v->published_at) · published {{ $v->published_at->timezone('Africa/Lagos')->format('j M Y') }}@if ($v->reviewer) by {{ $v->reviewer->name }}@endif @endif
                    </p>
                    @if ($v->note)<p class="cpv-meta">{{ $v->note }}</p>@endif
                </div>
                <div class="cpv-tools">
                    <a class="btn btn-sm btn-quiet" href="{{ route('hq.company-profile.preview', $v) }}" wire:navigate><i data-lucide="eye"></i> Preview</a>
                    <a class="btn btn-sm btn-quiet" href="{{ route('company-profile.download', ['locale' => 'en', 'version' => $v->number]) }}" title="The PDF in English">PDF EN</a>
                    <a class="btn btn-sm btn-quiet" href="{{ route('company-profile.download', ['locale' => 'fr', 'version' => $v->number]) }}" title="The PDF in French">PDF FR</a>
                    <a class="btn btn-sm btn-quiet" href="{{ route('company-profile.deck', ['locale' => 'en', 'version' => $v->number]) }}" title="The deck in English">Deck EN</a>
                    <a class="btn btn-sm btn-quiet" href="{{ route('company-profile.deck', ['locale' => 'fr', 'version' => $v->number]) }}" title="The deck in French">Deck FR</a>
                    @if ($v->isOpen() && $canApprove)
                        <button type="button" class="btn btn-sm btn-primary" wire:click="publish('{{ $v->id }}')"
                                wire:confirm="Publish {{ $v->label() }}? It becomes what every download and share link carries.">Publish</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="cpv-meta">No version has been written yet.</p>
        @endforelse
    </div>
</div>
