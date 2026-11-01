{{-- The lead's instructions for the AI's SOW draft, and whether it reads the full transcripts (SowTab) --}}
<div class="field">
    <label for="{{ $id }}" class="label">{{ __('proposals.start.instructions') }}</label>
    <textarea id="{{ $id }}" class="textarea" rows="3" maxlength="{{ \App\Support\Opportunities\Sows::MAX_NOTE }}" wire:model="instructions"
              aria-describedby="{{ $id }}-help @error('instructions') {{ $id }}-error @enderror" @error('instructions') aria-invalid="true" @enderror></textarea>
    <span class="help" id="{{ $id }}-help">{{ __('sow.start.instructions_help') }}</span>
    @error('instructions') <span class="field-error" id="{{ $id }}-error">{{ $message }}</span> @enderror
</div>
<label class="pp-check">
    <input type="checkbox" class="checkbox" wire:model="withTranscripts">
    <span>{{ __('sow.start.transcripts') }} <small>{{ __('sow.start.transcripts_help') }}</small></span>
</label>
<p class="help" style="margin: 0">{{ __('sow.start.language', ['language' => __('proposals.language_names.' . $o->language)]) }} {{ __('sow.start.cost') }}</p>
