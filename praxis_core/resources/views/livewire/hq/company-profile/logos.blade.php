@php
    /**
     * HQ › Company profile › Client logos (docs/COMPANY_PROFILE.md section 2). One library; the profile, the
     * deck and the strip on jbspraxis.com all read it. The browser makes the three renditions before anything
     * is sent (resources/company-profile/logos.js), because no PHP here can convert an image.
     */
    $file = fn ($logo, $kind = 'web') => route('hq.company-profile.logo-file', ['logo' => $logo->id, 'kind' => $kind]);
@endphp
<div class="logos">
    @assets
        <script src="{{ route('hq.company-profile.script', ['v' => filemtime(resource_path('company-profile/logos.js'))]) }}"></script>
    @endassets

    <style>
        /* Only what the shared component classes don't cover. Brand tokens only. */
        .logo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
        .logo-card { display: flex; flex-direction: column; border: 1px solid var(--color-line); border-radius: 12px; background: var(--color-paper); overflow: hidden; }
        .logo-card.is-retired { opacity: 0.6; }
        .logo-plate { display: flex; align-items: center; justify-content: center; min-height: 92px; padding: 14px; background: var(--color-mist); }
        .logo-plate img { max-height: 56px; max-width: 100%; }
        .logo-plate .logo-missing { font-size: 0.8125rem; color: var(--color-muted); }
        .logo-body { padding: 10px 12px 12px; }
        .logo-body strong { display: block; color: var(--color-ink); }
        .logo-body .sector { display: block; margin-top: 1px; font-size: 0.8125rem; color: var(--color-muted); }
        .logo-uses { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; }
        .logo-use { padding: 1px 8px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; background: var(--color-mist); color: var(--color-muted); }
        .logo-use.is-on { background: #E7F4EC; color: #1A6E47; }
        .logo-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .logo-order { display: flex; gap: 4px; margin-left: auto; }
        .logo-form { max-width: 720px; }
        .logo-pick { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .logo-pick .preview { display: flex; align-items: center; justify-content: center; width: 150px; height: 84px; border: 1px dashed var(--color-line); border-radius: 10px; background: var(--color-mist); }
        .logo-pick .preview img { max-height: 60px; max-width: 130px; }
        .logo-checks { display: flex; flex-wrap: wrap; gap: 8px 20px; }
        @media (max-width: 600px) { .logo-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Client logos</h1>
            <p class="page-subtitle">
                The one library the company profile, the deck and jbspraxis.com all read. A logo added here and marked
                for the website appears on the site by itself; nobody has to touch the code. Name each organisation the
                way the brand book allows it to be named.
            </p>
        </div>
        @if ($canEdit && ! $adding && $editing === '')
            <div class="page-actions">
                <button type="button" class="btn btn-primary" wire:click="add">Add a logo</button>
            </div>
        @endif
    </div>

    @if ($flash)
        <div class="alert alert-success" role="status" wire:key="flash-{{ $flash }}">{{ $flash }}</div>
    @endif

    @if ($adding || $editing !== '')
        <div class="card logo-form">
            <h2 class="card-title">{{ $editing !== '' ? 'Edit this logo' : 'Add a logo' }}</h2>

            <div class="field">
                <label class="label" for="logo-name">The organisation</label>
                <input id="logo-name" type="text" class="input" wire:model="name" maxlength="150">
                <p class="help">
                    Exactly as it may be named. A client who has not agreed to be named is described instead
                    (&ldquo;a leading pharmaceutical manufacturer&rdquo;), and then no logo is used.
                </p>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label class="label" for="logo-sector">Sector</label>
                <input id="logo-sector" type="text" class="input" wire:model="sector" maxlength="80" placeholder="Logistics">
                @error('sector')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field" x-data="logoPicker()">
                <span class="label">The logo file</span>
                <div class="logo-pick">
                    <span class="preview">
                        <template x-if="preview"><img :src="preview" alt=""></template>
                        <template x-if="! preview">
                            @if ($editing !== '')
                                <img src="{{ $file(\App\Models\CompanyProfile\ClientLogo::find($editing)) }}" alt="">
                            @else
                                <span class="logo-missing">No file yet</span>
                            @endif
                        </template>
                    </span>
                    <div>
                        <input type="file" id="logo-file" class="input" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp" x-on:change="choose($event)">
                        <p class="help">
                            An SVG is best: it stays sharp at any size. A PNG on transparency works too. The browser makes
                            the three copies each use needs &mdash; one for the PDF, one for the deck, one for the website.
                        </p>
                        <p class="help" x-show="busy" x-cloak>Preparing the file&hellip;</p>
                        <p class="field-error" x-show="error" x-text="error" x-cloak></p>
                        @if ($chosen)<p class="help">Chosen: {{ $chosen }}</p>@endif
                    </div>
                </div>
                @error('printUri')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <span class="label">Where it appears</span>
                <div class="logo-checks">
                    <label class="checkbox"><input type="checkbox" wire:model="onProfile"> Company profile</label>
                    <label class="checkbox"><input type="checkbox" wire:model="onDeck"> Deck</label>
                    <label class="checkbox"><input type="checkbox" wire:model="onWebsite"> Website</label>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn" wire:click="cancel">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">Save</button>
            </div>
        </div>
    @endif

    @if ($logos->isEmpty())
        <div class="empty-state">
            <p>No client logos yet. Add the first one and it will appear on the profile and, if you tick it, on the website.</p>
        </div>
    @else
        <div class="logo-grid">
            @foreach ($logos as $index => $logo)
                <div class="logo-card" wire:key="logo-{{ $logo->id }}">
                    <div class="logo-plate">
                        @if ($logo->web_path || $logo->png_path || $logo->print_path)
                            <img src="{{ $file($logo, $logo->web_path ? 'web' : ($logo->png_path ? 'png' : 'print')) }}" alt="{{ $logo->name }}">
                        @else
                            <span class="logo-missing">No file</span>
                        @endif
                    </div>
                    <div class="logo-body">
                        <strong>{{ $logo->name }}</strong>
                        <span class="sector">{{ $logo->sector ?: 'No sector' }} &middot; {{ $logo->print_kind === 'svg' ? 'SVG' : 'JPEG' }} for print</span>
                        <div class="logo-uses">
                            <span class="logo-use {{ $logo->on_profile ? 'is-on' : '' }}">Profile</span>
                            <span class="logo-use {{ $logo->on_deck ? 'is-on' : '' }}">Deck</span>
                            <span class="logo-use {{ $logo->on_website ? 'is-on' : '' }}">Website</span>
                        </div>
                        <div class="logo-actions">
                            @if ($canEdit)
                                <button type="button" class="btn btn-sm" wire:click="edit('{{ $logo->id }}')">Edit</button>
                            @endif
                            @if ($canManage)
                                <button type="button" class="btn btn-sm" wire:click="retire('{{ $logo->id }}')"
                                        wire:confirm="Retire {{ $logo->name }}? It comes off the profile, the deck and the website, and stays on the versions that already carried it.">Retire</button>
                            @endif
                            @if ($canEdit)
                                <span class="logo-order">
                                    <button type="button" class="btn btn-sm" wire:click="moveUp('{{ $logo->id }}')" @disabled($index === 0) aria-label="Move {{ $logo->name }} earlier">&uarr;</button>
                                    <button type="button" class="btn btn-sm" wire:click="moveDown('{{ $logo->id }}')" @disabled($index === $logos->count() - 1) aria-label="Move {{ $logo->name }} later">&darr;</button>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card" style="margin-top: 20px;">
        <label class="checkbox"><input type="checkbox" wire:model.live="showRetired"> Show retired logos</label>
        @if ($showRetired)
            @if ($retiredLogos->isEmpty())
                <p class="help">Nothing is retired.</p>
            @else
                <div class="logo-grid" style="margin-top: 12px;">
                    @foreach ($retiredLogos as $logo)
                        <div class="logo-card is-retired" wire:key="retired-{{ $logo->id }}">
                            <div class="logo-plate">
                                @if ($logo->web_path || $logo->print_path)
                                    <img src="{{ $file($logo, $logo->web_path ? 'web' : 'print') }}" alt="{{ $logo->name }}">
                                @else
                                    <span class="logo-missing">No file</span>
                                @endif
                            </div>
                            <div class="logo-body">
                                <strong>{{ $logo->name }}</strong>
                                <span class="sector">Retired {{ $logo->retired_at?->timezone('Africa/Lagos')->format('j M Y') }}</span>
                                @if ($canManage)
                                    <div class="logo-actions">
                                        <button type="button" class="btn btn-sm" wire:click="restore('{{ $logo->id }}')">Put it back</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
