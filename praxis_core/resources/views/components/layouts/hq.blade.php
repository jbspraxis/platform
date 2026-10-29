<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'JBS PRAXIS | Command HQ' }}</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Roboto+Mono:wght@400;500;700&family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    @livewireStyles

    <style>
        :root {
            --erp-bg-base: #03050A;      
            --erp-bg-main: #0A1128;      
            --erp-bg-card: rgba(10, 17, 40, 0.85); 
            
            --erp-teal: #00E5FF;
            --erp-red: #FF3366;
            --erp-white: #FFFFFF;
            --erp-muted: #8A9BB2;
            --erp-gold: #D4AF37;
            --erp-border: rgba(0, 229, 255, 0.2);
            --erp-border-dark: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: var(--erp-bg-main); color: var(--erp-white); font-family: 'Inter', sans-serif; display: flex; height: 100vh; overflow: hidden; }
        h1, h2, h3, h4 { font-family: 'Montserrat', sans-serif; text-transform: uppercase; letter-spacing: 1px; }
        .mono-font { font-family: 'Roboto Mono', monospace; }
        .teal-text { color: var(--erp-teal); }

        /* Sidebar Architecture */
        .sidebar { width: 280px; background-color: var(--erp-bg-base); border-right: 1px solid var(--erp-border); display: flex; flex-direction: column; z-index: 100; flex-shrink: 0; }
        .sidebar-header { height: 70px; display: flex; align-items: center; padding: 0 25px; border-bottom: 1px solid var(--erp-border-dark); }
        .brand-logo { font-family: 'Roboto Mono', monospace; font-size: 1.2rem; color: var(--erp-teal); font-weight: 700; letter-spacing: 2px; display: flex; align-items: center; gap: 10px; }
        .nav-list { flex: 1; padding: 30px 0; overflow-y: auto; display: flex; flex-direction: column; gap: 5px; }
        .nav-item { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.8rem; text-decoration: none; border-left: 3px solid transparent; transition: all 0.2s ease; cursor: pointer; }
        .nav-item:hover { background: rgba(0, 229, 255, 0.05); color: var(--erp-white); }
        .nav-item.active { background: rgba(0, 229, 255, 0.08); border-left-color: var(--erp-teal); color: var(--erp-teal); font-weight: 700; }
        .nav-sub { font-size: 0.65rem; color: #4A5568; padding-left: 58px; margin-top: -10px; margin-bottom: 15px; letter-spacing: 1px; }
        
        .nav-sub-menu { display: flex; flex-direction: column; gap: 8px; padding-left: 58px; margin-top: -5px; margin-bottom: 15px; }
        .nav-sub-link { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); text-decoration: none; transition: 0.3s; display: flex; align-items: center; gap: 5px; }
        .nav-sub-link:hover { color: var(--erp-teal); }
        .nav-sub-link::before { content: '>'; color: var(--erp-teal); font-size: 0.6rem; }

        .sidebar-footer { padding: 20px 25px; border-top: 1px solid var(--erp-border-dark); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); line-height: 1.5; }

        /* Main Canvas & Topbar */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .main-content::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, transparent 50%, rgba(0, 229, 255, 0.02) 51%); background-size: 100% 4px; pointer-events: none; z-index: 0; }
        
        .top-bar { height: 70px; background-color: var(--erp-bg-base); border-bottom: 1px solid var(--erp-border-dark); display: flex; align-items: center; justify-content: space-between; padding: 0 40px; flex-shrink: 0; z-index: 10; }
        .breadcrumb { font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); }
        .breadcrumb span { color: var(--erp-white); }
        
        /* Top Controls */
        .top-controls { display: flex; align-items: center; gap: 30px; }
        .clock-pill { display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.05); padding: 5px 15px; border-radius: 50px; border: 1px solid var(--erp-border-dark); }
        .btn-clock { background: transparent; border: none; color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .btn-clock:hover { color: var(--erp-white); }
        .btn-clock.clocked-in { color: #00FF66; }

        /* Admin Profile */
        .admin-profile { display: flex; align-items: center; gap: 15px; border-left: 1px solid var(--erp-border-dark); padding-left: 30px; text-decoration: none; }
        .admin-details { display: flex; flex-direction: column; text-align: right; }
        .admin-name { font-size: 0.85rem; font-weight: 700; color: var(--erp-white); letter-spacing: 0.5px; }
        .admin-role { font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-teal); text-transform: uppercase; }
        .admin-avatar { width: 36px; height: 36px; background: rgba(0, 229, 255, 0.1); border: 1px solid var(--erp-teal); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-family: 'Roboto Mono', monospace; font-weight: bold; color: var(--erp-teal); }

        /* Workspace (The Slot) */
        .dashboard-workspace { flex: 1; display: flex; flex-direction: column; overflow-y: auto; position: relative; z-index: 5; padding: 40px; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0, 229, 255, 0.2); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--erp-teal); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logo">
                <span style="font-size: 1.5rem;">⟁</span> PRAXIS_HQ
            </div>
        </div>
        
        <nav class="nav-list">
            
    {{-- Each link shows only when the person can open that screen (roles & permissions, config/access.php) --}}
    @php $sub = fn ($route) => request()->routeIs($route) ? 'color: var(--erp-teal); font-weight: bold;' : ''; @endphp

    <a href="{{ route('hq.dash.workspace') }}" class="nav-item {{ request()->routeIs('hq.dash.workspace') ? 'active' : '' }}" wire:navigate>
        <i data-lucide="user-round" class="w-4 h-4"></i> MY WORKSPACE
    </a>

    @canany(['dashboard.view', 'audit.view'])
        <a href="{{ auth()->user()->can('dashboard.view') ? route('hq.dash.admin') : route('hq.dash.audit') }}" class="nav-item {{ request()->routeIs('hq.dash.admin', 'hq.dash.investor', 'hq.dash.audit') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> COMMAND OVERVIEW
        </a>
        @if(request()->routeIs('hq.dash.admin', 'hq.dash.investor', 'hq.dash.audit'))
            <div class="nav-sub-menu">
                @can('dashboard.view')
                    <a href="{{ route('hq.dash.admin') }}" class="nav-sub-link" style="{{ $sub('hq.dash.admin') }}" wire:navigate>GLOBAL TELEMETRY</a>
                    <a href="{{ route('hq.dash.investor') }}" class="nav-sub-link" style="{{ $sub('hq.dash.investor') }}" wire:navigate>INVESTOR TERMINAL</a>
                @endcan
                @can('audit.view')
                    <a href="{{ route('hq.dash.audit') }}" class="nav-sub-link" style="{{ $sub('hq.dash.audit') }}" wire:navigate>AUDIT TERMINAL</a>
                @endcan
            </div>
        @endif
    @endcanany

    @can('mdm.view')
        <a href="{{ route('hq.mdm.dictionary') }}" class="nav-item {{ request()->routeIs('hq.mdm.dictionary', 'hq.mdm.copq', 'hq.mdm.tax', 'hq.mdm.remittance') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="database" class="w-4 h-4"></i> FINANCIAL MDM
        </a>
        @if(request()->routeIs('hq.mdm.dictionary', 'hq.mdm.copq', 'hq.mdm.tax', 'hq.mdm.remittance'))
            <div class="nav-sub-menu">
                <a href="{{ route('hq.mdm.dictionary') }}" class="nav-sub-link" style="{{ $sub('hq.mdm.dictionary') }}" wire:navigate>FINANCIAL DICTIONARY</a>
                <a href="{{ route('hq.mdm.copq') }}" class="nav-sub-link" style="{{ $sub('hq.mdm.copq') }}" wire:navigate>COPQ MATRICES</a>
                <a href="{{ route('hq.mdm.tax') }}" class="nav-sub-link" style="{{ $sub('hq.mdm.tax') }}" wire:navigate>TAX COMPLIANCE</a>
                <a href="{{ route('hq.mdm.remittance') }}" class="nav-sub-link" style="{{ $sub('hq.mdm.remittance') }}" wire:navigate>REMITTANCE ACCOUNTS</a>
            </div>
        @endif

        <a href="{{ route('hq.mdm.legal') }}" class="nav-item {{ request()->routeIs('hq.mdm.legal') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="building-2" class="w-4 h-4"></i> CORPORATE ENTITIES
        </a>
    @endcan

    @can('hr.view')
        <a href="{{ route('hq.mdm.employees') }}" class="nav-item {{ request()->routeIs('hq.mdm.employees', 'hq.hr.*') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="users" class="w-4 h-4"></i> HUMAN CAPITAL
        </a>
        @if(request()->routeIs('hq.mdm.employees', 'hq.hr.*'))
            <div class="nav-sub-menu">
                <a href="{{ route('hq.mdm.employees') }}" class="nav-sub-link" style="{{ $sub('hq.mdm.employees') }}" wire:navigate>MASTER REGISTRY</a>
                <a href="{{ route('hq.hr.time') }}" class="nav-sub-link" style="{{ $sub('hq.hr.time') }}" wire:navigate>TIME & ATTENDANCE</a>
                <a href="{{ route('hq.hr.compliance') }}" class="nav-sub-link" style="{{ $sub('hq.hr.compliance') }}" wire:navigate>CONTRACTS & APPRAISALS</a>
            </div>
        @endif
    @endcan

    @canany(['crm.view', 'broadcasts.view'])
        <a href="{{ auth()->user()->can('crm.view') ? route('hq.crm.leads') : route('hq.crm.broadcast') }}" class="nav-item {{ request()->routeIs('hq.crm.*') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="target" class="w-4 h-4"></i> INTELLIGENCE DESK
        </a>
        @if(request()->routeIs('hq.crm.*'))
            <div class="nav-sub-menu">
                @can('crm.view')
                    <a href="{{ route('hq.crm.leads') }}" class="nav-sub-link" style="{{ $sub('hq.crm.leads') }}" wire:navigate>LEADS REGISTER</a>
                    <a href="{{ route('hq.crm.consult') }}" class="nav-sub-link" style="{{ $sub('hq.crm.consult') }}" wire:navigate>LIVE CONSULTATION</a>
                    <a href="{{ route('hq.crm.proposal') }}" class="nav-sub-link" style="{{ $sub('hq.crm.proposal') }}" wire:navigate>PROPOSALS LEDGER</a>
                @endcan
                @can('broadcasts.view')
                    <a href="{{ route('hq.crm.broadcast') }}" class="nav-sub-link" style="{{ $sub('hq.crm.broadcast') }}" wire:navigate>BROADCAST LEDGER</a>
                @endcan
            </div>
        @endif
    @endcanany

    @canany(['projects.view', 'qa.view', 'timesheets.view', 'signoffs.view'])
        @php
            $opsHome = collect(['projects.view' => 'hq.ops.deployments', 'qa.view' => 'hq.ops.qa', 'timesheets.view' => 'hq.ops.timers', 'signoffs.view' => 'hq.ops.signoffs'])
                ->first(fn ($route, $perm) => auth()->user()->can($perm));
        @endphp
        <a href="{{ route($opsHome) }}" class="nav-item {{ request()->routeIs('hq.ops.*') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="cpu" class="w-4 h-4"></i> OPERATIONS MATRIX
        </a>
        @if(request()->routeIs('hq.ops.*'))
            <div class="nav-sub-menu">
                @can('projects.view')
                    <a href="{{ route('hq.ops.deployments') }}" class="nav-sub-link" style="{{ $sub('hq.ops.deployments') }}" wire:navigate>ACTIVE DEPLOYMENTS</a>
                    <a href="{{ route('hq.ops.boards.directory') }}" class="nav-sub-link" style="{{ request()->routeIs('hq.ops.boards.directory', 'hq.ops.board') ? 'color: var(--erp-teal); font-weight: bold;' : '' }}" wire:navigate>SPRINT BOARDS (LIVE)</a>
                @endcan
                @can('qa.view')
                    <a href="{{ route('hq.ops.qa') }}" class="nav-sub-link" style="{{ $sub('hq.ops.qa') }}" wire:navigate>QA VULNERABILITY HUB</a>
                @endcan
                @can('timesheets.view')
                    <a href="{{ route('hq.ops.timers') }}" class="nav-sub-link" style="{{ $sub('hq.ops.timers') }}" wire:navigate>SILENT TIMERS LOG</a>
                @endcan
                @can('signoffs.view')
                    <a href="{{ route('hq.ops.signoffs') }}" class="nav-sub-link" style="{{ $sub('hq.ops.signoffs') }}" wire:navigate>PROJECT SIGNOFFS</a>
                @endcan
            </div>
        @endif
    @endcanany

    @canany(['finance.view', 'payroll.view', 'expenses.view', 'expenses.create'])
        @php
            $financeHome = collect(['finance.view' => 'hq.finance.bi', 'payroll.view' => 'hq.finance.payroll', 'expenses.view' => 'hq.finance.manager', 'expenses.create' => 'hq.finance.request'])
                ->first(fn ($route, $perm) => auth()->user()->can($perm));
        @endphp
        <a href="{{ route($financeHome) }}" class="nav-item {{ request()->routeIs('hq.finance.*') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="landmark" class="w-4 h-4"></i> FINANCIAL DISPATCH
        </a>
        @if(request()->routeIs('hq.finance.*'))
            <div class="nav-sub-menu">
                @can('finance.view')
                    <a href="{{ route('hq.finance.bi') }}" class="nav-sub-link" style="{{ $sub('hq.finance.bi') }}" wire:navigate>EXECUTIVE BI MATRIX</a>
                    <a href="{{ route('hq.finance.invoices') }}" class="nav-sub-link" style="{{ $sub('hq.finance.invoices') }}" wire:navigate>INVOICE LEDGER</a>
                    <a href="{{ route('hq.finance.escrow') }}" class="nav-sub-link" style="{{ $sub('hq.finance.escrow') }}" wire:navigate>ESCROW VERIFICATION</a>
                @endcan

                @canany(['finance.view', 'expenses.view', 'expenses.create'])
                    <div style="margin-top:10px; padding-top:10px; border-top: 1px dashed rgba(255,255,255,0.1); color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; font-weight: bold; padding-left: 10px;">TREASURY & IFRS</div>
                    @can('finance.view')
                        <a href="{{ route('hq.finance.budgets') }}" class="nav-sub-link" style="{{ $sub('hq.finance.budgets') }}" wire:navigate>PROJECT BUDGETS</a>
                    @endcan
                    @can('expenses.create')
                        <a href="{{ route('hq.finance.request') }}" class="nav-sub-link" style="{{ $sub('hq.finance.request') }}" wire:navigate>CAPITAL REQUISITION</a>
                    @endcan
                    @can('expenses.view')
                        <a href="{{ route('hq.finance.manager') }}" class="nav-sub-link" style="{{ $sub('hq.finance.manager') }}" wire:navigate>TREASURY MANAGER</a>
                    @endcan
                    @can('finance.view')
                        <a href="{{ route('hq.finance.coa') }}" class="nav-sub-link" style="{{ $sub('hq.finance.coa') }}" wire:navigate>CHART OF ACCOUNTS</a>
                        <a href="{{ route('hq.finance.gl') }}" class="nav-sub-link" style="{{ $sub('hq.finance.gl') }}" wire:navigate>GENERAL LEDGER (IFRS)</a>
                    @endcan
                @endcanany

                @can('payroll.view')
                    <div style="margin-top:10px; padding-top:10px; border-top: 1px dashed rgba(255,255,255,0.1); color: #8A9BB2; font-family: 'Roboto Mono', monospace; font-size: 0.6rem; font-weight: bold; padding-left: 10px;">PAYROLL & TAX ENGINE</div>
                    <a href="{{ route('hq.finance.tax-config') }}" class="nav-sub-link" style="{{ $sub('hq.finance.tax-config') }}" wire:navigate>TAX CONFIGURATOR</a>
                    <a href="{{ route('hq.finance.variables') }}" class="nav-sub-link" style="{{ $sub('hq.finance.variables') }}" wire:navigate>VARIABLE COMPENSATION</a>
                    <a href="{{ route('hq.finance.payroll') }}" class="nav-sub-link" style="{{ $sub('hq.finance.payroll') }}" wire:navigate>MASTER PAYROLL LEDGER</a>
                    <a href="{{ route('hq.finance.matrix') }}" class="nav-sub-link" style="{{ $sub('hq.finance.matrix') }}" wire:navigate>COMPLIANCE MATRIX</a>
                    <a href="{{ route('hq.finance.payslips') }}" class="nav-sub-link" style="{{ $sub('hq.finance.payslips') }}" wire:navigate>PAYSLIP INSPECTOR</a>
                @endcan
            </div>
        @endif
    @endcanany

    @can('marketing.view')
        <a href="{{ route('hq.marketing.campaigns') }}" class="nav-item {{ request()->routeIs('hq.marketing.*') ? 'active' : '' }}" wire:navigate>
            <i data-lucide="megaphone" class="w-4 h-4"></i> MARKETING & ROAS
        </a>
        @if(request()->routeIs('hq.marketing.*'))
            <div class="nav-sub-menu">
                <a href="{{ route('hq.marketing.campaigns') }}" class="nav-sub-link" style="color: var(--erp-teal); font-weight: bold;" wire:navigate>
                    CAMPAIGN COMMAND
                </a>
            </div>
        @endif
    @endcan

    @canany(['access.view', 'system.view'])
        <a href="{{ auth()->user()->can('access.view') ? route('hq.system.roles') : route('hq.system.health') }}" class="nav-item {{ request()->routeIs('hq.system.*') ? 'active' : '' }}" style="margin-top: auto; border-top: 1px solid var(--erp-border-dark); padding-top: 20px;" wire:navigate>
            <i data-lucide="shield" class="w-4 h-4"></i> SYSTEM & ACCESS
        </a>
        @if(request()->routeIs('hq.system.*'))
            <div class="nav-sub-menu">
                @can('access.view')
                    <a href="{{ route('hq.system.roles') }}" class="nav-sub-link" style="{{ $sub('hq.system.roles') }}" wire:navigate>ACCOUNTS</a>
                    <a href="{{ route('hq.system.permissions') }}" class="nav-sub-link" style="{{ $sub('hq.system.permissions') }}" wire:navigate>ROLES & PERMISSIONS</a>
                @endcan
                @can('system.view')
                    <a href="{{ route('hq.system.health') }}" class="nav-sub-link" style="{{ $sub('hq.system.health') }}" wire:navigate>SYSTEM HEALTH</a>
                @endcan
            </div>
        @endif
    @endcanany
</nav>

        <div class="sidebar-footer">
            SYS.STATUS: <span style="color: #00FF66;">OPTIMAL</span><br>
            PING: 14ms
        </div>
    </aside>

    <main class="main-content">
        
        <header class="top-bar">
            <div class="breadcrumb">
                root / hq / <span>{{ explode('.', request()->route()->getName())[1] ?? 'system' }}</span>
            </div>
            
            <div class="top-controls">
                
                <div class="clock-pill" x-data="systemClock()">
                    <span class="mono-font" x-text="currentTime">00:00:00</span>
                    <button class="btn-clock" :class="{'clocked-in': isClockedIn}" @click="toggleClock">
                        <i data-lucide="fingerprint" class="w-4 h-4"></i> 
                        <span x-text="isClockedIn ? 'CLOCKED IN' : 'CLOCK IN'">CLOCK IN</span>
                    </button>
                </div>

                <livewire:hq.components.notification-bell />

                <a href="{{ route('hq.logout') }}" class="admin-profile" title="Click to Terminate Session">
                    <div class="admin-details">
                        <span class="admin-name">{{ auth()->user()->name ?? 'System Admin' }}</span>
                        <span class="admin-role">{{ auth()->user()?->role_names ?: 'Staff' }}</span>
                    </div>
                    <div class="admin-avatar">
                        {{ strtoupper(substr(auth()->user()->first_name ?? 'S', 0, 1) . substr(auth()->user()->last_name ?? 'A', 0, 1)) }}
                    </div>
                </a>

            </div>
        </header>

        <div class="dashboard-workspace">
            {{ $slot }}
        </div>

    </main>

    @livewireScripts

    <script>
        // Init Lucide Icons
        lucide.createIcons();
        document.addEventListener('livewire:navigated', () => { lucide.createIcons(); });

        // Alpine.js Logic for Real-Time Clock
        document.addEventListener('alpine:init', () => {
            Alpine.data('systemClock', () => ({
                currentTime: '00:00:00',
                isClockedIn: false,
                init() {
                    this.updateTime();
                    setInterval(() => this.updateTime(), 1000);
                    this.isClockedIn = localStorage.getItem('praxis_clock_state') === 'true';
                },
                updateTime() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString('en-US', { hour12: false });
                },
                toggleClock() {
                    this.isClockedIn = !this.isClockedIn;
                    localStorage.setItem('praxis_clock_state', this.isClockedIn);
                }
            }))
        })
    </script>
</body>
</html>