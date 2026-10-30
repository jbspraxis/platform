{{-- "English | Français" (docs/BRAND.md section 4): the component's switchLanguage() remembers the choice and reloads the page --}}
@php $current = app()->getLocale() === 'fr' ? 'fr' : 'en'; @endphp
<div class="flex items-center gap-2 text-sm" role="group" aria-label="{{ __('portal.language.label') }}">
    @foreach (['en', 'fr'] as $lang)
        @if (!$loop->first)<span class="text-line-strong" aria-hidden="true">|</span>@endif
        @if ($current === $lang)
            <span lang="{{ $lang }}" class="font-semibold text-ink" aria-current="true">{{ __('portal.language.' . $lang) }}</span>
        @else
            <button type="button" lang="{{ $lang }}" wire:click="switchLanguage('{{ $lang }}')" class="cursor-pointer border-0 bg-transparent p-0 font-medium text-deep-teal hover:underline">{{ __('portal.language.' . $lang) }}</button>
        @endif
    @endforeach
</div>
