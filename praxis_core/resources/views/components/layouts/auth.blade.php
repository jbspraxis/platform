<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.app-head', ['app' => 'hq', 'title' => $title ?? 'Sign in'])
</head>
{{-- HQ sign-in and account setup: one card on Mist, the logo above it --}}
<body>
    <x-pwa.launch />
    <main class="auth-page">
        <div class="auth-card">
            <img class="auth-logo" src="/assets/img/logo/jbs-praxis-horizontal-colour.svg" alt="JBS Praxis" width="210" height="40">
            {{ $slot }}
        </div>
    </main>
    @livewireScripts
</body>
</html>
