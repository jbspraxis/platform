<div>
    <style>
        /* --- GLOBAL CYBER STYLES --- */
        .cyber-input, .cyber-select {
            background: rgba(0,0,0,0.4); border: 1px solid rgba(0, 229, 255, 0.2);
            color: #FFFFFF; padding: 10px 15px; font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem; border-radius: 2px; transition: 0.3s; outline: none; width: 100%;
        }
        .cyber-input:focus, .cyber-select:focus { 
            border-color: #00E5FF; background: rgba(0, 229, 255, 0.05); box-shadow: inset 2px 0 0 #00E5FF; 
        }
        .cyber-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2300E5FF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
        }
        .cyber-select option { background-color: #0A1128; color: #FFF; }
        
        .btn-action {
            background: transparent; border: 1px solid #00E5FF; color: #00E5FF; padding: 10px 20px;
            font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; cursor: pointer;
            transition: 0.3s; border-radius: 2px; display: inline-flex; align-items: center; gap: 8px; justify-content: center;
        }
        .btn-action:hover { background: #00E5FF; color: #0A1128; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }

        /* --- FILTERS BAR --- */
        .filter-bar {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-left: 4px solid #00E5FF;
            padding: 20px; border-radius: 4px; display: flex; gap: 15px; align-items: flex-end; margin-bottom: 30px;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.2); flex-wrap: wrap;
        }

        /* --- KPI GRID --- */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card {
            background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.05); border-top: 3px solid;
            padding: 25px 20px; border-radius: 4px; text-align: center; box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            position: relative; overflow: hidden; transition: transform 0.3s ease;
        }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-label { font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2; text-transform: uppercase; margin-bottom: 10px; display: block; letter-spacing: 1px; }
        .kpi-val { font-family: 'Montserrat', sans-serif; font-size: 1.8rem; font-weight: 900; color: #FFF; }
        .kpi-currency { font-size: 1rem; color: #8A9BB2; vertical-align: text-top; }
        .kpi-bg-icon { position: absolute; right: -10px; bottom: -10px; opacity: 0.05; width: 80px; height: 80px; }

        /* --- STATEMENT PANELS (P&L and Cash Flow) --- */
        .statement-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; align-items: start; }
        @media (max-width: 1024px) { .statement-grid { grid-template-columns: 1fr; } }
        
        .statement-card {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 4px;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.2); overflow: hidden;
        }
        .statement-header {
            background: rgba(0, 0, 0, 0.4); padding: 15px 25px; border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
            font-family: 'Montserrat', sans-serif; font-weight: 900; font-size: 1rem; color: #FFF; letter-spacing: 2px;
            display: flex; align-items: center; gap: 10px;
        }
        .statement-body { padding: 20px 25px; }
        
        .stmt-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; }
        .stmt-row:last-child { border-bottom: none; }
        .stmt-label { color: #8A9BB2; }
        .stmt-val { color: #FFF; font-weight: bold; text-align: right; }
        
        .stmt-subtotal { background: rgba(0, 229, 255, 0.05); border-bottom: 1px dashed rgba(0, 229, 255, 0.3) !important; margin-top: 5px; margin-bottom: 5px; }
        .stmt-subtotal .stmt-label { color: #00E5FF; }
        
        .stmt-total { background: rgba(0, 255, 102, 0.1); border: 1px solid #00FF66 !important; border-radius: 2px; padding: 15px; margin-top: 15px; }
        .stmt-total .stmt-label { color: #00FF66; font-size: 0.95rem; font-weight: bold; text-transform: uppercase; }
        .stmt-total .stmt-val { color: #00FF66; font-size: 1.2rem; }
        .stmt-total.negative { background: rgba(255, 51, 102, 0.1); border-color: #FF3366 !important; }
        .stmt-total.negative .stmt-label, .stmt-total.negative .stmt-val { color: #FF3366; }

        /* --- CHART PANEL --- */
        .chart-container {
            background: rgba(10, 17, 40, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 4px;
            padding: 25px; box-shadow: inset 0 0 20px rgba(0,0,0,0.2); position: relative; height: 400px; width: 100%;
        }
    </style>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; padding-bottom: 15px; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-family: 'Montserrat', sans-serif; font-size: 1.8rem; font-weight: 900; letter-spacing: 1px; color: #FFF; margin: 0;">EXECUTIVE BI & TELEMETRY</h1>
            <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: #8A9BB2; margin: 5px 0 0 0;">> Global Double-Entry Aggregation & Financial Health Matrices</p>
        </div>
        <div>
            <button wire:click="exportStatements" class="btn-action" style="border-color: #FFBD2E; color: #FFBD2E;">
                <i data-lucide="download-cloud" style="width: 16px; height: 16px;"></i> EXPORT AUDITOR REPORTS
            </button>
        </div>
    </div>

    @if(session()->has('success'))
        <div style="background: rgba(255, 189, 46, 0.1); border: 1px solid #FFBD2E; color: #FFBD2E; padding: 15px; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; margin-bottom: 20px; border-radius: 2px; text-align: center;">
            {{ session('success') }}
        </div>
    @endif

    <div class="filter-bar">
        <div style="flex: 1; min-width: 150px;">
            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; display: block; margin-bottom: 5px;">FISCAL PERIOD START</label>
            <input type="date" wire:model="startDate" class="cyber-input">
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; display: block; margin-bottom: 5px;">FISCAL PERIOD END</label>
            <input type="date" wire:model="endDate" class="cyber-input">
        </div>
        <div style="flex: 1; min-width: 150px;">
            <label style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #8A9BB2; display: block; margin-bottom: 5px;">COMPLIANCE REGION</label>
            <select wire:model="regionFilter" class="cyber-select">
                <option value="ALL">GLOBAL AGGREGATION</option>
                <option value="NG">JBS PRAXIS NIGERIA</option>
                <option value="CM">JBS PRAXIS CAMEROON</option>
            </select>
        </div>
        <div style="flex: 0 0 auto;">
            <button wire:click="filterMatrix" class="btn-action" style="padding: 10px 25px; height: 41px;">
                <i data-lucide="activity" style="width: 16px; height: 16px;"></i> RECALCULATE MATRIX
            </button>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-top-color: #00FF66;">
            <i data-lucide="banknote" class="kpi-bg-icon" style="color: #00FF66;"></i>
            <span class="kpi-label">Liquid Cash on Hand</span>
            <div class="kpi-val"><span class="kpi-currency">$</span>{{ number_format($kpis['cash_on_hand'], 2) }}</div>
        </div>
        <div class="kpi-card" style="border-top-color: #00E5FF;">
            <i data-lucide="file-clock" class="kpi-bg-icon" style="color: #00E5FF;"></i>
            <span class="kpi-label">Accounts Receivable (A/R)</span>
            <div class="kpi-val"><span class="kpi-currency">$</span>{{ number_format($kpis['outstanding_ar'], 2) }}</div>
        </div>
        <div class="kpi-card" style="border-top-color: #FF3366;">
            <i data-lucide="flame" class="kpi-bg-icon" style="color: #FF3366;"></i>
            <span class="kpi-label">30-Day Capital Burn</span>
            <div class="kpi-val"><span class="kpi-currency">$</span>{{ number_format($kpis['monthly_burn_rate'], 2) }}</div>
        </div>
        <div class="kpi-card" style="border-top-color: #FFBD2E;">
            <i data-lucide="rocket" class="kpi-bg-icon" style="color: #FFBD2E;"></i>
            <span class="kpi-label">Operational Runway</span>
            <div class="kpi-val" style="color: {{ $kpis['runway_months'] < 3 ? '#FF3366' : '#FFF' }};">
                {{ $kpis['runway_months'] > 99 ? 'INFINITE' : number_format($kpis['runway_months'], 1) . ' MO' }}
            </div>
        </div>
    </div>

    <div class="statement-grid">
        
        <div class="statement-card">
            <div class="statement-header">
                <i data-lucide="bar-chart-2" style="width: 18px; height: 18px; color: #00E5FF;"></i> 
                STATEMENT OF COMPREHENSIVE INCOME (P&L)
            </div>
            <div class="statement-body">
                <div class="stmt-row">
                    <span class="stmt-label">Operating Revenue (4xxx)</span>
                    <span class="stmt-val" style="color: #00FF66;">${{ number_format($pnl['total_revenue'], 2) }}</span>
                </div>
                <div class="stmt-row">
                    <span class="stmt-label">Less: Cost of Goods Sold (5xxx)</span>
                    <span class="stmt-val" style="color: #FF3366;">(${{ number_format(abs($pnl['cogs']), 2) }})</span>
                </div>
                <div class="stmt-row stmt-subtotal">
                    <span class="stmt-label">GROSS PROFIT</span>
                    <span class="stmt-val">${{ number_format($pnl['gross_profit'], 2) }}</span>
                </div>
                <div class="stmt-row">
                    <span class="stmt-label">Less: Operating Expenses (6xxx)</span>
                    <span class="stmt-val" style="color: #FF3366;">(${{ number_format(abs($pnl['opex']), 2) }})</span>
                </div>
                <div class="stmt-row stmt-total {{ $pnl['net_income'] < 0 ? 'negative' : '' }}">
                    <span class="stmt-label">NET INCOME</span>
                    <span class="stmt-val">${{ number_format($pnl['net_income'], 2) }}</span>
                </div>
                <div style="text-align: right; margin-top: 10px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: #8A9BB2;">
                    NET PROFIT MARGIN: <strong style="color: {{ $pnl['net_margin_percent'] < 0 ? '#FF3366' : '#00E5FF' }};">{{ number_format($pnl['net_margin_percent'], 1) }}%</strong>
                </div>
            </div>
        </div>

        <div class="statement-card">
            <div class="statement-header">
                <i data-lucide="refresh-cw" style="width: 18px; height: 18px; color: #00FF66;"></i> 
                STATEMENT OF CASH FLOWS (INDIRECT)
            </div>
            <div class="statement-body">
                <div class="stmt-row">
                    <span class="stmt-label">Starting Net Income</span>
                    <span class="stmt-val">${{ number_format($cashFlow['net_income_start'], 2) }}</span>
                </div>
                <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: #00E5FF; margin: 15px 0 5px 0;">> WORKING CAPITAL ADJUSTMENTS</div>
                <div class="stmt-row">
                    <span class="stmt-label">Change in Accounts Receivable (11xx)</span>
                    <span class="stmt-val" style="color: {{ $cashFlow['adjustment_ar'] < 0 ? '#FF3366' : '#00FF66' }};">{{ $cashFlow['adjustment_ar'] < 0 ? '($' . number_format(abs($cashFlow['adjustment_ar']), 2) . ')' : '$' . number_format($cashFlow['adjustment_ar'], 2) }}</span>
                </div>
                <div class="stmt-row">
                    <span class="stmt-label">Change in Prepayments (12xx)</span>
                    <span class="stmt-val" style="color: {{ $cashFlow['adjustment_prepaid'] < 0 ? '#FF3366' : '#00FF66' }};">{{ $cashFlow['adjustment_prepaid'] < 0 ? '($' . number_format(abs($cashFlow['adjustment_prepaid']), 2) . ')' : '$' . number_format($cashFlow['adjustment_prepaid'], 2) }}</span>
                </div>
                <div class="stmt-row">
                    <span class="stmt-label">Change in Deferred Revenue (21xx)</span>
                    <span class="stmt-val" style="color: {{ $cashFlow['adjustment_def_rev'] < 0 ? '#FF3366' : '#00FF66' }};">{{ $cashFlow['adjustment_def_rev'] < 0 ? '($' . number_format(abs($cashFlow['adjustment_def_rev']), 2) . ')' : '$' . number_format($cashFlow['adjustment_def_rev'], 2) }}</span>
                </div>
                <div class="stmt-row">
                    <span class="stmt-label">Change in Tax/Payroll Liabilities (2xxx)</span>
                    <span class="stmt-val" style="color: {{ $cashFlow['adjustment_liabilities'] < 0 ? '#FF3366' : '#00FF66' }};">{{ $cashFlow['adjustment_liabilities'] < 0 ? '($' . number_format(abs($cashFlow['adjustment_liabilities']), 2) . ')' : '$' . number_format($cashFlow['adjustment_liabilities'], 2) }}</span>
                </div>
                <div class="stmt-row stmt-total {{ $cashFlow['net_operating_cash'] < 0 ? 'negative' : '' }}">
                    <span class="stmt-label">NET CASH FROM OPERATIONS</span>
                    <span class="stmt-val">${{ number_format($cashFlow['net_operating_cash'], 2) }}</span>
                </div>
            </div>
        </div>

    </div>

    <div class="chart-container" wire:ignore>
        <div style="font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 900; color: #FFF; margin-bottom: 15px; position: absolute; top: 25px; left: 25px;">
            <i data-lucide="trending-up" style="width: 16px; height: 16px; color: #FFBD2E; display: inline-block; vertical-align: bottom;"></i> 6-MONTH REVENUE VS EXPENSE TREND
        </div>
        <canvas id="financialTrendChart"></canvas>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:navigated', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
        document.addEventListener('DOMContentLoaded', () => { 
            if(typeof lucide !== 'undefined') lucide.createIcons(); 
            initFinancialChart();
        });
        
        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => { 
                if(typeof lucide !== 'undefined') lucide.createIcons(); 
                // Re-init chart smoothly if DOM morphs it
                if(!window.financialChart) { initFinancialChart(); }
            });
        });

        // Listen for custom event if we want to redraw chart on filter
        window.addEventListener('matrix-recalculated', event => {
            initFinancialChart(event.detail[0].trends);
        });

        function initFinancialChart(liveTrends = null) {
            const ctx = document.getElementById('financialTrendChart');
            if(!ctx) return;

            // Destroy existing chart if it exists to prevent overlap
            if(window.financialChart) {
                window.financialChart.destroy();
            }

            // Fallback to PHP data on first load
            const rawData = liveTrends || @json($trends);
            
            // Format Data for Chart.js
            const labels = rawData.map(t => t.month);
            const revData = rawData.map(t => t.revenue);
            const expData = rawData.map(t => t.expenses);

            window.financialChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Operating Revenue',
                            data: revData,
                            borderColor: '#00E5FF',
                            backgroundColor: 'rgba(0, 229, 255, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#0A1128',
                            pointBorderColor: '#00E5FF',
                            pointRadius: 4
                        },
                        {
                            label: 'Total Expenses (COGS + OPEX)',
                            data: expData,
                            borderColor: '#FF3366',
                            backgroundColor: 'transparent',
                            borderWidth: 3,
                            tension: 0.4,
                            borderDash: [5, 5],
                            pointBackgroundColor: '#0A1128',
                            pointBorderColor: '#FF3366',
                            pointRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    color: '#8A9BB2',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#8A9BB2', font: { family: 'Roboto Mono' } } },
                        tooltip: { backgroundColor: 'rgba(10, 17, 40, 0.9)', titleColor: '#FFF', bodyColor: '#00E5FF', borderColor: '#00E5FF', borderWidth: 1, padding: 10, displayColors: false, callbacks: { label: function(context) { return '$' + context.parsed.y.toLocaleString(); } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#8A9BB2', font: { family: 'Roboto Mono' }, callback: function(value) { return '$' + value.toLocaleString(); } } },
                        x: { grid: { display: false }, ticks: { color: '#8A9BB2', font: { family: 'Roboto Mono' } } }
                    },
                    interaction: { mode: 'index', intersect: false }
                }
            });
        }
    </script>
</div>