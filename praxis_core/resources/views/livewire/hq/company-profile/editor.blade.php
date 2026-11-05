<div class="cp">
    <style>
        /* HQ › Company profile (docs/COMPANY_PROFILE.md) */
        .cp-top { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px 24px; }
        .cp-state { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 6px; }
        .cp-pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 2px 10px; font-size: 0.75rem; line-height: 1.25rem; font-weight: 600; background: var(--color-mist); color: var(--color-slate); }
        .cp-pill[data-status="published"] { background: color-mix(in srgb, var(--color-success) 14%, transparent); color: var(--color-success); }
        .cp-pill[data-status="in_review"] { background: color-mix(in srgb, var(--color-warning) 18%, transparent); color: var(--color-warning); }
        .cp-ref { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .cp-body { display: grid; grid-template-columns: minmax(180px, 220px) minmax(0, 1fr); gap: 20px; align-items: start; }
        .cp-nav { display: grid; gap: 2px; position: sticky; top: 12px; }
        .cp-nav button { display: block; width: 100%; text-align: left; border: 0; background: none; border-radius: 8px; padding: 7px 10px; font: inherit; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); cursor: pointer; }
        .cp-nav button:hover { background: var(--color-mist); color: var(--color-ink); }
        .cp-nav button[aria-current="true"] { background: var(--color-mist); color: var(--color-ink); font-weight: 600; }
        .cp-blurb { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-muted); margin: 0 0 16px; }
        .cp-field { border-top: 1px solid var(--color-line); padding-top: 14px; margin-top: 14px; }
        .cp-field:first-of-type { border-top: 0; padding-top: 0; margin-top: 0; }
        .cp-field-label { display: block; font-size: 0.8125rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); margin-bottom: 6px; }
        .cp-langs { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px 16px; }
        .cp-shared { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px 16px; margin-top: 12px; }
        .cp-shared .field-label em, .field-label em { font-style: normal; font-weight: 400; color: var(--color-muted); }
        .cp-list { border: 1px solid var(--color-line); border-radius: 10px; padding: 14px; margin-top: 18px; }
        .cp-list .cp-list { background: var(--color-mist); }
        .cp-list-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .cp-list-title { font-size: 0.8125rem; line-height: 1.25rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--color-muted); margin: 0; }
        .cp-item { border-top: 1px solid var(--color-line); padding: 14px 0 4px; }
        .cp-item:first-of-type { border-top: 0; padding-top: 2px; }
        .cp-item-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .cp-item-no { font-size: 0.75rem; line-height: 1.25rem; color: var(--color-muted); text-transform: capitalize; }
        .cp-item-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .cp-switch { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-ink); }
        .cp-remove:hover { color: var(--color-danger); }
        .cp-empty { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-muted); margin: 0; }
        .cp-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 18px; }
        .cp-foot .input { max-width: 26rem; }
        @media (max-width: 860px) {
            .cp-body { grid-template-columns: minmax(0, 1fr); }
            .cp-nav { position: static; grid-auto-flow: column; grid-auto-columns: max-content; overflow-x: auto; gap: 4px; padding-bottom: 4px; }
            .cp-nav button { white-space: nowrap; }
        }
    </style>

    <div class="page-header cp-top">
        <div>
            <h1 class="page-title">Company profile</h1>
            <p class="page-subtitle">The firm's own profile, written once in English and French. Only a draft can be edited; what has been published never changes.</p>
            @if ($version)
                <div class="cp-state">
                    <span class="cp-pill" data-status="{{ $version->status }}">{{ $version->label() }} · {{ $version->statusLabel() }}</span>
                    <span class="cp-ref">{{ $version->reference }}</span>
                    @if ($changeCount > 0)
                        <span class="cp-ref">{{ $changeCount }} {{ \Illuminate\Support\Str::plural('change', $changeCount) }} since the published version</span>
                    @endif
                </div>
            @endif
        </div>
        <div class="cp-item-tools">
            <label class="field">
                <span class="field-label">Language</span>
                <select class="input" wire:model.live="lang">
                    <option value="both">Side by side</option>
                    <option value="en">English</option>
                    <option value="fr">French</option>
                </select>
            </label>
            @if ($version)
                <a class="btn btn-quiet" href="{{ route('company-profile.download', ['locale' => $lang === 'fr' ? 'fr' : 'en', 'version' => $version->number]) }}">
                    <i data-lucide="download"></i> Download
                </a>
                <a class="btn btn-quiet" href="{{ route('hq.company-profile.preview', $version) }}" wire:navigate>
                    <i data-lucide="eye"></i> Preview
                </a>
            @endif
        </div>
    </div>

    <x-flash class="mb-6" />
    @if ($flash)<div class="alert alert-success mb-6" role="status">{{ $flash }}</div>@endif

    @if (! $version)
        <div class="card">
            <h2 class="card-title">Nothing written yet</h2>
            <p class="cp-blurb">There is no version of the company profile. Starting one gives you every section, empty, in both languages.</p>
            @if ($canEdit)
                <button type="button" class="btn btn-primary" wire:click="startDraft"><i data-lucide="plus"></i> Start the first version</button>
            @else
                <p class="cp-empty">You can read the profile, but not write it. Ask the owner for Company profile: Edit.</p>
            @endif
        </div>
    @else
        @unless ($editable)
            <div class="alert alert-info mb-6" role="status">
                @if (! $canEdit)
                    You are reading {{ $version->label() }}. Editing it needs Company profile: Edit.
                @else
                    {{ $version->label() }} is {{ strtolower($version->statusLabel()) }}, so it cannot be changed. Start a new version to make changes.
                    <button type="button" class="btn btn-sm btn-quiet" wire:click="startDraft">Start a new version</button>
                @endif
            </div>
        @endunless

        <div class="cp-body">
            <nav class="cp-nav" aria-label="Profile sections">
                @foreach ($sections as $key => $def)
                    <button type="button" wire:click="openSection('{{ $key }}')" aria-current="{{ $key === $section ? 'true' : 'false' }}">
                        {{ $def['label'] }}
                    </button>
                @endforeach
            </nav>

            <div class="card">
                <h2 class="card-title">{{ $spec['label'] }}</h2>
                <p class="cp-blurb">{{ $spec['blurb'] }}</p>

                @php
                    $langs = $lang === 'both' ? ['en' => 'English', 'fr' => 'French'] : [$lang => $lang === 'fr' ? 'French' : 'English'];
                @endphp

                @foreach ($spec['fields'] ?? [] as $field => $fieldSpec)
                    <div class="cp-field">
                        <span class="cp-field-label">{{ $fieldSpec['label'] }}</span>
                        <div class="cp-langs">
                            @foreach ($langs as $code => $label)
                                <label class="field">
                                    <span class="field-label">{{ $label }}</span>
                                    @if ($fieldSpec['long'] ?? false)
                                        <textarea class="input" rows="3" wire:model.live.blur="{{ $code }}.fields.{{ $field }}" @disabled(! $editable)></textarea>
                                    @else
                                        <input type="text" class="input" wire:model.live.blur="{{ $code }}.fields.{{ $field }}" @disabled(! $editable)>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($spec['shared'] ?? false)
                    <div class="cp-shared">
                        @foreach ($spec['shared'] as $field => $fieldSpec)
                            <label class="field">
                                <span class="field-label">{{ $fieldSpec['label'] }} <em>both languages</em></span>
                                <input type="text" class="input" wire:model.live.blur="structure.shared.{{ $field }}" @disabled(! $editable)>
                            </label>
                        @endforeach
                    </div>
                @endif

                @if ($section === 'clients')
                    <p class="cp-blurb" style="margin-top:16px">
                        The logo wall is managed in the logo library, not here. The profile shows whatever the library holds when it is rendered.
                    </p>
                @endif

                @foreach ($spec['lists'] ?? [] as $list => $listSpec)
                    @include('livewire.hq.company-profile.partials.list', [
                        'list' => $list,
                        'spec' => $listSpec,
                        'items' => $structure['lists'][$list] ?? [],
                        'op' => $list,
                        'sBase' => 'structure.lists.' . $list,
                        'tBase' => 'lists.' . $list,
                        'editable' => $editable,
                        'lang' => $lang,
                    ])
                @endforeach

                <div class="cp-foot">
                    @if ($editable)
                        <button type="button" class="btn btn-quiet" wire:click="save"><i data-lucide="check"></i> Save</button>
                    @endif

                    @if ($version->status === 'draft' && $canEdit)
                        <input type="text" class="input" placeholder="What changed in this version?" wire:model="note" maxlength="500">
                        <button type="button" class="btn btn-primary" wire:click="submitForReview"><i data-lucide="send"></i> Send for review</button>
                    @endif

                    @if ($version->status === 'in_review' && $canApprove)
                        <input type="text" class="input" placeholder="If you send it back, say why" wire:model="note" maxlength="500">
                        <button type="button" class="btn btn-primary" wire:click="publish"
                                wire:confirm="Publish {{ $version->label() }}? It becomes what every download and share link carries."><i data-lucide="check-circle"></i> Approve and publish</button>
                        <button type="button" class="btn btn-quiet" wire:click="sendBack">Send back</button>
                    @elseif ($version->status === 'draft' && $canApprove)
                        <button type="button" class="btn btn-quiet" wire:click="publish"
                                wire:confirm="Publish {{ $version->label() }} without a review?">Publish now</button>
                    @endif

                    @if ($version->isOpen() && $canManage)
                        <button type="button" class="btn btn-quiet cp-remove" wire:click="discardDraft"
                                wire:confirm="Throw away {{ $version->label() }}? Everything written in it is lost. The published version is untouched.">
                            <i data-lucide="trash-2"></i> Throw the draft away
                        </button>
                    @endif
                </div>
                @error('note')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    @endif
</div>
