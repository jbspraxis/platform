<div class="cp">
    @assets
    <style>
        /* One client (HQ › Clients): only what the shared component classes don't cover. Brand tokens only. */
        .cp { max-width: 1120px; }
        .cp-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 0.875rem; text-decoration: none; }
        .cp-back svg { width: 16px; height: 16px; }
        .cp-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-top: 8px; font-size: 0.875rem; color: var(--color-muted); }
        .cp-meta > span { display: inline-flex; align-items: center; gap: 6px; }
        .cp-meta svg { width: 16px; height: 16px; flex-shrink: 0; }
        .cp-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); align-items: start; }
        .cp-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .cp-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
        .cp-card-head .card-title { margin: 0; }
        .cp-note { display: flex; gap: 8px; align-items: flex-start; margin: 0 0 16px; }
        .cp-note svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
        .cp-dl { display: grid; grid-template-columns: minmax(130px, auto) 1fr; gap: 8px 16px; margin: 0; font-size: 0.875rem; line-height: 1.375rem; }
        .cp-dl dt { color: var(--color-muted); }
        .cp-dl dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; }
        .cp-dl dd.is-empty { color: var(--color-warning); }
        .cp-address { white-space: pre-line; }
        .cp-form { display: flex; flex-direction: column; gap: 16px; }
        .cp-row { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr)); align-items: start; }
        .cp-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .cp-optional { font-weight: 400; color: var(--color-muted); }
        .cp-radios { display: flex; flex-wrap: wrap; gap: 16px; }
        .cp-radios label { display: inline-flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .cp-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .cp-list > li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px; padding: 12px 0; border-top: 1px solid var(--color-line); font-size: 0.875rem; }
        .cp-list > li:first-child { border-top: 0; padding-top: 0; }
        .cp-person { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .cp-person-name { font-weight: 600; color: var(--color-ink); display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .cp-ways { display: flex; flex-wrap: wrap; gap: 4px 12px; color: var(--color-slate); }
        .cp-ways a { overflow-wrap: anywhere; }
        .cp-portal { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }
        .cp-panel { width: 100%; padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist); }
        .cp-money { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--color-ink); white-space: nowrap; }
        .cp-total { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-line-strong); font-size: 0.875rem; }
        .cp-foot { margin-top: 12px; }
        @media (max-width: 900px) { .cp-grid { grid-template-columns: 1fr; } }
        @media (max-width: 600px) {
            .cp-dl { grid-template-columns: 1fr; gap: 2px; }
            .cp-dl dd { margin-bottom: 8px; }
            .cp-actions .btn { flex: 1 1 100%; }
            .cp-portal { justify-content: flex-start; }
        }
    </style>
    @endassets

    @php
        $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->locale(app()->getLocale())->translatedFormat('j M Y') : '';
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 2);
        $isPast = $client->status === 'Past Client';
        $language = strtoupper((string) $client->preferred_language) === 'FR' ? 'FR' : 'EN';
        $printed = [
            'name' => $client->name,
            'address' => $client->billing_address,
            'registration_number' => $client->registration_number,
            'tax_number' => $client->tax_identification_number,
            'email' => $client->email,
            'phone' => $client->phone,
        ];
        $missingList = collect($missing)->map(fn ($k) => __('clients.missing_names.' . $k))->implode(', ');
        $statePill = ['none' => 'pill-neutral', 'invited' => 'pill-warning', 'active' => 'pill-success', 'suspended' => 'pill-danger'];
        // Errors on fields this form doesn't show (Leads::updateDetails checks them all)
        $shown = array_merge(\App\Livewire\Hq\Clients\ClientPage::FIELDS, $askIndustry ? ['industry'] : []);
        $otherErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'details.') && ! in_array(substr($k, 8), $shown, true))->map(fn ($m) => $m[0]);
    @endphp

    <a href="{{ route('hq.clients') }}" class="cp-back" wire:navigate><i data-lucide="arrow-left"></i> {{ __('clients.back') }}</a>

    <div class="page-header">
        <div style="min-width: 0">
            <h1 class="page-title" style="overflow-wrap: anywhere">{{ $client->name }}</h1>
            <div class="cp-meta">
                <span class="pill {{ $isPast ? 'pill-neutral' : 'pill-success' }}">{{ __('clients.status.' . $client->status) }}</span>
                @if ($client->client_since)
                    <span><i data-lucide="calendar"></i> {{ __('clients.since', ['date' => $day($client->client_since)]) }}</span>
                @endif
                @if ($client->country || $client->city)
                    <span><i data-lucide="map-pin"></i> {{ collect([$client->city, $client->country])->filter()->implode(', ') }}</span>
                @endif
            </div>
        </div>
        @if ($leadUrl)
            <div class="cp-actions">
                <a href="{{ $leadUrl }}" class="btn btn-secondary" wire:navigate><i data-lucide="target"></i> {{ __('clients.open_lead') }}</a>
            </div>
        @endif
    </div>

    <div class="cp-grid">
        <div class="cp-col">
            {{-- What prints in the invoice's "Billed to" box (resources/views/pdf/invoice.blade.php) --}}
            <section class="card" aria-labelledby="cp-details-title">
                <div class="cp-card-head">
                    <h2 class="card-title" id="cp-details-title">{{ __('clients.details.title') }}</h2>
                    @if ($canEdit && ! $editing)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="editDetails" wire:loading.attr="disabled" wire:target="editDetails"><i data-lucide="pencil"></i> {{ __('clients.details.edit') }}</button>
                    @endif
                </div>
                <p class="help cp-note"><i data-lucide="info" aria-hidden="true"></i> <span>{{ __('clients.details.note') }}</span></p>

                @if ($editing)
                    <form wire:submit="saveDetails" class="cp-form" novalidate aria-labelledby="cp-details-title">
                        @if ($otherErrors->isNotEmpty())
                            <div class="alert alert-danger" role="alert">
                                <i data-lucide="circle-alert" aria-hidden="true"></i>
                                <div>{{ __('clients.details.other_errors') }} {{ $otherErrors->implode(' ') }}</div>
                            </div>
                        @endif
                        <div class="field">
                            <label class="label" for="cp-name">{{ __('clients.fields.name') }}</label>
                            <input id="cp-name" type="text" class="input" maxlength="150" wire:model="details.name" @error('details.name') aria-invalid="true" aria-describedby="cp-name-error" @enderror>
                            @error('details.name') <span class="field-error" id="cp-name-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="cp-address">{{ __('clients.fields.address') }}</label>
                            <textarea id="cp-address" class="textarea" rows="3" maxlength="2000" wire:model="details.address" @error('details.address') aria-invalid="true" aria-describedby="cp-address-error" @enderror></textarea>
                            @error('details.address') <span class="field-error" id="cp-address-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="cp-row">
                            <div class="field">
                                <label class="label" for="cp-rc">{{ __('clients.fields.registration_number') }} <span class="cp-optional">({{ __('clients.details.rc_help') }})</span></label>
                                <input id="cp-rc" type="text" class="input" maxlength="60" wire:model="details.registration_number" placeholder="{{ __('clients.details.rc_placeholder') }}" @error('details.registration_number') aria-invalid="true" aria-describedby="cp-rc-error" @enderror>
                                @error('details.registration_number') <span class="field-error" id="cp-rc-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="cp-tin">{{ __('clients.fields.tax_number') }} <span class="cp-optional">({{ __('clients.details.tax_help') }})</span></label>
                                <input id="cp-tin" type="text" class="input" maxlength="100" wire:model="details.tax_number" @error('details.tax_number') aria-invalid="true" aria-describedby="cp-tin-error" @enderror>
                                @error('details.tax_number') <span class="field-error" id="cp-tin-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="cp-row">
                            <div class="field">
                                <label class="label" for="cp-email">{{ __('clients.fields.email') }}</label>
                                <input id="cp-email" type="email" class="input" maxlength="150" wire:model="details.email" placeholder="{{ __('clients.details.email_placeholder') }}" @error('details.email') aria-invalid="true" aria-describedby="cp-email-error" @enderror>
                                @error('details.email') <span class="field-error" id="cp-email-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="cp-phone">{{ __('clients.fields.phone') }}</label>
                                <input id="cp-phone" type="tel" class="input" maxlength="30" wire:model="details.phone" placeholder="{{ __('clients.details.phone_placeholder') }}" @error('details.phone') aria-invalid="true" aria-describedby="cp-phone-error" @enderror>
                                @error('details.phone') <span class="field-error" id="cp-phone-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="cp-row">
                            <div class="field">
                                <label class="label" for="cp-country">{{ __('clients.fields.country') }}</label>
                                <input id="cp-country" type="text" class="input" maxlength="100" list="cp-countries" wire:model="details.country" @error('details.country') aria-invalid="true" aria-describedby="cp-country-error" @enderror>
                                <datalist id="cp-countries">
                                    @foreach ($countries as $c) <option value="{{ $c }}"></option> @endforeach
                                </datalist>
                                @error('details.country') <span class="field-error" id="cp-country-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="cp-city">{{ __('clients.fields.city') }} <span class="cp-optional">{{ __('clients.details.optional') }}</span></label>
                                <input id="cp-city" type="text" class="input" maxlength="100" wire:model="details.city" @error('details.city') aria-invalid="true" aria-describedby="cp-city-error" @enderror>
                                @error('details.city') <span class="field-error" id="cp-city-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        @if ($askIndustry)
                            <div class="field">
                                <label class="label" for="cp-industry">{{ __('clients.fields.industry') }}</label>
                                <select id="cp-industry" class="select" wire:model="details.industry" aria-describedby="cp-industry-help @error('details.industry') cp-industry-error @enderror" @error('details.industry') aria-invalid="true" @enderror>
                                    <option value="">{{ __('clients.details.choose_industry') }}</option>
                                    @foreach ($industries as $industry)
                                        <option value="{{ $industry }}">{{ $industry }}</option>
                                    @endforeach
                                </select>
                                <span class="help" id="cp-industry-help">{{ __('clients.details.industry_help') }}</span>
                                @error('details.industry') <span class="field-error" id="cp-industry-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                            <legend class="label" style="margin-bottom: 6px">{{ __('clients.fields.preferred_language') }}</legend>
                            <div class="cp-radios">
                                @foreach (__('clients.details.languages') as $code => $label)
                                    <label><input type="radio" value="{{ $code }}" wire:model="details.preferred_language"> {{ $label }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="cp-actions">
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveDetails"><i data-lucide="save"></i> {{ __('clients.details.save') }}</button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelDetails">{{ __('clients.details.cancel') }}</button>
                        </div>
                    </form>
                @else
                    @if ($missing)
                        <div class="alert alert-warning" role="status" style="margin-bottom: 16px">
                            <i data-lucide="triangle-alert" aria-hidden="true"></i>
                            <div>{{ __('clients.details.missing', ['list' => $missingList]) }}</div>
                        </div>
                    @endif
                    <dl class="cp-dl">
                        @foreach ($printed as $key => $value)
                            <dt>{{ __('clients.fields.' . $key) }}</dt>
                            <dd @class(['is-empty' => blank($value), 'cp-address' => $key === 'address'])>{{ filled($value) ? $value : __('clients.details.not_given') }}</dd>
                        @endforeach
                        <dt>{{ __('clients.fields.preferred_language') }}</dt>
                        <dd>{{ __('clients.details.languages.' . $language) }}</dd>
                    </dl>
                @endif
            </section>

            {{-- Contacts, and their Client Portal accounts (PortalInvites) --}}
            <section class="card" aria-labelledby="cp-people-title">
                <div class="cp-card-head">
                    <h2 class="card-title" id="cp-people-title">{{ __('clients.people.title') }}</h2>
                    <div class="cp-actions">
                        @if ($canOpenAccounts)
                            <a href="{{ route('hq.system.roles') }}" class="btn btn-ghost btn-sm" wire:navigate><i data-lucide="users"></i> {{ __('clients.people.manage') }}</a>
                        @endif
                        @if ($canEdit && ! $adding)
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="newPerson"><i data-lucide="user-plus"></i> {{ __('clients.people.add') }}</button>
                        @endif
                    </div>
                </div>

                @if ($adding)
                    <form wire:submit="addPerson" class="cp-form cp-panel" novalidate aria-label="{{ __('clients.people.add') }}" style="margin-bottom: 16px">
                        <div class="cp-row">
                            <div class="field">
                                <label class="label" for="cp-p-name">{{ __('clients.people.name') }}</label>
                                <input id="cp-p-name" type="text" class="input" maxlength="100" wire:model="person.name" @error('person.name') aria-invalid="true" aria-describedby="cp-p-name-error" @enderror>
                                @error('person.name') <span class="field-error" id="cp-p-name-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="cp-p-job">{{ __('clients.people.job_title') }} <span class="cp-optional">{{ __('clients.details.optional') }}</span></label>
                                <input id="cp-p-job" type="text" class="input" maxlength="100" wire:model="person.job_title" @error('person.job_title') aria-invalid="true" aria-describedby="cp-p-job-error" @enderror>
                                @error('person.job_title') <span class="field-error" id="cp-p-job-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="cp-row">
                            <div class="field">
                                <label class="label" for="cp-p-email">{{ __('clients.people.email') }}</label>
                                <input id="cp-p-email" type="email" class="input" maxlength="100" wire:model="person.email" placeholder="{{ __('clients.details.email_placeholder') }}" @error('person.email') aria-invalid="true" aria-describedby="cp-p-email-error" @enderror>
                                @error('person.email') <span class="field-error" id="cp-p-email-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="cp-p-phone">{{ __('clients.people.phone') }} <span class="cp-optional">{{ __('clients.details.optional') }}</span></label>
                                <input id="cp-p-phone" type="tel" class="input" maxlength="30" wire:model="person.phone" placeholder="{{ __('clients.details.phone_placeholder') }}" @error('person.phone') aria-invalid="true" aria-describedby="cp-p-phone-error" @enderror>
                                @error('person.phone') <span class="field-error" id="cp-p-phone-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        @if ($canInvite)
                            <div class="cp-radios">
                                <label><input type="checkbox" wire:model.live="person.invite"> {{ __('clients.people.invite_now') }}</label>
                            </div>
                            @if (! empty($person['invite']))
                                @if ($nextIsPrimary)
                                    <p class="help" style="margin: 0">{{ __('clients.portal.first_is_primary') }}</p>
                                @else
                                    <div class="field" style="max-width: 280px">
                                        <label class="label" for="cp-p-role">{{ __('clients.portal.role') }}</label>
                                        <select id="cp-p-role" class="select" wire:model="inviteRole">
                                            @foreach ($roles as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            @endif
                        @endif
                        <div class="cp-actions">
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="addPerson">
                                <i data-lucide="{{ $canInvite && ! empty($person['invite']) ? 'mail-plus' : 'user-plus' }}"></i>
                                {{ $canInvite && ! empty($person['invite']) ? __('clients.people.save_and_invite') : __('clients.people.save') }}
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelPerson">{{ __('clients.people.cancel') }}</button>
                        </div>
                    </form>
                @endif

                @if ($contacts->isEmpty())
                    <p class="help" style="margin: 0">{{ __('clients.people.none') }}</p>
                @else
                    <ul class="cp-list">
                        @foreach ($contacts as $contact)
                            @php $state = $states[$contact->id] ?? 'none'; @endphp
                            <li wire:key="contact-{{ $contact->id }}">
                                <div class="cp-person">
                                    <span class="cp-person-name">
                                        {{ $contact->full_name }}
                                        @if ($contact->is_primary_client)
                                            <span class="pill pill-info">{{ __('clients.portal.primary') }}</span>
                                        @elseif ($contact->is_main_contact)
                                            <span class="tag">{{ __('clients.people.main') }}</span>
                                        @endif
                                    </span>
                                    @if ($contact->job_title)
                                        <span class="text-muted">{{ $contact->job_title }}</span>
                                    @endif
                                    <span class="cp-ways">
                                        @if ($contact->email) <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a> @endif
                                        @if ($contact->phone) <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}">{{ $contact->phone }}</a> @endif
                                    </span>
                                </div>
                                <div class="cp-portal">
                                    <span class="pill {{ $statePill[$state] }}">{{ __('clients.portal.state.' . $state) }}</span>
                                    @if ($state === 'none' && $canInvite && $inviting !== $contact->id)
                                        @if ($contact->email)
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="startInvite('{{ $contact->id }}')"><i data-lucide="mail-plus"></i> {{ __('clients.portal.invite') }}</button>
                                        @else
                                            <span class="text-caption text-muted">{{ __('clients.people.no_email') }}</span>
                                        @endif
                                    @elseif ($state === 'invited' && $canResend)
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="resend('{{ $contact->id }}')" wire:loading.attr="disabled" wire:target="resend('{{ $contact->id }}')"><i data-lucide="send"></i> {{ __('clients.portal.resend') }}</button>
                                    @endif
                                </div>
                                @if ($inviting === $contact->id)
                                    <form wire:submit="invite" class="cp-form cp-panel" novalidate aria-label="{{ __('clients.portal.invite') }}">
                                        <p class="help" style="margin: 0">{{ __('clients.portal.what_happens', ['email' => $contact->email, 'language' => __('clients.portal.language_names.' . $language)]) }}</p>
                                        @if ($nextIsPrimary)
                                            <p class="help" style="margin: 0">{{ __('clients.portal.becomes_primary') }}</p>
                                        @else
                                            <div class="field" style="max-width: 280px">
                                                <label class="label" for="cp-role">{{ __('clients.portal.role') }}</label>
                                                <select id="cp-role" class="select" wire:model="inviteRole">
                                                    @foreach ($roles as $id => $name)
                                                        <option value="{{ $id }}">{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        @error('invite') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                                        <div class="cp-actions">
                                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="invite"><i data-lucide="send"></i> {{ __('clients.portal.send') }}</button>
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelInvite">{{ __('clients.portal.cancel') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($leadUrl && $contacts->isNotEmpty())
                    <p class="help cp-foot">{{ __('clients.people.edit_on_lead') }}</p>
                @endif
            </section>
        </div>

        <div class="cp-col">
            <section class="card" aria-labelledby="cp-projects-title">
                <div class="cp-card-head">
                    <h2 class="card-title" id="cp-projects-title">{{ __('clients.projects.title') }}</h2>
                </div>
                @if ($projects->isEmpty())
                    <p class="help" style="margin: 0">{{ __('clients.projects.none') }}</p>
                @else
                    <ul class="cp-list">
                        @foreach ($projects as $project)
                            <li wire:key="project-{{ $project->id }}">
                                @if ($canOpenProjects)
                                    <a href="{{ route('hq.ops.project', ['project' => $project->id]) }}" wire:navigate style="overflow-wrap: anywhere">{{ $project->title }}</a>
                                @else
                                    <span class="text-ink" style="overflow-wrap: anywhere">{{ $project->title }}</span>
                                @endif
                                <span class="tag">{{ __('projects.project_statuses.' . $project->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($canSeeMoney)
                <section class="card" aria-labelledby="cp-invoices-title">
                    <div class="cp-card-head">
                        <h2 class="card-title" id="cp-invoices-title">{{ __('clients.invoices.title') }}</h2>
                        <a href="{{ route('hq.finance.invoices') }}" class="btn btn-ghost btn-sm" wire:navigate>{{ __('clients.invoices.all') }}</a>
                    </div>
                    @if ($invoices->isEmpty())
                        <p class="help" style="margin: 0">{{ __('clients.invoices.none') }}</p>
                    @else
                        <ul class="cp-list">
                            @foreach ($invoices as $invoice)
                                <li wire:key="invoice-{{ $invoice->id }}">
                                    <div class="cp-person">
                                        <a href="{{ route('hq.finance.invoice-pdf', ['invoice' => $invoice->id]) }}" target="_blank" rel="noopener" class="cp-person-name">{{ $invoice->invoice_number }} <i data-lucide="file-down" aria-hidden="true" style="width: 14px; height: 14px"></i></a>
                                        <span class="text-muted" style="overflow-wrap: anywhere">{{ $invoice->project_title }}</span>
                                        <span @class(['text-caption', 'text-muted' => $invoice->status !== 'Overdue'])>
                                            {{ __('clients.invoices.due', ['date' => $day($invoice->due_date)]) }}
                                            @if ($invoice->status === 'Overdue') <span class="pill pill-danger">{{ __('clients.invoices.overdue') }}</span> @endif
                                        </span>
                                    </div>
                                    <span class="cp-money">{{ __('clients.invoices.left', ['amount' => $money($invoice->currency, $invoice->left)]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="cp-total">
                            <span class="text-muted">{{ __('clients.invoices.total') }}</span>
                            <span class="cp-money">{{ $invoices->groupBy('currency')->map(fn ($rows, $currency) => $money($currency, $rows->sum('left')))->implode(' · ') }}</span>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
</div>
