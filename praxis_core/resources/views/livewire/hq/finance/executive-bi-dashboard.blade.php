<div>
    @php
        // Financial statements show negatives in brackets
        $stmt = fn ($v) => $v < 0 ? '(' . number_format(abs($v), 2) . ')' : number_format($v, 2);
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Finance overview</h1>
            <p class="page-subtitle">Cash, receivables, profit and cash flow, calculated from the general ledger. Amounts are shown as posted.</p>
        </div>
        @can('finance.export')
        <button type="button" wire:click="exportStatements" class="btn btn-secondary">
            <i data-lucide="download"></i> Download statements
        </button>
        @endcan
    </div>


    <div class="card mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="field min-w-[150px] flex-1">
                <label class="label" for="bi-start">From</label>
                <input id="bi-start" type="date" wire:model="startDate" class="input">
                @error('startDate') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field min-w-[150px] flex-1">
                <label class="label" for="bi-end">To</label>
                <input id="bi-end" type="date" wire:model="endDate" class="input">
                @error('endDate') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <div class="field min-w-[150px] flex-1">
                <label class="label" for="bi-region">Region</label>
                <select id="bi-region" wire:model="regionFilter" class="select">
                    <option value="ALL">All regions</option>
                    <option value="NG">JBS Praxis Nigeria</option>
                    <option value="CM">JBS Praxis Cameroon</option>
                </select>
            </div>
            <button type="button" wire:click="filterMatrix" class="btn btn-primary">
                <i data-lucide="refresh-cw"></i> Update figures
            </button>
        </div>
        <p class="help mt-3">The dates and region apply to the profit and loss and the cash flow. Cash, receivables, spending and runway are always to date, for all regions.</p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card">
            <div class="stat-label">Cash position</div>
            <div class="headline-number mt-1" style="font-variant-numeric: lining-nums tabular-nums;">{{ number_format($kpis['cash_on_hand'], 2) }}</div>
            <div class="help mt-1">Balance of the cash and bank accounts</div>
        </div>
        <div class="card">
            <div class="stat-label">Receivables</div>
            <div class="stat-value">{{ number_format($kpis['outstanding_ar'], 2) }}</div>
            <div class="help mt-1">Invoiced but not yet paid</div>
        </div>
        <div class="card">
            <div class="stat-label">Spending, last 30 days</div>
            <div class="stat-value">{{ number_format($kpis['monthly_burn_rate'], 2) }}</div>
            <div class="help mt-1">Cost of sales and operating expenses</div>
        </div>
        <div class="card">
            <div class="stat-label">Cash runway</div>
            @if($kpis['monthly_burn_rate'] <= 0)
                <div class="stat-value">No spending</div>
                <div class="help mt-1">Nothing spent in the last 30 days</div>
            @else
                <div class="stat-value" @if($kpis['runway_months'] < 3) style="color: var(--color-danger);" @endif>
                    {{ $kpis['runway_months'] > 99 ? 'Over 99 months' : number_format($kpis['runway_months'], 1) . ' months' }}
                </div>
                <div class="help mt-1">
                    @if($kpis['runway_months'] < 3)
                        <span class="pill pill-danger">Under 3 months</span>
                    @else
                        Cash position divided by 30-day spending
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="mb-6 grid items-start gap-6 xl:grid-cols-2">

        <section class="card">
            <h2 class="card-title mb-4">Profit and loss</h2>
            <table class="table">
                <tbody>
                    <tr>
                        <td>Revenue (4000s)</td>
                        <td class="num text-ink">{{ $stmt($pnl['total_revenue']) }}</td>
                    </tr>
                    <tr>
                        <td>Less cost of sales (5000s)</td>
                        <td class="num text-ink">({{ number_format(abs($pnl['cogs']), 2) }})</td>
                    </tr>
                    <tr class="font-semibold text-ink" style="background: var(--color-mist);">
                        <td>Gross profit</td>
                        <td class="num">{{ $stmt($pnl['gross_profit']) }}</td>
                    </tr>
                    <tr>
                        <td>Less operating expenses (6000s)</td>
                        <td class="num text-ink">({{ number_format(abs($pnl['opex']), 2) }})</td>
                    </tr>
                    <tr class="font-semibold" style="color: {{ $pnl['net_income'] < 0 ? 'var(--color-danger)' : 'var(--color-ink)' }};">
                        <td style="border-top: 1px solid var(--color-line-strong); border-bottom: 3px double var(--color-line-strong);">{{ $pnl['net_income'] < 0 ? 'Net loss' : 'Net profit' }}</td>
                        <td class="num" style="border-top: 1px solid var(--color-line-strong); border-bottom: 3px double var(--color-line-strong);">{{ $stmt($pnl['net_income']) }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="help mt-3 text-right">Net margin: <strong style="font-variant-numeric: tabular-nums; color: {{ $pnl['net_margin_percent'] < 0 ? 'var(--color-danger)' : 'var(--color-ink)' }};">{{ $pnl['net_margin_percent'] < 0 ? '−' : '' }}{{ number_format(abs($pnl['net_margin_percent']), 1) }}%</strong></p>
        </section>

        <section class="card">
            <h2 class="card-title mb-4">Cash flow from operations (indirect method)</h2>
            <table class="table">
                <tbody>
                    <tr>
                        <td>Net profit or loss</td>
                        <td class="num text-ink">{{ $stmt($cashFlow['net_income_start']) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="label" style="border-bottom: 0; padding-top: 16px;">Changes in working capital</td>
                    </tr>
                    <tr>
                        <td>Receivables (1100s)</td>
                        <td class="num text-ink">{{ $stmt($cashFlow['adjustment_ar']) }}</td>
                    </tr>
                    <tr>
                        <td>Prepayments (1200s)</td>
                        <td class="num text-ink">{{ $stmt($cashFlow['adjustment_prepaid']) }}</td>
                    </tr>
                    <tr>
                        <td>Deferred revenue (2100s)</td>
                        <td class="num text-ink">{{ $stmt($cashFlow['adjustment_def_rev']) }}</td>
                    </tr>
                    <tr>
                        <td>Tax, payroll and other liabilities (2000s)</td>
                        <td class="num text-ink">{{ $stmt($cashFlow['adjustment_liabilities']) }}</td>
                    </tr>
                    <tr class="font-semibold" style="color: {{ $cashFlow['net_operating_cash'] < 0 ? 'var(--color-danger)' : 'var(--color-ink)' }};">
                        <td style="border-top: 1px solid var(--color-line-strong); border-bottom: 3px double var(--color-line-strong);">Net cash from operations</td>
                        <td class="num" style="border-top: 1px solid var(--color-line-strong); border-bottom: 3px double var(--color-line-strong);">{{ $stmt($cashFlow['net_operating_cash']) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

    </div>

    <section class="card">
        <h2 class="card-title mb-1">Revenue and spending, last 6 months</h2>
        <p class="help mb-4">All regions. Spending is cost of sales plus operating expenses.</p>
        <div class="relative h-[320px] w-full" wire:ignore>
            <canvas id="financialTrendChart" aria-label="Line chart of monthly revenue and spending for the last 6 months" role="img"></canvas>
        </div>
    </section>

    @script
    <script>
        const initialTrends = @json($trends);
        // Chart.js is our own build (resources/js/charts.js), fetched by the pages that draw a chart only, after the page has shown
        const chartReady = import(@js(\Illuminate\Support\Facades\Vite::asset('resources/js/charts.js'))).then((module) => module.default);

        function initFinancialChart(liveTrends = null) {
            chartReady.then((Chart) => drawFinancialChart(Chart, liveTrends)).catch(() => {});
        }

        function drawFinancialChart(Chart, liveTrends) {
            const ctx = document.getElementById('financialTrendChart');
            if (!ctx || !ctx.isConnected) return;

            // Destroy the existing chart so they don’t overlap
            if (window.financialChart) {
                window.financialChart.destroy();
            }

            const rawData = liveTrends || initialTrends;
            const css = getComputedStyle(document.documentElement);
            const token = (name, fallback) => (css.getPropertyValue(name) || '').trim() || fallback;
            const petrol = token('--color-petrol', '#044B6A');
            const teal = token('--color-teal', '#0C8B9B');
            const line = token('--color-line', '#D8DEE6');
            const muted = token('--color-muted', '#5E6679');
            const ink = token('--color-ink', '#0C142B');
            const font = { family: "'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif", size: 12 };

            const paper = token('--color-paper', '#FFFFFF');
            const area = token('--color-teal-tint', '#E3F2F3');

            window.financialChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: rawData.map(t => t.month),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: rawData.map(t => t.revenue),
                            borderColor: petrol,
                            backgroundColor: area,
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: paper,
                            pointBorderColor: petrol,
                            pointRadius: 3
                        },
                        {
                            label: 'Spending',
                            data: rawData.map(t => t.expenses),
                            borderColor: teal,
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3,
                            borderDash: [4, 2],
                            pointBackgroundColor: paper,
                            pointBorderColor: teal,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    color: muted,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: muted, font: font, usePointStyle: true } },
                        tooltip: { backgroundColor: ink, titleColor: paper, bodyColor: paper, padding: 10, titleFont: font, bodyFont: font, callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.y.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: line }, border: { display: false }, ticks: { color: muted, font: font, callback: (value) => value.toLocaleString('en-GB') } },
                        x: { grid: { display: false }, ticks: { color: muted, font: font } }
                    },
                    interaction: { mode: 'index', intersect: false }
                }
            });
        }

        initFinancialChart();

        // Redraw if the server sends new trend data
        window.addEventListener('matrix-recalculated', event => {
            initFinancialChart(event.detail[0].trends);
        });
    </script>
    @endscript
</div>
