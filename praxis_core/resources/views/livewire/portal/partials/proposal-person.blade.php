{{-- Who is commenting, approving or asking for changes: an address JBS Praxis already knows for this work --}}
<div class="pr-row2">
    <div class="field">
        <label for="pr-name" class="label">{{ __('proposals.portal.name') }}</label>
        <input id="pr-name" type="text" class="input" maxlength="150" autocomplete="name" wire:model="name" @error('name') aria-invalid="true" aria-describedby="pr-name-error" @enderror>
        @error('name') <span class="field-error" id="pr-name-error">{{ $message }}</span> @enderror
    </div>
    <div class="field">
        <label for="pr-email" class="label">{{ __('proposals.portal.email') }}</label>
        <input id="pr-email" type="email" class="input" maxlength="150" autocomplete="email" wire:model="email" aria-describedby="pr-email-help @error('email') pr-email-error @enderror" @error('email') aria-invalid="true" @enderror>
        <span class="help" id="pr-email-help">{{ __('proposals.portal.email_help') }}</span>
        @error('email') <span class="field-error" id="pr-email-error">{{ $message }}</span> @enderror
    </div>
</div>
