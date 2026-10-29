<div>
    <!-- Workspace Tabs -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ GLOBAL_TELEMETRY ]</a>
            
            <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ MY_WORKSPACE ]</a>
            
            <a href="{{ route('hq.dash.investor') }}" wire:navigate class="ws-tab active" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); padding: 5px 15px; background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px; text-decoration: none;">[ INVESTOR_TERMINAL ]</a>
            
            <a href="{{ route('hq.dash.audit') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ AUDIT_TERMINAL ]</a>
        </div>
        <button style="background: transparent; border: 1px solid var(--erp-muted); color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; padding: 10px 20px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-white)'; this.style.color='#0A1128'; this.style.borderColor='var(--erp-white)';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-muted)'; this.style.borderColor='var(--erp-muted)';">GENERATE SHAREHOLDER REPORT</button>
    </div>

    <!-- Security Banner -->
    <div style="background: rgba(212, 175, 55, 0.05); border: 1px solid rgba(212, 175, 55, 0.3); border-left: 4px solid var(--erp-gold); padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; border-radius: 4px;">
        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-gold); letter-spacing: 1px;">⚠ SANITIZED VIEW: GRANULAR OPERATIONAL DATA & CLIENT PII REDACTED FOR SHAREHOLDER REVIEW.</span>
    </div>

    <!-- Investor KPI Grid -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px;">
        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #00FF66; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> YTD Gross Revenue</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">
                ${{ number_format($metrics['ytd_revenue'], 2) }}
            </div>
            <span style="font-size: 0.75rem; color: #00FF66; margin-top: 8px; display: block;">Cleared Capital</span>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-teal); padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Active Contract Pipeline</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-teal); line-height: 1;">
                ${{ number_format($metrics['active_pipeline'], 2) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Total value of active SOWs</span>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-gold); padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Active Deployments</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-gold); line-height: 1;">
                {{ str_pad($metrics['active_deployments'], 2, '0', STR_PAD_LEFT) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Tier-1 Enterprises</span>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #8A9BB2; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Average Contract Value (ACV)</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">
                ${{ number_format($metrics['acv'], 2) }}
            </div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Historical Mean</span>
        </div>
    </div>

    <!-- Active Capital Matrix (Table) -->
    <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
            > ACTIVE DEPLOYMENTS (CAPITAL ALLOCATION)
        </div>
        
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02);">Deployment Architecture</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02);">Client Industry</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02);">Initiation Date</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 15px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; background: rgba(255,255,255,0.02); text-align: right;">Contract Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activeDeployments as $deployment)
                <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.9rem; font-weight: 600; color: var(--erp-white);">
                        {{ $deployment->title }}
                    </td>
                    <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.85rem; color: var(--erp-muted);">
                        <span style="background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 2px; font-family: 'Roboto Mono', monospace; font-size: 0.7rem;">{{ $deployment->industry ?? 'N/A' }}</span>
                    </td>
                    <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">
                        {{ \Carbon\Carbon::parse($deployment->created_at)->format('M d, Y') }}
                    </td>
                    <td style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.9rem; font-weight: bold; color: var(--erp-teal); text-align: right;">
                        {{ $deployment->currency }} {{ number_format($deployment->total_budget, 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding: 40px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); border-bottom: 1px solid rgba(255,255,255,0.02);">
                        > NO ACTIVE DEPLOYMENTS DETECTED IN PIPELINE.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>