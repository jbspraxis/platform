<div class="mp">
    <style>
        /* My profile: only what the shared component classes don't cover. Brand tokens only. */
        .mp { max-width: 820px; }
        .mp-cards { display: flex; flex-direction: column; gap: 24px; }
        .mp-who { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
        .mp-who .avatar { width: 64px; height: 64px; font-size: 1.25rem; }
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

    <div class="page-header">
        <div>
            <h1 class="page-title">My profile</h1>
            <p class="page-subtitle">Your details in HQ, your phone numbers and your password.</p>
        </div>
    </div>

    <div class="mp-cards">
        <section class="card" aria-labelledby="mp-who">
            <div class="mp-who">
                <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                <div style="min-width: 0; flex: 1 1 240px;">
                    <h2 id="mp-who">{{ $person['name'] }}</h2>
                    <dl class="mp-facts">
                        <dt>Job title</dt><dd>{{ $person['title'] ?: 'Not set' }}</dd>
                        <dt>Department</dt><dd>{{ $person['department'] ?: 'Not set' }}</dd>
                        <dt>Email</dt><dd>{{ $user->email }}</dd>
                        <dt>Roles</dt><dd>{{ $user->role_names ?: 'Staff' }}</dd>
                        @if ($lastSignIn)<dt>Signed in</dt><dd>{{ $lastSignIn }} (WAT)</dd>@endif
                    </dl>
                    <p class="help mt-4" style="margin-bottom: 0;">These come from your HR record and your roles. To change them, ask HR or an administrator.</p>
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

        <section class="card" aria-labelledby="mp-notify">
            <h2 id="mp-notify" class="mp-section-title">Notifications</h2>
            <p class="help mp-section-help">Which updates reach this device and your others as notifications.</p>
            <div class="mp-actions">
                <button type="button" class="btn btn-secondary" x-data x-on:click="$dispatch('open-notification-settings')"><i data-lucide="settings"></i> Notification settings</button>
            </div>
        </section>
    </div>
</div>
