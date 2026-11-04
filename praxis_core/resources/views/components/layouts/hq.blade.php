<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.app-head', ['app' => 'hq', 'title' => $title ?? null])
</head>
<body>
<x-pwa.launch />
@php
    // The rail: each link shows only when the person can open that screen (roles & permissions, config/access.php).
    // Names follow docs/BRAND.md section 5: plain module names, sentence case.
    $on = fn (...$routes) => request()->routeIs(...$routes) ? 'is-active' : '';
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
        ][explode('.', (string) request()->route()?->getName())[1] ?? ''] ?? null,
    };
    $user = auth()->user();
@endphp
<div class="app-shell">
    {{-- On phones and tablets the rail folds into its header; the menu button opens it as a full-height panel (app.css) --}}
    <aside class="rail rail-hq" aria-label="HQ navigation"
           x-data="{ menuOpen: false }" :class="{ 'is-open': menuOpen }"
           x-effect="document.body.classList.toggle('rail-menu-open', menuOpen)"
           @keydown.escape.window="if (menuOpen) { menuOpen = false; $refs.menuBtn.focus() }">
        <div class="rail-head">
            <a href="{{ route('hq.dash.workspace') }}" wire:navigate aria-label="JBS Praxis HQ home">
                <img src="/assets/img/logo/jbs-praxis-horizontal-reversed.svg" alt="JBS Praxis" width="147" height="28">
            </a>
            <button type="button" class="rail-menu-btn" x-ref="menuBtn" @click="menuOpen = !menuOpen"
                    aria-controls="hq-rail-nav" aria-expanded="false" :aria-expanded="menuOpen.toString()"
                    aria-label="Open menu" :aria-label="menuOpen ? 'Close menu' : 'Open menu'">
                <span x-show="!menuOpen"><i data-lucide="menu"></i></span>
                <span x-show="menuOpen" x-cloak><i data-lucide="x"></i></span>
            </button>
        </div>

        <nav class="rail-nav" id="hq-rail-nav">
            <a href="{{ route('hq.dash.workspace') }}" class="rail-link {{ $on('hq.dash.workspace') }}" wire:navigate>
                <i data-lucide="user-round"></i> My workspace
            </a>

            {{-- Mail (docs/MAIL.md): owner-only until the owner opens it to everyone --}}
            @can('use-mail')
                @php $mailUnread = \App\Support\Mail\MailNav::unread($user); @endphp
                <a href="{{ route('hq.mail.inbox') }}" class="rail-link {{ $on('hq.mail.*') }}" wire:navigate>
                    <i data-lucide="mail"></i> Mail
                    @if ($mailUnread > 0)
                        <span style="margin-left: auto; min-width: 20px; padding: 0 6px; border-radius: 999px; background: var(--color-bright-teal); color: var(--color-ink); font-size: 0.75rem; line-height: 1.25rem; font-weight: 700; text-align: center;" aria-label="{{ $mailUnread }} unread">{{ $mailUnread > 99 ? '99+' : $mailUnread }}</span>
                    @endif
                </a>
                @if (request()->routeIs('hq.mail.*'))
                    <div class="rail-sub">
                        <a href="{{ route('hq.mail.inbox') }}" class="{{ $on('hq.mail.inbox') }}" wire:navigate>Inbox</a>
                        <a href="{{ route('hq.mail.mailboxes') }}" class="{{ $on('hq.mail.mailboxes') }}" wire:navigate>Mailboxes</a>
                        <a href="{{ route('hq.mail.signature') }}" class="{{ $on('hq.mail.signature') }}" wire:navigate>Signature</a>
                    </div>
                @endif
            @endcan

            @canany(['dashboard.view', 'audit.view'])
                <a href="{{ $user->can('dashboard.view') ? route('hq.dash.admin') : route('hq.dash.audit') }}" class="rail-link {{ $on('hq.dash.admin', 'hq.dash.investor', 'hq.dash.audit') }}" wire:navigate>
                    <i data-lucide="layout-dashboard"></i> Overview
                </a>
                @if (request()->routeIs('hq.dash.admin', 'hq.dash.investor', 'hq.dash.audit'))
                    <div class="rail-sub">
                        @can('dashboard.view')
                            <a href="{{ route('hq.dash.admin') }}" class="{{ $on('hq.dash.admin') }}" wire:navigate>Company overview</a>
                            <a href="{{ route('hq.dash.investor') }}" class="{{ $on('hq.dash.investor') }}" wire:navigate>Investor view</a>
                        @endcan
                        @can('audit.view')
                            <a href="{{ route('hq.dash.audit') }}" class="{{ $on('hq.dash.audit') }}" wire:navigate>Audit log</a>
                        @endcan
                    </div>
                @endif
            @endcanany

            @php $opportunities = \App\Support\Opportunities\OpportunityAccess::canOpenBoard($user); @endphp
            @if ($opportunities || $user->can('crm.view'))
                <a href="{{ $opportunities ? route('hq.crm.opportunities') : route('hq.crm.leads') }}" class="rail-link {{ $on('hq.crm.*') }}" wire:navigate>
                    <i data-lucide="target"></i> Leads and proposals
                </a>
                @if (request()->routeIs('hq.crm.*'))
                    <div class="rail-sub">
                        {{-- docs/OPPORTUNITIES.md: one record per piece of work with a client --}}
                        @if ($opportunities)
                            <a href="{{ route('hq.crm.opportunities') }}" class="{{ $on('hq.crm.opportunities', 'hq.crm.opportunity') }}" wire:navigate>{{ __('opportunities.nav') }}</a>
                        @endif
                        @can('crm.view')
                            <a href="{{ route('hq.crm.leads') }}" class="{{ $on('hq.crm.leads', 'hq.crm.leads.new', 'hq.crm.lead') }}" wire:navigate>Leads</a>
                            <a href="{{ route('hq.crm.leads.follow-ups') }}" class="{{ $on('hq.crm.leads.follow-ups') }}" wire:navigate>My follow-ups</a>
                            <a href="{{ route('hq.crm.consult') }}" class="{{ $on('hq.crm.consult') }}" wire:navigate>Free audits</a>
                            <a href="{{ route('hq.crm.proposal') }}" class="{{ $on('hq.crm.proposal') }}" wire:navigate>Proposals</a>
                        @endcan
                    </div>
                @endif
            @endif

            {{-- Campaigns (docs/CAMPAIGNS.md): Broadcasts to people who know us; Outreach (C2) to researched prospects --}}
            @canany(['broadcasts.view', 'outreach.view'])
                <a href="{{ $user->can('broadcasts.view') ? route('hq.campaigns.broadcasts') : route('hq.campaigns.outreach') }}" class="rail-link {{ $on('hq.campaigns.*') }}" wire:navigate>
                    <i data-lucide="send"></i> Campaigns
                </a>
                @if (request()->routeIs('hq.campaigns.*'))
                    <div class="rail-sub">
                        @can('broadcasts.view')
                            <a href="{{ route('hq.campaigns.broadcasts') }}" class="{{ $on('hq.campaigns.broadcasts', 'hq.campaigns.broadcast', 'hq.campaigns.report') }}" wire:navigate>Broadcasts</a>
                            <a href="{{ route('hq.campaigns.audiences') }}" class="{{ $on('hq.campaigns.audiences') }}" wire:navigate>Audiences</a>
                        @endcan
                        @can('outreach.view')
                            <a href="{{ route('hq.campaigns.outreach') }}" class="{{ $on('hq.campaigns.outreach', 'hq.campaigns.outreach.*') }}" wire:navigate>Outreach</a>
                        @endcan
                    </div>
                @endif
            @endcanany

            {{-- Documents (docs/DOCUMENTS.md): formal documents on the company letterhead, signed and issued here --}}
            @can('letters.view')
                <a href="{{ route('hq.letters.register') }}" class="rail-link {{ $on('hq.letters.*') }}" wire:navigate>
                    <i data-lucide="file-signature"></i> Documents
                </a>
                @if (request()->routeIs('hq.letters.*'))
                    <div class="rail-sub">
                        <a href="{{ route('hq.letters.register') }}" class="{{ $on('hq.letters.register', 'hq.letters.document') }}" wire:navigate>Register</a>
                        <a href="{{ route('hq.letters.signature') }}" class="{{ $on('hq.letters.signature') }}" wire:navigate>My signature</a>
                    </div>
                @endif
            @endcan

            @canany(['projects.view', 'qa.view', 'timesheets.view', 'signoffs.view'])
                @php
                    $opsHome = collect(['projects.view' => 'hq.ops.deployments', 'qa.view' => 'hq.ops.qa', 'timesheets.view' => 'hq.ops.timers', 'signoffs.view' => 'hq.ops.signoffs'])
                        ->first(fn ($route, $perm) => $user->can($perm));
                @endphp
                <a href="{{ route($opsHome) }}" class="rail-link {{ $on('hq.ops.*') }}" wire:navigate>
                    <i data-lucide="folder-kanban"></i> Projects
                </a>
                @if (request()->routeIs('hq.ops.*'))
                    <div class="rail-sub">
                        @can('projects.view')
                            <a href="{{ route('hq.ops.deployments') }}" class="{{ $on('hq.ops.deployments', 'hq.ops.project') }}" wire:navigate>All projects</a>
                            <a href="{{ route('hq.ops.boards.directory') }}" class="{{ $on('hq.ops.boards.directory', 'hq.ops.board') }}" wire:navigate>Milestone boards</a>
                        @endcan
                        @can('qa.view')
                            <a href="{{ route('hq.ops.qa') }}" class="{{ $on('hq.ops.qa') }}" wire:navigate>Quality and issues</a>
                        @endcan
                        @can('timesheets.view')
                            <a href="{{ route('hq.ops.timers') }}" class="{{ $on('hq.ops.timers') }}" wire:navigate>Time tracking</a>
                        @endcan
                        @can('signoffs.view')
                            <a href="{{ route('hq.ops.signoffs') }}" class="{{ $on('hq.ops.signoffs') }}" wire:navigate>Sign-offs</a>
                        @endcan
                    </div>
                @endif
            @endcanany

            @canany(['finance.view', 'payroll.view', 'expenses.view', 'expenses.create'])
                @php
                    $financeHome = collect(['finance.view' => 'hq.finance.bi', 'payroll.view' => 'hq.finance.payroll', 'expenses.view' => 'hq.finance.manager', 'expenses.create' => 'hq.finance.request'])
                        ->first(fn ($route, $perm) => $user->can($perm));
                @endphp
                <a href="{{ route($financeHome) }}" class="rail-link {{ $on('hq.finance.*') }}" wire:navigate>
                    <i data-lucide="landmark"></i> Finance
                </a>
                @if (request()->routeIs('hq.finance.*'))
                    <div class="rail-sub">
                        @can('finance.view')
                            <a href="{{ route('hq.finance.bi') }}" class="{{ $on('hq.finance.bi') }}" wire:navigate>Finance overview</a>
                            <a href="{{ route('hq.finance.invoices') }}" class="{{ $on('hq.finance.invoices') }}" wire:navigate>Invoices</a>
                            <a href="{{ route('hq.finance.escrow') }}" class="{{ $on('hq.finance.escrow') }}" wire:navigate>Payment verification</a>
                        @endcan
                        @canany(['finance.view', 'expenses.view', 'expenses.create'])
                            <span class="rail-group">Treasury and IFRS</span>
                            @can('finance.view')
                                <a href="{{ route('hq.finance.budgets') }}" class="{{ $on('hq.finance.budgets') }}" wire:navigate>Project budgets</a>
                            @endcan
                            @can('expenses.create')
                                <a href="{{ route('hq.finance.request') }}" class="{{ $on('hq.finance.request') }}" wire:navigate>Cash requests</a>
                            @endcan
                            @can('expenses.view')
                                <a href="{{ route('hq.finance.manager') }}" class="{{ $on('hq.finance.manager') }}" wire:navigate>Cash request approvals</a>
                            @endcan
                            @can('finance.view')
                                <a href="{{ route('hq.finance.coa') }}" class="{{ $on('hq.finance.coa') }}" wire:navigate>Chart of accounts</a>
                                <a href="{{ route('hq.finance.gl') }}" class="{{ $on('hq.finance.gl') }}" wire:navigate>General ledger</a>
                            @endcan
                        @endcanany
                        @can('payroll.view')
                            <span class="rail-group">Payroll and tax</span>
                            <a href="{{ route('hq.finance.tax-config') }}" class="{{ $on('hq.finance.tax-config') }}" wire:navigate>Tax rules</a>
                            <a href="{{ route('hq.finance.variables') }}" class="{{ $on('hq.finance.variables') }}" wire:navigate>Variable pay</a>
                            <a href="{{ route('hq.finance.payroll') }}" class="{{ $on('hq.finance.payroll') }}" wire:navigate>Payroll</a>
                            <a href="{{ route('hq.finance.matrix') }}" class="{{ $on('hq.finance.matrix') }}" wire:navigate>Payroll compliance</a>
                            <a href="{{ route('hq.finance.payslips') }}" class="{{ $on('hq.finance.payslips') }}" wire:navigate>Payslips</a>
                        @endcan
                    </div>
                @endif
            @endcanany

            @can('hr.view')
                <a href="{{ route('hq.mdm.employees') }}" class="rail-link {{ $on('hq.mdm.employees', 'hq.hr.*') }}" wire:navigate>
                    <i data-lucide="users"></i> Team
                </a>
                @if (request()->routeIs('hq.mdm.employees', 'hq.hr.*'))
                    <div class="rail-sub">
                        <a href="{{ route('hq.mdm.employees') }}" class="{{ $on('hq.mdm.employees') }}" wire:navigate>Staff directory</a>
                        <a href="{{ route('hq.hr.time') }}" class="{{ $on('hq.hr.time') }}" wire:navigate>Time and attendance</a>
                        <a href="{{ route('hq.hr.compliance') }}" class="{{ $on('hq.hr.compliance') }}" wire:navigate>Contracts and appraisals</a>
                    </div>
                @endif
            @endcan

            @canany(['marketing.view', 'website.view'])
                <a href="{{ $user->can('marketing.view') ? route('hq.marketing.campaigns') : route('hq.marketing.website') }}" class="rail-link {{ $on('hq.marketing.*') }}" wire:navigate>
                    <i data-lucide="megaphone"></i> Marketing
                </a>
                @if (request()->routeIs('hq.marketing.*'))
                    <div class="rail-sub">
                        @can('marketing.view')
                            {{-- Paid ads; email campaigns are under Campaigns --}}
                            <a href="{{ route('hq.marketing.campaigns') }}" class="{{ $on('hq.marketing.campaigns') }}" wire:navigate>Ad campaigns</a>
                        @endcan
                        @can('website.view')
                            <a href="{{ route('hq.marketing.website') }}" class="{{ $on('hq.marketing.website') }}" wire:navigate>Website settings</a>
                        @endcan
                    </div>
                @endif
            @endcanany

            @can('mdm.view')
                <a href="{{ route('hq.mdm.dictionary') }}" class="rail-link {{ $on('hq.mdm.dictionary', 'hq.mdm.copq', 'hq.mdm.tax', 'hq.mdm.remittance', 'hq.mdm.legal') }}" wire:navigate>
                    <i data-lucide="database"></i> Master data
                </a>
                @if (request()->routeIs('hq.mdm.dictionary', 'hq.mdm.copq', 'hq.mdm.tax', 'hq.mdm.remittance', 'hq.mdm.legal'))
                    <div class="rail-sub">
                        <a href="{{ route('hq.mdm.dictionary') }}" class="{{ $on('hq.mdm.dictionary') }}" wire:navigate>Financial dictionary</a>
                        <a href="{{ route('hq.mdm.copq') }}" class="{{ $on('hq.mdm.copq') }}" wire:navigate>Cost of poor quality tables</a>
                        <a href="{{ route('hq.mdm.tax') }}" class="{{ $on('hq.mdm.tax') }}" wire:navigate>Tax compliance</a>
                        <a href="{{ route('hq.mdm.remittance') }}" class="{{ $on('hq.mdm.remittance') }}" wire:navigate>Remittance accounts</a>
                        <a href="{{ route('hq.mdm.legal') }}" class="{{ $on('hq.mdm.legal') }}" wire:navigate>Legal entities</a>
                    </div>
                @endif
            @endcan

            @canany(['access.view', 'system.view', 'integrations.manage'])
                <div class="rail-foot">
                    <a href="{{ $user->can('access.view') ? route('hq.system.roles') : ($user->can('system.view') ? route('hq.system.health') : route('hq.system.integrations')) }}" class="rail-link {{ $on('hq.system.*') }}" wire:navigate>
                        <i data-lucide="settings"></i> Settings and access
                    </a>
                    @if (request()->routeIs('hq.system.*'))
                        <div class="rail-sub">
                            @can('access.view')
                                <a href="{{ route('hq.system.roles') }}" class="{{ $on('hq.system.roles') }}" wire:navigate>Accounts</a>
                                <a href="{{ route('hq.system.permissions') }}" class="{{ $on('hq.system.permissions') }}" wire:navigate>Roles and permissions</a>
                            @endcan
                            @can('system.view')
                                <a href="{{ route('hq.system.health') }}" class="{{ $on('hq.system.health') }}" wire:navigate>System health</a>
                            @endcan
                            @can('integrations.manage')
                                <a href="{{ route('hq.system.integrations') }}" class="{{ $on('hq.system.integrations') }}" wire:navigate>{{ __('integrations.nav') }}</a>
                            @endcan
                        </div>
                    @endif
                </div>
            @endcanany
        </nav>
    </aside>

    <div class="main">
        {{-- Top bar menus (the bell and the avatar): a dropdown on computers; on phones a full-width panel just
             under the top bar over a dimmed page (owner, 30 Oct 2026). --hq-menu-top is set when a menu opens. --}}
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
            @media (max-width: 640px) {
                .hq-menu-panel, .hq-user-panel { position: fixed; left: 0; right: 0; top: var(--hq-menu-top, 64px); width: auto; border-radius: 0 0 12px 12px; border-left: 0; border-right: 0;
                    max-height: calc(100dvh - var(--hq-menu-top, 64px) - 12px); display: flex; flex-direction: column; }
                .hq-menu-scroll { flex: 1 1 auto; max-height: none !important; min-height: 0; }
                .hq-menu-dim { display: block; position: fixed; left: 0; right: 0; bottom: 0; top: var(--hq-menu-top, 64px); background: rgba(12, 20, 43, 0.45); z-index: 59; }
            }
        </style>
        <header class="topbar">
            <div class="topbar-title">HQ @if ($section) / <strong>{{ $section }}</strong>@endif</div>
            <div class="topbar-actions">
                <livewire:hq.components.notification-bell />
                @php
                    $userName = $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                    // One role shows with the name: the primary one (Settings → Accounts); the others only add access
                    $userRole = $user?->primary_role_name ?: 'Staff';
                @endphp
                {{-- The person's menu: My profile, notification settings, installing the app, and Sign out --}}
                <div class="hq-user" x-data="{ open: false, top: 64 }" @keydown.escape.window="if (open) { open = false; $refs.userBtn.focus() }">
                    <button type="button" class="user-chip hq-user-btn" x-ref="userBtn"
                            @click="open = !open; if (open) top = Math.max(0, Math.round($el.closest('.topbar').getBoundingClientRect().bottom))"
                            aria-haspopup="true" aria-controls="hq-user-menu" aria-expanded="false" :aria-expanded="open.toString()"
                            aria-label="Your account: {{ $userName }}">
                        <div>
                            <span class="user-chip-name">{{ $userName }}</span>
                            <span class="user-chip-role">{{ $userRole }}</span>
                        </div>
                        <x-hq.avatar :user="$user" />
                    </button>
                    <div class="hq-menu-dim" x-show="open" x-transition.opacity @click="open = false" :style="'--hq-menu-top:' + top + 'px'" style="display: none;"></div>
                    <div id="hq-user-menu" class="hq-menu-panel hq-user-panel bg-paper border border-line rounded-card shadow-float"
                         x-show="open" x-transition.opacity @click.outside="open = false" :style="'--hq-menu-top:' + top + 'px'" style="display: none;">
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

        <main class="workspace" id="content">
            {{-- After a password sign-in with no passkey yet: offer one (docs/SIGN_IN.md) --}}
            @if (\App\Livewire\Account\PasskeyOffer::due('web'))
                <livewire:account.passkey-offer guard="web" />
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
{{-- The 45-minute check while a task timer runs, on every HQ page (docs/PROJECTS.md) --}}
@auth
    <livewire:hq.timer-presence />
@endauth
@livewireScripts
</body>
</html>
