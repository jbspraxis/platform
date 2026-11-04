<div class="kv">
    @assets
        <script src="{{ route('hq.system.key-vault.script', ['v' => \App\Http\Controllers\KeyVaultController::scriptVersion()]) }}"></script>
    @endassets

    <style>
        /* HQ › Settings and access › Key vault (docs/KEY_VAULT.md) */
        .kv { max-width: 760px; }
        .kv-copies { margin: 0; padding-left: 1.25rem; list-style: decimal; display: grid; gap: 6px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .kv-copies strong { color: var(--color-ink); font-weight: 600; }
        .kv-item { border-top: 1px solid var(--color-line); padding: 16px 0; }
        .kv-item:first-of-type { border-top: 0; padding-top: 4px; }
        .kv-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 8px 16px; }
        .kv-name { font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); margin: 0; overflow-wrap: anywhere; }
        .kv-meta { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); margin: 2px 0 0; }
        .kv-meta .kv-fp { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .kv-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .kv-panel { margin-top: 12px; display: grid; gap: 10px; }
        .kv-step { background: var(--color-mist); border-radius: 8px; padding: 12px; display: grid; gap: 10px; }
        .kv-code { max-width: 11rem; font-size: 1.125rem; letter-spacing: 0.2em; font-variant-numeric: tabular-nums; }
        .kv-secret { font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace; font-size: 0.8125rem; line-height: 1.25rem; min-height: 12rem; white-space: pre; overflow-wrap: normal; overflow-x: auto; }
        .kv-form { display: grid; gap: 14px; }
        .kv-two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
        .kv-pass { position: relative; }
        .kv-pass .input { padding-right: 4.5rem; }
        .kv-pass .kv-toggle { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); }
        .kv-meter { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; height: 6px; margin-top: 2px; }
        .kv-meter span { border-radius: 3px; background: var(--color-line); }
        .kv-meter[data-strength="weak"] span:nth-child(1) { background: var(--color-danger); }
        .kv-meter[data-strength="fair"] span:nth-child(-n+2) { background: var(--color-warning); }
        .kv-meter[data-strength="strong"] span { background: var(--color-success); }
        .kv-check { display: flex; gap: 10px; align-items: flex-start; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); }
        .kv-check input { margin-top: 3px; flex: none; }
        .kv-msg { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); margin: 0; }
        .kv-error { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-danger); margin: 0; }
        .kv .modal-actions .btn { white-space: normal; text-align: center; height: auto; }
        @media (max-width: 600px) {
            .kv-two { grid-template-columns: 1fr; }
            .kv-row .btn { flex: 1 1 100%; justify-content: center; }
            .kv-head .kv-row .btn { flex: 1 1 auto; }
            .kv .modal-actions { flex-direction: column-reverse; }
            .kv .modal-actions .btn { width: 100%; }
            .kv-code { max-width: none; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Key vault</h1>
            <p class="page-subtitle">Secrets only you can open, such as the server-backup private key. Your browser locks each one with a passphrase only you know; HQ keeps it locked and can never read it.</p>
        </div>
    </div>

    <x-flash class="mb-6" />
    @if ($flash)<div class="alert alert-success mb-6" role="status">{{ $flash }}</div>@endif

    <div class="card mb-6">
        <h2 class="card-title">Where the backup key lives</h2>
        <ol class="kv-copies">
            <li><strong>Bitwarden: the main copy.</strong> Use it first.</li>
            <li><strong>Here, in the vault.</strong> Opening it needs your password, a code emailed to you and your passphrase.</li>
            <li><strong>A locked copy in the jbspraxisllc@gmail.com Google Drive.</strong> One HTML file that opens offline in any browser with the passphrase, even if HQ and its server are gone.</li>
        </ol>
    </div>

    <div class="card mb-6">
        <h2 class="card-title">Your items</h2>
        @forelse ($items as $item)
            <div class="kv-item" wire:key="kv-item-{{ $item->id }}">
                <div class="kv-head">
                    <div>
                        <h3 class="kv-name">{{ $item->name }}</h3>
                        <p class="kv-meta">
                            Locked {{ $item->updated_at?->setTimezone('Africa/Lagos')->format('j M Y') }}
                            · {{ $item->last_revealed_at ? 'last opened ' . $item->last_revealed_at->setTimezone('Africa/Lagos')->format('j M Y, H:i') : 'never opened' }}
                            · fingerprint <span class="kv-fp">{{ $fingerprints[$item->id] }}</span>
                        </p>
                    </div>
                    <div class="kv-row">
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="startRename('{{ $item->id }}')">Rename</button>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="openReplace('{{ $item->id }}')">Replace</button>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="confirmDelete('{{ $item->id }}')">Delete</button>
                    </div>
                </div>

                @if ($renaming === $item->id)
                    <div class="kv-panel" wire:key="kv-rename-{{ $item->id }}">
                        <div class="field">
                            <label class="label" for="kv-rename-{{ $item->id }}">New name</label>
                            <input id="kv-rename-{{ $item->id }}" type="text" class="input" wire:model="newName" wire:keydown.enter="rename" maxlength="{{ \App\Support\KeyVault\KeyVault::NAME_MAX }}" autocomplete="off">
                            @error('newName')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="kv-row">
                            <button type="button" class="btn btn-primary" wire:click="rename">Save the name</button>
                            <button type="button" class="btn btn-secondary" wire:click="cancelRename">Cancel</button>
                        </div>
                    </div>
                @endif

                {{-- The reveal: kept by the browser, so Livewire never redraws a code, a passphrase or a secret --}}
                <div class="kv-panel" wire:ignore wire:key="kv-reveal-{{ $item->id }}"
                     x-data="keyVaultReveal(@js(['id' => $item->id, 'name' => $item->name, 'codeSent' => $codeItem === $item->id, 'sentTo' => $sentTo]))">
                    <div class="kv-row" x-show="step === 'idle'">
                        <button type="button" class="btn btn-primary" @click="start()" :disabled="busy">
                            <i data-lucide="key-round"></i> <span x-text="busy ? 'Sending a code…' : 'Reveal'">Reveal</span>
                        </button>
                    </div>

                    <div class="kv-step" x-show="step === 'code'" x-cloak>
                        <p class="kv-msg">We emailed a 6-digit code to <span x-text="sentTo"></span>. It works for 10 minutes, once, in this browser.</p>
                        <div class="field">
                            <label class="label" :for="'kv-code-' + id">Code from the email</label>
                            <input :id="'kv-code-' + id" x-ref="code" type="text" class="input kv-code" x-model="code" @keydown.enter.prevent="unlock()"
                                   inputmode="numeric" autocomplete="one-time-code" maxlength="7" spellcheck="false">
                        </div>
                        <div class="kv-row">
                            <button type="button" class="btn btn-primary" @click="unlock()" :disabled="busy"><span x-text="busy ? 'Checking…' : 'Unlock'">Unlock</span></button>
                            <button type="button" class="btn btn-secondary" @click="start()" :disabled="busy">Send a new code</button>
                            <button type="button" class="btn btn-ghost" @click="hide(); note = ''">Cancel</button>
                        </div>
                    </div>

                    <div class="kv-step" x-show="step === 'passphrase'" x-cloak>
                        <div class="field">
                            <label class="label" :for="'kv-pass-' + id">Passphrase</label>
                            <input :id="'kv-pass-' + id" x-ref="pass" type="password" class="input" x-model="passphrase" @keydown.enter.prevent="open()"
                                   autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-1p-ignore data-lpignore="true" data-bwignore>
                            <p class="help">The one you chose when you added it. It is checked here, in this browser: HQ never sees it.</p>
                        </div>
                        <div class="kv-row">
                            <button type="button" class="btn btn-primary" @click="open()" :disabled="busy"><span x-text="busy ? 'Opening…' : 'Show the secret'">Show the secret</span></button>
                            <button type="button" class="btn btn-ghost" @click="hide()">Cancel</button>
                        </div>
                    </div>

                    <div class="kv-step" x-show="step === 'shown'" x-cloak>
                        <div class="field">
                            <label class="label" :for="'kv-secret-' + id">The secret <span class="help">(hides itself in <span x-text="left"></span> seconds)</span></label>
                            <textarea :id="'kv-secret-' + id" x-ref="secret" class="input textarea kv-secret" readonly :value="secret"
                                      autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-gramm="false"></textarea>
                        </div>
                        <div class="kv-row">
                            <button type="button" class="btn btn-primary" @click="copy()"><i data-lucide="copy"></i> Copy</button>
                            <button type="button" class="btn btn-secondary" @click="save()"><i data-lucide="download"></i> Save as a file</button>
                            <button type="button" class="btn btn-secondary" @click="hide()"><i data-lucide="eye-off"></i> Hide it</button>
                        </div>
                    </div>

                    <p class="kv-msg" x-show="note" x-text="note" role="status" aria-live="polite"></p>
                    <p class="kv-error" x-show="error" x-text="error" role="alert"></p>
                </div>

                @if (in_array($item->id, $unlocked, true))
                    <div class="kv-panel" wire:key="kv-download-{{ $item->id }}">
                        <div class="kv-row">
                            <a class="btn btn-secondary" href="{{ route('hq.system.key-vault.locked-copy', ['item' => $item->id]) }}" download>
                                <i data-lucide="file-down"></i> Download a locked copy
                            </a>
                        </div>
                        <p class="help">One HTML file that opens offline with the passphrase. Put it in the jbspraxisllc@gmail.com Google Drive (not praxisjbs@gmail.com, which holds the backups). The download stays open for {{ \App\Support\KeyVault\KeyVault::UNLOCKED_MINUTES }} minutes after your code.</p>
                    </div>
                @endif

                @if ($replacing === $item->id)
                    <div class="kv-panel" wire:ignore wire:key="kv-replace-{{ $item->id }}" x-data="keyVaultLock(@js(['id' => $item->id, 'name' => $item->name]))">
                        <div class="kv-step">
                            <p class="kv-msg">Paste the secret again (or a new one) and choose its passphrase (the same or a new one). It replaces what is locked now. Locked copies you made before keep the old secret and the old passphrase.</p>
                            @include('livewire.hq.system.partials.key-vault-lock-fields', ['prefix' => 'kv-r-' . $item->id])
                            <div class="kv-row">
                                <button type="button" class="btn btn-primary" @click="submit()" :disabled="busy"><span x-text="busy ? 'Locking…' : 'Lock and replace'">Lock and replace</span></button>
                                <button type="button" class="btn btn-secondary" wire:click="closeReplace">Cancel</button>
                            </div>
                            <p class="kv-error" x-show="error" x-text="error" role="alert"></p>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="help">Nothing in the vault yet. Add the server-backup private key first: open <code>backup-private-key.pem</code> (in Documents › JBS Praxis backup key) with Notepad, copy all of it, and paste it below.</p>
        @endforelse
    </div>

    <div class="card" wire:ignore x-data="keyVaultLock()">
        <h2 class="card-title">Add a secret</h2>
        <div class="kv-form">
            <div class="alert alert-warning" x-show="!supported" x-cloak>This browser cannot lock secrets. Use a recent Chrome, Edge, Firefox or Safari.</div>
            <div class="field">
                <label class="label" for="kv-name">Name</label>
                <input id="kv-name" type="text" class="input" x-model="name" maxlength="{{ \App\Support\KeyVault\KeyVault::NAME_MAX }}" autocomplete="off" placeholder="Server backup private key">
                <p class="help">Only the name is kept in plain text, so you know which secret it is.</p>
            </div>
            @include('livewire.hq.system.partials.key-vault-lock-fields', ['prefix' => 'kv-a'])
            <div class="kv-row">
                <button type="button" class="btn btn-primary" @click="submit()" :disabled="busy"><i data-lucide="lock"></i> <span x-text="busy ? 'Locking…' : 'Lock and save'">Lock and save</span></button>
            </div>
            <p class="kv-error" x-show="error" x-text="error" role="alert"></p>
            <p class="help">Saving, revealing, renaming and deleting ask for your password again if you signed in more than ten minutes ago. Each one is written to the audit log; each reveal also emails you.</p>
        </div>
    </div>

    @if ($needsPassword)
        <div class="modal-backdrop" wire:key="kv-confirm">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="kv-confirm-title">
                <h2 class="modal-title" id="kv-confirm-title">Type your password again</h2>
                <p class="help">You signed in more than ten minutes ago. The key vault asks for your HQ password, so a session left open cannot be used to reach it.</p>
                <div class="field">
                    <label class="label" for="kv-password">HQ password</label>
                    <input id="kv-password" type="password" class="input" wire:model="confirmPassword" wire:keydown.enter="confirmIdentity" autocomplete="current-password" autofocus>
                    @error('confirmPassword')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="cancelConfirm">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmIdentity">Confirm</button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingDelete && ($doomed = $items->firstWhere('id', $confirmingDelete)))
        <div class="modal-backdrop" wire:key="kv-delete">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="kv-delete-title">
                <h2 class="modal-title" id="kv-delete-title">Delete “{{ $doomed->name }}” from the vault?</h2>
                <p class="help">HQ’s copy goes for good. Bitwarden and any locked copies you downloaded are not touched: they still open with their passphrase.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="cancelDelete">Keep it</button>
                    <button type="button" class="btn btn-danger" wire:click="delete">Delete “{{ \Illuminate\Support\Str::limit($doomed->name, 40) }}”</button>
                </div>
            </div>
        </div>
    @endif
</div>
