<div class="ws">
    <style>
        /* Website settings: only what the shared component classes don’t cover. Brand tokens only. */
        .ws { max-width: 960px; } /* a form reads best narrow; the header's Save button lines up with the cards */
        .ws-form { display: flex; flex-direction: column; gap: 24px; }
        .ws-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; display: flex; flex-direction: column; gap: 24px; }
        .ws-intro { margin: 4px 0 20px; }
        .ws-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); }
        .ws-grid-2 { grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr)); }
        .ws-sub { margin: 28px 0 4px; font-size: 0.9375rem; line-height: 1.375rem; font-weight: 600; color: var(--color-ink); }
        .ws-sub-help { margin: 0 0 12px; }
        .ws-rows { display: flex; flex-direction: column; gap: 12px; }
        .ws-row { display: grid; gap: 12px; align-items: end; padding: 12px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); }
        .ws-row-4 { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1.2fr) minmax(0, 1.3fr) auto; }
        .ws-row-3 { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 2fr) auto; }
        .ws-row-2 { grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto; }
        .ws-row .field { align-self: start; }
        .ws-person { padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-control); background: var(--color-paper); }
        .ws-person-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .ws-person-head h4 { margin: 0; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); }
        .ws-add { margin-top: 12px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .ws-switch { display: flex; align-items: flex-start; gap: 10px; }
        .ws-switch input { margin-top: 2px; flex-shrink: 0; }
        .ws-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 12px; }
        .ws-fieldset:disabled .ws-remove, .ws-fieldset:disabled .ws-add { display: none; }
        @media (max-width: 760px) {
            .ws-row-4, .ws-row-3, .ws-row-2 { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .ws-row-3 .ws-wide { grid-column: 1 / -1; }
            .ws-row .ws-remove { grid-column: 1 / -1; justify-self: start; }
        }
        @media (max-width: 480px) {
            .ws-row-4, .ws-row-3, .ws-row-2 { grid-template-columns: minmax(0, 1fr); }
        }
    </style>

    @php
        // Field helpers: aria attributes that tie each field to its message
        $invalid = fn (string $key) => $errors->has($key) ? 'aria-invalid=true aria-describedby=' . str_replace('.', '-', $key) . '-error' : '';
        $regs = $settings['company']['registrations'] ?? [];
        $offices = $settings['company']['offices'] ?? [];
        $phones = $settings['contact']['phones'] ?? [];
        $whatsapp = $settings['contact']['whatsapp'] ?? [];
        $people = $settings['leadership']['people'] ?? [];
        $showLeadership = (bool) ($settings['leadership']['show'] ?? false);
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Website settings</h1>
            <p class="page-subtitle">
                What jbspraxis.com shows about the company: legal details, contact, social links and leadership.
                @if ($lastSaved) Last saved {{ $lastSaved }}. @else Not saved yet: the website shows the defaults below. @endif
            </p>
        </div>
        @if ($canEdit)
            <div class="ws-actions">
                @if ($unsaved) <span class="help">Unsaved changes</span> @endif
                <button type="submit" form="ws-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                    <i data-lucide="save"></i>
                    <span wire:loading.remove wire:target="save">Save settings</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        @endif
    </div>

    @if ($flash)
        <div class="alert alert-success mb-6" role="status">
            <i data-lucide="circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="alert alert-info mb-6" role="note">
        <i data-lucide="info"></i>
        <span>WhatsApp numbers, opening hours, office addresses, the year founded and leadership are saved now. The new website shows them from its next release (PR2c); until then it shows the names, offices, registrations, phone numbers, email and social links.</span>
    </div>

    @unless ($canEdit)
        <div class="alert alert-info mb-6" role="status">
            <i data-lucide="info"></i>
            <span>You can view these settings but not change them. To change them, ask an administrator to change your role.</span>
        </div>
    @endunless

    @if ($errors->any())
        <div class="alert alert-danger mb-6" role="alert">
            <i data-lucide="circle-alert"></i>
            <div>
                <strong>We couldn’t save the settings.</strong> Fix the following and save again:
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form id="ws-form" class="ws-form" wire:submit="save" novalidate>
        <fieldset class="ws-fieldset" @disabled(! $canEdit)>
            <legend class="sr-only">Website settings</legend>

            {{-- Company --}}
            <section class="card" aria-labelledby="ws-company-title">
                <h2 class="card-title" id="ws-company-title">Company</h2>
                <p class="help ws-intro">The names, registrations and offices the website shows, for example in the footer.</p>

                <div class="ws-grid">
                    <div class="field">
                        <label class="label" for="ws-legal-name">Legal name</label>
                        <input id="ws-legal-name" type="text" class="input" maxlength="120" wire:model.live.blur="settings.company.legal_name" {!! $invalid('settings.company.legal_name') !!}>
                        @error('settings.company.legal_name') <span class="field-error" id="settings-company-legal_name-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ws-trading-name">Trading name</label>
                        <input id="ws-trading-name" type="text" class="input" maxlength="80" wire:model.live.blur="settings.company.trading_name" {!! $invalid('settings.company.trading_name') !!}>
                        @error('settings.company.trading_name') <span class="field-error" id="settings-company-trading_name-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ws-founded">Year founded (optional)</label>
                        <input id="ws-founded" type="text" inputmode="numeric" class="input num" style="text-align: left;" maxlength="4" wire:model.live.blur="settings.company.founded" {!! $invalid('settings.company.founded') !!}>
                        @error('settings.company.founded') <span class="field-error" id="settings-company-founded-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <h3 class="ws-sub">Registrations</h3>
                <p class="help ws-sub-help">While a registration is in progress, the website says ‘registration in progress’ and never shows a number.</p>
                <div class="ws-rows">
                    @foreach ($regs as $i => $row)
                        @php $k = "settings.company.registrations.{$i}"; @endphp
                        <div class="ws-row ws-row-4" wire:key="reg-{{ $i }}">
                            <div class="field">
                                <label class="label" for="ws-reg-{{ $i }}-country">Country</label>
                                <select id="ws-reg-{{ $i }}-country" class="select" wire:model.live="{{ $k }}.country" {!! $invalid("{$k}.country") !!}>
                                    @foreach ($countries as $code => $name) <option value="{{ $code }}">{{ $name }}</option> @endforeach
                                </select>
                                @error("{$k}.country") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-country-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ws-reg-{{ $i }}-label">Registry</label>
                                <input id="ws-reg-{{ $i }}-label" type="text" class="input" maxlength="40" placeholder="CAC" wire:model.live.blur="{{ $k }}.label" {!! $invalid("{$k}.label") !!}>
                                @error("{$k}.label") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-label-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ws-reg-{{ $i }}-number">Number</label>
                                <input id="ws-reg-{{ $i }}-number" type="text" class="input" maxlength="60" wire:model.live.blur="{{ $k }}.number" {!! $invalid("{$k}.number") !!}>
                                @error("{$k}.number") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-number-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ws-reg-{{ $i }}-status">Status</label>
                                <select id="ws-reg-{{ $i }}-status" class="select" wire:model.live="{{ $k }}.status" {!! $invalid("{$k}.status") !!}>
                                    @foreach ($statuses as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                                </select>
                                @error("{$k}.status") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-status-error">{{ $message }}</span> @enderror
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm ws-remove" wire:click="removeRow('company.registrations', {{ $i }})" aria-label="Remove registration {{ $i + 1 }}">
                                <i data-lucide="trash-2"></i> Remove
                            </button>
                        </div>
                    @endforeach
                    @if (! $regs) <p class="help">No registrations listed. The website shows none.</p> @endif
                </div>
                @error('settings.company.registrations') <p class="field-error">{{ $message }}</p> @enderror
                <div class="ws-add">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow('company.registrations')" @disabled(count($regs) >= $limits['company.registrations'])>
                        <i data-lucide="plus"></i> Add registration
                    </button>
                    @if (count($regs) >= $limits['company.registrations']) <span class="help">You can list up to {{ $limits['company.registrations'] }}.</span> @endif
                </div>

                <h3 class="ws-sub">Offices</h3>
                <p class="help ws-sub-help">Leave the address empty to show the city and country only.</p>
                <div class="ws-rows">
                    @foreach ($offices as $i => $row)
                        @php $k = "settings.company.offices.{$i}"; @endphp
                        <div class="ws-row ws-row-3" wire:key="office-{{ $i }}">
                            <div class="field">
                                <label class="label" for="ws-office-{{ $i }}-city">City</label>
                                <input id="ws-office-{{ $i }}-city" type="text" class="input" maxlength="80" wire:model.live.blur="{{ $k }}.city" {!! $invalid("{$k}.city") !!}>
                                @error("{$k}.city") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-city-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ws-office-{{ $i }}-country">Country</label>
                                <select id="ws-office-{{ $i }}-country" class="select" wire:model.live="{{ $k }}.country" {!! $invalid("{$k}.country") !!}>
                                    @foreach ($countries as $code => $name) <option value="{{ $code }}">{{ $name }}</option> @endforeach
                                </select>
                                @error("{$k}.country") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-country-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field ws-wide">
                                <label class="label" for="ws-office-{{ $i }}-address">Address (optional)</label>
                                <input id="ws-office-{{ $i }}-address" type="text" class="input" maxlength="200" wire:model.live.blur="{{ $k }}.address" {!! $invalid("{$k}.address") !!}>
                                @error("{$k}.address") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-address-error">{{ $message }}</span> @enderror
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm ws-remove" wire:click="removeRow('company.offices', {{ $i }})" aria-label="Remove office {{ $i + 1 }}">
                                <i data-lucide="trash-2"></i> Remove
                            </button>
                        </div>
                    @endforeach
                    @if (! $offices) <p class="help">No offices listed. The website shows none.</p> @endif
                </div>
                @error('settings.company.offices') <p class="field-error">{{ $message }}</p> @enderror
                <div class="ws-add">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow('company.offices')" @disabled(count($offices) >= $limits['company.offices'])>
                        <i data-lucide="plus"></i> Add office
                    </button>
                    @if (count($offices) >= $limits['company.offices']) <span class="help">You can list up to {{ $limits['company.offices'] }}.</span> @endif
                </div>
            </section>

            {{-- Contact --}}
            <section class="card" aria-labelledby="ws-contact-title">
                <h2 class="card-title" id="ws-contact-title">Contact</h2>
                <p class="help ws-intro">How visitors reach us from the website.</p>

                <div class="ws-grid">
                    <div class="field">
                        <label class="label" for="ws-email">General email</label>
                        <input id="ws-email" type="email" class="input" maxlength="120" autocomplete="off" wire:model.live.blur="settings.contact.email" {!! $invalid('settings.contact.email') !!}>
                        @error('settings.contact.email') <span class="field-error" id="settings-contact-email-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="ws-hours">Opening hours (optional)</label>
                        <input id="ws-hours" type="text" class="input" maxlength="120" placeholder="Monday to Friday, 09:00 to 17:00 (WAT)" wire:model.live.blur="settings.contact.hours" {!! $invalid('settings.contact.hours') !!}>
                        @error('settings.contact.hours') <span class="field-error" id="settings-contact-hours-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                @foreach (['phones' => ['Phone numbers', 'phone', 'Add phone number', 'No phone numbers listed.'], 'whatsapp' => ['WhatsApp', 'WhatsApp number', 'Add WhatsApp number', 'No WhatsApp numbers listed.']] as $list => [$heading, $noun, $addLabel, $emptyText])
                    @php $rows = $list === 'phones' ? $phones : $whatsapp; $limit = $limits["contact.{$list}"]; @endphp
                    <h3 class="ws-sub">{{ $heading }}</h3>
                    <p class="help ws-sub-help">Start with the country code, for example +234 800 000 0000 or +237 6 99 00 00 00.</p>
                    <div class="ws-rows">
                        @foreach ($rows as $i => $row)
                            @php $k = "settings.contact.{$list}.{$i}"; @endphp
                            <div class="ws-row ws-row-2" wire:key="{{ $list }}-{{ $i }}">
                                <div class="field">
                                    <label class="label" for="ws-{{ $list }}-{{ $i }}-label">Label</label>
                                    <input id="ws-{{ $list }}-{{ $i }}-label" type="text" class="input" maxlength="40" placeholder="Nigeria" wire:model.live.blur="{{ $k }}.label" {!! $invalid("{$k}.label") !!}>
                                    @error("{$k}.label") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-label-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="ws-{{ $list }}-{{ $i }}-number">Number</label>
                                    <input id="ws-{{ $list }}-{{ $i }}-number" type="tel" class="input" maxlength="24" placeholder="+234 800 000 0000" wire:model.live.blur="{{ $k }}.number" {!! $invalid("{$k}.number") !!}>
                                    @error("{$k}.number") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-number-error">{{ $message }}</span> @enderror
                                </div>
                                <button type="button" class="btn btn-ghost btn-sm ws-remove" wire:click="removeRow('contact.{{ $list }}', {{ $i }})" aria-label="Remove {{ $noun }} {{ $i + 1 }}">
                                    <i data-lucide="trash-2"></i> Remove
                                </button>
                            </div>
                        @endforeach
                        @if (! $rows) <p class="help">{{ $emptyText }}</p> @endif
                    </div>
                    @error("settings.contact.{$list}") <p class="field-error">{{ $message }}</p> @enderror
                    <div class="ws-add">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow('contact.{{ $list }}')" @disabled(count($rows) >= $limit)>
                            <i data-lucide="plus"></i> {{ $addLabel }}
                        </button>
                        @if (count($rows) >= $limit) <span class="help">You can list up to {{ $limit }}.</span> @endif
                    </div>
                @endforeach
            </section>

            {{-- Social links --}}
            <section class="card" aria-labelledby="ws-social-title">
                <h2 class="card-title" id="ws-social-title">Social links</h2>
                <p class="help ws-intro">Full links that start with https://. Leave a link empty if we don’t use that network.</p>

                <div class="ws-grid">
                    @foreach (['linkedin' => ['LinkedIn', 'https://www.linkedin.com/company/…'], 'x' => ['X', 'https://x.com/…'], 'youtube' => ['YouTube', 'https://www.youtube.com/@…']] as $network => [$label, $hint])
                        <div class="field">
                            <label class="label" for="ws-social-{{ $network }}">{{ $label }}</label>
                            <input id="ws-social-{{ $network }}" type="url" class="input" maxlength="255" placeholder="{{ $hint }}" wire:model.live.blur="settings.social.{{ $network }}" {!! $invalid("settings.social.{$network}") !!}>
                            @error("settings.social.{$network}") <span class="field-error" id="settings-social-{{ $network }}-error">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Leadership --}}
            <section class="card" aria-labelledby="ws-leadership-title">
                <div class="card-header" style="margin-bottom: 4px;">
                    <h2 class="card-title" id="ws-leadership-title">Leadership</h2>
                    <span class="pill {{ $showLeadership ? 'pill-warning' : 'pill-neutral' }}">{{ $showLeadership ? 'Shown on the website' : 'Hidden from the website' }}</span>
                </div>
                <p class="help ws-intro">Off by default. You can prepare the list below while it is off; nothing in it is published until you switch it on and save.</p>

                <div class="field">
                    <label class="ws-switch" for="ws-leadership-show">
                        <input id="ws-leadership-show" type="checkbox" class="checkbox" wire:model.live="settings.leadership.show" aria-describedby="ws-leadership-note">
                        <span class="label">Show leadership on the website</span>
                    </label>
                    @error('settings.leadership.show') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div id="ws-leadership-note" class="alert {{ $showLeadership ? 'alert-warning' : 'alert-info' }}" style="margin-top: 12px;">
                    <i data-lucide="{{ $showLeadership ? 'triangle-alert' : 'info' }}"></i>
                    <span>
                        @if ($showLeadership)
                            Names are public: jbspraxis.com shows the name, job title, bio, LinkedIn link and photo of everyone listed below.
                        @else
                            When this is on, names are public: jbspraxis.com shows the name, job title, bio, LinkedIn link and photo of everyone listed below.
                        @endif
                    </span>
                </div>

                <h3 class="ws-sub">People</h3>
                <p class="help ws-sub-help">Keep each bio to 2 or 3 sentences, in English and in French.</p>
                <div class="ws-rows">
                    @foreach ($people as $i => $row)
                        @php $k = "settings.leadership.people.{$i}"; @endphp
                        <div class="ws-person" wire:key="person-{{ $i }}">
                            <div class="ws-person-head">
                                <h4>Person {{ $i + 1 }}</h4>
                                <button type="button" class="btn btn-ghost btn-sm ws-remove" wire:click="removeRow('leadership.people', {{ $i }})" aria-label="Remove person {{ $i + 1 }}">
                                    <i data-lucide="trash-2"></i> Remove
                                </button>
                            </div>
                            <div class="ws-grid ws-grid-2">
                                <div class="field">
                                    <label class="label" for="ws-person-{{ $i }}-name">Name</label>
                                    <input id="ws-person-{{ $i }}-name" type="text" class="input" maxlength="80" autocomplete="off" wire:model.live.blur="{{ $k }}.name" {!! $invalid("{$k}.name") !!}>
                                    @error("{$k}.name") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-name-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="ws-person-{{ $i }}-title">Job title</label>
                                    <input id="ws-person-{{ $i }}-title" type="text" class="input" maxlength="80" wire:model.live.blur="{{ $k }}.title" {!! $invalid("{$k}.title") !!}>
                                    @error("{$k}.title") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-title-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="ws-person-{{ $i }}-linkedin">LinkedIn link (optional)</label>
                                    <input id="ws-person-{{ $i }}-linkedin" type="url" class="input" maxlength="255" placeholder="https://www.linkedin.com/in/…" wire:model.live.blur="{{ $k }}.linkedin" {!! $invalid("{$k}.linkedin") !!}>
                                    @error("{$k}.linkedin") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-linkedin-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="ws-person-{{ $i }}-photo">Photo link (optional)</label>
                                    <input id="ws-person-{{ $i }}-photo" type="url" class="input" maxlength="255" placeholder="https://…" wire:model.live.blur="{{ $k }}.photo_url" {!! $invalid("{$k}.photo_url") !!}>
                                    @error("{$k}.photo_url") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-photo_url-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="ws-grid ws-grid-2" style="margin-top: 16px;">
                                <div class="field">
                                    <label class="label" for="ws-person-{{ $i }}-bio-en">Short bio in English (optional)</label>
                                    <textarea id="ws-person-{{ $i }}-bio-en" class="textarea" rows="3" maxlength="400" wire:model.live.blur="{{ $k }}.bio_en" {!! $invalid("{$k}.bio_en") !!}></textarea>
                                    @error("{$k}.bio_en") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-bio_en-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="ws-person-{{ $i }}-bio-fr">Short bio in French (optional)</label>
                                    <textarea id="ws-person-{{ $i }}-bio-fr" class="textarea" rows="3" maxlength="400" lang="fr" wire:model.live.blur="{{ $k }}.bio_fr" {!! $invalid("{$k}.bio_fr") !!}></textarea>
                                    @error("{$k}.bio_fr") <span class="field-error" id="{{ str_replace('.', '-', $k) }}-bio_fr-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if (! $people) <p class="help">No one listed yet.</p> @endif
                </div>
                @error('settings.leadership.people') <p class="field-error">{{ $message }}</p> @enderror
                <div class="ws-add">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addRow('leadership.people')" @disabled(count($people) >= $limits['leadership.people'])>
                        <i data-lucide="plus"></i> Add person
                    </button>
                    @if (count($people) >= $limits['leadership.people']) <span class="help">You can list up to {{ $limits['leadership.people'] }}.</span> @endif
                </div>
            </section>

            @if ($canEdit)
                <div class="ws-actions">
                    @if ($unsaved) <span class="help">Unsaved changes</span> @endif
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <i data-lucide="save"></i>
                        <span wire:loading.remove wire:target="save">Save settings</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            @endif
        </fieldset>
    </form>
</div>
