{{-- A platform's own screen (redesign, 4 Nov 2026): the owner's screenshots from public/img/screens, browser and window
     bars trimmed, email addresses blurred, 2:1, AVIF then WebP at 800, 1280 and 1920px. Fixed size, so nothing jumps.
     Usage: <x-screen slug="mycity-app" :alt="__('…')" sizes="(min-width: 1200px) 1136px, 100vw" /> --}}
@props(['slug', 'alt', 'sizes' => '100vw', 'priority' => false])
@php
    $set = fn (string $ext) => collect([800, 1280, 1920])->map(fn ($w) => asset("img/screens/{$slug}-{$w}.{$ext}") . " {$w}w")->implode(', ');
@endphp
<picture {{ $attributes->merge(['class' => 'screen-shot']) }}>
    <source type="image/avif" srcset="{{ $set('avif') }}" sizes="{{ $sizes }}">
    <source type="image/webp" srcset="{{ $set('webp') }}" sizes="{{ $sizes }}">
    <img src="{{ asset("img/screens/{$slug}-1280.webp") }}" alt="{{ $alt }}" width="1920" height="960"
         @if ($priority) fetchpriority="high" @else loading="lazy" @endif decoding="async">
</picture>
