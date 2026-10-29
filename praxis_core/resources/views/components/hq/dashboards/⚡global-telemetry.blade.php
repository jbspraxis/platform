<div>
    <!-- Workspace Tabs -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <div class="ws-tab active" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); padding: 5px 15px; background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px;">[ GLOBAL_TELEMETRY ]</div>
            <div class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); cursor: not-allowed; padding: 5px 15px; border: 1px solid transparent;">[ MY_WORKSPACE ]</div>
            <div class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); cursor: not-allowed; padding: 5px 15px; border: 1px solid transparent;">[ INVESTOR_TERMINAL ]</div>
        </div>
        <button style="background: transparent; border: 1px solid var(--erp-teal); color: var(--erp-teal); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; padding: 10px 20px; border-radius: 2px; cursor: pointer;" onclick="alert('Exporting Firm Telemetry...')">EXPORT REPORT (CSV)</button>
    </div>

    <!-- KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-teal); padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Total Capital Cleared</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-teal); text-shadow: 0 0 15px rgba(0, 229, 255, 0.3); line-height: 1;">
                ${{ number_format($kpis['capital_cleared'], 2) }}
            </div>
            <span style="font-size: 0.75rem; color: #00FF66; margin-top: 5px; display: block;">¢¬ Real-time Escrow Sync</span>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid #FFBD2E; padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> A/R Pipeline (Pending)</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #FFBD2E; line-height: 1;">
                ${{ number_format($kpis['ar_pipeline'], 2) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 5px; display: block;">- Accounts Receivable</span>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-gold); padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Active Deployments</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-gold); line-height: 1;">
                {{ str_pad($kpis['active_deployments'], 2, '0', STR_PAD_LEFT) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 5px; display: block;">- Active SOWs in Engineering</span>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-white); padding: 20px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Total Leads Infiltrated</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">
                {{ str_pad($kpis['total_leads'], 2, '0', STR_PAD_LEFT) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 5px; display: block;">- Requires Processing</span>
        </div>
    </div>

    <!-- Analytics & Preview Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        
        <!-- Financial Chart Placeholder -->
        <div style="background: rgba(0,0,0,0.3); border: 1px dashed var(--erp-border-dark); border-radius: 4px; padding: 20px; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <span style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-teal);">> Q1 FIRM REVENUE vs BURN RATE</span>
            </div>
            <div style="flex: 1; position: relative; min-height: 250px;">
                <canvas id="telemetryChart"></canvas>
            </div>
        </div>

        <!-- Recent Leads Ledger -->
        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 25px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">
                <span>> RECENT INFILTRATIONS (LEADS)</span>
                <a href="{{ route('hq.crm.leads') }}" wire:navigate style="color: var(--erp-teal); font-size: 0.7rem; text-decoration: none;">VIEW ALL</a>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">ENTERPRISE</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">INDUSTRY</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 10px; border-bottom: 1px solid var(--erp-border-dark);">DATE</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLeads as $lead)
                    <tr>
                        <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.8rem; font-weight: bold;">{{ $lead->name }}</td>
                        <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.8rem; color: var(--erp-muted);">{{ $lead->industry ?? 'Unknown' }}</td>
                        <td style="padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-teal);">{{ \Carbon\Carbon::parse($lead->created_at)->format('Y-m-d') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" style="padding: 20px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted);">> NO RECENT LEADS DETECTED.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <!-- Initialize Chart.js specifically for this component -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:navigated', function () {
            const ctx = document.getElementById('telemetryChart');
            if (ctx) {
                Chart.defaults.color = '#8A9BB2';
                Chart.defaults.font.family = "'Roboto Mono', monospace";
                
                new Chart(ctx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr'],
                        datasets: [
                            {
                                label: 'Revenue ($)',
                                data: [12000, 34000, 0, 0],
                                backgroundColor: 'rgba(0, 229, 255, 0.8)',
                                borderRadius: 2
                            },
                            {
                                label: 'Burn Rate ($)',
                                data: [8000, 14000, 0, 0],
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
                            y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        });
    </script>
</div>