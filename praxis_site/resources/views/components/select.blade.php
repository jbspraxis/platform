{{-- A labelled select. $options: stored value => label. --}}
@props(['name', 'label', 'options' => [], 'optional' => false, 'hint' => null, 'value' => null, 'placeholder' => true])
@php
    $id = 'f-' . str_replace(['.', '[', ']', '_'], '-', $name);
    $error = $errors->first($name);
    $current = (string) old($name, $value);
    $describedBy = trim(($hint ? $id . '-hint ' : '') . ($error ? $id . '-error' : ''));
@endphp
<div class="field">
    <label class="label" for="{{ $id }}">{{ $label }}@if ($optional) <span class="optional">({{ __('site.optional') }})</span>@endif</label>
    @if ($hint)
        <p class="help" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <select {{ $attributes->merge(['class' => 'select']) }} id="{{ $id }}" name="{{ $name }}"@if ($describedBy) aria-describedby="{{ $describedBy }}"@endif @if ($error) aria-invalid="true"@endif @unless ($optional) aria-required="true"@endunless>
        @if ($placeholder)
            <option value="">{{ __('site.form.choose') }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($error)
        <p class="field-error" id="{{ $id }}-error"><i data-lucide="circle-alert"></i><span>{{ $error }}</span></p>
    @endif
</div>
