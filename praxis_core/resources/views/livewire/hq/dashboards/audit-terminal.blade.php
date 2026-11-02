<div>
    @php
        // Display labels for stored values (docs/BRAND.md section 12); the stored values stay as they are.
        $invoiceStatus = fn ($s) => match ($s) {
            'Paid' => ['Paid', 'pill-success'],
            'Pending' => ['Sent', 'pill-info'],
            'Overdue' => ['Overdue', 'pill-danger'],
            'Draft' => ['Draft', 'pill-neutral'],
            default => [\Illuminate\Support\Str::ucfirst(strtolower((string) $s)), 'pill-neutral'],
        };
        $paymentStatus = fn ($s) => match ($s) {
            'Verified' => ['Verified', 'pill-success'],
            'Pending Verification' => ['Awaiting verification', 'pill-warning'],
            'Rejected', 'Not Verified' => ['Not verified', 'pill-danger'],
            default => [\Illuminate\Support\Str::ucfirst(strtolower((string) $s)), 'pill-neutral'],
        };
        // Stored audit action codes => plain labels; unknown codes are shown in sentence case
        $actionLabels = [
            'AUTH_LOGIN' => 'Signed in', 'AUTH_INIT_KEY' => 'Account set up',
            'SUBMITTED_CASH_REQUEST' => 'Cash request submitted', 'AUTHORIZED_CASH_REQUEST' => 'Cash request approved',
            'ESCROW_PAYMENT_VERIFIED' => 'Payment verified', 'ESCROW_PAYMENT_REJECTED' => 'Payment not verified',
            'INVOICE_CANCELLED' => 'Invoice cancelled', 'IFRS_REVERSING_ENTRY_POSTED' => 'IFRS reversing entry posted',
            'GUILLOTINE_PROTOCOL_ENGAGED' => 'Access put on hold: unpaid invoice', 'BUDGET_SWEEP_EXECUTED' => 'Budget sweep run',
            'BACKLOG_ENGINEERED' => 'Project backlog created', 'EXPORT_GLOBAL_TELEMETRY' => 'Company overview downloaded',
            'EXPORT_REPORT' => 'Invoices downloaded', 'GENERATED_SHAREHOLDER_PDF' => 'Investor report printed',
            'ASSET_REGISTERED' => 'Social account added', 'CREDENTIAL_UPDATED' => 'Social account password changed',
        ];
        $actionLabel = fn ($a) => $actionLabels[$a] ?? \Illuminate\Support\Str::ucfirst(strtolower(str_replace('_', ' ', (string) $a)));
        // Stored payment methods => sentence-case labels
        $methodLabels = ['Bank Transfer' => 'Bank transfer', 'Mobile Money' => 'Mobile money', 'Campay' => 'Mobile money (Campay)', 'Crypto Wallet' => 'Crypto wallet'];
        $methodLabel = fn ($m) => $methodLabels[$m] ?? $m;
        // Amounts with their currency (docs/BRAND.md section 4): ₦ and $ before the amount, FCFA after it
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount);
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Audit log</h1>
            <p class="page-subtitle">Invoices, payment verifications and activity in HQ. This page is read-only.</p>
        </div>
        @canany(['audit.export', 'finance.export'])
        <button type="button" wire:click="exportBooks" wire:loading.attr="disabled" wire:target="exportBooks" class="btn btn-secondary">
            <i data-lucide="download"></i>
            <span wire:loading.remove wire:target="exportBooks">Download invoices (CSV)</span>
            <span wire:loading wire:target="exportBooks">Preparing file…</span>
        </button>
        @endcanany
    </div>

    <nav class="tabs" aria-label="Overviews">
        @can('dashboard.view')
            <a href="{{ route('hq.dash.admin') }}" wire:navigate class="tab">Company overview</a>
        @endcan
        <a href="{{ route('hq.dash.workspace') }}" wire:navigate class="tab">My workspace</a>
        @can('dashboard.view')
            <a href="{{ route('hq.dash.investor') }}" wire:navigate class="tab">Investor view</a>
        @endcan
        <a href="{{ route('hq.dash.audit') }}" wire:navigate class="tab is-active" aria-current="page">Audit log</a>
    </nav>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4" style="margin-bottom: 24px;">
        <div class="card">
            <div class="stat-label">Revenue received, {{ now()->year }}</div>
            <x-hq.money-totals :totals="$kpis['ytd_cleared_revenue']" />
        </div>
        <div class="card">
            <div class="stat-label">VAT on paid invoices, {{ now()->year }}</div>
            <x-hq.money-totals :totals="$kpis['ytd_vat_liability']" />
        </div>
        <div class="card">
            <div class="stat-label">Payments verified, {{ now()->year }}</div>
            <div class="stat-value">{{ number_format($kpis['verified_transactions']) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Payments awaiting verification</div>
            <div class="stat-value">{{ number_format($kpis['pending_escrow']) }}</div>
        </div>
        <div class="card">
            <div class="stat-label">Logged events, {{ now()->year }}</div>
            <div class="stat-value">{{ number_format($kpis['total_system_events']) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6" style="margin-bottom: 24px;">
        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Latest invoices</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th class="num" style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoiceLedger as $inv)
                        @php [$invText, $invPill] = $invoiceStatus($inv->status); @endphp
                        <tr>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-ink" style="font-variant-numeric: tabular-nums;">{{ $inv->invoice_number }}</span>
                                    <span class="pill {{ $invPill }}">{{ $invText }}</span>
                                </div>
                                <div class="text-caption text-muted" style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($inv->created_at)->format('j M Y') }}</div>
                            </td>
                            <td>{{ $inv->client_name }}</td>
                            <td class="num text-ink">{{ $money($inv->currency, $inv->total_amount) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3"><div class="empty-state">No invoices yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Payment verifications</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="num" style="text-align: right;">Amount</th>
                            <th style="text-align: right;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($escrowLogs as $tx)
                        @php [$txText, $txPill] = $paymentStatus($tx->status); @endphp
                        <tr>
                            <td style="font-variant-numeric: tabular-nums; white-space: nowrap;">{{ \Carbon\Carbon::parse($tx->created_at)->setTimezone('Africa/Lagos')->format('j M Y, H:i') }}</td>
                            <td class="num">
                                <div class="text-ink font-semibold">{{ $money($tx->currency ?? 'USD', $tx->amount_claimed) }}</div>
                                <div class="text-caption text-muted">{{ $methodLabel($tx->method) }}</div>
                            </td>
                            <td style="text-align: right;"><span class="pill {{ $txPill }}">{{ $txText }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="3"><div class="empty-state">No payments to verify yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="card" style="padding: 0;">
        <div class="card-header" style="padding: 20px 24px 0;">
            <h2 class="card-title">Activity log</h2>
            @php $eventCount = count($systemLogs); @endphp
            @if ($eventCount > 0)
                <span class="help">{{ $eventCount === 1 ? 'The latest event' : 'The latest ' . $eventCount . ' events' }}</span>
            @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time (WAT)</th>
                        <th>Who</th>
                        <th>Action</th>
                        <th style="text-align: right;">IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($systemLogs as $log)
                    <tr>
                        <td style="font-variant-numeric: tabular-nums; white-space: nowrap;">{{ \Carbon\Carbon::parse($log->created_at, config('app.timezone'))->setTimezone('Africa/Lagos')->format('j M Y, H:i:s') }}</td>
                        <td class="text-ink">{{ $log->user_email ?? 'System (no one signed in)' }}</td>
                        <td>{{ $actionLabel($log->action) }}</td>
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">{{ $log->ip_address ?? 'Not recorded' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4"><div class="empty-state">No activity logged yet.</div></td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
