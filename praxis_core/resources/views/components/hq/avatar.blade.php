{{-- A person's avatar: their photo (uploaded on My profile) or their initials. Decorative: the name is always next to it. --}}
@props(['user'])
@php $url = $user?->avatarUrl(); @endphp
@if ($url)
    <img src="{{ $url }}" alt="" aria-hidden="true" {{ $attributes->merge(['class' => 'avatar']) }} style="object-fit: cover;" loading="lazy" decoding="async">
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'avatar']) }}>{{ $user?->initials() ?? 'JP' }}</span>
@endif
