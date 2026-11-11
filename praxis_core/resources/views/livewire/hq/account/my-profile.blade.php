<div class="mp">
    {{-- Static styles: sent once with the page, not again on every round trip --}}
    @assets
    <style>
        /* My profile: only what the shared component classes don't cover. Brand tokens only. */
        .mp { max-width: 820px; }
        .mp-cards { display: flex; flex-direction: column; gap: 24px; }
        .mp-who { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
        .mp-who .avatar { width: 80px; height: 80px; font-size: 1.5rem; }
        .mp-photo { position: relative; flex-shrink: 0; }
        .mp-photo-btn { position: relative; display: block; padding: 0; border: 0; background: none; border-radius: 50%; cursor: pointer; }
        .mp-photo-btn:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 3px; }
        .mp-photo-btn:hover .avatar { box-shadow: 0 0 0 2px var(--color-teal); }
        .mp-photo-cam { position: absolute; right: -2px; bottom: -2px; width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: var(--color-deep-teal); color: #fff; border: 2px solid var(--color-paper); }
        .mp-photo-cam svg { width: 14px; height: 14px; }
        .mp-who h2 { margin: 0; font-size: 1.125rem; line-height: 1.625rem; font-weight: 600; color: var(--color-ink); }
        .mp-facts { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 6px 16px; margin: 12px 0 0; }
        .mp-facts dt { color: var(--color-muted); font-size: 0.8125rem; line-height: 1.25rem; }
        .mp-facts dd { margin: 0; color: var(--color-ink); font-size: 0.875rem; line-height: 1.25rem; overflow-wrap: anywhere; }
        .mp-form { display: flex; flex-direction: column; gap: 16px; }
        .mp-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); }
        .mp-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .mp-section-title { margin: 0 0 4px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .mp-section-help { margin: 0 0 16px; }
    </style>
    @endassets

    <div class="page-header">
        <div>
            <h1 class="page-title">My profile</h1>
            <p class="page-subtitle">Your details in HQ, your phone numbers and how you sign in.</p>
        </div>
    </div>

    <div class="mp-cards">
        {{-- The photo is cropped to a square in the browser, then uploaded and saved (App\Support\Avatars) --}}
        <section class="card" aria-labelledby="mp-who"
                 x-data="{
                     preview: null, blob: null, busy: false, error: '',
                     pick() { this.error = ''; this.$refs.photoFile.click() },
                     async choose(e) {
                         const file = e.target.files[0]; e.target.value = '';
                         if (!file) return;
                         if (!/^image\//.test(file.type)) { this.error = 'Choose a photo (JPG, PNG or WebP).'; return }
                         if (file.size > 20 * 1024 * 1024) { this.error = 'Choose a photo under 20 MB.'; return }
                         let img = null;
                         try { img = await createImageBitmap(file, { imageOrientation: 'from-image' }) } catch (err) { img = null }
                         if (!img) { this.error = 'That photo couldn’t be opened. Try a JPG or PNG.'; return }
                         const side = Math.min(img.width, img.height), size = Math.min({{ \App\Support\Avatars::SIZE }}, side);
                         const canvas = document.createElement('canvas'); canvas.width = canvas.height = size;
                         canvas.getContext('2d').drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, size, size);
                         this.blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.9));
                         this.preview = canvas.toDataURL('image/jpeg', 0.9);
                     },
                     cancel() { this.preview = null; this.blob = null; this.error = '' },
                     save() {
                         if (!this.blob) return;
                         this.busy = true; this.error = '';
                         const file = new File([Alpine.raw(this.blob)], 'photo.jpg', { type: 'image/jpeg' });
                         this.$wire.upload('photo', file,
                             () => this.$wire.savePhoto().then(() => { this.busy = false; this.cancel() }),
                             () => { this.busy = false; this.error = 'The photo couldn’t be uploaded. Check the connection and try again.' });
                     },
                 }">
            @if ($photoFlash)
                <div class="alert alert-success mb-4" role="status"><i data-lucide="circle-check"></i><span>{{ $photoFlash }}</span></div>
            @endif
            <div class="mp-who">
                <div class="mp-photo">
                    <button type="button" class="mp-photo-btn" x-on:click="pick()" x-show="!preview" aria-label="{{ $user->avatar_path ? 'Change your photo' : 'Add a photo' }}" title="{{ $user->avatar_path ? 'Change your photo' : 'Add a photo' }}">
                        <x-hq.avatar :user="$user" />
                        <span class="mp-photo-cam" aria-hidden="true"><i data-lucide="camera"></i></span>
                    </button>
                    <img x-show="preview" x-bind:src="preview" alt="Your new photo" class="avatar" style="display: none; object-fit: cover;">
                    <input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" x-ref="photoFile" x-on:change="choose($event)" hidden>
                </div>
                <div style="min-width: 0; flex: 1 1 240px;">
                    <h2 id="mp-who">{{ $person['name'] }}</h2>
                    <div class="mp-actions mt-1" x-show="!preview">
                        <button type="button" class="btn btn-secondary btn-sm" x-on:click="pick()"><i data-lucide="camera"></i> {{ $user->avatar_path ? 'Change photo' : 'Add a photo' }}</button>
                        @if ($user->avatar_path)
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="removePhoto" wire:confirm="Remove your photo? HQ shows your initials instead.">Remove photo</button>
                        @endif
                    </div>
                    <div class="mp-actions mt-1" x-show="preview" style="display: none;">
                        <button type="button" class="btn btn-primary btn-sm" x-on:click="save()" x-bind:disabled="busy">
                            <span x-show="!busy">Save photo</span><span x-show="busy" style="display: none;">Saving…</span>
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" x-on:click="cancel()" x-bind:disabled="busy">Cancel</button>
                        <span class="help">Cropped to a square from the middle of the photo.</span>
                    </div>
                    <p class="field-error" x-show="error" x-text="error" style="display: none;"></p>
                    @error('photo') <p class="field-error">{{ $message }}</p> @enderror
                    <dl class="mp-facts">
                        <dt>Job title</dt><dd>{{ $person['title'] ?: 'Not set' }}</dd>
                        <dt>Email</dt><dd>{{ $user->email }}</dd>
                        <dt>Role</dt><dd>{{ $primaryRole }}</dd>
                        @if ($otherRoles !== '')<dt>Also has access as</dt><dd>{{ $otherRoles }}</dd>@endif
                        @if ($lastSignIn)<dt>Signed in</dt><dd>{{ $lastSignIn }} (WAT)</dd>@endif
                    </dl>
                    <p class="help mt-4" style="margin-bottom: 0;">Your job title comes from your HR record, your roles from Settings and access. To change them, ask HR or an administrator. Your photo shows in HQ only.</p>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="mp-numbers">
            <h2 id="mp-numbers" class="mp-section-title">Phone numbers</h2>
            <p class="help mp-section-help">Shown in your email signature. Leave a field empty to leave that number out.</p>
            @if ($numbersFlash)
                <div class="alert alert-success mb-4" role="status"><i data-lucide="circle-check"></i><span>{{ $numbersFlash }}</span></div>
            @endif
            <form wire:submit="saveNumbers" class="mp-form" novalidate>
                <div class="mp-grid">
                    <div class="field">
                        <label class="label" for="mp-mobile">Mobile</label>
                        <input id="mp-mobile" class="input" type="tel" autocomplete="tel" wire:model="mobile" maxlength="30" placeholder="+234 800 000 0000"
                               @error('mobile') aria-invalid="true" aria-describedby="mp-mobile-error" @enderror>
                        @error('mobile') <p class="field-error" id="mp-mobile-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="mp-office">Office line</label>
                        <input id="mp-office" class="input" type="tel" autocomplete="tel" wire:model="office" maxlength="30" placeholder="{{ $hrPhone !== '' ? $hrPhone : '+234 800 000 0000' }}"
                               @error('office') aria-invalid="true" aria-describedby="mp-office-error" @enderror>
                        @error('office') <p class="field-error" id="mp-office-error">{{ $message }}</p>
                        @else @if ($hrPhone !== '') <p class="help">Empty uses the number in your HR record ({{ $hrPhone }}).</p> @endif @enderror
                    </div>
                </div>
                <div class="mp-actions">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveNumbers"><i data-lucide="save"></i> Save numbers</button>
                    @if ($canUseMail)
                        <a href="{{ route('hq.mail.signature') }}" class="btn btn-ghost" wire:navigate>See your email signature</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="card" aria-labelledby="mp-password">
            <h2 id="mp-password" class="mp-section-title">Password</h2>
            <p class="help mp-section-help">The password you sign in to HQ with. Mailbox passwords are separate (Mail, Mailboxes).</p>
            @if ($passwordFlash)
                <div class="alert alert-success mb-4" role="status"><i data-lucide="circle-check"></i><span>{{ $passwordFlash }}</span></div>
            @endif
            <form wire:submit="changePassword" class="mp-form" novalidate>
                <div class="field" style="max-width: 360px;">
                    <label class="label" for="mp-current">Current password</label>
                    <input id="mp-current" class="input" type="password" autocomplete="current-password" wire:model="currentPassword"
                           @error('currentPassword') aria-invalid="true" aria-describedby="mp-current-error" @enderror>
                    @error('currentPassword') <p class="field-error" id="mp-current-error">{{ $message }}</p> @enderror
                </div>
                <div class="mp-grid">
                    <div class="field">
                        <label class="label" for="mp-new">New password</label>
                        <input id="mp-new" class="input" type="password" autocomplete="new-password" wire:model="newPassword"
                               @error('newPassword') aria-invalid="true" aria-describedby="mp-new-error" @enderror>
                        @error('newPassword') <p class="field-error" id="mp-new-error">{{ $message }}</p> @else <p class="help">At least 8 characters.</p> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="mp-confirm">New password again</label>
                        <input id="mp-confirm" class="input" type="password" autocomplete="new-password" wire:model="newPasswordConfirmation"
                               @error('newPasswordConfirmation') aria-invalid="true" aria-describedby="mp-confirm-error" @enderror>
                        @error('newPasswordConfirmation') <p class="field-error" id="mp-confirm-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mp-actions">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="changePassword"><i data-lucide="key-round"></i> Change password</button>
                </div>
            </form>
        </section>

        {{-- Passkeys and the PIN (docs/SIGN_IN.md) --}}
        <section class="card" aria-labelledby="mp-sign-in">
            <h2 id="mp-sign-in" class="mp-section-title">Sign-in</h2>
            <p class="help mp-section-help">Sign in with your fingerprint, face or a PIN instead of your password.</p>
            <livewire:account.sign-in-methods guard="web" />
        </section>

        <section class="card" aria-labelledby="mp-notify">
            <h2 id="mp-notify" class="mp-section-title">Notifications</h2>
            <p class="help mp-section-help">Which updates reach this device and your others as notifications.</p>
            <div class="mp-actions">
                <button type="button" class="btn btn-secondary" x-data x-on:click="$dispatch('open-notification-settings')"><i data-lucide="settings"></i> Notification settings</button>
            </div>
        </section>
    </div>
</div>
