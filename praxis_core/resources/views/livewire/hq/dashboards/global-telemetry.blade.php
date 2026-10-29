<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">Company overview</h1>
            <p class="page-subtitle">Cash, invoices, projects and leads across JBS Praxis. Figures as of {{ \Carbon\Carbon::now('Africa/Lagos')->format('j M Y, H:i') }} WAT.</p>
        </div>
        @can('dashboard.export')
        <button type="button" wire:click="exportTelemetry" wire:loading.attr="disabled" wire:target="exportTelemetry" class="btn btn-secondary">
            <i data-lucide="download"></i>
            <span wire:loading.remove wire:target="exportTelemetry">Download CSV</span>
            <span wire:loading wire:target="exportTelemetry">Preparing file…</span>
        </button>
        @endcan
    </div>

    <nav class="tabs" aria-label="Overviews">
        <a href="{{ route('hq.dash.admin') }}" wire:navigate class="tab is-active" aria-current="page">Company overview</a>
        <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="tab">My workspace</a>
        <a href="{{ route('hq.dash.investor') }}" wire:navigate class="tab">Investor view</a>
        <a href="{{ route('hq.dash.audit') }}" wire:navigate class="tab">Audit log</a>
    </nav>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4" style="margin-bottom: 24px;">
        <div class="card">
            <div class="stat-label">Paid invoices, total</div>
            <div class="stat-value">${{ number_format($kpis['capital_cleared'], 2) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Unpaid invoices</div>
            <div class="stat-value">${{ number_format($kpis['ar_pipeline'], 2) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Active projects</div>
            <div class="stat-value">{{ number_format($kpis['active_deployments']) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Open leads</div>
            <div class="stat-value">{{ number_format($kpis['total_leads']) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Staff logging time now</div>
            <div class="stat-value">{{ number_format($kpis['strike_capacity']) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6" style="margin-bottom: 24px;">
        <section class="card xl:col-span-2 flex flex-col">
            <div class="card-header">
                <h2 class="card-title">Revenue and staff time cost, {{ now()->year }} to date</h2>
            </div>
            <div style="flex: 1; position: relative; min-height: 300px;">
                <canvas id="telemetryChart" role="img" aria-label="Bar chart of revenue received and staff time cost by month this year"></canvas>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Invoices overdue by more than 30 days</h2>
            </div>

            <div class="flex flex-col">
                @forelse($criticalAlerts as $alert)
                    <div style="padding: 12px 0; border-top: 1px solid var(--color-line);">
                        <div class="text-caption text-muted" style="font-variant-numeric: tabular-nums;">{{ $alert->invoice_number }} · {{ $alert->client_name }}</div>
                        <div class="flex justify-between items-center gap-3" style="margin-top: 4px;">
                            <div class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ $alert->currency }} {{ number_format($alert->total_amount, 2) }}</div>
                            <span class="pill pill-danger">{{ (int) \Carbon\Carbon::parse($alert->due_date)->diffInDays(now()) }} days overdue</span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state" style="padding: 24px 8px;">No invoices are more than 30 days overdue.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card" style="padding: 0;">
        <div class="card-header" style="padding: 20px 24px 0;">
            <h2 class="card-title">Newest leads</h2>
            @can('crm.view')
                <a href="{{ route('hq.crm.leads') }}" wire:navigate class="btn btn-ghost btn-sm">Go to leads <i data-lucide="arrow-right"></i></a>
            @endcan
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Organisation</th>
                        <th>Industry</th>
                        <th>Source</th>
                        <th style="text-align: right;">Added</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLeads as $lead)
                    <tr>
                        <td class="font-semibold text-ink">{{ $lead->name }}</td>
                        <td>{{ $lead->industry ?? 'Not given' }}</td>
                        <td>{{ $lead->source ?? 'Direct' }}</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap;">{{ \Carbon\Carbon::parse($lead->created_at)->format('j M Y, H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <strong>No open leads</strong>
                                New leads appear here when someone uses the cost of poor quality calculator or books a free audit.
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @script
    <script>
        document.addEventListener('livewire:navigated', function () {
            const ctx = document.getElementById('telemetryChart');
            if (!ctx) return;

            // Wait for Chart.js to finish downloading before drawing
            const waitForChart = setInterval(() => {
                if (typeof Chart !== 'undefined') {
                    clearInterval(waitForChart);

                    // Prevent "Canvas is already in use" errors when navigating back and forth
                    if (window.telemetryChartInstance) {
                        window.telemetryChartInstance.destroy();
                    }

                    // Brand chart colours (docs/BRAND.md section 7): Petrol and Teal, labelled; Muted text; Line grid
                    Chart.defaults.color = '#5E6679';
                    Chart.defaults.font.family = "'Plus Jakarta Sans Variable', 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif";

                    window.telemetryChartInstance = new Chart(ctx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: $wire.chartLabels,
                            datasets: [
                                {
                                    label: 'Revenue received ($)',
                                    data: $wire.chartRevenue,
                                    backgroundColor: '#044B6A',
                                    borderRadius: 2
                                },
                                {
                                    label: 'Staff time cost ($)',
                                    data: $wire.chartBurn,
                                    backgroundColor: '#0C8B9B',
                                    borderRadius: 2
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', align: 'end' } },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#D8DEE6' },
                                    ticks: { callback: function(value) { return '$' + value / 1000 + 'k'; } }
                                },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            }, 50);
        });
    </script>
    @endscript
</div>
