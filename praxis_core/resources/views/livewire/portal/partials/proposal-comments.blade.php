{{-- The comments on one section of the proposal in the portal, and the form to add one while it is under review --}}
@if ($comments->has($key))
    <ul class="pr-comments" aria-label="{{ __('proposals.portal.comments_on', ['section' => $label]) }}">
        @foreach ($comments[$key] as $c)
            <li class="pr-comment" wire:key="c-{{ $c->id }}">
                <small>{{ __('proposals.comments.by', ['name' => $c->author_name, 'date' => $day($c->created_at)]) }}@if ($c->resolved_at) · {{ __('proposals.portal.addressed') }}@endif</small>
                <p>{{ $c->body }}</p>
            </li>
        @endforeach
    </ul>
@endif
@if ($reviewing)
    <div class="pr-comment-actions">
        @if ($commenting === $key)
            <form wire:submit="comment" class="pr-form" style="margin-top: 0" novalidate>
                <div class="field">
                    <label for="pr-body-{{ $key }}" class="label">{{ __('proposals.portal.comment_label', ['section' => $label]) }}</label>
                    <textarea id="pr-body-{{ $key }}" class="textarea" rows="4" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_COMMENT }}" wire:model="body" x-init="$nextTick(() => $el.focus())"
                              @error('body') aria-invalid="true" aria-describedby="pr-body-error" @enderror></textarea>
                    @error('body') <span class="field-error" id="pr-body-error">{{ $message }}</span> @enderror
                </div>
                @include('livewire.portal.partials.proposal-person')
                <div class="pr-buttons">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="comment"><i data-lucide="message-square-plus"></i> {{ __('proposals.portal.comment_submit') }}</button>
                    <button type="button" class="btn btn-secondary" wire:click="closeComment">{{ __('proposals.portal.cancel') }}</button>
                </div>
            </form>
        @else
            <button type="button" class="btn btn-ghost btn-sm" wire:click="openComment('{{ $key }}')"><i data-lucide="message-square-plus"></i> {{ $key === \App\Support\Opportunities\Proposals::GENERAL ? __('proposals.portal.comment_general') : __('proposals.portal.comment') }}</button>
        @endif
    </div>
@endif
