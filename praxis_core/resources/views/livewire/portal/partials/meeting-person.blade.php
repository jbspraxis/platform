{{-- Who books the follow-up, and what they would like to discuss (portal meetings page) --}}
<div class="mg-row2">
    <div class="field">
        <label for="mg-name" class="label">{{ __('meetings.portal.name') }}</label>
        <input id="mg-name" type="text" class="input" wire:model="name" maxlength="150" autocomplete="name"
               @error('name') aria-invalid="true" aria-describedby="mg-name-error" @enderror>
        @error('name') <span class="field-error" id="mg-name-error">{{ $message }}</span> @enderror
    </div>
    <div class="field">
        <label for="mg-email" class="label">{{ __('meetings.portal.email') }}</label>
        <input id="mg-email" type="email" class="input" wire:model="email" maxlength="150" autocomplete="email"
               @error('email') aria-invalid="true" aria-describedby="mg-email-error" @else aria-describedby="mg-email-help" @enderror>
        @error('email')
            <span class="field-error" id="mg-email-error">{{ $message }}</span>
        @else
            <span class="help" id="mg-email-help">{{ __('meetings.portal.email_help') }}</span>
        @enderror
    </div>
</div>
<div class="field">
    <label for="mg-note" class="label">{{ __('meetings.portal.note') }}</label>
    <textarea id="mg-note" class="textarea" rows="3" maxlength="2000" wire:model="note"
              @error('note') aria-invalid="true" aria-describedby="mg-note-error" @enderror></textarea>
    @error('note') <span class="field-error" id="mg-note-error">{{ $message }}</span> @enderror
</div>
