{{-- The Client Portal (docs/BRAND.md sections 9, 12 and 14): Ink rail, white top bar with the organisation and the
     language switch, light workspace with white cards. Every label is in lang/<locale>/portal.php (English and French). --}}
@use('App\Livewire\ClientOs\PortalFormat')
@php
    // Stored values (statuses, severities, methods) stay in English in the database; these are their labels
    $label = function (string $group, $value) {
        $map = trans('portal.' . $group);
        return is_array($map) ? ($map[(string) $value] ?? (string) $value) : (string) $value;
    };
    $pills = [
        'project' => ['Active' => 'pill-success', 'Scoping' => 'pill-warning', 'Locked' => 'pill-warning', 'Completed' => 'pill-info', 'Cancelled' => 'pill-neutral'],
        'task' => ['To-Do' => 'pill-neutral', 'In-Progress' => 'pill-info', 'QA_Review' => 'pill-warning', 'Failed' => 'pill-danger', 'Done' => 'pill-success'],
        'issue' => ['Open' => 'pill-warning', 'Investigating' => 'pill-info', 'Resolved' => 'pill-success', 'Closed' => 'pill-neutral'],
        'severity' => ['Low' => 'pill-neutral', 'Medium' => 'pill-info', 'Critical' => 'pill-danger'],
        'invoice' => ['Pending' => 'pill-info', 'Overdue' => 'pill-danger', 'Paid' => 'pill-success', 'Cancelled' => 'pill-neutral'],
        'account' => ['Active' => 'pill-success', 'Suspended' => 'pill-danger'],
    ];
    $pill = fn (string $group, $value) => $pills[$group][(string) $value] ?? 'pill-neutral';
    // Older tickets were saved with a "[DIRECT COMM] " / "[BUG REPORT] " prefix; the type now shows as a label
    $ticketTitle = fn ($title) => preg_replace('/^\[(DIRECT COMM|BUG REPORT)\]\s*/', '', (string) $title);
    $roleLabel = fn ($name) => trans('portal.roles')[$name] ?? $name;
    // Notes the portal itself writes on a task (stored in English), shown in the page's language
    $activityText = function ($text) {
        $approved = [__('portal.tasks.approved_comment', [], 'en'), '[CLIENT VERIFICATION] Deliverable approved and structurally locked.'];
        $fromIssue = [__('portal.tasks.approved_from_issue_comment', [], 'en'), '[CLIENT VERIFICATION] Deliverable verified and locked directly from QA Hub override.'];
        return in_array($text, $approved, true) ? __('portal.overview.log_approved')
            : (in_array($text, $fromIssue, true) ? __('portal.overview.log_approved_from_issue') : (string) $text);
    };

    // Sections: [permission, icon]. scripts/icons.mjs bundles only icons named in the views, so the loop's icons are
    // listed here: data-lucide="layout-dashboard" data-lucide="list-checks" data-lucide="shield-alert"
    // data-lucide="credit-card" data-lucide="file-text" data-lucide="users" data-lucide="lock" data-lucide="clock" data-lucide="triangle-alert"
    $tabs = [
        'overview' => ['projects.view', 'layout-dashboard'], 'sprints' => ['projects.view', 'list-checks'],
        'qa_hub' => ['qa.view', 'shield-alert'], 'financials' => ['billing.view', 'credit-card'],
        'vault' => ['documents.view', 'file-text'], 'team' => ['team.view', 'users'],
    ];

    // The organisation's main colour marks the selected section and the progress bar (only a valid hex colour)
    $clientColour = ($clientSettings && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($clientSettings->primary_color ?? ''))) ? $clientSettings->primary_color : null;
    $initials = mb_strtoupper(mb_substr((string) $user->first_name, 0, 1) . mb_substr((string) $user->last_name, 0, 1)) ?: 'JP';
    $emailDomain = strtolower(substr((string) strrchr((string) $user->email, '@'), 1));
@endphp
<div class="portal-app"
     style="--portal-accent: {{ $clientColour ?? 'var(--color-deep-teal)' }}; --portal-indicator: {{ $clientColour ?? 'var(--color-bright-teal)' }};"
     x-data="{ showGuide: false, showSignIn: false, showQaOffcanvas: false, mobileMenuOpen: false }"
     @open-qa.window="showQaOffcanvas = true" @close-qa.window="showQaOffcanvas = false"
     @keydown.escape.window="showGuide = false; showSignIn = false; showQaOffcanvas = false">

    <style>
        /* Portal-only layout on top of the shared shell (resources/css/app.css); brand tokens only */
        .portal-app .rail-link { width: 100%; border-top: 0; border-right: 0; border-bottom: 0; background: transparent; font-family: inherit; text-align: left; cursor: pointer; }
        .portal-app .rail-link.is-active { border-left-color: var(--portal-indicator); }
        .portal-app .rail-link.is-locked { opacity: 0.6; }
        .portal-app .rail-link .portal-lock { width: 14px; height: 14px; margin-left: auto; }
        .portal-app .rail-head { justify-content: space-between; gap: 12px; }
        .portal-app .rail-foot { display: flex; flex-direction: column; gap: 2px; padding: 12px; }
        .portal-menu-btn { display: none; }
        .portal-mobile-only { display: none; }

        .portal-progress { height: 8px; border-radius: 999px; background: var(--color-mist); overflow: hidden; }
        .portal-progress > span { display: block; height: 100%; border-radius: 999px; background: var(--portal-accent); }

        /* Phases: horizontal rules only, dots for each phase (scrolls inside the card on small screens) */
        .portal-phases-wrap { position: relative; }
        .portal-phases-wrap::before { content: ""; position: absolute; top: 15px; left: 0; right: 0; height: 2px; background: var(--color-line); }
        .portal-phases { position: relative; display: flex; list-style: none; margin: 0; padding: 0; }
        .portal-phases-done { position: absolute; top: 15px; left: 0; height: 2px; background: var(--portal-accent); }
        .portal-phase { position: relative; flex: 1 1 0; min-width: 132px; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 0 8px; text-align: center; }
        .portal-phase-dot { position: relative; z-index: 1; width: 32px; height: 32px; margin-bottom: 6px; border-radius: 50%; display: grid; place-items: center; background: var(--color-paper); border: 2px solid var(--color-line-strong); color: var(--color-muted); font-size: 0.8125rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .portal-phase-dot svg { width: 16px; height: 16px; }
        .portal-phase.is-done .portal-phase-dot { background: var(--color-deep-teal); border-color: var(--color-deep-teal); color: #fff; }
        .portal-phase.is-active .portal-phase-dot { background: var(--color-teal-tint); border-color: var(--color-deep-teal); color: var(--color-deep-teal); }

        /* Slide-over panels (an issue, the guide) */
        .portal-overlay { position: fixed; inset: 0; z-index: 60; background: rgba(12, 20, 43, 0.5); opacity: 0; pointer-events: none; transition: opacity var(--motion-quick) var(--ease-settle); }
        .portal-overlay.is-open { opacity: 1; pointer-events: auto; }
        .portal-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 61; width: 100%; max-width: 560px; display: flex; flex-direction: column; background: var(--color-paper); border-left: 1px solid var(--color-line); box-shadow: var(--shadow-float); transform: translateX(100%); visibility: hidden; transition: transform var(--motion-standard) var(--ease-settle), visibility 0s linear var(--motion-standard); }
        .portal-drawer.is-open { transform: none; visibility: visible; transition: transform var(--motion-standard) var(--ease-settle), visibility 0s; }
        .portal-drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 20px 24px; border-bottom: 1px solid var(--color-line); }
        .portal-drawer-body { flex: 1; overflow-y: auto; padding: 24px; }
        .portal-drawer-foot { padding: 16px 24px; border-top: 1px solid var(--color-line); }
        .portal-sign-in-slot > div:empty { min-height: 160px; border-radius: 8px; background: var(--color-mist); }

        .portal-fab { position: fixed; right: 24px; bottom: 24px; z-index: 40; box-shadow: var(--shadow-float); }

        @media (max-width: 900px) {
            .portal-menu-btn { display: inline-flex; }
            .portal-app .rail:not(.is-open) .rail-nav, .portal-app .rail:not(.is-open) .rail-foot { display: none; }
            .portal-app .rail-nav { flex-direction: column; flex-wrap: nowrap; }
            .portal-app .topbar { height: auto; min-height: 56px; padding: 8px 16px; gap: 12px; }
            .portal-desktop-only { display: none !important; }
            .portal-mobile-only { display: flex; }
            .portal-fab { right: 16px; bottom: 16px; }
        }
    </style>

    <div class="app-shell">
        {{-- ===== Rail ===== --}}
        <aside class="rail" :class="{ 'is-open': mobileMenuOpen }" aria-label="{{ __('portal.nav.label') }}">
            <div class="rail-head">
                <img src="/assets/img/logo/jbs-praxis-horizontal-reversed.svg" alt="JBS Praxis" width="147" height="28">
                <button type="button" class="portal-menu-btn btn btn-sm" style="color: #fff; border-color: var(--color-border-on-dark);"
                        @click="mobileMenuOpen = !mobileMenuOpen" :aria-expanded="mobileMenuOpen.toString()" aria-label="{{ __('portal.nav.label') }}">
                    <i data-lucide="menu"></i>
                </button>
            </div>

            <nav class="rail-nav">
                @foreach ($tabs as $tab => [$permission, $icon])
                    @if ($can[$permission] ?? false)
                        @php $locked = $financialLockout && in_array($tab, \App\Livewire\ClientOs\Dashboard::LOCKED_TABS, true); @endphp
                        <button type="button" wire:click="switchTab('{{ $tab }}')" @click="mobileMenuOpen = false"
                                class="rail-link {{ $activeTab === $tab ? 'is-active' : '' }} {{ $locked ? 'is-locked' : '' }}"
                                @if ($activeTab === $tab) aria-current="page" @endif
                                @if ($locked) title="{{ __('portal.nav.locked') }}" @endif>
                            <i data-lucide="{{ $icon }}"></i>
                            <span>{{ __('portal.nav.' . $tab) }}</span>
                            @if ($locked)
                                <i data-lucide="lock" class="portal-lock" aria-hidden="true"></i>
                                <span class="sr-only">{{ __('portal.nav.locked') }}</span>
                            @endif
                        </button>
                    @endif
                @endforeach
            </nav>

            <div class="rail-foot">
                <button type="button" class="rail-link" @click="showGuide = true; mobileMenuOpen = false">
                    <i data-lucide="help-circle"></i> <span>{{ __('portal.nav.guide') }}</span>
                </button>
                {{-- Passkeys and the PIN (docs/SIGN_IN.md) --}}
                <button type="button" class="rail-link" @click="showSignIn = true; mobileMenuOpen = false">
                    <i data-lucide="fingerprint"></i> <span>{{ __('sign_in.settings.open') }}</span>
                </button>
                @if ($can['support.create'] ?? false)
                    <button type="button" class="rail-link" wire:click="$set('showRedlineModal', true)" @click="mobileMenuOpen = false">
                        <i data-lucide="triangle-alert"></i> <span>{{ __('portal.nav.urgent') }}</span>
                    </button>
                @endif
                <button type="button" class="rail-link portal-mobile-only" wire:click="executeLogout">
                    <i data-lucide="log-out"></i> <span>{{ __('portal.nav.sign_out') }}</span>
                </button>
            </div>
        </aside>

        <div class="main">
            {{-- ===== Top bar: the organisation, language, person ===== --}}
            <header class="topbar">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($clientSettings && $clientSettings->logo_path)
                        <img src="{{ Storage::url($clientSettings->logo_path) }}" alt="{{ $organization->name }}" class="h-8 w-auto max-w-[120px] object-contain">
                    @endif
                    <div class="topbar-title min-w-0 truncate">
                        <span class="portal-desktop-only" style="display: inline;">{{ __('portal.app_name') }} / </span><strong>{{ $organization->name }}</strong>
                    </div>
                </div>
                <div class="topbar-actions" style="gap: 16px;">
                    {{-- The portal's notifications, and the app on this device (docs/PWA.md), in the language worked out above --}}
                    <livewire:client-os.notification-bell :locale="app()->getLocale()" />
                    @include('livewire.client-os.partials.language-switch')
                    <div class="user-chip">
                        <div>
                            <span class="user-chip-name">{{ $user->first_name }} {{ $user->last_name }}</span>
                            <span class="user-chip-role">{{ $user->is_primary_client ? __('portal.nav.primary_contact') : __('portal.nav.team_member') }}</span>
                        </div>
                        <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                    </div>
                    <button type="button" wire:click="executeLogout" class="btn btn-ghost btn-sm portal-desktop-only">
                        <i data-lucide="log-out"></i> {{ __('portal.nav.sign_out') }}
                    </button>
                </div>
            </header>

            <main class="workspace" id="content">
                {{-- Sections close while a payment is due --}}
                @if ($financialLockout)
                    <div class="alert alert-warning mb-4" role="status">
                        <i data-lucide="lock"></i>
                        <span>{{ $lockoutReason }}</span>
                    </div>
                @endif


                {{-- After a password sign-in with no passkey yet: offer one (docs/SIGN_IN.md) --}}
                @if (\App\Livewire\Account\PasskeyOffer::due('client'))
                    <livewire:account.passkey-offer guard="client" />
                @endif

                <div class="page-header">
                    <div class="min-w-0">
                        <h1 class="page-title">{{ __('portal.nav.' . $activeTab) }}</h1>
                        <p class="page-subtitle">{{ __('portal.subtitles.' . $activeTab, ['max' => \App\Livewire\ClientOs\Dashboard::MAX_PORTAL_ACCOUNTS]) }}</p>
                    </div>
                    @if (count($allProjects) > 1)
                        <div class="field w-full sm:w-72">
                            <label for="portal-project" class="label">{{ __('portal.nav.project') }}</label>
                            <select id="portal-project" wire:model.live="activeProjectId" wire:change="switchProject($event.target.value)" class="select">
                                @foreach ($allProjects as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif ($activeProject && $activeTab !== 'overview')
                        <div class="sm:text-right">
                            <div class="stat-label">{{ __('portal.nav.project') }}</div>
                            <div class="font-semibold text-ink">{{ $activeProject->title }}</div>
                        </div>
                    @endif
                </div>

                {{-- ===================== ON HOLD, with nowhere else this account may go ===================== --}}
                @if ($financialLockout && in_array($activeTab, \App\Livewire\ClientOs\Dashboard::LOCKED_TABS, true))
                    <section class="card"><div class="empty-state"><strong>{{ __('portal.lockout.tab_closed') }}</strong> {{ $lockoutReason }}</div></section>

                {{-- ===================== NO PROJECT YET (until the statement of work is signed) ===================== --}}
                @elseif (! $activeProject && in_array($activeTab, \App\Livewire\ClientOs\Dashboard::PROJECT_TABS, true))
                    <section class="card">
                        <div class="empty-state">
                            <strong>{{ __('portal.no_project.title') }}</strong>
                            {{ __('portal.no_project.body') }}
                            @if ($can['documents.view'] ?? false)
                                <div class="mt-4">
                                    <button type="button" wire:click="switchTab('vault')" class="btn btn-secondary btn-sm"><i data-lucide="file-text"></i> {{ __('portal.no_project.documents') }}</button>
                                </div>
                            @endif
                        </div>
                    </section>

                {{-- ===================== OVERVIEW ===================== --}}
                @elseif ($activeTab === 'overview' && $activeProject)
                    <section class="card mb-6" aria-label="{{ __('portal.overview.project') }}">
                        <dl class="m-0 grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-5">
                            <div class="min-w-0">
                                <dt class="stat-label">{{ __('portal.overview.project') }}</dt>
                                <dd class="m-0 mt-1 truncate font-semibold text-ink" title="{{ $activeProject->title }}">{{ $activeProject->title }}</dd>
                            </div>
                            <div>
                                <dt class="stat-label">{{ __('portal.overview.status') }}</dt>
                                <dd class="m-0 mt-1"><span class="pill {{ $pill('project', $activeProject->status) }}">{{ $label('status.project', $activeProject->status) }}</span></dd>
                            </div>
                            <div>
                                <dt class="stat-label">{{ __('portal.overview.progress') }}</dt>
                                <dd class="m-0 mt-1">
                                    <div class="font-semibold text-ink tabular-nums">{{ __('portal.overview.progress_value', ['percent' => $overallProgress]) }}</div>
                                    <div class="portal-progress mt-2" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $overallProgress }}" aria-label="{{ __('portal.overview.progress') }}">
                                        <span style="width: {{ min(100, max(0, (float) $overallProgress)) }}%;"></span>
                                    </div>
                                </dd>
                            </div>
                            <div>
                                <dt class="stat-label">{{ __('portal.overview.budget') }}</dt>
                                <dd class="m-0 mt-1 font-semibold text-ink tabular-nums">{{ PortalFormat::money($activeProject->total_budget, $activeProject->currency) }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="stat-label">{{ __('portal.overview.lead') }}</dt>
                                <dd class="m-0 mt-1 truncate font-semibold text-ink">{{ $leadArchitect }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="card mb-6">
                        <div class="card-header"><h2 class="card-title">{{ __('portal.overview.phases') }}</h2></div>
                        @if (count($phases))
                            <div class="overflow-x-auto pb-1">
                                <div class="portal-phases-wrap" style="min-width: {{ count($phases) * 132 }}px;">
                                <span class="portal-phases-done" style="width: {{ min(100, max(0, (float) $overallProgress)) }}%;" aria-hidden="true"></span>
                                <ol class="portal-phases">
                                    @foreach ($phases as $phase)
                                        @php $phaseState = $phase['status'] === 'Completed' ? 'is-done' : ($phase['status'] === 'Active' ? 'is-active' : ''); @endphp
                                        <li class="portal-phase {{ $phaseState }}" @if ($phase['status'] === 'Active') aria-current="step" @endif>
                                            <span class="portal-phase-dot" aria-hidden="true">
                                                @if ($phase['status'] === 'Completed') <i data-lucide="check"></i> @else {{ $phase['phase_number'] }} @endif
                                            </span>
                                            <span class="text-xs text-muted">{{ __('portal.overview.phase_number', ['number' => $phase['phase_number']]) }}</span>
                                            <span class="text-sm font-semibold text-ink">{{ $phase['title'] }}</span>
                                            <span class="text-xs text-muted">{{ $label('status.phase', $phase['status']) }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                                </div>
                            </div>
                        @else
                            <div class="empty-state">
                                <strong>{{ __('portal.overview.no_phases') }}</strong>
                                {{ __('portal.overview.no_phases_help') }}
                            </div>
                        @endif
                    </section>

                    @if (count($clientNeeds) || $clientNeedsReceived)
                        <section class="card mb-6" aria-labelledby="portal-needs-title">
                            <div class="card-header"><h2 class="card-title" id="portal-needs-title">{{ __('portal.needs.title') }}</h2></div>
                            <p class="m-0 mb-3 text-sm text-muted">{{ __('portal.needs.help') }}</p>
                            @if ($clientNeedsReceived)
                                <p class="m-0 mb-3 text-sm text-muted">{{ __('portal.needs.received', ['count' => $clientNeedsReceived]) }}</p>
                            @endif
                            @if (count($clientNeeds))
                                <ul class="m-0 list-none divide-y divide-line p-0">
                                    @foreach ($clientNeeds as $need)
                                        <li class="py-3 first:pt-0" wire:key="need-{{ $need['id'] }}">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="min-w-0" style="flex: 1 1 18rem;">
                                                    <div class="text-xs text-muted">{{ $need['milestone'] }}@if ($need['group']) · {{ $need['group'] }}@endif</div>
                                                    <div class="text-sm text-ink break-words">{{ $need['title'] }}</div>
                                                    <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                                        @if ($need['due']) <span class="text-muted">{{ __('portal.needs.due', ['date' => $need['due']]) }}</span> @endif
                                                        @if ($need['late_days'] > 0 && $need['status'] !== 'sent') <span class="pill pill-danger">{{ trans_choice('portal.needs.late', $need['late_days'], ['days' => $need['late_days']]) }}</span> @endif
                                                        @if ($need['status'] === 'sent' && $need['sent']) <span class="text-muted">{{ __('portal.needs.sent_on', ['date' => $need['sent']]) }}</span> @endif
                                                    </div>
                                                </div>
                                                @if ($need['status'] !== 'sent')
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="openNeed({{ $need['id'] }})"><i data-lucide="send"></i> {{ __('portal.needs.mark_sent') }}</button>
                                                @else
                                                    <span class="pill pill-info">{{ __('portal.needs.statuses.sent') }}</span>
                                                @endif
                                            </div>
                                            @if ($needItemId === $need['id'])
                                                <form wire:submit.prevent="submitNeedSent" class="mt-3 flex flex-col gap-3">
                                                    <div class="field">
                                                        <label class="label" for="need-note-{{ $need['id'] }}">{{ __('portal.needs.note') }}</label>
                                                        <textarea id="need-note-{{ $need['id'] }}" class="textarea" rows="2" maxlength="2000" wire:model="needNote" aria-describedby="need-note-help-{{ $need['id'] }}"></textarea>
                                                        <span class="help" id="need-note-help-{{ $need['id'] }}">{{ __('portal.needs.note_help') }}</span>
                                                        @error('needNote') <span class="field-error">{{ $message }}</span> @enderror
                                                    </div>
                                                    <div class="field">
                                                        <label class="label" for="need-file-{{ $need['id'] }}">{{ __('portal.needs.file') }}</label>
                                                        <input id="need-file-{{ $need['id'] }}" type="file" class="input" wire:model="needFile">
                                                        @error('needFile') <span class="field-error">{{ $message }}</span> @enderror
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">{{ __('portal.needs.submit') }}</button>
                                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelNeed">{{ __('portal.needs.cancel') }}</button>
                                                    </div>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="m-0 text-sm text-muted">{{ __('portal.needs.none') }}</p>
                            @endif
                        </section>
                    @endif

                    <section class="card">
                        <div class="card-header"><h2 class="card-title">{{ __('portal.overview.activity') }}</h2></div>
                        @if (count($liveLogs))
                            <ul class="m-0 max-h-[480px] list-none divide-y divide-line overflow-y-auto p-0">
                                @foreach ($liveLogs as $log)
                                    <li class="py-3 first:pt-0">
                                        <div class="text-xs text-muted tabular-nums">{{ $log['time'] }} · {{ $log['author'] }}</div>
                                        <p class="m-0 mt-1 text-sm text-slate break-words">{!! nl2br(e($activityText($log['text']))) !!}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="empty-state">
                                <strong>{{ __('portal.overview.no_activity') }}</strong>
                                {{ __('portal.overview.no_activity_help') }}
                            </div>
                        @endif
                    </section>

                {{-- ===================== PROJECT PROGRESS (tasks) ===================== --}}
                @elseif ($activeTab === 'sprints' && $activeProject)
                    <section class="card" style="padding: 0;">
                        @forelse ($activeTasks as $task)
                            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line px-6 py-4 last:border-b-0">
                                <div class="min-w-0">
                                    <div class="text-xs text-muted">{{ $task->phase_title }}</div>
                                    <div class="font-semibold text-ink break-words">{{ $task->title }}</div>

                                    @if ($task->status === 'QA_Review')
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($can['projects.approve'] ?? false)
                                                <button type="button" wire:click="approveTask('{{ $task->id }}')" class="btn btn-primary btn-sm">
                                                    <i data-lucide="check"></i> {{ __('portal.tasks.approve') }}
                                                </button>
                                            @endif
                                            @if ($can['qa.create'] ?? false)
                                                <button type="button" wire:click="teleportToQa('{{ $task->id }}', '{{ addslashes($task->title) }}')" class="btn btn-secondary btn-sm">
                                                    <i data-lucide="shield-alert"></i> {{ __('portal.tasks.report') }}
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <span class="pill {{ $pill('task', $task->status) }}">{{ $label('status.task', $task->status) }}</span>
                            </div>
                        @empty
                            <div class="empty-state">
                                <strong>{{ __('portal.tasks.empty') }}</strong>
                                {{ __('portal.tasks.empty_help') }}
                            </div>
                        @endforelse
                    </section>

                {{-- ===================== QUALITY AND ISSUES ===================== --}}
                @elseif ($activeTab === 'qa_hub' && $activeProject)
                    <div class="alert alert-info mb-6" role="note">
                        <i data-lucide="clock"></i>
                        <div>
                            <strong class="block">{{ __('portal.qa.sla_title') }}</strong>
                            <span>{{ __('portal.qa.sla_body') }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                        <section class="card">
                            <div class="card-header"><h2 class="card-title">{{ __('portal.qa.form_title') }}</h2></div>


                            @if ($can['qa.create'] ?? false)
                                <form wire:submit.prevent="submitQaTicket" class="flex flex-col gap-4">
                                    @if ($qaTaskId)
                                        <div class="callout flex flex-wrap items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="stat-label">{{ __('portal.qa.about_task') }}</div>
                                                <div class="font-semibold text-ink break-words">{{ collect($activeTasks)->firstWhere('id', $qaTaskId)->title ?? __('portal.qa.unknown_task') }}</div>
                                            </div>
                                            <button type="button" wire:click="$set('qaTaskId', '')" class="btn btn-ghost btn-sm">
                                                <i data-lucide="x"></i> {{ __('portal.qa.remove_task') }}
                                            </button>
                                        </div>
                                    @else
                                        <p class="help m-0">{{ __('portal.qa.general_help') }}</p>
                                    @endif

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div class="field sm:col-span-2">
                                            <label for="qa-title-input" class="label">{{ __('portal.qa.subject') }}</label>
                                            <input type="text" id="qa-title-input" wire:model="qaTitle" class="input" required x-init="setTimeout(() => $el.focus(), 100)">
                                            @error('qaTitle') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="field">
                                            <label for="qa-severity" class="label">{{ __('portal.qa.priority') }}</label>
                                            <select id="qa-severity" wire:model="qaSeverity" class="select">
                                                @foreach (['Low', 'Medium', 'Critical'] as $severity)
                                                    <option value="{{ $severity }}">{{ $label('status.severity', $severity) }}</option>
                                                @endforeach
                                            </select>
                                            @error('qaSeverity') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label for="qa-description" class="label">{{ __('portal.qa.details') }}</label>
                                        <textarea id="qa-description" wire:model="qaDescription" class="textarea" rows="5" required aria-describedby="qa-description-help"></textarea>
                                        <span id="qa-description-help" class="help">{{ __('portal.qa.details_help') }}</span>
                                        @error('qaDescription') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="field">
                                        <label for="qa-attachment" class="label">{{ __('portal.qa.attachment') }} <span class="font-normal text-muted">({{ __('portal.common.optional') }})</span></label>
                                        <input type="file" id="qa-attachment" wire:model="initialAttachment" class="input" accept=".png,.jpg,.jpeg,.pdf" aria-describedby="qa-attachment-help">
                                        <span id="qa-attachment-help" class="help">{{ __('portal.common.file_help') }}</span>
                                        @error('initialAttachment') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>

                                    <button type="submit" class="btn btn-primary self-start" wire:loading.attr="disabled" wire:target="submitQaTicket, initialAttachment">
                                        <span wire:loading.remove wire:target="submitQaTicket"><i data-lucide="send" class="inline h-4 w-4 align-[-3px]"></i> {{ $qaTaskId ? __('portal.qa.submit_issue') : __('portal.qa.submit_message') }}</span>
                                        <span wire:loading wire:target="submitQaTicket">{{ __('portal.common.sending') }}</span>
                                    </button>
                                </form>
                            @else
                                <p class="help m-0">{{ __('portal.qa.no_create') }}</p>
                            @endif
                        </section>

                        <section class="card">
                            <div class="card-header"><h2 class="card-title">{{ __('portal.qa.list_title') }}</h2></div>
                            <div class="flex flex-col gap-3">
                                @forelse ($qaTickets as $ticket)
                                    <button type="button" wire:click="openTicket('{{ $ticket->id }}')"
                                            class="w-full cursor-pointer rounded-lg border border-line bg-paper p-4 text-left hover:bg-mist">
                                        <span class="flex flex-wrap items-start justify-between gap-2">
                                            <span class="min-w-0 font-semibold text-ink break-words">{{ $ticketTitle($ticket->title) }}</span>
                                            <span class="pill {{ $pill('issue', $ticket->status) }}">{{ $label('status.issue', $ticket->status) }}</span>
                                        </span>
                                        <span class="mt-1 block text-xs text-muted">
                                            {{ $ticket->task_id ? __('portal.qa.type_issue') : __('portal.qa.type_message') }}
                                            · {{ PortalFormat::date($ticket->created_at, 'datetime') }}
                                            · {{ __('portal.qa.priority_value', ['priority' => $label('status.severity', $ticket->severity)]) }}
                                        </span>
                                        <span class="mt-2 block text-sm text-slate break-words">{{ $ticket->excerpt }}</span>
                                        @if ($ticket->task_title)
                                            <span class="mt-2 block text-xs text-deep-teal">{{ __('portal.qa.linked_task', ['task' => $ticket->task_title]) }}</span>
                                        @endif
                                    </button>
                                @empty
                                    <div class="empty-state">
                                        <strong>{{ __('portal.qa.empty') }}</strong>
                                        {{ __('portal.qa.empty_help') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    </div>

                {{-- ===================== INVOICES AND PAYMENTS ===================== --}}
                @elseif ($activeTab === 'financials' && $activeProject)

                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                        <section class="card lg:col-span-2">
                            <div class="card-header"><h2 class="card-title">{{ __('portal.finance.invoices') }}</h2></div>
                            @forelse ($invoices as $inv)
                                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line py-4 first:pt-0 last:border-b-0 last:pb-0">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink tabular-nums">{{ $inv->invoice_number }}</div>
                                        <div class="mt-1 text-sm text-muted tabular-nums">
                                            {{ __('portal.finance.issued', ['date' => PortalFormat::date($inv->issued_at)]) }}
                                            · <span class="{{ $inv->status === 'Overdue' ? 'font-semibold text-danger' : '' }}">{{ __('portal.finance.due', ['date' => PortalFormat::date($inv->due_date)]) }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="pill {{ $pill('invoice', $inv->status) }}">{{ $label('status.invoice', $inv->status) }}</span>
                                            @if ($inv->has_pending_pop)
                                                <span class="pill pill-info">{{ __('portal.status.payment_pending') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-3">
                                        <div class="text-lg font-semibold text-ink num">{{ PortalFormat::money($inv->total_amount, $inv->currency) }}</div>
                                        <a href="{{ route('portal.invoice.pdf', $inv->id) }}" class="btn btn-secondary btn-sm" aria-label="{{ __('invoices.portal.pdf_label', ['number' => $inv->invoice_number]) }}">
                                            <i data-lucide="file-down"></i> {{ __('invoices.portal.pdf') }}
                                        </a>
@if ($inv->status !== 'Paid' && !$inv->has_pending_pop)
                                            @if ($can['billing.create'] ?? false)
                                                <button type="button" wire:click="triggerPopUpload('{{ $inv->id }}', {{ $inv->total_amount }})" class="btn btn-primary btn-sm">
                                                    <i data-lucide="upload"></i> {{ __('portal.finance.upload_pop') }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">
                                    <strong>{{ __('portal.finance.empty') }}</strong>
                                    {{ __('portal.finance.empty_help') }}
                                </div>
                            @endforelse
                        </section>

                        @if ($uploadInvoiceId)
                            <section class="card" style="border-color: var(--color-deep-teal);">
                                <div class="mb-4">
                                    <h2 class="card-title">{{ __('portal.finance.pop_title') }}</h2>
                                    <p class="help m-0 mt-1">{{ __('portal.finance.pop_for', ['invoice' => collect($invoices)->firstWhere('id', $uploadInvoiceId)->invoice_number ?? '']) }}</p>
                                </div>

                                @if ($can['billing.create'] ?? false)
                                    <form wire:submit.prevent="submitProofOfPayment" class="flex flex-col gap-4">
                                        <div class="field">
                                            <label for="pop-method" class="label">{{ __('portal.finance.method') }}</label>
                                            <select id="pop-method" wire:model="popMethod" class="select">
                                                @foreach (['Bank Transfer', 'USDT', 'Campay'] as $method)
                                                    <option value="{{ $method }}">{{ $label('payment_methods', $method) }}</option>
                                                @endforeach
                                            </select>
                                            @error('popMethod') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="field">
                                            <label for="pop-amount" class="label">{{ __('portal.finance.amount') }}</label>
                                            <input type="number" id="pop-amount" step="0.01" wire:model="popAmount" class="input tabular-nums" required>
                                            @error('popAmount') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="field">
                                            <label for="pop-ref" class="label">{{ __('portal.finance.reference') }}</label>
                                            <input type="text" id="pop-ref" wire:model="popRef" class="input" required aria-describedby="pop-ref-help">
                                            <span id="pop-ref-help" class="help">{{ __('portal.finance.reference_help') }}</span>
                                            @error('popRef') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="field">
                                            <label for="pop-file" class="label">{{ __('portal.finance.proof') }}</label>
                                            <input type="file" id="pop-file" wire:model="popFile" class="input" accept=".pdf,.jpg,.jpeg,.png" required aria-describedby="pop-file-help">
                                            <span id="pop-file-help" class="help">{{ __('portal.finance.proof_help') }}</span>
                                            @error('popFile') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        @error('uploadInvoiceId') <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div> @enderror

                                        <div class="flex flex-wrap justify-end gap-3">
                                            <button type="button" wire:click="cancelPopUpload" class="btn btn-secondary">{{ __('portal.common.cancel') }}</button>
                                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submitProofOfPayment, popFile">
                                                <span wire:loading.remove wire:target="submitProofOfPayment">{{ __('portal.finance.pop_submit') }}</span>
                                                <span wire:loading wire:target="submitProofOfPayment">{{ __('portal.common.uploading') }}</span>
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </section>
                        @else
                            <section class="card">
                                <div class="card-header">
                                    <h2 class="card-title flex items-center gap-2"><i data-lucide="landmark" class="h-4 w-4 text-deep-teal"></i> {{ __('portal.finance.pay_title') }}</h2>
                                </div>
                                @if (count($remittanceAccounts))
                                    <p class="help m-0 mb-4">{{ __('portal.finance.pay_intro') }}</p>
                                    <div class="flex flex-col gap-4">
                                        @foreach ($remittanceAccounts as $acc)
                                            <div class="rounded-lg bg-mist p-4 text-sm">
                                                <div class="mb-3 font-semibold text-ink">{{ $label('account_types', $acc->type) }} · {{ $acc->currency }}</div>
                                                <dl class="m-0 grid grid-cols-1 gap-2">
                                                    <div>
                                                        <dt class="text-xs text-muted">{{ __('portal.finance.pay_provider') }}</dt>
                                                        <dd class="m-0 text-ink break-words">{{ $acc->provider_name }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-muted">{{ __('portal.finance.pay_account_name') }}</dt>
                                                        <dd class="m-0 text-ink break-words">{{ $acc->account_name }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs text-muted">{{ __('portal.finance.pay_reference') }}</dt>
                                                        <dd class="m-0 font-semibold text-ink tabular-nums break-all">{{ $acc->account_reference }}</dd>
                                                    </div>
                                                    @if ($acc->routing_code)
                                                        <div>
                                                            <dt class="text-xs text-muted">{{ __('portal.finance.pay_routing') }}</dt>
                                                            <dd class="m-0 text-ink tabular-nums break-all">{{ $acc->routing_code }}</dd>
                                                        </div>
                                                    @endif
                                                </dl>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="help m-0">{{ __('portal.finance.pay_empty') }}</p>
                                @endif
                            </section>
                        @endif
                    </div>

                {{-- ===================== DOCUMENTS ===================== --}}
                @elseif ($activeTab === 'vault')
                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                        <section class="card lg:col-span-2">
                            <div class="card-header"><h2 class="card-title">{{ __('portal.documents.signed') }}</h2></div>

                            {{-- Statements of work and proposals: the version the client has now, with links made as the page is drawn --}}
                            @foreach (['contracts' => $vaultContracts, 'proposals' => $vaultProposals] as $kind => $documents)
                                <h3 class="section-title text-base">{{ __('portal.documents.' . $kind) }}</h3>
                                <div class="mb-6">
                                    @forelse ($documents as $doc)
                                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line py-3 last:border-b-0" wire:key="doc-{{ $doc['id'] }}">
                                            <div class="min-w-0">
                                                <div class="font-semibold text-ink break-words">{{ $doc['title'] }}</div>
                                                <div class="text-xs text-muted tabular-nums">{{ __('portal.documents.version_meta', ['ref' => $doc['reference'], 'version' => $doc['version']]) }}</div>
                                                <div class="text-sm text-slate">{{ __('portal.documents.state.' . $doc['state'], ['date' => $doc['date']]) }}</div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                @if ($doc['open'])
                                                    <a href="{{ $doc['open'] }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i data-lucide="external-link"></i> {{ __('portal.documents.open') }}</a>
                                                @endif
                                                @if ($doc['pdf'])
                                                    <a href="{{ $doc['pdf'] }}" class="btn btn-ghost btn-sm"><i data-lucide="download"></i> {{ __('portal.documents.pdf') }}</a>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="empty-state" style="padding: 24px;">
                                            <strong>{{ __('portal.documents.no_' . $kind) }}</strong>
                                            {{ __('portal.documents.no_' . $kind . '_help') }}
                                        </div>
                                    @endforelse
                                </div>
                            @endforeach

                            @if (count($vaultMeetings))
                                <h3 class="section-title text-base">{{ __('portal.documents.meetings') }}</h3>
                                <div class="mb-6">
                                    @foreach ($vaultMeetings as $work)
                                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line py-3 last:border-b-0" wire:key="meetings-{{ $work['id'] }}">
                                            <div class="min-w-0">
                                                <div class="font-semibold text-ink break-words">{{ $work['title'] }}</div>
                                                <div class="text-sm text-slate">{{ __('portal.documents.meetings_help') }}</div>
                                            </div>
                                            <a href="{{ $work['link'] }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i data-lucide="calendar"></i> {{ __('portal.documents.meetings_open') }}</a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Proposals signed before the opportunity pipeline (shown only where there are some) --}}
                            @if (count($vaultSows))
                            <h3 class="section-title text-base">{{ __('portal.documents.sows') }}</h3>
                            <div class="mb-6">
                                @forelse ($vaultSows as $sow)
                                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line py-3 last:border-b-0">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-ink break-words">{{ $sow->title }}</div>
                                            <div class="text-xs text-muted tabular-nums">{{ __('portal.documents.sow_meta', ['ref' => $sow->proposal_ref, 'date' => PortalFormat::date($sow->updated_at)]) }}</div>
                                        </div>
                                        <a href="{{ route('portal.proposal', ['token' => $sow->token_hash]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                                            <i data-lucide="external-link"></i> {{ __('portal.documents.view') }}
                                        </a>
                                    </div>
                                @empty
                                    <div class="empty-state" style="padding: 24px;">
                                        <strong>{{ __('portal.documents.no_sows') }}</strong>
                                        {{ __('portal.documents.no_sows_help') }}
                                    </div>
                                @endforelse
                            </div>
                            @endif

                            <h3 class="section-title text-base">{{ __('portal.documents.handovers') }}</h3>
                            <div>
                                @forelse ($vaultSignoffs as $signoff)
                                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line py-3 last:border-b-0">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-ink break-words">{{ $signoff->project_title }}</div>
                                            <div class="text-xs text-muted tabular-nums break-all">{{ __('portal.documents.handover_meta', ['ref' => substr($signoff->token, 0, 12), 'hash' => substr((string) $signoff->client_signature_hash, 0, 10)]) }}</div>
                                        </div>
                                        <a href="{{ route('portal.signoff.gateway', ['token' => $signoff->token]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                                            <i data-lucide="external-link"></i> {{ __('portal.documents.view') }}
                                        </a>
                                    </div>
                                @empty
                                    <div class="empty-state" style="padding: 24px;">
                                        <strong>{{ __('portal.documents.no_handovers') }}</strong>
                                        {{ __('portal.documents.no_handovers_help') }}
                                    </div>
                                @endforelse
                            </div>
                        </section>

                        <section class="card">
                            <div class="card-header">
                                <h2 class="card-title flex items-center gap-2"><i data-lucide="palette" class="h-4 w-4 text-deep-teal"></i> {{ __('portal.documents.appearance') }}</h2>
                            </div>


                            @if ($can['settings.edit'] ?? false)
                                <p class="help m-0 mb-4">{{ __('portal.documents.appearance_intro') }}</p>
                                <form wire:submit.prevent="saveBrandSettings" class="flex flex-col gap-4">
                                    @foreach ([['brandPrimary', 'primary', 'primary_help'], ['brandSecondary', 'secondary', null], ['brandAccent', 'accent', null]] as [$model, $name, $help])
                                        <div class="flex items-center justify-between gap-3 rounded-lg border border-line p-3">
                                            <div>
                                                <label for="colour-{{ $name }}" class="label">{{ __('portal.documents.' . $name) }}</label>
                                                <div class="help">{{ $help ? __('portal.documents.' . $help) : __('portal.documents.colours_help') }}</div>
                                                @error($model) <div class="field-error">{{ $message }}</div> @enderror
                                            </div>
                                            <input type="color" id="colour-{{ $name }}" wire:model="{{ $model }}" value="{{ $this->{$model} }}" class="h-9 w-12 shrink-0 cursor-pointer rounded border border-line-strong bg-paper p-0.5">
                                        </div>
                                    @endforeach

                                    <div class="field">
                                        <label for="brand-logo" class="label">{{ __('portal.documents.logo') }} <span class="font-normal text-muted">({{ __('portal.common.optional') }})</span></label>
                                        <input type="file" id="brand-logo" wire:model="brandLogo" class="input" accept=".png" aria-describedby="brand-logo-help">
                                        <span id="brand-logo-help" class="help">{{ __('portal.documents.logo_help') }}</span>
                                        @error('brandLogo') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>

                                    <button type="submit" class="btn btn-primary self-start" wire:loading.attr="disabled" wire:target="saveBrandSettings, brandLogo">
                                        <span wire:loading.remove wire:target="saveBrandSettings">{{ __('portal.documents.save') }}</span>
                                        <span wire:loading wire:target="saveBrandSettings">{{ __('portal.common.saving') }}</span>
                                    </button>
                                </form>
                            @else
                                <p class="help m-0">{{ __('portal.documents.no_edit') }}</p>
                            @endif
                        </section>
                    </div>

                {{-- ===================== TEAM ===================== --}}
                @elseif ($activeTab === 'team' && ($can['team.view'] ?? false))
                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                        <section class="card lg:col-span-2">
                            <div class="card-header"><h2 class="card-title">{{ __('portal.team.members', ['count' => count($teamMembers ?? []), 'max' => \App\Livewire\ClientOs\Dashboard::MAX_PORTAL_ACCOUNTS]) }}</h2></div>


                            @foreach ($teamMembers ?? [] as $member)
                                @php $manageable = !$member->is_primary_client && ($can['team.edit'] ?? false) && $member->id !== $this->user->id; @endphp
                                <div class="border-b border-line py-4 first:pt-0 last:border-b-0 last:pb-0">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-ink">{{ $member->first_name }} {{ $member->last_name }}</span>
                                                <span class="pill {{ $pill('account', $member->status) }}">{{ $label('status.account', $member->status) }}</span>
                                                @if ($member->is_primary_client)
                                                    <span class="pill pill-info">{{ __('portal.team.primary') }}</span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-muted break-all">{{ $member->email }}</div>
                                        </div>

                                        @if ($manageable)
                                            @if ($member->status === 'Suspended')
                                                <button type="button" wire:click="toggleSuspendUser('{{ $member->id }}')" class="btn btn-secondary btn-sm">
                                                    <i data-lucide="unlock"></i> {{ __('portal.team.restore') }}
                                                </button>
                                            @else
                                                <button type="button" wire:click="toggleSuspendUser('{{ $member->id }}')"
                                                        wire:confirm="{{ __('portal.team.suspend_confirm', ['name' => trim($member->first_name . ' ' . $member->last_name)]) }}"
                                                        class="btn btn-secondary btn-sm text-danger">
                                                    <i data-lucide="ban"></i> {{ __('portal.team.suspend') }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>

                                    <div>
                                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div class="field">
                                                @if ($manageable)
                                                    <label for="role-{{ $member->id }}" class="label">{{ __('portal.team.role') }}</label>
                                                    <select id="role-{{ $member->id }}" wire:change="changeMemberRole('{{ $member->id }}', $event.target.value)" class="select">
                                                        @foreach ($clientRoles as $role)
                                                            <option value="{{ $role->id }}" {{ (int) $member->role_id === (int) $role->id ? 'selected' : '' }}>{{ $roleLabel($role->name) }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="stat-label">{{ __('portal.team.role') }}</span>
                                                    <span class="text-sm font-medium text-ink">{{ $roleLabel($member->role_name) }}</span>
                                                @endif
                                            </div>
                                            <div class="field">
                                                @if ($member->is_primary_client)
                                                    <span class="stat-label">{{ __('portal.team.access') }}</span>
                                                    <span class="text-sm font-medium text-ink">{{ __('portal.team.all_projects') }}</span>
                                                @elseif ($can['team.edit'] ?? false)
                                                    <label for="access-{{ $member->id }}" class="label">{{ __('portal.team.access') }}</label>
                                                    <select id="access-{{ $member->id }}" wire:change="updateNodeAccess('{{ $member->id }}', $event.target.value)" class="select">
                                                        <option value="">{{ __('portal.team.all_projects') }}</option>
                                                        @foreach ($allProjects as $proj)
                                                            <option value="{{ $proj->id }}" {{ $member->restricted_project_id === $proj->id ? 'selected' : '' }}>{{ $proj->title }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="stat-label">{{ __('portal.team.access') }}</span>
                                                    <span class="text-sm font-medium text-ink">{{ $member->restricted_project_title ?: __('portal.team.all_projects') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </section>

                        <section class="card">
                            <div class="card-header">
                                <h2 class="card-title flex items-center gap-2"><i data-lucide="user-plus" class="h-4 w-4 text-deep-teal"></i> {{ __('portal.team.invite_title') }}</h2>
                            </div>

                            @if ($errors->any())
                                <div class="alert alert-danger mb-4" role="alert">
                                    <i data-lucide="circle-alert"></i>
                                    <div>
                                        <strong class="block">{{ __('portal.team.invite_errors') }}</strong>
                                        <ul class="m-0 mt-1 list-disc pl-5">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif

                            @if ($can['team.create'] ?? false)
                                <form wire:submit.prevent="inviteTeamMember" class="flex flex-col gap-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                        <div class="field">
                                            <label for="invite-first" class="label">{{ __('portal.team.first_name') }}</label>
                                            <input type="text" id="invite-first" wire:model="inviteFirstName" class="input" required autocomplete="off">
                                        </div>
                                        <div class="field">
                                            <label for="invite-last" class="label">{{ __('portal.team.last_name') }}</label>
                                            <input type="text" id="invite-last" wire:model="inviteLastName" class="input" required autocomplete="off">
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label for="invite-email" class="label">{{ __('portal.team.email') }}</label>
                                        <input type="email" id="invite-email" wire:model="inviteEmail" class="input" required autocomplete="off" aria-describedby="invite-email-help">
                                        <span id="invite-email-help" class="help">{{ __('portal.team.email_help', ['domain' => $emailDomain]) }}</span>
                                    </div>

                                    <div class="field">
                                        <label for="invite-project" class="label">{{ __('portal.team.project_optional') }} <span class="font-normal text-muted">({{ __('portal.common.optional') }})</span></label>
                                        <select id="invite-project" wire:model="inviteProjectId" class="select">
                                            <option value="">{{ __('portal.team.all_projects') }}</option>
                                            @foreach ($allProjects as $proj)
                                                <option value="{{ $proj->id }}">{{ $proj->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="field">
                                        <label for="invite-role" class="label">{{ __('portal.team.role') }}</label>
                                        <select id="invite-role" wire:model="inviteRoleId" class="select">
                                            <option value="">{{ __('portal.team.role_default') }}</option>
                                            @foreach ($clientRoles as $role)
                                                {{-- The default above is the Team member role: listed once --}}
                                                @continue(($role->slug ?? '') === 'client-member')
                                                <option value="{{ $role->id }}">{{ $roleLabel($role->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary self-start" wire:loading.attr="disabled" wire:target="inviteTeamMember">
                                        <span wire:loading.remove wire:target="inviteTeamMember">{{ __('portal.team.invite_submit') }}</span>
                                        <span wire:loading wire:target="inviteTeamMember">{{ __('portal.common.sending') }}</span>
                                    </button>
                                </form>
                            @else
                                <p class="help m-0">{{ __('portal.team.no_invite') }}</p>
                            @endif
                        </section>
                    </div>
                @endif
            </main>
        </div>
    </div>

    {{-- Message the delivery team from any section --}}
    @if (($can['qa.view'] ?? false) && $activeProject)
        <button type="button" wire:click="switchTab('qa_hub')" class="btn btn-primary portal-fab {{ $activeTab === 'qa_hub' ? 'hidden' : '' }}"
                aria-label="{{ __('portal.nav.message') }}" title="{{ __('portal.nav.message') }}">
            <i data-lucide="message-square"></i> <span class="hidden sm:inline">{{ __('portal.nav.message') }}</span>
        </button>
    @endif


    {{-- ===== Urgent support (signed-in client) ===== --}}
    @if ($showRedlineModal)
        <div class="modal-backdrop" style="z-index: 80;" role="dialog" aria-modal="true" aria-labelledby="urgent-title">
            <div class="modal">
                <h2 id="urgent-title" class="modal-title flex items-center gap-2"><i data-lucide="triangle-alert" class="h-5 w-5 text-danger"></i> {{ __('portal.urgent.title') }}</h2>
                <p class="m-0 mb-4 text-sm text-slate">{{ __('portal.urgent.intro') }}</p>

                @if ($can['support.create'] ?? false)
                    <form wire:submit.prevent="triggerAuthenticatedRedline">
                        <div class="field">
                            <label for="urgent-description" class="label">{{ __('portal.urgent.description') }}</label>
                            <textarea id="urgent-description" wire:model="redlineAuthDescription" class="textarea" rows="5" required aria-describedby="urgent-description-help"></textarea>
                            <span id="urgent-description-help" class="help">{{ __('portal.urgent.description_help') }}</span>
                            @error('redlineAuthDescription') <span class="field-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="modal-actions">
                            <button type="button" wire:click="$set('showRedlineModal', false)" class="btn btn-secondary">{{ __('portal.common.cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="triggerAuthenticatedRedline">
                                <span wire:loading.remove wire:target="triggerAuthenticatedRedline">{{ __('portal.urgent.submit') }}</span>
                                <span wire:loading wire:target="triggerAuthenticatedRedline">{{ __('portal.common.sending') }}</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== An issue or message: details, files, conversation ===== --}}
    <div class="portal-overlay" :class="{ 'is-open': showQaOffcanvas }" @click="showQaOffcanvas = false"></div>
    <aside class="portal-drawer" :class="{ 'is-open': showQaOffcanvas }" role="dialog" aria-modal="true" aria-labelledby="issue-title">
        @if ($selectedTicket)
            <div class="portal-drawer-head">
                <div class="min-w-0">
                    <div class="text-xs text-muted tabular-nums">
                        {{ $selectedTicket->task_id ? __('portal.qa.type_issue') : __('portal.qa.type_message') }}
                        · {{ __('portal.qa.reference', ['ref' => strtoupper(substr($selectedTicket->id, 0, 8))]) }}
                    </div>
                    <h2 id="issue-title" class="m-0 mt-1 text-lg font-semibold text-ink break-words">{{ $ticketTitle($selectedTicket->title) }}</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span class="pill {{ $pill('issue', $selectedTicket->status) }}">{{ $label('status.issue', $selectedTicket->status) }}</span>
                        <span class="pill {{ $pill('severity', $selectedTicket->severity) }}">{{ __('portal.qa.priority_value', ['priority' => $label('status.severity', $selectedTicket->severity)]) }}</span>
                    </div>
                </div>
                <button type="button" @click="showQaOffcanvas = false" class="btn btn-ghost btn-sm" aria-label="{{ __('portal.common.close') }}" title="{{ __('portal.common.close') }}"><i data-lucide="x"></i></button>
            </div>

            <div class="portal-drawer-body">
                <div class="mb-6 rounded-lg bg-mist p-4 text-sm text-slate break-words">
                    {!! nl2br(e($selectedTicket->description)) !!}
                </div>

                <h3 class="section-title text-base">{{ __('portal.qa.attachments') }}</h3>
                <div class="mb-4 flex flex-col gap-2">
                    @forelse ($ticketAttachments as $att)
                        <a href="{{ route('portal.file', ['path' => $att->file_path]) }}" target="_blank" rel="noopener"
                           class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm text-deep-teal no-underline hover:bg-mist" title="{{ $att->file_name }}">
                            <i data-lucide="paperclip" class="h-4 w-4 text-muted"></i>
                            <span class="min-w-0 truncate">{{ $att->file_name }}</span>
                        </a>
                    @empty
                        <p class="help m-0">{{ __('portal.qa.no_attachments') }}</p>
                    @endforelse
                </div>

                @if (in_array($selectedTicket->status, ['Open', 'Investigating']) && ($can['qa.create'] ?? false))
                    <div class="field mb-6">
                        <label for="qa-new-file" class="label">{{ __('portal.qa.add_file') }}</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <input type="file" id="qa-new-file" wire:model="newQaAttachment" class="input min-w-0 flex-1" accept=".png,.jpg,.jpeg,.pdf" aria-describedby="qa-new-file-help">
                            <button type="button" wire:click="uploadQaEvidence" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="newQaAttachment, uploadQaEvidence">
                                <span wire:loading.remove wire:target="uploadQaEvidence"><i data-lucide="upload" class="inline h-4 w-4 align-[-3px]"></i> {{ __('portal.qa.upload') }}</span>
                                <span wire:loading wire:target="uploadQaEvidence">{{ __('portal.common.uploading') }}</span>
                            </button>
                        </div>
                        <span id="qa-new-file-help" class="help">{{ __('portal.common.file_help') }}</span>
                        @error('newQaAttachment') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                @endif

                <h3 class="section-title text-base">{{ __('portal.qa.conversation') }}</h3>
                <div class="flex flex-col gap-3">
                    @forelse ($ticketComments as $comment)
                        @php $mine = ($comment->author_type ?? null) === 'client' && $comment->user_id === $user->id; @endphp
                        <div class="rounded-lg border border-line p-3 {{ $mine ? 'bg-teal-tint' : 'bg-paper' }}">
                            <div class="mb-1 flex flex-wrap justify-between gap-2 text-xs text-muted">
                                <span class="font-semibold text-ink">{{ $mine ? __('portal.common.you') : trim($comment->first_name . ' ' . $comment->last_name) }}</span>
                                <span class="tabular-nums">{{ PortalFormat::date($comment->created_at, 'datetime') }}</span>
                            </div>
                            <div class="text-sm text-slate break-words">{!! nl2br(e($comment->comment_text)) !!}</div>
                        </div>
                    @empty
                        <p class="help m-0">{{ __('portal.qa.no_replies') }}</p>
                    @endforelse
                </div>

                @if (in_array($selectedTicket->status, ['Open', 'Investigating', 'Resolved']))
                    @if ($can['qa.create'] ?? false)
                        <div class="field mt-6">
                            <label for="qa-reply" class="label">{{ __('portal.qa.reply') }}</label>
                            <textarea id="qa-reply" wire:model="newCommentText" class="textarea" rows="3"></textarea>
                            @error('newCommentText') <span class="field-error">{{ $message }}</span> @enderror
                            <button type="button" wire:click="postTicketComment" class="btn btn-primary mt-2 self-start" wire:loading.attr="disabled" wire:target="postTicketComment">
                                <i data-lucide="send"></i> {{ __('portal.qa.send_reply') }}
                            </button>
                        </div>
                    @endif

                    @if ($selectedTicket->task_id && $selectedTicket->status !== 'Closed' && ($can['projects.approve'] ?? false))
                        <div class="mt-6 border-t border-line pt-6">
                            <button type="button" wire:click="approveAndLockFromQa" wire:confirm="{{ __('portal.qa.approve_task_confirm') }}" class="btn btn-secondary w-full whitespace-normal">
                                <i data-lucide="check"></i> {{ __('portal.qa.approve_task') }}
                            </button>
                        </div>
                    @endif
                @endif
            </div>

            @if ($selectedTicket->status === 'Resolved')
                <div class="portal-drawer-foot" style="background: var(--color-success-tint);">
                    <p class="m-0 mb-3 text-sm text-success">{{ __('portal.qa.resolved_note') }}</p>
                    @if ($can['projects.approve'] ?? false)
                        <button type="button" wire:click="approveTicketFix" class="btn btn-primary w-full whitespace-normal">
                            <i data-lucide="circle-check"></i> {{ __('portal.qa.approve_fix') }}
                        </button>
                    @endif
                </div>
            @endif
        @endif
    </aside>

    {{-- ===== Sign-in settings: passkeys and the PIN (docs/SIGN_IN.md) ===== --}}
    <div class="portal-overlay" :class="{ 'is-open': showSignIn }" @click="showSignIn = false"></div>
    <aside class="portal-drawer" :class="{ 'is-open': showSignIn }" role="dialog" aria-modal="true" aria-labelledby="sign-in-title">
        <div class="portal-drawer-head">
            <h2 id="sign-in-title" class="m-0 flex items-center gap-2 text-lg font-semibold text-ink"><i data-lucide="fingerprint" class="h-5 w-5 text-deep-teal"></i> {{ __('sign_in.settings.title') }}</h2>
            <button type="button" @click="showSignIn = false" class="btn btn-ghost btn-sm" aria-label="{{ __('portal.common.close') }}" title="{{ __('portal.common.close') }}"><i data-lucide="x"></i></button>
        </div>
        <div class="portal-drawer-body text-sm text-slate">
            <p class="m-0 mb-6">{{ __('sign_in.settings.intro') }}</p>
            {{-- Lazy: the drawer sits off-screen until it is opened, so the passkeys and PIN load then (one small request),
                 not on every page load; the grey block keeps their place meanwhile --}}
            <div class="portal-sign-in-slot">
                <livewire:account.sign-in-methods guard="client" lazy />
            </div>
        </div>
    </aside>

    {{-- ===== How the portal works ===== --}}
    <div class="portal-overlay" :class="{ 'is-open': showGuide }" @click="showGuide = false"></div>
    <aside class="portal-drawer" :class="{ 'is-open': showGuide }" role="dialog" aria-modal="true" aria-labelledby="guide-title">
        <div class="portal-drawer-head">
            <h2 id="guide-title" class="m-0 flex items-center gap-2 text-lg font-semibold text-ink"><i data-lucide="book-open" class="h-5 w-5 text-deep-teal"></i> {{ __('portal.guide.title') }}</h2>
            <button type="button" @click="showGuide = false" class="btn btn-ghost btn-sm" aria-label="{{ __('portal.common.close') }}" title="{{ __('portal.common.close') }}"><i data-lucide="x"></i></button>
        </div>
        <div class="portal-drawer-body text-sm text-slate">
            <p class="m-0 mb-6">{{ __('portal.guide.intro') }}</p>

            <h3 class="section-title text-base">{{ __('portal.guide.sections') }}</h3>
            <dl class="m-0 mb-6 flex flex-col gap-4">
                @foreach (['overview', 'sprints', 'qa_hub', 'financials', 'vault', 'team'] as $section)
                    <div class="border-l-2 border-line pl-4">
                        <dt class="font-semibold text-ink">{{ __('portal.nav.' . $section) }}</dt>
                        <dd class="m-0 mt-1">{{ __('portal.guide.' . $section, ['max' => \App\Livewire\ClientOs\Dashboard::MAX_PORTAL_ACCOUNTS]) }}</dd>
                    </div>
                @endforeach
            </dl>

            <h3 class="section-title text-base">{{ __('portal.guide.questions') }}</h3>
            <div class="flex flex-col gap-3">
                @foreach (['locked' => 'lock', 'review' => 'clock', 'urgent' => 'triangle-alert'] as $q => $icon)
                    <div class="rounded-lg border border-line p-4">
                        <div class="mb-1 flex items-start gap-2 font-semibold text-ink"><i data-lucide="{{ $icon }}" class="mt-0.5 h-4 w-4 text-deep-teal"></i> {{ __('portal.guide.q_' . $q) }}</div>
                        <p class="m-0">{{ __('portal.guide.a_' . $q) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </aside>
</div>
