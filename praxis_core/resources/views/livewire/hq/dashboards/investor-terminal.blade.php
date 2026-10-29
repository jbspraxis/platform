<div>
    <style>
        /* Printing gives a clean report: the page only, without navigation */
        @media print {
            .no-print, .rail, .topbar { display: none !important; }
            .workspace { padding: 0 !important; overflow: visible !important; }
            body, .app-shell, .main { background: #fff !important; height: auto !important; overflow: visible !important; }
            .card { break-inside: avoid; }
        }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Investor view</h1>
            <p class="page-subtitle">Headline figures for shareholders, as of {{ \Carbon\Carbon::now('Africa/Lagos')->format('j M Y, H:i') }} WAT. Detailed operational data is left out.</p>
        </div>
        @can('dashboard.export')
        <button type="button" wire:click="logReportGeneration" class="btn btn-secondary no-print">
            <i data-lucide="printer"></i> Print or save as PDF
        </button>
        @endcan
    </div>

    <nav class="tabs no-print" aria-label="Overviews">
        <a href="{{ route('hq.dash.admin') }}" wire:navigate class="tab">Company overview</a>
        <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="tab">My workspace</a>
        <a href="{{ route('hq.dash.investor') }}" wire:navigate class="tab is-active" aria-current="page">Investor view</a>
        <a href="{{ route('hq.dash.audit') }}" wire:navigate class="tab">Audit log</a>
    </nav>

    <h2 class="section-title">Money</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" style="margin-bottom: 32px;">
        <div class="card">
            <div class="stat-label">Revenue received, {{ now()->year }}</div>
            <div class="stat-value">${{ number_format($metrics['ytd_revenue'], 2) }}</div>
            <div class="help" style="margin-top: 4px;">Paid invoices this year</div>
        </div>
        <div class="card">
            <div class="stat-label">Receivables</div>
            <div class="stat-value">${{ number_format($metrics['pending_revenue'], 2) }}</div>
            <div class="help" style="margin-top: 4px;">Invoices awaiting payment</div>
        </div>
        <div class="card">
            <div class="stat-label">Active contract value</div>
            <div class="stat-value">${{ number_format($metrics['active_pipeline'], 2) }}</div>
            <div class="help" style="margin-top: 4px;">Budgets of active projects</div>
        </div>
        <div class="card">
            <div class="stat-label">Proposals in progress</div>
            <div class="stat-value">${{ number_format($metrics['proposal_pipeline'], 2) }}</div>
            <div class="help" style="margin-top: 4px;">Awaiting signature, or signed and not yet started</div>
        </div>
    </div>

    <h2 class="section-title">Operations</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" style="margin-bottom: 32px;">
        <div class="card">
            <div class="stat-label">Active projects</div>
            <div class="stat-value">{{ number_format($metrics['active_deployments']) }}</div>
            <div class="help" style="margin-top: 4px;">Running now</div>
        </div>
        <div class="card">
            <div class="stat-label">Average contract value</div>
            <div class="stat-value">${{ number_format($metrics['acv'], 2) }}</div>
            <div class="help" style="margin-top: 4px;">All projects except cancelled ones</div>
        </div>
        <div class="card">
            <div class="stat-label">Active clients</div>
            <div class="stat-value">{{ number_format($metrics['active_clients']) }}</div>
            <div class="help" style="margin-top: 4px;">Organisations with an active account</div>
        </div>
        <div class="card">
            <div class="stat-label">Team size</div>
            <div class="stat-value">{{ number_format($metrics['strike_team_size']) }}</div>
            <div class="help" style="margin-top: 4px;">Active staff members</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <section class="card xl:col-span-2" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Active projects</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Industry</th>
                            <th class="num" style="text-align: right;">Contract value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeDeployments as $deployment)
                        <tr>
                            <td>
                                <div class="font-semibold text-ink">{{ $deployment->title }}</div>
                                <div class="text-caption text-muted" style="font-variant-numeric: tabular-nums;">Started {{ \Carbon\Carbon::parse($deployment->created_at)->format('j M Y') }}</div>
                            </td>
                            <td>{{ $deployment->industry ?? 'Not given' }}</td>
                            <td class="num text-ink font-semibold">{{ $deployment->currency }} {{ number_format($deployment->total_budget, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3"><div class="empty-state">No active projects right now.</div></td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex flex-col gap-6">
            <section class="card">
                <h2 class="card-title" style="margin-bottom: 12px;">Leadership team</h2>
                <ul class="flex flex-col" style="list-style: none; padding: 0; margin: 0;">
                    @forelse($leadershipTeam as $leader)
                    <li style="padding: 10px 0; border-top: 1px solid var(--color-line);">
                        <div class="font-semibold text-ink text-small">{{ $leader->first_name }} {{ $leader->last_name }}</div>
                        <div class="text-caption text-muted">{{ $leader->job_title }}</div>
                    </li>
                    @empty
                    <li class="text-small text-muted">No staff members with a job title yet.</li>
                    @endforelse
                </ul>
            </section>

            <section class="card">
                <h2 class="card-title" style="margin-bottom: 12px;">Proposals awaiting signature</h2>
                <ul class="flex flex-col" style="list-style: none; padding: 0; margin: 0;">
                    @forelse($proposalPipeline as $proposal)
                    <li class="flex justify-between gap-4" style="padding: 10px 0; border-top: 1px solid var(--color-line);">
                        <div>
                            <div class="text-small text-ink">{{ \Illuminate\Support\Str::limit($proposal->title, 25) }}</div>
                            <div class="text-caption text-muted">{{ $proposal->client_name }}</div>
                        </div>
                        <div class="text-small font-semibold text-ink" style="font-variant-numeric: tabular-nums; white-space: nowrap;">${{ number_format($proposal->total_value) }}</div>
                    </li>
                    @empty
                    <li class="text-small text-muted">No proposals awaiting signature.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>

    @script
    <script>
        $wire.on('trigger-print', () => {
            window.print();
        });
    </script>
    @endscript
</div>
