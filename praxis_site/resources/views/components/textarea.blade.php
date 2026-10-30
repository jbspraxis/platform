{{-- A labelled text area with its hint and its error --}}
@props(['name', 'label', 'optional' => false, 'hint' => null, 'value' => null, 'bag' => 'default'])
@php
    $id = $attributes->get('id') ?? 'f-' . str_replace(['.', '[', ']', '_'], '-', $name);
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? $id . '-hint ' : '') . ($error ? $id . '-error' : ''));
@endphp
<div class="field">
    <label class="label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="optional">({{ __('site.optional') }})</span>@endif</label>
    @if ($hint)
        <p class="help" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <textarea {{ $attributes->except('id')->merge(['class' => 'textarea']) }} id="{{ $id }}" name="{{ $name }}"@if ($describedBy) aria-describedby="{{ $describedBy }}"@endif @if ($error) aria-invalid="true"@endif @unless ($optional) aria-required="true"@endunless>{{ old($name, $value) }}</textarea>
    @if ($error)
        <p class="field-error" id="{{ $id }}-error"><i data-lucide="circle-alert"></i><span>{{ $error }}</span></p>
    @endif
</div>
