<div>
    @php
        // Display labels for the stored tax rule types (the stored values stay as they are)
        $logicLabels = [
            'Destination-based' => 'Destination-based',
            'Origin-based' => 'Origin-based',
            'Tax Exempt (B2B)' => 'Tax exempt (business to business)',
            'Reverse Charge' => 'Reverse charge',
        ];
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="tax-saved-title">
                <h2 id="tax-saved-title" class="modal-title">Changes saved</h2>
                <p class="muted">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Tax compliance</h1>
            <p class="page-subtitle">VAT rules for each country JBS Praxis invoices in, and the exchange rates HQ uses to convert amounts to US dollars.</p>
        </div>
        @if($activeTab === 'currency')
            <button type="button" wire:click="syncRatesFromApi" wire:loading.attr="disabled" class="btn btn-secondary">
                <i data-lucide="refresh-cw"></i>
                <span wire:loading.remove wire:target="syncRatesFromApi">Update rates from the rate service</span>
                <span wire:loading wire:target="syncRatesFromApi">Updating rates…</span>
            </button>
        @endif
    </div>

    @include('livewire.hq.mdm.partials.tabs')

    <div class="flex flex-wrap gap-2" style="margin-bottom: 24px;" role="group" aria-label="Show">
        <button type="button" wire:click="switchTab('tax')" class="btn btn-sm {{ $activeTab === 'tax' ? 'btn-primary' : 'btn-secondary' }}" aria-pressed="{{ $activeTab === 'tax' ? 'true' : 'false' }}">VAT by country</button>
        <button type="button" wire:click="switchTab('currency')" class="btn btn-sm {{ $activeTab === 'currency' ? 'btn-primary' : 'btn-secondary' }}" aria-pressed="{{ $activeTab === 'currency' ? 'true' : 'false' }}">Exchange rates</button>
    </div>

    @error('api_sync')
        <div class="alert alert-danger mb-6" role="alert">
            <i data-lucide="circle-alert"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">

        <div class="card">
            @if($activeTab === 'tax')
                <div class="card-header">
                    <h2 class="card-title">{{ $editMode ? 'Edit VAT rule' : 'Add a VAT rule' }}</h2>
                    @if($editMode) <span class="pill pill-warning">Editing</span> @endif
                </div>

                <form wire:submit.prevent="saveTax" class="flex flex-col gap-4">
                    <div class="field">
                        <label class="label" for="tx-region">Country or tax authority</label>
                        <input id="tx-region" type="text" wire:model="region_name" required placeholder="e.g. United Kingdom (HMRC)" class="input">
                        @error('region_name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="field">
                            <label class="label" for="tx-country">Country code</label>
                            <input id="tx-country" type="text" wire:model="country_code" required placeholder="e.g. GB" class="input">
                            @error('country_code') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="tx-vat">VAT rate (%)</label>
                            <input id="tx-vat" type="number" step="0.01" wire:model="vat_rate" required class="input" style="font-variant-numeric: tabular-nums;">
                            @error('vat_rate') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="tx-logic">How VAT applies</label>
                        <select id="tx-logic" wire:model="tax_logic_type" class="select">
                            <option value="Destination-based">Destination-based</option>
                            <option value="Origin-based">Origin-based</option>
                            <option value="Tax Exempt (B2B)">Tax exempt (business to business)</option>
                            <option value="Reverse Charge">Reverse charge</option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="tx-peg">Invoice currency</label>
                        <input id="tx-peg" type="text" wire:model="exchange_rate_peg" required placeholder="e.g. USD" class="input">
                        @error('exchange_rate_peg') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-wrap gap-3" style="margin-top: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 2;">{{ $editMode ? 'Save changes' : 'Add VAT rule' }}</button>
                        @if($editMode) <button type="button" wire:click="resetForms" class="btn btn-secondary" style="flex: 1;">Cancel</button> @endif
                    </div>
                </form>

            @else
                <div class="card-header">
                    <h2 class="card-title">{{ $currEditMode ? 'Edit exchange rate' : 'Add a currency' }}</h2>
                    @if($currEditMode) <span class="pill pill-warning">Editing</span> @endif
                </div>

                <form wire:submit.prevent="saveCurrency" class="flex flex-col gap-4">
                    <div class="grid gap-3 sm:grid-cols-[1fr_2fr]">
                        <div class="field">
                            <label class="label" for="fx-code">Currency code</label>
                            <input id="fx-code" type="text" wire:model="currency_code" required placeholder="e.g. NGN" class="input" {{ $currEditMode ? 'readonly' : '' }}>
                        </div>
                        <div class="field">
                            <label class="label" for="fx-name">Currency name</label>
                            <input id="fx-name" type="text" wire:model="currency_name" required placeholder="e.g. Nigerian naira" class="input">
                        </div>
                    </div>
                    @error('currency_code') <span class="field-error">{{ $message }}</span> @enderror
                    @error('currency_name') <span class="field-error">{{ $message }}</span> @enderror

                    <div class="field">
                        <label class="label" for="fx-rate">Rate for 1 US dollar</label>
                        <input id="fx-rate" type="number" step="0.000001" wire:model="rate_to_usd" required class="input" style="font-variant-numeric: tabular-nums;">
                        @error('rate_to_usd') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="callout flex items-start gap-3">
                        <input type="checkbox" wire:model="is_manual_override" id="manual_override" class="checkbox" style="margin-top: 3px;">
                        <label for="manual_override" style="cursor: pointer;">
                            <strong class="text-ink" style="display: block; margin-bottom: 2px;">Keep this rate fixed</strong>
                            <span class="help" style="color: var(--color-slate);">Rate updates from the rate service won't change it. Use this for a parallel market or agreed company rate.</span>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3" style="margin-top: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 2;">{{ $currEditMode ? 'Save rate' : 'Add currency' }}</button>
                        @if($currEditMode) <button type="button" wire:click="resetForms" class="btn btn-secondary" style="flex: 1;">Cancel</button> @endif
                    </div>
                </form>
            @endif
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">

            @if($activeTab === 'tax')
                <div class="card-header" style="padding: 16px 20px 0;">
                    <h2 class="card-title">VAT by country</h2>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>How VAT applies</th>
                                <th class="num">VAT rate</th>
                                <th>Currency</th>
                                <th>Status</th>
                                <th><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($taxes as $tax)
                            <tr>
                                <td>
                                    <div class="text-ink" style="font-weight: 600;">{{ $tax->region_name }}</div>
                                    <div class="help">{{ $tax->country_code }}</div>
                                </td>
                                <td>{{ $logicLabels[$tax->tax_logic_type] ?? $tax->tax_logic_type }}</td>
                                <td class="num text-ink" style="font-weight: 600;">{{ number_format($tax->vat_rate, 2) }}%</td>
                                <td>{{ $tax->exchange_rate_peg }}</td>
                                <td>
                                    @if($tax->is_active)
                                        <span class="pill pill-success">Active</span>
                                    @else
                                        <span class="pill pill-neutral">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-col items-end gap-1">
                                        <button type="button" wire:click="editTax({{ $tax->id }})" class="btn btn-ghost btn-sm">Edit</button>
                                        <button type="button" wire:click="toggleTaxStatus({{ $tax->id }}, {{ $tax->is_active }})" class="btn btn-ghost btn-sm" @if($tax->is_active) style="color: var(--color-danger);" @endif>
                                            {{ $tax->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <strong>No VAT rules yet</strong>
                                        Add a rule for each country you invoice in, using the form.
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @else
                <div class="card-header" style="padding: 16px 20px 0;">
                    <h2 class="card-title">Exchange rates</h2>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Currency</th>
                                <th class="num">Rate for 1 USD</th>
                                <th>How it updates</th>
                                <th style="width: 100px;"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($currencies as $curr)
                            <tr>
                                <td>
                                    <div class="text-ink" style="font-weight: 600;">{{ $curr->currency_code }}</div>
                                    <div class="help">{{ $curr->currency_name }}</div>
                                </td>
                                <td class="num text-ink" style="font-weight: 600;">{{ number_format($curr->rate_to_usd, 4) }}</td>
                                <td>
                                    @if($curr->is_manual_override)
                                        <span class="pill pill-warning">Fixed rate</span>
                                    @else
                                        <span class="pill pill-info">Rate service</span>
                                        <div class="help" style="margin-top: 4px;">{{ $curr->last_synced_at ? 'Updated ' . \Carbon\Carbon::parse($curr->last_synced_at)->diffForHumans() : 'Not updated yet' }}</div>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" wire:click="editCurrency({{ $curr->id }})" class="btn btn-ghost btn-sm">Edit</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <strong>No currencies yet</strong>
                                        Add each currency HQ should convert, with its rate for 1 US dollar.
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</div>
