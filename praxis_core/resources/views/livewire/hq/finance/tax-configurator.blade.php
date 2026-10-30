<div>
    @php
        $regionNames = ['NG' => 'Nigeria', 'CM' => 'Cameroon', 'KE' => 'Kenya', 'GLOBAL' => 'Other countries'];
        $regionName = $regionNames[$activeRegion] ?? $activeRegion;
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="tax-saved-title">
                <h2 id="tax-saved-title" class="modal-title">Tax rules saved</h2>
                <p class="text-sm text-slate">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Tax rules</h1>
            <p class="page-subtitle">Set the payroll tax rates, pension and social contributions, and tax bands for each country.</p>
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setRegion('NG')" class="tab {{ $activeRegion === 'NG' ? 'is-active' : '' }}" aria-selected="{{ $activeRegion === 'NG' ? 'true' : 'false' }}">Nigeria (PITA)</button>
        <button type="button" role="tab" wire:click="setRegion('CM')" class="tab {{ $activeRegion === 'CM' ? 'is-active' : '' }}" aria-selected="{{ $activeRegion === 'CM' ? 'true' : 'false' }}">Cameroon (CGI)</button>
        <button type="button" role="tab" wire:click="setRegion('KE')" class="tab {{ $activeRegion === 'KE' ? 'is-active' : '' }}" aria-selected="{{ $activeRegion === 'KE' ? 'true' : 'false' }}">Kenya (KRA)</button>
        <button type="button" role="tab" wire:click="setRegion('GLOBAL')" class="tab {{ $activeRegion === 'GLOBAL' ? 'is-active' : '' }}" aria-selected="{{ $activeRegion === 'GLOBAL' ? 'true' : 'false' }}">Other countries (flat rate)</button>
    </div>

    @if(session()->has('error'))
        <div class="alert alert-danger mb-6" role="alert"><i data-lucide="circle-alert"></i><span>{{ session('error') }}</span></div>
    @endif

    @if(empty($config['id']))
        <div class="alert alert-info mb-6" role="note">
            <i data-lucide="info"></i>
            <span>{{ $regionName }} has no saved tax rules yet. Fill in the rates and save to create them.</span>
        </div>
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-2">

        <form wire:submit.prevent="saveConfiguration" class="card">
            <h2 class="card-title mb-4">Rates and reliefs: {{ $regionName }}</h2>
            <p class="help mb-4">Enter rates as decimals: 0.08 for 8%.</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="tx-minwage">Minimum wage (monthly)</label>
                    <input id="tx-minwage" type="number" step="0.01" wire:model="config.minimum_wage" class="input" placeholder="e.g. 70000">
                </div>
            </div>

            <hr class="divider my-5">
            <h3 class="label mb-3">Pension and social contributions</h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="tx-pen-ee">Pension, employee rate</label>
                    <input id="tx-pen-ee" type="number" step="0.0001" wire:model="config.pension_employee_rate" class="input" placeholder="e.g. 0.08">
                </div>
                <div class="field">
                    <label class="label" for="tx-pen-er">Pension, employer rate</label>
                    <input id="tx-pen-er" type="number" step="0.0001" wire:model="config.pension_employer_rate" class="input" placeholder="e.g. 0.10">
                </div>
                <div class="field">
                    <label class="label" for="tx-health">Health insurance, employee rate</label>
                    <input id="tx-health" type="number" step="0.0001" wire:model="config.health_employee_rate" class="input">
                    <span class="help">NHIS in Nigeria.</span>
                </div>
                <div class="field">
                    <label class="label" for="tx-housing">Housing fund, employee rate</label>
                    <input id="tx-housing" type="number" step="0.0001" wire:model="config.housing_fund_rate" class="input">
                </div>
                <div class="field sm:col-span-2">
                    <label class="label" for="tx-cap">Pension cap (optional)</label>
                    <input id="tx-cap" type="number" step="0.01" wire:model="config.pension_cap_amount" class="input" placeholder="e.g. 750000">
                    <span class="help">The most monthly gross pay that pension is taken from, such as the CNPS ceiling in Cameroon. Leave blank if there is no cap.</span>
                </div>
            </div>

            <hr class="divider my-5">
            <h3 class="label mb-3">Tax reliefs</h3>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="tx-fixed-ab">Fixed relief (monthly)</label>
                    <input id="tx-fixed-ab" type="number" step="0.01" wire:model="config.fixed_abatement" class="input" placeholder="e.g. 41666.67">
                </div>
                <div class="field">
                    <label class="label" for="tx-prop-ab">Proportional relief rate</label>
                    <input id="tx-prop-ab" type="number" step="0.0001" wire:model="config.proportional_abatement_rate" class="input" placeholder="e.g. 0.30">
                </div>
                <div class="field">
                    <label class="label" for="tx-cra-fixed">Consolidated relief allowance, fixed (yearly)</label>
                    <input id="tx-cra-fixed" type="number" step="0.01" wire:model="config.cra_fixed_amount" class="input" placeholder="e.g. 200000">
                </div>
                <div class="field">
                    <label class="label" for="tx-cra-rate">Consolidated relief allowance rate</label>
                    <input id="tx-cra-rate" type="number" step="0.0001" wire:model="config.cra_proportional_rate" class="input" placeholder="e.g. 0.20">
                </div>
                <div class="field sm:col-span-2">
                    <label class="label" for="tx-min-tax">Minimum tax rate</label>
                    <input id="tx-min-tax" type="number" step="0.0001" wire:model="config.minimum_tax_rate" class="input" placeholder="e.g. 0.01">
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveConfiguration">Save tax rules</span>
                    <span wire:loading wire:target="saveConfiguration">Saving…</span>
                </button>
            </div>
        </form>

        <div class="card">
            <h2 class="card-title mb-4">Tax bands: {{ $regionName }}</h2>

            <div class="table-wrap mb-6 rounded-lg border border-line">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Band</th>
                            <th class="num text-right">From</th>
                            <th class="num text-right">To</th>
                            <th class="num text-right">Rate</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bands as $band)
                            <tr>
                                <td class="text-ink">{{ $band->band_name }}</td>
                                <td class="num">{{ number_format($band->lower_limit, 0) }}</td>
                                <td class="num">{{ $band->upper_limit ? number_format($band->upper_limit, 0) : 'No limit' }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($band->tax_rate * 100, 2), '0'), '.') }}%</td>
                                <td class="text-right">
                                    <button type="button" wire:click="removeTaxBand('{{ $band->id }}')" class="btn btn-ghost btn-sm" style="color: var(--color-danger);" aria-label="Remove band {{ $band->band_name }}" wire:confirm="Remove the tax band {{ $band->band_name }}? Payroll runs created after this use the remaining bands."><i data-lucide="trash-2"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No tax bands yet</strong>
                                        Without bands, payroll uses the flat rate. Add the first band below.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h3 class="label mb-3">Add a band</h3>
            <form wire:submit.prevent="addTaxBand">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field sm:col-span-2">
                        <label class="label" for="band-name">Band name</label>
                        <input id="band-name" type="text" wire:model="newBandName" required class="input" placeholder="e.g. First ₦300,000">
                        @error('newBandName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="band-lower">From (lower limit)</label>
                        <input id="band-lower" type="number" step="0.01" wire:model="newBandLower" required class="input">
                        @error('newBandLower') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="band-upper">To (upper limit, optional)</label>
                        <input id="band-upper" type="number" step="0.01" wire:model="newBandUpper" class="input">
                        <span class="help">Leave blank for the top band.</span>
                        @error('newBandUpper') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field sm:col-span-2">
                        <label class="label" for="band-rate">Tax rate</label>
                        <input id="band-rate" type="number" step="0.0001" wire:model="newBandRate" required class="input" placeholder="e.g. 0.07">
                        <span class="help">As a decimal: 0.07 for 7%.</span>
                        @error('newBandRate') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="addTaxBand" class="inline-flex items-center gap-2"><i data-lucide="plus"></i> Add band</span>
                        <span wire:loading wire:target="addTaxBand">Adding…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
