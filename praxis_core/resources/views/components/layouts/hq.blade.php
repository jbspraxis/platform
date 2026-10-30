<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8">
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
        default => [
            'dash' => 'Overview', 'mdm' => 'Master data', 'hr' => 'Team', 'crm' => 'Leads and proposals',
            'ops' => 'Projects', 'finance' => 'Finance', 'marketing' => 'Marketing', 'system' => 'Settings and access', 'mail' => 'Mail',
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

            @canany(['crm.view', 'broadcasts.view'])
                <a href="{{ $user->can('crm.view') ? route('hq.crm.leads') : route('hq.crm.broadcast') }}" class="rail-link {{ $on('hq.crm.*') }}" wire:navigate>
                    <i data-lucide="target"></i> Leads and proposals
                </a>
                @if (request()->routeIs('hq.crm.*'))
                    <div class="rail-sub">
                        @can('crm.view')
                            <a href="{{ route('hq.crm.leads') }}" class="{{ $on('hq.crm.leads') }}" wire:navigate>Leads</a>
                            <a href="{{ route('hq.crm.consult') }}" class="{{ $on('hq.crm.consult') }}" wire:navigate>Free audits</a>
                            <a href="{{ route('hq.crm.proposal') }}" class="{{ $on('hq.crm.proposal') }}" wire:navigate>Proposals</a>
                        @endcan
                        @can('broadcasts.view')
                            <a href="{{ route('hq.crm.broadcast') }}" class="{{ $on('hq.crm.broadcast') }}" wire:navigate>Broadcasts</a>
                        @endcan
                    </div>
                @endif
            @endcanany

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
                            <a href="{{ route('hq.ops.deployments') }}" class="{{ $on('hq.ops.deployments') }}" wire:navigate>All projects</a>
                            <a href="{{ route('hq.ops.boards.directory') }}" class="{{ $on('hq.ops.boards.directory', 'hq.ops.board') }}" wire:navigate>Sprint boards</a>
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
                            <a href="{{ route('hq.marketing.campaigns') }}" class="{{ $on('hq.marketing.campaigns') }}" wire:navigate>Campaigns</a>
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

            @canany(['access.view', 'system.view'])
                <div class="rail-foot">
                    <a href="{{ $user->can('access.view') ? route('hq.system.roles') : route('hq.system.health') }}" class="rail-link {{ $on('hq.system.*') }}" wire:navigate>
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
                        </div>
                    @endif
                </div>
            @endcanany
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="topbar-title">HQ @if ($section) / <strong>{{ $section }}</strong>@endif</div>
            <div class="topbar-actions">
                <livewire:hq.components.notification-bell />
                <div class="user-chip">
                    <div>
                        <span class="user-chip-name">{{ $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }}</span>
                        <span class="user-chip-role">{{ $user?->role_names ?: 'Staff' }}</span>
                    </div>
                    <span class="avatar" aria-hidden="true">{{ strtoupper(mb_substr($user->first_name ?? '', 0, 1) . mb_substr($user->last_name ?? '', 0, 1)) ?: 'JP' }}</span>
                </div>
                <a href="{{ route('hq.logout') }}" class="btn btn-ghost btn-sm"><i data-lucide="log-out"></i> Sign out</a>
            </div>
        </header>

        <main class="workspace" id="content">
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
