<div class="ms">
    <style>
        /* Mail → Signature (PR 3). Brand tokens only. */
        .ms { max-width: 960px; }
        .ms-grid { display: grid; gap: 24px; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); align-items: start; }
        .ms-preview { background: var(--color-paper); border: 1px solid var(--color-line); border-radius: var(--radius-card); }
        .ms-preview-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--color-line); }
        .ms-preview-head h2 { margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
        .ms-preview-controls { display: flex; flex-wrap: wrap; gap: 8px; }
        .ms-preview-controls .select { width: auto; max-width: 100%; }
        .ms-preview-body { padding: 20px; overflow-x: auto; }
        .ms-sample { margin: 0 0 4px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .ms-text { margin: 0; padding: 16px 20px; border-top: 1px solid var(--color-line); background: var(--color-mist); white-space: pre-wrap; font-family: var(--font-sans); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-slate); border-radius: 0 0 var(--radius-card) var(--radius-card); }
        .ms-facts { margin: 0; display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 8px 16px; font-size: 0.875rem; line-height: 1.375rem; }
        .ms-facts dt { color: var(--color-muted); }
        .ms-facts dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; }
        .ms-form { display: flex; flex-direction: column; gap: 16px; }
        .ms-form-2 { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); }
        .ms-section { margin-top: 32px; }
        .ms-stack { display: flex; flex-direction: column; gap: 24px; }
        @media (max-width: 900px) { .ms-grid { grid-template-columns: minmax(0, 1fr); } }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Signature</h1>
            <p class="page-subtitle">Your email signature, built from your HR record in the company design. HQ adds it when you send: the full one on new mail, a short one on replies.</p>
        </div>
        <a href="{{ route('hq.mail.compose.outbox') }}" class="btn btn-secondary" wire:navigate><i data-lucide="clock"></i> Scheduled and unsent</a>
    </div>

    @if ($flash)
        <div class="alert alert-success mb-6" role="status"><i data-lucide="circle-check"></i><span>{{ $flash }}</span></div>
    @endif

    <div class="ms-grid">
        <section class="ms-preview" aria-labelledby="ms-preview-title">
            <div class="ms-preview-head">
                <h2 id="ms-preview-title">How it looks</h2>
                <div class="ms-preview-controls">
                    @if ($accounts->count() > 1)
                        <label class="sr-only" for="ms-box">Mailbox</label>
                        <select id="ms-box" class="select" wire:model.live="previewAccountId">
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->email }}</option>
                            @endforeach
                        </select>
                    @endif
                    <label class="sr-only" for="ms-style">Version</label>
                    <select id="ms-style" class="select" wire:model.live="previewStyle">
                        <option value="full">Full, on new mail</option>
                        <option value="slim">Short, on replies</option>
                    </select>
                </div>
            </div>
            <div class="ms-preview-body">
                <p class="ms-sample">Hello Ada,</p>
                <p class="ms-sample">Thank you for your message. …</p>
                {!! $previewHtml !!}
            </div>
            <pre class="ms-text" aria-label="Plain-text version">{{ $previewText }}</pre>
        </section>

        <div class="ms-stack">
            <section class="card" aria-labelledby="ms-hr-title">
                <h2 id="ms-hr-title" class="card-title mb-4">From your HR record</h2>
                <dl class="ms-facts">
                    <dt>Name</dt><dd>{{ $person['name'] }}</dd>
                    <dt>Job title</dt><dd>{{ $person['title'] ?? 'Not set' }}</dd>
                    <dt>Department</dt><dd>{{ $person['department'] ?? 'Not set' }}</dd>
                </dl>
                <p class="help mt-4">To change these, ask HR to update your employee record.@if ($previewShared) On a shared mailbox the signature shows the shared address, not yours.@endif</p>
            </section>

            <section class="card" aria-labelledby="ms-numbers-title">
                <h2 id="ms-numbers-title" class="card-title mb-4">Your numbers</h2>
                <form class="ms-form" wire:submit="saveNumbers">
                    <div class="field">
                        <label class="label" for="ms-phone">Office phone</label>
                        <input id="ms-phone" class="input" type="tel" autocomplete="tel" wire:model="phone" placeholder="{{ $hrPhone ?: '+234 800 000 0000' }}">
                        @error('phone') <span class="field-error">{{ $message }}</span> @else <span class="help">{{ $hrPhone ? 'Leave empty to use ' . $hrPhone . ' from your HR record.' : 'For example +234 800 000 0000.' }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ms-mobile">Mobile</label>
                        <input id="ms-mobile" class="input" type="tel" autocomplete="tel" wire:model="mobile">
                        @error('mobile') <span class="field-error">{{ $message }}</span> @else <span class="help">Optional.</span> @enderror
                    </div>
                    <div><button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save numbers</button></div>
                </form>
            </section>
        </div>
    </div>

    @if ($canEditCompany)
        <section class="ms-section card" aria-labelledby="ms-company-title">
            <h2 id="ms-company-title" class="card-title">Company details</h2>
            <p class="help mt-1 mb-4">Shown in everyone’s full signature. Changes apply to the next messages sent.</p>
            <form class="ms-form" wire:submit="saveCompany">
                <div class="ms-form-2">
                    <div class="field">
                        <label class="label" for="ms-c-name">Company name</label>
                        <input id="ms-c-name" class="input" type="text" maxlength="80" wire:model="company.name">
                        @error('company.name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ms-c-website">Website</label>
                        <input id="ms-c-website" class="input" type="url" maxlength="120" wire:model="company.website" placeholder="https://jbspraxis.com">
                        @error('company.website') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ms-c-descriptor">Descriptor</label>
                        <input id="ms-c-descriptor" class="input" type="text" maxlength="120" wire:model="company.descriptor">
                        @error('company.descriptor') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ms-c-address">Offices</label>
                        <input id="ms-c-address" class="input" type="text" maxlength="120" wire:model="company.address" placeholder="Lagos and Douala">
                        @error('company.address') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ms-c-phone">Main phone</label>
                        <input id="ms-c-phone" class="input" type="tel" wire:model="company.phone">
                        @error('company.phone') <span class="field-error">{{ $message }}</span> @else <span class="help">Shown when a person has no number of their own.</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ms-c-logo">Logo address</label>
                        <input id="ms-c-logo" class="input" type="url" maxlength="300" wire:model="company.logo_url">
                        @error('company.logo_url') <span class="field-error">{{ $message }}</span> @else <span class="help">A PNG on the public website, on a white background.</span> @enderror
                    </div>
                </div>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="company.show_logo"> Show the logo</label>
                <div><button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save company details</button></div>
            </form>
        </section>
    @endif
</div>
