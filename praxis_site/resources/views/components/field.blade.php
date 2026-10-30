{{-- A labelled input with its hint and its error (docs/BRAND.md section 12: visible label, optional fields marked) --}}
@props(['name', 'label', 'type' => 'text', 'optional' => false, 'hint' => null, 'value' => null, 'bag' => 'default'])
@php
    $id = 'f-' . str_replace(['.', '[', ']', '_'], '-', $name);
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? $id . '-hint ' : '') . ($error ? $id . '-error' : ''));
@endphp
<div class="field">
    <label class="label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="optional">({{ __('site.optional') }})</span>@endif</label>
    @if ($hint)
        <p class="help" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <input {{ $attributes->merge(['class' => 'input']) }} type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"@if ($type !== 'file') value="{{ old($name, $value) }}"@endif @if ($describedBy) aria-describedby="{{ $describedBy }}"@endif @if ($error) aria-invalid="true"@endif @unless ($optional) aria-required="true"@endunless>
    @if ($error)
        <p class="field-error" id="{{ $id }}-error"><i data-lucide="circle-alert"></i><span>{{ $error }}</span></p>
    @endif
</div>
