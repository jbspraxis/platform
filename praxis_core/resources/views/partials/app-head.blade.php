{{-- The <head> shared by HQ and the Client Portal: icons, app manifest, splash screens and the compiled
     stylesheet (docs/BRAND.md section 14). $app is 'hq' or 'portal'; $title is the page name. --}}
@php
    $app = ($app ?? 'hq') === 'portal' ? 'portal' : 'hq';
    // The Client Portal's name is translated (docs/BRAND.md section 4): « Portail client JBS Praxis »
    $appName = $app === 'portal' ? __('portal.app_full_name') : 'JBS Praxis HQ';
    $splash = [
        [1290, 2796, '(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)'],
        [1179, 2556, '(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)'],
        [1170, 2532, '(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)'],
        [750, 1334, '(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)'],
        [2048, 2732, '(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)'],
    ];
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ filled($title ?? null) ? $title . ' | ' . $appName : $appName }}</title>
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="manifest" href="/manifest-{{ $app }}.webmanifest">
<meta name="theme-color" content="#0C142B">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $app === 'portal' ? __('portal.app_name') : 'HQ' }}">
@foreach ($splash as [$w, $h, $media])
<link rel="apple-touch-startup-image" href="/splash/{{ $app }}-splash-{{ $w }}x{{ $h }}.png" media="{{ $media }} and (orientation: portrait)">
@endforeach
{{-- Views may call lucide.createIcons() while the page is still loading; the bundle (deferred) replaces this stub --}}
<script>window.lucide = window.lucide || { createIcons: function () {} };</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
@stack('styles')
