<div>
    <style>
        /* Print styles to generate a clean PDF Report */
        @media print {
            .no-print { display: none !important; }
            body, .ws-tab, div { background: #FFF !important; color: #000 !important; }
            .print-border { border: 1px solid #CCC !important; }
            .print-text { color: #000 !important; }
        }
    </style>

    <div class="no-print" style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;">[ GLOBAL_TELEMETRY ]</a>
            <a href="#" class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;">[ MY_WORKSPACE ]</a>
            <a href="{{ route('hq.dash.investor') }}" wire:navigate class="ws-tab active" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-gold); padding: 5px 15px; background: rgba(212, 175, 55, 0.05); border: 1px solid var(--erp-gold); border-radius: 4px; text-decoration: none;">[ INVESTOR_TERMINAL ]</a>
            <a href="{{ route('hq.dash.audit') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;">[ AUDIT_TERMINAL ]</a>
        </div>
        
        <button wire:click="logReportGeneration" style="background: transparent; border: 1px solid var(--erp-muted); color: var(--erp-muted); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; padding: 10px 20px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-white)'; this.style.color='#0A1128'; this.style.borderColor='var(--erp-white)';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-muted)'; this.style.borderColor='var(--erp-muted)';">
            GENERATE SHAREHOLDER PDF
        </button>
    </div>

    <div style="margin-bottom: 30px;">
        <h1 class="print-text" style="font-family: 'Roboto Mono', monospace; color: var(--erp-white); margin-bottom: 5px;">SHAREHOLDER BRIEFING DOSSIER</h1>
        <p class="print-text" style="color: var(--erp-muted); font-size: 0.85rem;">Generated: {{ \Carbon\Carbon::now()->format('Y-m-d H:i:s T') }}</p>
    </div>

    <div class="no-print" style="background: rgba(212, 175, 55, 0.05); border: 1px solid rgba(212, 175, 55, 0.3); border-left: 4px solid var(--erp-gold); padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; border-radius: 4px;">
        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-gold); letter-spacing: 1px;">⚠ SANITIZED VIEW: GRANULAR OPERATIONAL DATA REDACTED FOR SHAREHOLDER REVIEW.</span>
    </div>

    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 15px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">> CAPITAL METRICS</div>
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #00FF66; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> YTD Gross Revenue</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #00FF66; line-height: 1;">${{ number_format($metrics['ytd_revenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Cleared Capital</span>
        </div>

        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #FFBD2E; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Pending Receivables</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #FFBD2E; line-height: 1;">${{ number_format($metrics['pending_revenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Invoices Awaiting Escrow</span>
        </div>

        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-teal); padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Active Contract Pipeline</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-teal); line-height: 1;">${{ number_format($metrics['active_pipeline'], 2) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Value of Active SOWs</span>
        </div>

        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #A1A1AA; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Proposal Pipeline</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #A1A1AA; line-height: 1;">${{ number_format($metrics['proposal_pipeline'], 2) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Pending Authorizations</span>
        </div>
    </div>

    <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 15px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 10px;">> OPERATIONAL METRICS</div>
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px;">
        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-gold); padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Active Deployments</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-gold); line-height: 1;">{{ str_pad($metrics['active_deployments'], 2, '0', STR_PAD_LEFT) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Concurrent Projects</span>
        </div>

        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #8A9BB2; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Average Contract Value</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">${{ number_format($metrics['acv'], 2) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Historical Mean (ACV)</span>
        </div>

        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #8A9BB2; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Retained Enterprises</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">{{ str_pad($metrics['active_clients'], 2, '0', STR_PAD_LEFT) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Active Clients</span>
        </div>

        <div class="print-border" style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #8A9BB2; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Strike Team Size</div>
            <div class="print-text" style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">{{ str_pad($metrics['strike_team_size'], 2, '0', STR_PAD_LEFT) }}</div>
            <span style="font-size: 0.75rem; color: var(--erp-muted); margin-top: 8px; display: block;">Active Personnel</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
        
        <div class="print-border" style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
            <div class="print-text" style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
                > ACTIVE DEPLOYMENTS (CAPITAL AT WORK)
            </div>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Architecture</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Industry</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; text-align: right;">Contract Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeDeployments as $deployment)
                    <tr>
                        <td class="print-text" style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.85rem; color: var(--erp-white);">
                            {{ $deployment->title }}
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); margin-top: 4px;">Initiated: {{ \Carbon\Carbon::parse($deployment->created_at)->format('Y-m-d') }}</div>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.75rem; color: var(--erp-muted);">{{ $deployment->industry ?? 'Enterprise' }}</td>
                        <td class="print-text" style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.85rem; font-weight: bold; color: var(--erp-teal); text-align: right;">
                            {{ $deployment->currency }} {{ number_format($deployment->total_budget, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding: 20px; text-align: center; color: var(--erp-muted);">> NO ACTIVE DEPLOYMENTS.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="display: flex; flex-direction: column; gap: 30px;">
            
            <div class="print-border" style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
                <div class="print-text" style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
                    > EXECUTIVE STRIKE TEAM
                </div>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    @forelse($leadershipTeam as $leader)
                    <li style="padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.02);">
                        <div class="print-text" style="font-size: 0.85rem; font-weight: bold; color: var(--erp-white);">{{ $leader->first_name }} {{ $leader->last_name }}</div>
                        <div style="font-size: 0.75rem; color: var(--erp-gold); margin-top: 3px;">{{ $leader->job_title }}</div>
                    </li>
                    @empty
                    <li style="color: var(--erp-muted); font-size: 0.8rem;">> NO TEAM DATA.</li>
                    @endforelse
                </ul>
            </div>

            <div class="print-border" style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
                <div class="print-text" style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
                    > PENDING NEGOTIATIONS
                </div>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    @forelse($proposalPipeline as $proposal)
                    <li style="padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.02); display: flex; justify-content: space-between;">
                        <div>
                            <div class="print-text" style="font-size: 0.8rem; color: var(--erp-white);">{{ \Illuminate\Support\Str::limit($proposal->title, 25) }}</div>
                            <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 3px;">{{ $proposal->client_name }}</div>
                        </div>
                        <div class="print-text" style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; font-weight: bold; color: #A1A1AA;">
                            ${{ number_format($proposal->total_value) }}
                        </div>
                    </li>
                    @empty
                    <li style="color: var(--erp-muted); font-size: 0.8rem;">> NO PENDING SOWs.</li>
                    @endforelse
                </ul>
            </div>

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