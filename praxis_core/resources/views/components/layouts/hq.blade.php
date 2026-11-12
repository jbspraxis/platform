<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.app-head', ['app' => 'hq', 'title' => $title ?? null])
</head>
<body class="hq-body">
<x-pwa.launch />
@php
    // The navigation (app/Support/HqNav.php): each section and page shows only when the person can open it (roles
    // and permissions, config/access.php). The rail on computers and, on phones, the bottom bar, the More sheet and
    // the strip of a module's pages all draw it (docs/HQ_PHONE.md).
    $user = auth()->user();
    $nav = \App\Support\HqNav::sections($user);
    $here = \App\Support\HqNav::current($nav);
    $bar = \App\Support\HqNav::bar($nav);
    $more = \App\Support\HqNav::more($nav);
    $barKeys = array_column($bar, 'key');
    // The top bar names the rail entry that is highlighted: two screens sit under a different rail entry
    // than their route group (My workspace; Staff directory under Team)
    $section = match (true) {
        request()->routeIs('hq.dash.workspace') => 'My workspace',
        request()->routeIs('hq.mdm.employees') => 'Team',
        request()->routeIs('hq.profile') => 'My profile',
        default => [
            'dash' => 'Overview', 'mdm' => 'Master data', 'hr' => 'Team', 'crm' => 'Leads and proposals',
            'ops' => 'Projects', 'finance' => 'Finance', 'marketing' => 'Marketing', 'system' => 'Settings and access', 'mail' => 'Mail',
            'campaigns' => 'Campaigns',
            'letters' => 'Documents',
            'company-profile' => 'Company profile',
        ][explode('.', (string) request()->route()?->getName())[1] ?? ''] ?? null,
    };
    $userName = $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    // One role shows with the name: the primary one (Settings → Accounts); the others only add access
    $userRole = $user?->primary_role_name ?: 'Staff';
    // Unread notifications, on the avatar on phones; the bell keeps it current (browser event hq-unread)
    try {
        $unread = $user ? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', $user->id)->where('is_read', 0)->count() : 0;
    } catch (\Throwable) {
        $unread = 0; // a convenience: never stop a page for it
    }
    $count = fn (int $n) => $n > 99 ? '99+' : (string) $n;
@endphp
<div class="app-shell hq-shell">
    {{-- Computers and tablets in landscape. Phones and tablets in portrait have the bottom bar instead (hq-phone.css) --}}
    <aside class="rail rail-hq" aria-label="HQ navigation">
        <div class="rail-head">
            <a href="{{ route('hq.dash.workspace') }}" wire:navigate aria-label="JBS Praxis HQ home">
                <img src="/assets/img/logo/jbs-praxis-horizontal-reversed.svg" alt="JBS Praxis" width="147" height="28">
            </a>
        </div>

        <nav class="rail-nav" id="hq-rail-nav">
            @foreach ($nav as $s)
                @if ($s['foot'])<div class="rail-foot">@endif
                <a href="{{ $s['href'] }}" class="rail-link {{ $s['active'] ? 'is-active' : '' }}" wire:navigate>
                    <i data-lucide="{{ $s['icon'] }}"></i> {{ $s['label'] }}
                    @if ($s['count'] > 0 && $s['count_tone'] === 'warning')
                        <span class="pill pill-warning num" style="margin-left: auto;" title="{{ $s['count_label'] }}">{{ $s['count'] }}</span>
                    @elseif ($s['count'] > 0)
                        <span style="margin-left: auto; min-width: 20px; padding: 0 6px; border-radius: 999px; background: var(--color-bright-teal); color: var(--color-ink); font-size: 0.75rem; line-height: 1.25rem; font-weight: 700; text-align: center;" aria-label="{{ $s['count_label'] }}">{{ $count($s['count']) }}</span>
                    @endif
                </a>
                @if ($s['active'] && $s['subs'])
                    <div class="rail-sub">
                        @php $group = null; @endphp
                        @foreach ($s['subs'] as $sub)
                            @if ($sub['group'] && $sub['group'] !== $group)
                                @php $group = $sub['group']; @endphp
                                <span class="rail-group">{{ $group }}</span>
                            @endif
                            <a href="{{ $sub['href'] }}" class="{{ $sub['active'] ? 'is-active' : '' }}" wire:navigate>{{ $sub['label'] }}@if ($sub['suffix']) <span class="num">{{ $sub['suffix'] }}</span>@endif</a>
                        @endforeach
                    </div>
                @endif
                @if ($s['foot'])</div>@endif
            @endforeach
        </nav>
    </aside>

    <div class="main">
        {{-- Top bar menus (the bell and the avatar): a dropdown on computers; on phones the bell is a sheet from the
             bottom (hq-phone.css) and the avatar opens More --}}
        <style>
            .hq-menu-panel { position: absolute; right: 0; top: calc(100% + 8px); width: min(360px, calc(100vw - 32px)); z-index: 60; overflow: hidden; }
            .hq-menu-dim { display: none; }
            .hq-user { position: relative; display: flex; align-items: center; }
            .hq-user-btn { background: none; border: 0; padding: 4px; margin: -4px; border-radius: var(--radius-control); cursor: pointer; font: inherit; color: inherit; }
            .hq-user-btn:hover .avatar, .hq-user-btn[aria-expanded="true"] .avatar { box-shadow: 0 0 0 2px var(--color-teal); }
            .hq-user-btn:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; }
            .hq-user-panel { width: min(300px, calc(100vw - 32px)); }
            .hq-user-head { display: flex; align-items: center; gap: 12px; padding: 16px; border-bottom: 1px solid var(--color-line); }
            .hq-user-head .avatar { width: 44px; height: 44px; font-size: 0.9375rem; }
            .hq-user-head strong { display: block; color: var(--color-ink); font-size: 0.9375rem; line-height: 1.375rem; font-weight: 600; }
            .hq-user-head div span { display: block; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); overflow-wrap: anywhere; }
            .hq-user-links { padding: 6px 0; border-bottom: 1px solid var(--color-line); }
            .hq-user-links .hqp-menu-item { text-decoration: none; }
            .hq-user-foot { padding: 12px 16px 16px; }
            .hq-user-foot .btn { width: 100%; justify-content: center; }
        </style>
        <header class="topbar" data-hq-topbar>
            {{-- Phones: the mark, the page's title once its own heading has scrolled away, and the avatar (owner, 12 Nov 2026) --}}
            <a href="{{ route('hq.dash.workspace') }}" class="hq-mark" wire:navigate aria-label="JBS Praxis HQ home">
                <img src="/assets/img/logo/jbs-praxis-monogram-colour.svg" alt="" width="40" height="24">
            </a>
            <div class="hq-bar-title" data-hq-bar-title aria-hidden="true">{{ $section ?? 'HQ' }}</div>
            <div class="topbar-title">HQ @if ($section) / <strong>{{ $section }}</strong>@endif</div>
            <div class="topbar-actions">
                <livewire:hq.components.notification-bell />
                {{-- The person's menu: My profile, notification settings, installing the app, and Sign out. On phones
                     the avatar opens More, which holds the same and the modules --}}
                <div class="hq-user" x-data="{ open: false, unread: {{ (int) $unread }} }" @hq-unread.window="unread = Number($event.detail.count) || 0"
                     @keydown.escape.window="if (open) { open = false; $refs.userBtn.focus() }">
                    <button type="button" class="user-chip hq-user-btn" x-ref="userBtn"
                            @click="if (window.matchMedia('(max-width: 900px)').matches) { $dispatch('hq-open-more') } else { open = !open }"
                            aria-haspopup="true" aria-controls="hq-user-menu" aria-expanded="false" :aria-expanded="open.toString()"
                            aria-label="Your account: {{ $userName }}">
                        <div>
                            <span class="user-chip-name">{{ $userName }}</span>
                            <span class="user-chip-role">{{ $userRole }}</span>
                        </div>
                        <span class="hq-avatar-wrap">
                            <x-hq.avatar :user="$user" />
                            <span class="hq-avatar-count" x-show="unread > 0" x-text="unread > 9 ? '9+' : unread" aria-hidden="true" @if (! $unread) style="display: none;" @endif>{{ $unread > 9 ? '9+' : $unread }}</span>
                        </span>
                    </button>
                    <div id="hq-user-menu" class="hq-menu-panel hq-user-panel bg-paper border border-line rounded-card shadow-float"
                         x-show="open" x-transition.opacity @click.outside="open = false" style="display: none;">
                        <div class="hq-user-head">
                            <x-hq.avatar :user="$user" />
                            <div style="min-width: 0;">
                                <strong>{{ $userName }}</strong>
                                <span>{{ $userRole }}</span>
                                <span>{{ $user->email }}</span>
                            </div>
                        </div>
                        <div class="hq-user-links hq-menu-scroll" style="overflow-y: auto;">
                            <a href="{{ route('hq.profile') }}" class="hqp-menu-item" wire:navigate @click="open = false">
                                <i data-lucide="user-round" style="width: 18px; height: 18px;"></i> My profile
                            </a>
                            <button type="button" class="hqp-menu-item" @click="open = false; $dispatch('open-notification-settings')">
                                <i data-lucide="settings" style="width: 18px; height: 18px;"></i> Notification settings
                            </button>
                            {{-- hq-pwa.js draws "Install the app" (and "Turn on notifications") here when this device can use them --}}
                            <div data-hq-pwa="bell-actions" x-on:hq-pwa-close-menu="open = false"></div>
                        </div>
                        <div class="hq-user-foot">
                            <a href="{{ route('hq.logout') }}" class="btn btn-danger"><i data-lucide="log-out"></i> Sign out</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Phones: the pages of the module in view, as a strip under the top bar (the rail lists them on computers) --}}
        @if ($here && count($here['subs']) > 1)
            <nav class="hq-subnav" aria-label="{{ $here['label'] }} pages" data-hq-subnav>
                @foreach ($here['subs'] as $sub)
                    <a href="{{ $sub['href'] }}" wire:navigate @if ($sub['active']) aria-current="page" @endif>{{ $sub['label'] }}@if ($sub['suffix']) <span class="num">{{ $sub['suffix'] }}</span>@endif</a>
                @endforeach
            </nav>
        @endif

        <main class="workspace" id="content">
            {{-- After a password sign-in with no passkey yet: offer one (docs/SIGN_IN.md) --}}
            @if (\App\Livewire\Account\PasskeyOffer::due('web'))
                <livewire:account.passkey-offer guard="web" />
            @endif
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Phones and tablets in portrait: the bottom bar and More (docs/HQ_PHONE.md). Hidden on computers (hq-phone.css) --}}
<div class="hq-phone" x-data="{ more: false }" @hq-open-more.window="more = true" @keydown.escape.window="more = false">
    <nav class="hq-bottombar" aria-label="Main">
        @foreach ($bar as $s)
            <a href="{{ $s['href'] }}" class="hq-tab" wire:navigate @if ($s['active']) aria-current="page" @endif
               @if ($s['count_label']) aria-label="{{ $s['short'] }}, {{ $s['count_label'] }}" @endif>
                <span class="hq-tab-icon">
                    <i data-lucide="{{ $s['bar_icon'] }}"></i>
                    @if ($s['count'] > 0)<span class="hq-count" aria-hidden="true">{{ $count($s['count']) }}</span>@endif
                </span>
                <span class="hq-tab-label">{{ $s['short'] }}</span>
            </a>
        @endforeach
        <button type="button" class="hq-tab {{ $here && ! in_array($here['key'], $barKeys, true) ? 'is-here' : '' }}" @click="more = true"
                aria-haspopup="dialog" aria-controls="hq-more" aria-expanded="false" :aria-expanded="more.toString()">
            <span class="hq-tab-icon"><i data-lucide="layout-grid"></i></span>
            <span class="hq-tab-label">More</span>
        </button>
    </nav>

    <div class="hq-sheet-dim" x-show="more" x-transition.opacity @click="more = false" style="display: none;"></div>
    <div id="hq-more" class="hq-sheet hq-more" role="dialog" aria-modal="true" aria-labelledby="hq-more-title"
         x-show="more" x-trap.inert.noscroll="more" data-hq-sheet @hq-sheet-close="more = false" style="display: none;">
        <div class="hq-sheet-grab" data-hq-sheet-grab><span class="hq-sheet-handle"></span></div>
        <h2 id="hq-more-title" class="sr-only">More</h2>
        <div class="hq-sheet-body">
            <div class="hq-more-person">
                <a href="{{ route('hq.profile') }}" class="hq-more-me" wire:navigate @click="more = false">
                    <x-hq.avatar :user="$user" :size="44" />
                    <span>
                        <strong>{{ $userName }}</strong>
                        <span>{{ $userRole }}</span>
                    </span>
                </a>
                <button type="button" class="hq-icon-btn" x-data="{ unread: {{ (int) $unread }} }" @hq-unread.window="unread = Number($event.detail.count) || 0"
                        @click="more = false; setTimeout(() => window.dispatchEvent(new CustomEvent('hq-open-bell')), 0)"
                        :aria-label="'Notifications' + (unread > 0 ? ', ' + unread + ' unread' : '')" aria-label="Notifications{{ $unread ? ', ' . $unread . ' unread' : '' }}">
                    <i data-lucide="bell"></i>
                    <span class="hq-count is-alert" x-show="unread > 0" x-text="unread > 9 ? '9+' : unread" aria-hidden="true" @if (! $unread) style="display: none;" @endif>{{ $unread > 9 ? '9+' : $unread }}</span>
                </button>
            </div>
            {{-- hq-pwa.js draws "Install the app" and "Turn on notifications" here when this device can use them --}}
            <div class="hq-more-app" data-hq-pwa="bell-actions" x-on:hq-pwa-close-menu="more = false"></div>

            @foreach ($more as $group => $sections)
                <h3 class="hq-group-title">{{ $group }}</h3>
                <div class="hq-tiles">
                    @foreach ($sections as $s)
                        <a href="{{ $s['href'] }}" class="hq-tile" wire:navigate @click="more = false" @if ($s['active']) aria-current="page" @endif
                           @if ($s['count_label']) aria-label="{{ $s['tile'] }}, {{ $s['count_label'] }}" @endif>
                            <span class="hq-tile-icon">
                                <i data-lucide="{{ $s['icon'] }}"></i>
                                @if ($s['count'] > 0)<span class="hq-count" aria-hidden="true">{{ $count($s['count']) }}</span>@endif
                            </span>
                            <span>{{ $s['tile'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach

            <div class="hq-more-foot">
                <a href="{{ route('hq.logout') }}" class="btn btn-danger"><i data-lucide="log-out"></i> Sign out</a>
            </div>
        </div>
    </div>
</div>
{{-- Pull down at the top of a page to refresh it, in the installed app on a phone (resources/js/hq-phone.js) --}}
<div class="hq-pull" data-hq-pull aria-hidden="true"><i data-lucide="refresh-cw"></i></div>

{{-- The 45-minute check while a task timer runs, on every HQ page (docs/PROJECTS.md) --}}
@auth
    <livewire:hq.timer-presence />
@endauth
{{-- Every confirmation and error, in the corner over the page (resources/views/components/flash.blade.php) --}}
<x-flash />
@livewireScripts
</body>
</html>
