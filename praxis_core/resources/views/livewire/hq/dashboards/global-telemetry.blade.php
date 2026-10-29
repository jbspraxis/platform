<div>
    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="ws-tab active" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); padding: 5px 15px; background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px; text-decoration: none;">[ GLOBAL_TELEMETRY ]</a>
            <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ MY_WORKSPACE ]</a>
            <a href="{{ route('hq.dash.investor') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ INVESTOR_TERMINAL ]</a>
            <a href="{{ route('hq.dash.audit') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ AUDIT_TERMINAL ]</a>
        </div>
        @can('dashboard.export')
        <button wire:click="exportTelemetry" wire:loading.attr="disabled" style="background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; padding: 10px 20px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-teal)'; this.style.color='#0A1128';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-teal)'">
            <span wire:loading.remove>EXPORT TELEMETRY (CSV)</span>
            <span wire:loading>EXPORTING...</span>
        </button>
        @endcan
    </div>

    <div style="margin-bottom: 25px;">
        <h1 style="font-family: 'Roboto Mono', monospace; font-size: 1.5rem; color: var(--erp-white); margin-bottom: 5px; letter-spacing: 1px;">JBS PRAXIS COMMAND CORE</h1>
        <p style="font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">SUPER ADMIN OMNI-VIEW | UTC: {{ \Carbon\Carbon::now('UTC')->format('Y-m-d H:i:s') }}</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px;">
        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid #00FF66; padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Total Capital Cleared</div>
            <div style="font-size: 1.8rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #00FF66; text-shadow: 0 0 10px rgba(0, 255, 102, 0.2); line-height: 1;">
                ${{ number_format($kpis['capital_cleared'], 2) }}
            </div>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid #FFBD2E; padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> A/R Pipeline (Pending)</div>
            <div style="font-size: 1.8rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #FFBD2E; line-height: 1;">
                ${{ number_format($kpis['ar_pipeline'], 2) }}
            </div>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-gold); padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Active Deployments</div>
            <div style="font-size: 1.8rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-gold); line-height: 1;">
                {{ str_pad($kpis['active_deployments'], 2, '0', STR_PAD_LEFT) }}
            </div>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-teal); padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Total Leads Infiltrated</div>
            <div style="font-size: 1.8rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-teal); line-height: 1;">
                {{ str_pad($kpis['total_leads'], 2, '0', STR_PAD_LEFT) }}
            </div>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-white); padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Strike Team Active</div>
            <div style="font-size: 1.8rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">
                {{ str_pad($kpis['strike_capacity'], 2, '0', STR_PAD_LEFT) }} <span style="font-size: 0.8rem; color: var(--erp-muted);">OPs</span>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
        
        <div style="background: rgba(0,0,0,0.3); border: 1px dashed var(--erp-border-dark); border-radius: 4px; padding: 20px; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <span style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal);">> JBS PRAXIS: YTD FIRM REVENUE vs BURN RATE</span>
            </div>
            <div style="flex: 1; position: relative; min-height: 300px;">
                <canvas id="telemetryChart"></canvas>
            </div>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-red); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                <span>> CRITICAL AGEING (30+ DAYS)</span>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                @forelse($criticalAlerts as $alert)
                    <div style="border-left: 2px solid var(--erp-red); padding-left: 10px;">
                        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted);">{{ $alert->invoice_number }} | {{ $alert->client_name }}</div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 4px;">
                            <div style="font-size: 0.9rem; font-weight: bold; color: var(--erp-white);">{{ $alert->currency }} {{ number_format($alert->total_amount, 2) }}</div>
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-red); background: rgba(255,51,102,0.1); padding: 2px 6px; border-radius: 2px;">
                                {{ \Carbon\Carbon::parse($alert->due_date)->diffInDays(now()) }} DAYS OVERDUE
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.75rem;">> NO CRITICAL OVERDUE INVOICES.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
            <span>> RECENT INFILTRATIONS (LEADS)</span>
            <a href="#" class="nav-item {{ request()->routeIs('hq.crm.*') ? 'active' : '' }}" style="color: var(--erp-teal); text-decoration: none; font-size: 0.75rem;">
                [ GO TO INTELLIGENCE DESK ]
            </a>
        </div>
        
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">ENTERPRISE</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">INDUSTRY</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">SOURCE</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark); text-align: right;">DATE ACQUIRED</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentLeads as $lead)
                <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.85rem; font-weight: bold; color: var(--erp-white);">{{ $lead->name }}</td>
                    <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.8rem; color: var(--erp-muted);">
                        <span style="background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">{{ $lead->industry ?? 'Unknown' }}</span>
                    </td>
                    <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">{{ $lead->source ?? 'Direct' }}</td>
                    <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-teal); text-align: right;">{{ \Carbon\Carbon::parse($lead->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding: 40px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); border-bottom: 1px dashed var(--erp-border-dark);">
                        > NO RECENT LEADS DETECTED.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    @script
    <script>
        document.addEventListener('livewire:navigated', function () {
            const ctx = document.getElementById('telemetryChart');
            if (!ctx) return;

            // FAILSAFE: Wait for Chart.js to finish downloading before executing
            const waitForChart = setInterval(() => {
                if (typeof Chart !== 'undefined') {
                    clearInterval(waitForChart); // Stop checking once loaded
                    
                    // Prevent "Canvas is already in use" errors if you navigate back and forth
                    if (window.telemetryChartInstance) {
                        window.telemetryChartInstance.destroy();
                    }

                    Chart.defaults.color = '#8A9BB2';
                    Chart.defaults.font.family = "'Roboto Mono', monospace";
                    
                    window.telemetryChartInstance = new Chart(ctx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: $wire.chartLabels, // Use $wire to pull data directly
                            datasets: [
                                {
                                    label: 'Cleared Revenue ($)',
                                    data: $wire.chartRevenue,
                                    backgroundColor: 'rgba(0, 255, 102, 0.8)',
                                    borderRadius: 2
                                },
                                {
                                    label: 'Eng. Burn Rate ($)',
                                    data: $wire.chartBurn,
                                    backgroundColor: 'rgba(255, 51, 102, 0.8)',
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
                                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                    ticks: { callback: function(value) { return '$' + value / 1000 + 'k'; } }
                                },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            }, 50); // Checks every 50 milliseconds
        });
    </script>
    @endscript
</div>