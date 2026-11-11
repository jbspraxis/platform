{{-- The <head> shared by HQ and the Client Portal: icons, app manifest, splash screens and the compiled
     stylesheet (docs/BRAND.md section 14). $app is 'hq' or 'portal'; $title is the page name.
     HQ and the Client Portal install as apps (docs/PWA.md): their manifest, startup images and app script come from pwa.head. --}}
@php
    $app = ($app ?? 'hq') === 'portal' ? 'portal' : 'hq';
    // The Client Portal's name is translated (docs/BRAND.md section 4): « Portail client JBS Praxis »
    $appName = $app === 'portal' ? __('portal.app_full_name') : 'JBS Praxis HQ';
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>{{ filled($title ?? null) ? $title . ' | ' . $appName : $appName }}</title>
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
@include('pwa.head', ['pwaApp' => $app])
{{-- Views may call lucide.createIcons() while the page is still loading; the bundle (deferred) replaces this stub --}}
<script>window.lucide = window.lucide || { createIcons: function () {} };</script>
{{-- Passkeys on HQ and the Client Portal (docs/SIGN_IN.md) --}}
@include('partials.passkey-script')
{{-- The UI font (Plus Jakarta Sans, Latin letters) downloads alongside app.css, not after it. The same file app.css
     imports: keep this path in step with it (FrontendAssetsTest checks). Not the serif or the other alphabets: few pages need them. --}}
@php
    try {
        $uiFont = \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource-variable/plus-jakarta-sans/files/plus-jakarta-sans-latin-wght-normal.woff2');
    } catch (\Throwable $e) {
        $uiFont = null; // not in this build: the page opens as before, only without the head start
    }
@endphp
@if ($uiFont)
<link rel="preload" as="font" type="font/woff2" crossorigin href="{{ $uiFont }}">
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
@stack('styles')
