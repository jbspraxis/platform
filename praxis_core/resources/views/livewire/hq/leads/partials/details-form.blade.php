{{-- The lead's details (Leads::DETAILS), source included; the owner has its own picker below --}}
<form wire:submit="saveDetails" class="ld-form" novalidate aria-label="Edit details">
    <div class="field">
        <label class="label" for="ld-d-name">Organisation name</label>
        <input id="ld-d-name" type="text" class="input" maxlength="150" wire:model="details.name" @error('details.name') aria-invalid="true" aria-describedby="ld-d-name-error" @enderror>
        @error('details.name') <span class="field-error" id="ld-d-name-error">{{ $message }}</span> @enderror
    </div>
    <div class="ld-row">
        <div class="field">
            <label class="label" for="ld-d-country">Country</label>
            <input id="ld-d-country" type="text" class="input" maxlength="100" list="ld-countries" wire:model="details.country" @error('details.country') aria-invalid="true" @enderror>
            <datalist id="ld-countries">
                @foreach ($countries as $c) <option value="{{ $c }}"></option> @endforeach
            </datalist>
            @error('details.country') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label class="label" for="ld-d-city">City <span class="ld-optional">(optional)</span></label>
            <input id="ld-d-city" type="text" class="input" maxlength="100" wire:model="details.city">
            @error('details.city') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="field">
        <label class="label" for="ld-d-industry">Industry</label>
        <select id="ld-d-industry" class="select" wire:model="details.industry" @error('details.industry') aria-invalid="true" @enderror>
            <option value="">Choose an industry</option>
            @foreach ($industries as $ind)
                <option value="{{ $ind }}">{{ $ind }}</option>
            @endforeach
            @if ($details['industry'] && ! in_array($details['industry'], $industries, true))
                <option value="{{ $details['industry'] }}">{{ $details['industry'] }}</option>
            @endif
        </select>
        @error('details.industry') <span class="field-error">{{ $message }}</span> @enderror
    </div>
    <div class="ld-row">
        <div class="field">
            <label class="label" for="ld-d-phone">Phone <span class="ld-optional">(optional)</span></label>
            <input id="ld-d-phone" type="tel" class="input" maxlength="30" wire:model="details.phone" placeholder="e.g. +234 800 000 0000" @error('details.phone') aria-invalid="true" @enderror>
            @error('details.phone') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label class="label" for="ld-d-email">Email <span class="ld-optional">(optional)</span></label>
            <input id="ld-d-email" type="email" class="input" maxlength="150" wire:model="details.email" placeholder="e.g. info@company.com" @error('details.email') aria-invalid="true" @enderror>
            @error('details.email') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="field">
        <label class="label" for="ld-d-address">Address <span class="ld-optional">(optional)</span></label>
        <textarea id="ld-d-address" class="textarea" rows="2" maxlength="2000" wire:model="details.address"></textarea>
        @error('details.address') <span class="field-error">{{ $message }}</span> @enderror
    </div>
    <div class="ld-row">
        <div class="field">
            <label class="label" for="ld-d-rc">Company number <span class="ld-optional">(RC, optional)</span></label>
            <input id="ld-d-rc" type="text" class="input" maxlength="60" wire:model="details.registration_number" placeholder="e.g. RC 7319824">
            @error('details.registration_number') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label class="label" for="ld-d-tin">Tax number <span class="ld-optional">(TIN, optional)</span></label>
            <input id="ld-d-tin" type="text" class="input" maxlength="100" wire:model="details.tax_number">
            @error('details.tax_number') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
    <p class="help" style="margin: -4px 0 0">The address, company number, tax number, email and phone are printed on this client’s invoices.</p>
    <fieldset class="field" style="border: 0; padding: 0; margin: 0">
        <legend class="label" style="margin-bottom: 6px">Language for emails</legend>
        <div class="ld-radios">
            <label><input type="radio" value="EN" wire:model="details.preferred_language"> English</label>
            <label><input type="radio" value="FR" wire:model="details.preferred_language"> French</label>
        </div>
    </fieldset>
    <div class="ld-row">
        <div class="field">
            <label class="label" for="ld-d-source">Source</label>
            <select id="ld-d-source" class="select" wire:model="details.source" @error('details.source') aria-invalid="true" @enderror>
                @foreach ($sources as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('details.source') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label class="label" for="ld-d-source-detail">Source details <span class="ld-optional">(optional)</span></label>
            <input id="ld-d-source-detail" type="text" class="input" maxlength="255" wire:model="details.source_detail" placeholder="e.g. who referred them">
            @error('details.source_detail') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="ld-actions">
        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveDetails"><i data-lucide="save"></i> Save details</button>
        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelDetails">Cancel</button>
    </div>
</form>
