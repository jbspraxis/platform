<div>
    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--erp-border-dark); margin-bottom: 30px; padding-bottom: 10px;">
        <div style="display: flex; gap: 15px;">
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ GLOBAL_TELEMETRY ]</a>
            <a href="#" class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ MY_WORKSPACE ]</a>
            <a href="#" class="ws-tab" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-muted); padding: 5px 15px; border: 1px solid transparent; text-decoration: none; transition: 0.3s;" onmouseover="this.style.color='#FFF'" onmouseout="this.style.color='var(--erp-muted)'">[ INVESTOR_TERMINAL ]</a>
            <a href="{{ route('hq.dash.audit') }}" wire:navigate class="ws-tab active" style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-teal); padding: 5px 15px; background: rgba(0, 229, 255, 0.05); border: 1px solid var(--erp-teal); border-radius: 4px; text-decoration: none;">[ AUDIT_TERMINAL ]</a>
        </div>
        
        <button wire:click="exportBooks" wire:loading.attr="disabled" style="background: transparent; border: 1px solid var(--erp-red); color: var(--erp-red); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; font-weight: bold; padding: 10px 20px; border-radius: 2px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='var(--erp-red)'; this.style.color='#FFF';" onmouseout="this.style.background='transparent'; this.style.color='var(--erp-red)';">
            <span wire:loading.remove>EXPORT BOOKS (CSV)</span>
            <span wire:loading>EXPORTING...</span>
        </button>
    </div>

    <div style="background: rgba(255, 51, 102, 0.05); border: 1px solid rgba(255, 51, 102, 0.3); border-left: 4px solid var(--erp-red); padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; border-radius: 4px;">
        <span style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-red); letter-spacing: 1px;">⚠ EXTERNAL AUDIT MODE: IMMUTABLE FINANCIAL & SECURITY LEDGER. ALL VIEWS ARE STRICTLY READ-ONLY.</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px;">
        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #00FF66; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> YTD Auditable Revenue</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-white); line-height: 1;">
                ${{ number_format($kpis['ytd_cleared_revenue'], 2) }}
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-red); padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> YTD VAT / Tax Liability</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-red); line-height: 1;">
                ${{ number_format($kpis['ytd_vat_liability'], 2) }}
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid var(--erp-teal); padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> Escrow Verifications</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: var(--erp-teal); line-height: 1;">
                {{ number_format($kpis['escrow_verifications']) }}
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--erp-border-dark); border-top: 2px solid #A1A1AA; padding: 25px; border-radius: 4px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.7rem; color: var(--erp-muted); margin-bottom: 10px; text-transform: uppercase;">> System Audit Events</div>
            <div style="font-size: 2.2rem; font-weight: 800; font-family: 'Roboto Mono', monospace; color: #A1A1AA; line-height: 1;">
                {{ number_format($kpis['total_system_events']) }}
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
                > IMMUTABLE INVOICE LEDGER (A/R)
            </div>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Invoice</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Entity</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; text-align: right;">Total / VAT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoiceLedger as $inv)
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                            <div style="font-family: 'Roboto Mono', monospace; font-weight: bold; font-size: 0.85rem; color: {{ $inv->status == 'Paid' ? '#00FF66' : '#FFBD2E' }};">{{ $inv->invoice_number }}</div>
                            <div style="font-size: 0.7rem; color: var(--erp-muted); margin-top: 4px;">{{ \Carbon\Carbon::parse($inv->created_at)->format('Y-m-d') }}</div>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.8rem; color: var(--erp-white);">{{ $inv->client_name }}</td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right;">
                            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.85rem; color: var(--erp-white); font-weight: bold;">{{ $inv->currency }} {{ number_format($inv->total_amount, 2) }}</div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding: 20px; text-align: center; color: var(--erp-muted);">> NO INVOICES DETECTED.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
            <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
                > FINANCIAL ESCROW & SETTLEMENTS
            </div>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Timestamp</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Amount</th>
                        <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($escrowLogs as $tx)
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">{{ \Carbon\Carbon::parse($tx->created_at)->setTimezone('Africa/Lagos')->format('Y-m-d H:i') }}</td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                            <div style="font-size: 0.85rem; font-weight: bold; color: var(--erp-white); font-family: 'Roboto Mono', monospace;">{{ $tx->currency ?? 'USD' }} {{ number_format($tx->amount_claimed, 2) }}</div>
                            <div style="font-size: 0.7rem; color: var(--erp-teal); font-family: 'Roboto Mono', monospace; margin-top: 4px;">{{ $tx->method }}</div>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right;">
                            <span style="color: {{ $tx->status === 'Verified' ? '#00FF66' : '#FFBD2E' }}; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; font-weight: bold;">{{ strtoupper($tx->status) }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding: 20px; text-align: center; color: var(--erp-muted);">> NO TRANSACTIONS DETECTED.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="background: rgba(10, 17, 40, 0.85); border: 1px solid var(--erp-border-dark); border-radius: 4px; padding: 30px;">
        <div style="font-family: 'Roboto Mono', monospace; font-size: 0.9rem; color: var(--erp-white); margin-bottom: 20px; border-bottom: 1px solid var(--erp-border-dark); padding-bottom: 15px;">
            > SYSTEM SECURITY & ACCESS LOGS
        </div>
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Timestamp (UTC)</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Identity (User / Email)</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase;">Action Executed</th>
                    <th style="font-family: 'Roboto Mono', monospace; font-size: 0.65rem; color: var(--erp-muted); padding: 12px; border-bottom: 1px solid var(--erp-border-dark); text-transform: uppercase; text-align: right;">IP Vector</th>
                </tr>
            </thead>
            <tbody>
                @forelse($systemLogs as $log)
                <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0, 229, 255, 0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">
                        {{ \Carbon\Carbon::parse($log->created_at)->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s') }}
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.8rem; color: var(--erp-white);">
                        {{ $log->user_email ?? 'SYSTEM_CRON / ANONYMOUS' }}
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                        <span style="background: rgba(255,255,255,0.05); border: 1px solid var(--erp-border-dark); padding: 3px 6px; font-family: 'Roboto Mono', monospace; font-size: 0.65rem; border-radius: 2px; color: var(--erp-white);">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); text-align: right; font-family: 'Roboto Mono', monospace; font-size: 0.75rem; color: var(--erp-muted);">
                        {{ $log->ip_address ?? '127.0.0.1' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="padding: 40px; text-align: center; font-family: 'Roboto Mono', monospace; font-size: 0.8rem; color: var(--erp-muted); border-bottom: 1px solid rgba(255,255,255,0.02);">
                        > NO SYSTEM EVENTS DETECTED.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>