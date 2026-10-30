{{--
    Flash messages in HQ and the Client Portal, on the brand alert (.alert in resources/css/app.css, docs/BRAND.md
    section 12). <x-flash /> shows the session's success, info, warning and error messages. :keys names other
    session keys, whose tone follows the name (team_success, lockout_error; anything else reads as success);
    :tones overrides a tone, e.g. :tones="['error' => 'warning']". "toast" floats the message over the page with
    a close button; success and info close by themselves after 6 seconds, warnings and errors stay.
--}}
@props(['keys' => ['success', 'info', 'warning', 'error'], 'tones' => [], 'toast' => false])
@php
    // tone => [alert class, Lucide icon, role]: problems are announced at once, news politely
    $looks = [
        'success' => ['alert-success', 'circle-check', 'status'],
        'info' => ['alert-info', 'info', 'status'],
        'warning' => ['alert-warning', 'triangle-alert', 'alert'],
        'error' => ['alert-danger', 'circle-alert', 'alert'],
    ];
    $toneOf = fn ($key) => $tones[$key] ?? (preg_match('/(success|info|warning|error)$/', $key, $m) ? $m[1] : 'success');
@endphp
@foreach ($keys as $key)
    @if (filled(session($key)))
        @php [$class, $icon, $role] = $looks[$toneOf($key)] ?? $looks['info']; @endphp
        <div {{ $attributes->class(['alert', $class, 'alert-toast' => $toast]) }} role="{{ $role }}" wire:key="flash-{{ $key }}"
            @if ($toast) x-data="{ show: true }" x-show="show" x-transition.opacity @if ($role === 'status') x-init="setTimeout(() => show = false, 6000)" @endif @endif>
            <i data-lucide="{{ $icon }}"></i>
            <span>{{ session($key) }}</span>
            @if ($toast)
                <button type="button" class="alert-close" x-on:click="show = false" aria-label="{{ __('flash.close') }}"><i data-lucide="x"></i></button>
            @endif
        </div>
    @endif
@endforeach
@if ($toast)
    @once
        <style>
            .alert-toast { position: fixed; top: 80px; right: 24px; z-index: 70; max-width: 420px; box-shadow: var(--shadow-float); }
            .alert-toast > span { flex: 1 1 auto; }
            .alert-close { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; margin: -3px -8px -3px 4px; padding: 0; border: 0; border-radius: 4px; background: transparent; color: inherit; cursor: pointer; }
            .alert-close:hover { background: rgba(12, 20, 43, 0.06); }
            .alert-close:focus-visible { outline: 2px solid currentColor; outline-offset: 1px; }
            .alert-close svg { width: 16px; height: 16px; margin: 0; }
            @media (max-width: 900px) { .alert-toast { top: 16px; left: 16px; right: 16px; max-width: none; } }
        </style>
    @endonce
@endif
