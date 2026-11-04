<div>
    @php
        $regionNames = ['CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya', 'GLOBAL' => 'Head office'];
        // VAT (App\Support\Finance\Vat): only people who may edit master data change it
        $canVat = auth()->user()->can('mdm.edit');
        $vatRate = $region_code ? \App\Support\Finance\Vat::countryRate(strtoupper($region_code)) : null;
        $locale = app()->getLocale();
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="entity-saved-title">
                <h2 id="entity-saved-title" class="modal-title">Legal entity saved</h2>
                <p class="muted">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Legal entities</h1>
            <p class="page-subtitle">The companies JBS Praxis employs people through, one per country. Payroll, contracts and tax filings use these details.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-6" role="alert">
            <i data-lucide="circle-alert"></i>
            <div>
                <strong>We couldn’t save the legal entity.</strong> Fix the following and save again:
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ $editMode ? 'Edit legal entity' : 'Add a legal entity' }}</h2>
                @if($editMode) <span class="pill pill-warning">Editing</span> @endif
            </div>

            <form wire:submit.prevent="saveEntity" class="flex flex-col gap-4">

                <h3 class="label" style="font-weight: 600; margin: 0;">Country and name</h3>

                <div class="flex flex-col gap-4">
                    <div class="field">
                        <label class="label" for="le-region">Country</label>
                        <select id="le-region" wire:model="region_code" required class="select" {{ $editMode ? 'disabled' : '' }}>
                            <option value="">Choose a country</option>
                            <option value="CM">CM: Cameroon</option>
                            <option value="NG">NG: Nigeria</option>
                            <option value="KE">KE: Kenya</option>
                            <option value="GLOBAL">Global: head office</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="le-name">Registered company name</label>
                        <input id="le-name" type="text" wire:model="legal_name" required class="input" placeholder="e.g. JBS Praxis SARL">
                    </div>
                </div>

                <hr class="divider" style="margin: 4px 0;">
                <h3 class="label" style="font-weight: 600; margin: 0;">Registration and tax numbers</h3>
                <p class="help" style="margin: 0;">Leave them blank while the company is being registered, and fill them in once it is.</p>

                <div class="field">
                    <label class="label" for="le-rc">Company registration number (RC)</label>
                    <input id="le-rc" type="text" wire:model="registration_number" class="input" style="font-variant-numeric: tabular-nums;">
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="field">
                        <label class="label" for="le-tax">Tax ID (NIU or TIN)</label>
                        <input id="le-tax" type="text" wire:model="tax_identification_number" class="input" style="font-variant-numeric: tabular-nums;">
                    </div>
                    <div class="field">
                        <label class="label" for="le-social">Employer social security number</label>
                        <input id="le-social" type="text" wire:model="employer_social_security_code" class="input" style="font-variant-numeric: tabular-nums;" aria-describedby="le-social-help">
                        <span id="le-social-help" class="help">CNPS in Cameroon, NSITF in Nigeria.</span>
                    </div>
                </div>
                {{-- From the certificate of incorporation; Company documents can fill them in from the uploaded certificate --}}
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="field">
                        <label class="label" for="le-incorporated">Date of registration (optional)</label>
                        <input id="le-incorporated" type="date" wire:model="incorporated_on" class="input">
                    </div>
                    <div class="field">
                        <label class="label" for="le-type">Company type (optional)</label>
                        <input id="le-type" type="text" wire:model="company_type" class="input" maxlength="100" placeholder="e.g. Private company limited by shares">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="le-capital">Share capital (optional)</label>
                    <input id="le-capital" type="text" wire:model="share_capital" class="input" maxlength="100" placeholder="e.g. NGN 1,000,000" style="font-variant-numeric: tabular-nums;">
                </div>

                <hr class="divider" style="margin: 4px 0;">
                <h3 class="label" style="font-weight: 600; margin: 0;" id="le-vat-heading">{{ __('vat.entity.heading') }}</h3>

                <div class="flex flex-col gap-3" role="group" aria-labelledby="le-vat-heading">
                    <label for="le-vat" class="flex items-center gap-3" style="min-height: 44px; font-size: 0.875rem; color: var(--color-ink); cursor: {{ $canVat ? 'pointer' : 'default' }};">
                        <input id="le-vat" type="checkbox" role="switch" wire:model.live="charges_vat" @disabled(! $canVat) aria-describedby="le-vat-help{{ $canVat ? '' : ' le-vat-locked' }}"
                               @error('charges_vat') aria-invalid="true" @enderror>
                        <span style="font-weight: 600;">{{ __('vat.entity.charges') }}</span>
                    </label>
                    <span id="le-vat-help" class="help" style="margin-top: -8px;">{{ __('vat.entity.help') }}</span>
                    @error('charges_vat') <span class="field-error" role="alert">{{ $message }}</span> @enderror
                    @unless ($canVat)
                        <span id="le-vat-locked" class="help"><i data-lucide="lock" style="width: 14px; height: 14px; vertical-align: -2px;"></i> {{ __('vat.entity.read_only') }}</span>
                    @endunless

                    @if ($charges_vat)
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div class="field">
                                <label class="label" for="le-vat-number">{{ __('vat.entity.number') }}</label>
                                <input id="le-vat-number" type="text" wire:model="vat_number" class="input" maxlength="100" style="font-variant-numeric: tabular-nums;" @disabled(! $canVat)
                                       @error('vat_number') aria-invalid="true" aria-describedby="le-vat-number-error" @enderror>
                                @error('vat_number') <span id="le-vat-number-error" class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="le-vat-starts">{{ __('vat.entity.starts_on') }}</label>
                                <input id="le-vat-starts" type="date" wire:model="vat_starts_on" class="input" @disabled(! $canVat)
                                       @error('vat_starts_on') aria-invalid="true" aria-describedby="le-vat-starts-error" @enderror>
                                @error('vat_starts_on') <span id="le-vat-starts-error" class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif
                    @if ($region_code)
                        <span class="help">
                            {{ $vatRate !== null
                                ? __('vat.entity.rate', ['country' => $this->countryName(strtoupper($region_code)), 'rate' => \App\Support\Finance\Vat::percent($vatRate, $locale)])
                                : __('vat.entity.no_rate', ['country' => $this->countryName(strtoupper($region_code))]) }}
                        </span>
                    @endif
                </div>

                <hr class="divider" style="margin: 4px 0;">
                <h3 class="label" style="font-weight: 600; margin: 0;">Legal representative</h3>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="field">
                        <label class="label" for="le-rep">Name</label>
                        <input id="le-rep" type="text" wire:model="legal_representative_name" required class="input">
                    </div>
                    <div class="field">
                        <label class="label" for="le-rep-title">Job title</label>
                        <input id="le-rep-title" type="text" wire:model="legal_representative_title" required class="input" placeholder="e.g. Managing Director">
                    </div>
                </div>

                <hr class="divider" style="margin: 4px 0;">
                <h3 class="label" style="font-weight: 600; margin: 0;">Contact details and address</h3>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="field">
                        <label class="label" for="le-email">Email address (optional)</label>
                        <input id="le-email" type="email" wire:model="contact_email" class="input">
                    </div>
                    <div class="field">
                        <label class="label" for="le-phone">Phone number (optional)</label>
                        <input id="le-phone" type="text" wire:model="contact_phone" class="input" placeholder="e.g. +237 6 99 00 00 00">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="le-address">Registered office address</label>
                    <textarea id="le-address" wire:model="registered_address" required class="textarea" rows="2" aria-describedby="le-address-help"></textarea>
                    <span id="le-address-help" class="help">Printed on contracts and payslips.</span>
                </div>

                <div class="flex flex-wrap gap-3" style="border-top: 1px solid var(--color-line); padding-top: 16px;">
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary" style="flex: 2;">
                        <span wire:loading.remove wire:target="saveEntity">{{ $editMode ? 'Save changes' : 'Add legal entity' }}</span>
                        <span wire:loading wire:target="saveEntity">Saving…</span>
                    </button>
                    @if($editMode)
                        <button type="button" wire:click="resetForm" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 200px;">Country and company</th>
                            <th style="min-width: 170px;">Registration numbers</th>
                            <th class="num text-right" style="min-width: 150px; white-space: normal;">Active staff and monthly pay</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entities as $entity)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2" style="margin-bottom: 4px;">
                                        <span class="pill pill-neutral" title="{{ $regionNames[$entity->region_code] ?? $entity->region_code }}">{{ $entity->region_code === 'GLOBAL' ? 'Global' : $entity->region_code }}</span>
                                        <span class="text-ink" style="font-weight: 600;">{{ $entity->legal_name }}</span>
                                    </div>
                                    <div class="help flex items-center gap-1"><i data-lucide="map-pin" style="width: 14px; height: 14px;"></i> {{ \Illuminate\Support\Str::limit($entity->registered_address, 35) }}</div>
                                    <div class="help flex items-center gap-1" style="margin-top: 2px;"><i data-lucide="user" style="width: 14px; height: 14px;"></i> Representative: {{ $entity->legal_representative_name }}</div>
                                </td>

                                <td style="font-variant-numeric: tabular-nums;">
                                    <div><span class="muted">RC</span> <span class="text-ink">{{ $entity->registration_number ?: 'Being registered' }}</span></div>
                                    <div style="margin-top: 2px;"><span class="muted">Tax ID</span> <span class="text-ink">{{ $entity->tax_identification_number ?: 'Not yet' }}</span></div>
                                    <div style="margin-top: 2px;"><span class="muted" style="white-space: nowrap;">Employer no.</span> <span class="text-ink">{{ $entity->employer_social_security_code ?: 'Not yet' }}</span></div>
                                    @if (! empty($entity->incorporated_on))
                                        <div style="margin-top: 2px;"><span class="muted">Registered</span> <span class="text-ink">{{ \Carbon\Carbon::parse($entity->incorporated_on)->format('j M Y') }}</span></div>
                                    @endif
                                    {{-- VAT: on from its start date, with its number; off until the company is registered --}}
                                    <div style="margin-top: 6px;">
                                        @if ($entity->charges_vat && $entity->vat_starts_on)
                                            <span class="pill pill-success">{{ __('vat.entity.status_on', ['date' => \App\Support\Finance\Vat::date(substr((string) $entity->vat_starts_on, 0, 10), $locale)]) }}</span>
                                            @if ($entity->vat_number)
                                                <div class="help" style="margin-top: 2px;">{{ __('vat.entity.status_number', ['number' => $entity->vat_number]) }}</div>
                                            @endif
                                        @else
                                            <span class="pill pill-neutral">{{ __('vat.entity.status_off') }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="num">
                                    <div class="help">{{ $entity->active_headcount }} active {{ $entity->active_headcount == 1 ? 'staff member' : 'staff members' }}</div>
                                    <div class="text-ink" style="font-weight: 600;">
                                        {{ \App\Livewire\Hq\HqMoney::format($entity->local_currency, $entity->monthly_liability) }} <span class="help">a month</span>
                                    </div>
                                    <div class="help">Gross pay and allowances</div>
                                </td>

                                <td>
                                    @if($entity->is_active)
                                        <span class="pill pill-success">Active</span>
                                    @else
                                        <span class="pill pill-neutral">Inactive</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="flex flex-col items-end gap-1">
                                        {{-- Master data › Company documents, filtered on this company --}}
                                        <a href="{{ route('hq.mdm.documents', ['company' => $entity->id]) }}" class="btn btn-ghost btn-sm" wire:navigate style="white-space: nowrap;">
                                            Documents ({{ $entity->documents_count ?? 0 }})
                                        </a>
                                        <button type="button" wire:click="editEntity('{{ $entity->id }}')" class="btn btn-ghost btn-sm">Edit</button>
                                        <button type="button" wire:click="toggleStatus('{{ $entity->id }}', {{ $entity->is_active }})" class="btn btn-ghost btn-sm" @if($entity->is_active) style="color: var(--color-danger);" @endif>
                                            {{ $entity->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No legal entities yet</strong>
                                        Payroll needs at least one. Start with the head office entity using the form.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
