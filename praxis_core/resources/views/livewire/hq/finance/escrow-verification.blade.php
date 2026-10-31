<div>
    @php
        // Stored statuses stay as they are; these are the labels people read
        $statusLabels = ['Pending Verification' => 'Awaiting verification', 'Verified' => 'Verified', 'Rejected' => 'Not verified'];
        $statusPills = ['Pending Verification' => 'pill-warning', 'Verified' => 'pill-success', 'Rejected' => 'pill-danger'];
        $emptyStates = [
            'Pending Verification' => ['No payments awaiting verification', 'When a client uploads proof of payment in their Client Portal, it appears here for you to verify.'],
            'Verified' => ['No verified payments yet', 'Payments you verify are kept here with their proof.'],
            'Rejected' => ['No payments marked as not verified', 'Payments you don’t verify are kept here as a record.'],
        ];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Payment verification</h1>
            <p class="page-subtitle">Check each proof of payment against its invoice, then verify it so the invoice is marked as paid and posted to the general ledger.</p>
        </div>
    </div>

    <x-flash :tones="['error' => 'warning']" class="mb-6" />

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setTab('Pending Verification')" class="tab {{ $activeTab === 'Pending Verification' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'Pending Verification' ? 'true' : 'false' }}">Awaiting verification</button>
        <button type="button" role="tab" wire:click="setTab('Verified')" class="tab {{ $activeTab === 'Verified' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'Verified' ? 'true' : 'false' }}">Verified</button>
        <button type="button" role="tab" wire:click="setTab('Rejected')" class="tab {{ $activeTab === 'Rejected' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'Rejected' ? 'true' : 'false' }}">Not verified</button>
    </div>

    <div class="card p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Received and reference</th>
                        <th>Client and invoice</th>
                        <th class="num text-right">Amount paid</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td>
                                <div class="text-ink" style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($tx->created_at)->format('j M, H:i') }}</div>
                                <div class="help break-all" style="font-variant-numeric: tabular-nums;">{{ $tx->method }} · {{ Str::limit($tx->tx_hash_or_ref, 15) }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-ink">{{ $tx->client_name }}</div>
                                <div class="help" style="font-variant-numeric: tabular-nums;">Invoice {{ $tx->invoice_number }}</div>
                            </td>
                            <td class="num">
                                <div class="font-semibold text-ink">{{ $tx->currency }} {{ number_format($tx->amount_claimed, 2) }}</div>
                                @if($tx->wht_deducted > 0)
                                    <div class="help">+ withholding tax {{ number_format($tx->wht_deducted, 2) }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="pill {{ $statusPills[$tx->status] ?? 'pill-neutral' }}">{{ $statusLabels[$tx->status] ?? $tx->status }}</span>
                            </td>
                            <td class="text-right">
                                @if($tx->status === 'Pending Verification')
                                    <button type="button" wire:click="openVerifyModal('{{ $tx->id }}')" class="btn btn-secondary btn-sm">
                                        <i data-lucide="shield-check"></i> Check payment
                                    </button>
                                @else
                                    <button type="button" wire:click="openVerifyModal('{{ $tx->id }}')" class="btn btn-ghost btn-sm">
                                        <i data-lucide="eye"></i> View
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <strong>{{ $emptyStates[$activeTab][0] ?? 'No payments here' }}</strong>
                                    {{ $emptyStates[$activeTab][1] ?? '' }}
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showVerifyModal && $selectedTx && $linkedInvoice)
        <div class="modal-backdrop">
            <div class="modal" style="max-width: 900px;" role="dialog" aria-modal="true" aria-labelledby="verify-title">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 id="verify-title" class="modal-title">Payment for invoice {{ $linkedInvoice->invoice_number }}</h2>
                    <button type="button" wire:click="$set('showVerifyModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-lg border border-line bg-mist p-5">
                        <h3 class="label mb-3">Invoice and payment</h3>
                        @php
                            $shortfall = $linkedInvoice->total_amount - ($selectedTx->amount_claimed + $selectedTx->wht_deducted);
                        @endphp
                        <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-2 text-sm" style="font-variant-numeric: tabular-nums;">
                            <dt class="muted">Invoice</dt>
                            <dd class="text-right font-medium text-ink">{{ $linkedInvoice->invoice_number }}</dd>
                            <dt class="muted">Invoice total</dt>
                            <dd class="text-right font-medium text-ink">{{ $linkedInvoice->currency }} {{ number_format($linkedInvoice->total_amount, 2) }}</dd>
                            <dt class="muted">Amount paid (per proof)</dt>
                            <dd class="text-right font-medium text-ink">{{ $selectedTx->currency }} {{ number_format($selectedTx->amount_claimed, 2) }}</dd>
                            @if($selectedTx->wht_deducted > 0)
                                <dt class="muted">Withholding tax</dt>
                                <dd class="text-right font-medium text-ink">+ {{ number_format($selectedTx->wht_deducted, 2) }}</dd>
                            @endif
                            <dt class="muted border-t border-line pt-2">{{ $shortfall > 0 ? 'Short by' : 'Over by' }}</dt>
                            <dd class="border-t border-line pt-2 text-right font-semibold" style="color: {{ $shortfall > 0 ? 'var(--color-danger)' : 'var(--color-success)' }};">
                                {{ $shortfall > 0 ? '−' : '+' }}{{ number_format(abs($shortfall), 2) }}
                            </dd>
                        </dl>

                        <h3 class="label mb-2 mt-5">Proof</h3>
                        <div class="flex flex-col items-start gap-2">
                            @if($selectedTx->proof_file_path)
                                <a href="{{ route('hq.file', ['path' => $selectedTx->proof_file_path]) }}" target="_blank" class="btn btn-secondary btn-sm">
                                    <i data-lucide="external-link"></i> View proof of payment
                                </a>
                            @endif

                            @if($selectedTx->wht_deducted > 0 && $selectedTx->wht_proof_file_path)
                                <a href="{{ route('hq.file', ['path' => $selectedTx->wht_proof_file_path]) }}" target="_blank" class="btn btn-secondary btn-sm">
                                    <i data-lucide="external-link"></i> View withholding tax certificate
                                </a>
                            @endif

                            <div class="w-full rounded-lg border border-line bg-paper p-3 text-sm">
                                <div class="help">Payment reference</div>
                                <div class="break-all text-ink" style="font-variant-numeric: tabular-nums;">{{ $selectedTx->tx_hash_or_ref }}</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="label mb-3">Decision</h3>

                        @if($selectedTx->status === 'Pending Verification')
                            <form wire:submit.prevent="approveTransaction" class="flex flex-col gap-4">

                                <div class="field">
                                    <label class="label" for="pv-writeoff">Fee write-off ({{ $linkedInvoice->currency }})</label>
                                    <input id="pv-writeoff" type="number" wire:model="tolerance_writeoff" step="0.01" min="0" class="input" style="font-variant-numeric: tabular-nums;">
                                    <span class="help">If the client paid slightly less because of bank or network fees, enter the shortfall to write off as an expense. Suggested automatically when it is 50 or less.</span>
                                    @error('tolerance_writeoff') <span class="field-error">{{ $message }}</span> @enderror
                                </div>

                                <div class="field">
                                    <label class="label" for="pv-extend">Extra days to pay the balance</label>
                                    <input id="pv-extend" type="number" wire:model="extend_guillotine_days" step="1" min="0" class="input" style="font-variant-numeric: tabular-nums;">
                                    <span class="help">For a part payment only. Moves the due date and lifts any hold on the client’s portal access. Enter 0 to keep the due date.</span>
                                    @error('extend_guillotine_days') <span class="field-error">{{ $message }}</span> @enderror
                                </div>

                                @can('finance.approve')
                                <div class="modal-actions">
                                    <button type="button" wire:click="rejectTransaction" class="btn btn-secondary" onclick="confirm('Mark this payment as not verified? The invoice stays unpaid, and this can’t be undone.') || event.stopImmediatePropagation()">
                                        <i data-lucide="ban"></i> Don’t verify
                                    </button>
                                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="approveTransaction" class="inline-flex items-center gap-2"><i data-lucide="check"></i> Verify payment</span>
                                        <span wire:loading wire:target="approveTransaction">Verifying…</span>
                                    </button>
                                </div>
                                @endcan
                            </form>
                        @else
                            <div class="rounded-lg border border-line p-5 text-center">
                                <span class="pill {{ $statusPills[$selectedTx->status] ?? 'pill-neutral' }}">{{ $statusLabels[$selectedTx->status] ?? $selectedTx->status }}</span>
                                <p class="help mt-3" style="font-variant-numeric: tabular-nums;">
                                    Decided on {{ \Carbon\Carbon::parse($selectedTx->verified_at)->format('j M Y, H:i') }}
                                </p>
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
