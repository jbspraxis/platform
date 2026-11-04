<div class="sig">
    @assets
        <script src="{{ route('hq.letters.script', ['v' => filemtime(resource_path('letters/documents.js'))]) }}"></script>
    @endassets

    <style>
        /* HQ › Documents › My signature (docs/DOCUMENTS.md section 5) */
        .sig-wrap { max-width: 640px; }
        .sig-pad { width: 100%; height: 190px; border: 1px dashed var(--color-line-strong); border-radius: 8px; background: var(--color-paper); touch-action: none; cursor: crosshair; }
        .sig-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .sig-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
        .sig-current { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
        .sig-current img { max-height: 70px; max-width: 280px; background: #FFF; border: 1px solid var(--color-line); border-radius: 6px; padding: 6px; }
        .sig-name { font-weight: 600; color: var(--color-ink); }
        .sig-name small { display: block; font-weight: 400; color: var(--color-muted); }
        .sig-ready { display: flex; align-items: center; gap: 10px; margin-top: 10px; font-size: 0.875rem; color: var(--color-muted); }
        .sig-ready img { max-height: 48px; background: #FFF; border: 1px solid var(--color-line); border-radius: 6px; padding: 4px; }
        @media (max-width: 600px) {
            .sig-two { grid-template-columns: 1fr; }
            .sig-row .btn { width: 100%; }
            .sig .modal-actions { flex-direction: column-reverse; }
            .sig .modal-actions .btn { width: 100%; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">My signature</h1>
            <p class="page-subtitle">Draw it once, or upload a photograph of your signature on white paper. It is stored encrypted, never on a public address, and only you can put it on a document.</p>
        </div>
    </div>

    <x-flash class="mb-6" />
    @if ($flash)<div class="alert alert-success mb-6">{{ $flash }}</div>@endif

    @unless ($canSign)
        <div class="alert alert-info mb-6">You can save a signature here, but signing documents needs the Documents: Sign permission. Ask the owner for it on Roles and permissions.</div>
    @endunless

    <div class="sig-wrap">
        @if ($signature)
            <div class="card mb-6">
                <h2 class="card-title">The signature you have saved</h2>
                <div class="sig-current">
                    <img src="{{ $signature->dataUri() }}" alt="Your saved signature">
                    <span class="sig-name">{{ $signature->signatory_name }}<small>{{ $signature->signatory_title }}</small></span>
                </div>
                <div class="sig-row">
                    <button type="button" class="btn btn-secondary" wire:click="$set('confirmingRemoval', true)">Remove it</button>
                </div>
                <p class="help mt-3">Removing it takes nothing off the documents you have already signed: each of those keeps the signature as it was that day.</p>
            </div>
        @endif

        <div class="card">
            <h2 class="card-title">{{ $signature ? 'Replace it' : 'Save your signature' }}</h2>

            <div class="sig-two">
                <div class="field">
                    <label class="label" for="sig-name">Name, as it is printed under the signature</label>
                    <input id="sig-name" type="text" class="input" wire:model="signatoryName" maxlength="150">
                    @error('signatoryName')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label class="label" for="sig-title">Role</label>
                    <input id="sig-title" type="text" class="input" wire:model="signatoryTitle" maxlength="150" placeholder="Director">
                    @error('signatoryTitle')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="field" x-data="signaturePad()" wire:ignore>
                <span class="label">Draw it</span>
                <canvas class="sig-pad" x-ref="pad" aria-label="Draw your signature here"></canvas>
                <div class="sig-row">
                    <button type="button" class="btn btn-primary" @click="use()">Use this drawing</button>
                    <button type="button" class="btn btn-secondary" @click="clear()">Clear</button>
                    <label class="btn btn-secondary" style="cursor: pointer;">
                        <i data-lucide="image"></i> Upload a photograph instead
                        <input type="file" accept="image/*" class="sr-only" @change="chooseFile($event)">
                    </label>
                </div>
            </div>

            @if ($image !== '')
                <div class="sig-ready">
                    <img src="{{ $image }}" alt="The signature about to be saved">
                    <span>Ready to save{{ $kind === 'uploaded' ? ', from the image you chose' : '' }}.</span>
                </div>
            @endif
            @error('image')<p class="field-error">{{ $message }}</p>@enderror

            <div class="sig-row">
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">Save my signature</button>
            </div>
            <p class="help mt-3">Saving asks for your password again if you signed in more than ten minutes ago.</p>
        </div>
    </div>

    @if ($needsPassword)
        <div class="modal-backdrop" wire:key="sig-confirm">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="sig-confirm-title">
                <h2 class="modal-title" id="sig-confirm-title">Type your password again</h2>
                <p class="help">You signed in more than ten minutes ago. Saving a signature asks for your password, so a session left open cannot set one up for you.</p>
                <div class="field">
                    <label class="label" for="sig-password">Password</label>
                    <input id="sig-password" type="password" class="input" wire:model="confirmPassword" wire:keydown.enter="confirmIdentity" autocomplete="current-password">
                    @error('confirmPassword')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="cancelConfirm">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmIdentity">Confirm and save</button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingRemoval)
        <div class="modal-backdrop" wire:key="sig-remove">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="sig-remove-title">
                <h2 class="modal-title" id="sig-remove-title">Remove your signature?</h2>
                <p class="help">You will not be able to sign a document until you save a new one. Documents you have already signed are not touched.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="$set('confirmingRemoval', false)">Keep it</button>
                    <button type="button" class="btn btn-primary" wire:click="remove">Remove it</button>
                </div>
            </div>
        </div>
    @endif
</div>
