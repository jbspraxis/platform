{{-- The JBS Praxis company that signs and bills the work (opportunities.billing_entity_id; empty = Nigeria's company:
     App\Support\Finance\Vat). Only the owner or the opportunity's lead chooses it; $prefix keeps the ids unique per form. --}}
@php $home = $billingCompanies->firstWhere('region_code', \App\Support\Finance\Vat::HOME); @endphp
<div class="field">
    <label class="label" for="{{ $prefix }}-billing">{{ __('vat.billing.label') }}</label>
    <select id="{{ $prefix }}-billing" class="select" wire:model="billingEntityId" @disabled(! $canChooseBilling) aria-describedby="{{ $prefix }}-billing-help"
            @error('billingEntityId') aria-invalid="true" @enderror>
        <option value="">{{ $home ? __('vat.billing.default', ['company' => $home->legal_name]) : __('vat.billing.default_none') }}</option>
        @foreach ($billingCompanies as $company)
            @continue($home && $company->id === $home->id)
            <option value="{{ $company->id }}">{{ $company->legal_name }}</option>
        @endforeach
    </select>
    <span class="help" id="{{ $prefix }}-billing-help">{{ __('vat.billing.help') }}@unless ($canChooseBilling) {{ __('vat.billing.only_lead') }}@endunless</span>
    @error('billingEntityId') <span class="field-error">{{ $message }}</span> @enderror
</div>
