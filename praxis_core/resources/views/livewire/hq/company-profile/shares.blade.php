@php
    /**
     * HQ › Company profile › Share links (docs/COMPANY_PROFILE.md section 14). A link per prospect, what it
     * opens, how often it has been opened, and the button that ends it.
     */
    $day = fn ($at) => $at ? \Carbon\Carbon::parse($at)->timezone('Africa/Lagos')->format('j M Y, H:i') : '—';
@endphp
<div class="shares">
    @assets
    <style>
        /* Only what the shared component classes don't cover. Brand tokens only. */
        .share-made { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
        .share-made code { flex: 1 1 320px; min-width: 0; overflow-wrap: anywhere; padding: 8px 10px;
            background: var(--color-mist); border-radius: 8px; font-size: 0.8125rem; color: var(--color-ink); }
        .share-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .share-state { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .share-state.is-ok { background: #E7F4EC; color: #1A6E47; }
        .share-state.is-revoked { background: #FBE9E7; color: #B3261E; }
        .share-state.is-expired { background: #FBF1DF; color: #8A5700; }
        .share-opens { font-variant-numeric: tabular-nums; }
        .share-form { max-width: 640px; }
        .share-two { display: flex; flex-wrap: wrap; gap: 0 16px; }
        .share-two .field { flex: 1 1 240px; min-width: 0; }
    </style>
    @endassets

    <div class="page-header">
        <div>
            <h1 class="page-title">Share links</h1>
            <p class="page-subtitle">
                A link per prospect. It opens the profile on jbspraxis.com with no sign-in, every open is counted and
                rings your bell, and you can end it at any time. The address carries a random token, never the version
                number, so nobody can guess one or walk the versions.
            </p>
        </div>
        @if (! $making)
            <div class="page-actions">
                <button type="button" class="btn btn-primary" wire:click="make">New link</button>
            </div>
        @endif
    </div>

    @if ($flash)
        <div class="alert alert-success" role="status" wire:key="flash-{{ md5($flash) }}">{{ $flash }}</div>
    @endif

    @if ($madeUrl)
        <div class="card">
            <h2 class="card-title">The link</h2>
            <div class="share-made">
                <code>{{ $madeUrl }}</code>
                <button type="button" class="btn btn-sm"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($madeUrl)).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                        x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
            <p class="help">Shown in full only now. After this the list shows what it is for, not the address.</p>
        </div>
    @endif

    @if ($making)
        <div class="card share-form">
            <h2 class="card-title">A new share link</h2>

            <div class="share-two">
                <div class="field">
                    <label class="label" for="share-name">Who it is for</label>
                    <input id="share-name" type="text" class="input" wire:model="recipientName" maxlength="150" placeholder="A. Mbeki">
                    @error('recipientName')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label class="label" for="share-company">Their company</label>
                    <input id="share-company" type="text" class="input" wire:model="recipientCompany" maxlength="200" placeholder="Foxtrot Foods Limited">
                    @error('recipientCompany')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="help">Both optional. They are never shown to the visitor; they name the link here and on your bell.</p>

            <div class="share-two">
                <div class="field">
                    <label class="label" for="share-locale">Language</label>
                    <select id="share-locale" class="input" wire:model="locale">
                        @foreach ($locales as $code)
                            <option value="{{ $code }}">{{ $code === 'fr' ? 'Français' : 'English' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="share-expiry">Stops working after</label>
                    <input id="share-expiry" type="number" class="input" wire:model="expiresInDays" min="1" max="365" placeholder="30">
                    <p class="help">Days. Leave it empty for a link that does not expire.</p>
                    @error('expiresInDays')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="field">
                <label class="label" for="share-version">Which version</label>
                <select id="share-version" class="input" wire:model="versionId">
                    <option value="">Whatever is published when it is opened</option>
                    @foreach ($versions as $version)
                        <option value="{{ $version->id }}">v{{ $version->number }} — {{ $version->reference }} ({{ $version->status }})</option>
                    @endforeach
                </select>
                <p class="help">Pin a version to send exactly what you read. Otherwise the link follows the published profile.</p>
                @error('versionId')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-actions">
                <button type="button" class="btn" wire:click="cancel">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="create" wire:loading.attr="disabled">Make the link</button>
            </div>
        </div>
    @endif

    @if ($links->isEmpty())
        <div class="empty-state">
            <p>No share links yet. Make one to send the profile to a prospect and see when they open it.</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table share-table">
                <thead>
                    <tr>
                        <th scope="col">For</th>
                        <th scope="col">Opens</th>
                        <th scope="col">Last opened</th>
                        <th scope="col">State</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($links as $link)
                        @php($state = $link->state())
                        <tr wire:key="share-{{ $link->id }}">
                            <td>
                                <strong>{{ $link->forWhom() }}</strong>
                                <small>
                                    {{ strtoupper($link->locale) }} &middot;
                                    {{ $link->version ? 'v' . $link->version->number : 'the published version' }} &middot;
                                    made by {{ $link->creator?->first_name }} {{ $link->creator?->last_name }}, {{ $day($link->created_at) }}
                                </small>
                            </td>
                            <td class="share-opens">{{ $link->opens }}</td>
                            <td>{{ $day($link->last_opened_at) }}</td>
                            <td>
                                <span class="share-state is-{{ $state }}">
                                    {{ ['ok' => 'Working', 'revoked' => 'Revoked', 'expired' => 'Expired'][$state] }}
                                </span>
                                @if ($state === 'ok' && $link->expires_at)
                                    <small>until {{ $day($link->expires_at) }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($state !== 'revoked' && $mayRevoke($link))
                                    <button type="button" class="btn btn-sm" wire:click="revoke('{{ $link->id }}')"
                                            wire:confirm="End this link? Anyone who opens it afterwards sees the ended page, never the profile.">Revoke</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
