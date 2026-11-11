<div>
    @php
        $regionLabels = ['ALL' => 'All regions', 'NG' => 'Nigeria', 'CM' => 'Cameroon', 'GLOBAL' => 'Head office'];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">General ledger</h1>
            <p class="page-subtitle">Every journal entry posted to the IFRS accounts. Entries are never deleted; a mistake is corrected with a reversing entry.</p>
        </div>
    </div>


    <div class="card mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="field min-w-[150px] flex-1">
                <label class="label" for="gl-start">From</label>
                <input id="gl-start" type="date" wire:model="startDate" class="input">
            </div>
            <div class="field min-w-[150px] flex-1">
                <label class="label" for="gl-end">To</label>
                <input id="gl-end" type="date" wire:model="endDate" class="input">
            </div>
            <div class="field min-w-[150px] flex-1">
                <label class="label" for="gl-region">Region</label>
                <select id="gl-region" wire:model="regionFilter" class="select">
                    <option value="ALL">All regions</option>
                    <option value="NG">JBS Praxis Nigeria</option>
                    <option value="CM">JBS Praxis Cameroon</option>
                    <option value="GLOBAL">Head office</option>
                </select>
            </div>
            <button type="button" wire:click="filterLedger" class="btn btn-primary">
                <i data-lucide="filter"></i> Show entries
            </button>
        </div>
    </div>

    <div class="flex flex-col gap-6">
        @forelse($entries as $entry)
            @php
                $isReversingEntry = $entry->is_reversal && $entry->reverses_entry_id;
                $isReversed = $entry->is_reversal && ! $entry->reverses_entry_id;
            @endphp
            <article class="card p-0" @if($isReversingEntry || $isReversed) style="box-shadow: inset 3px 0 0 {{ $isReversed ? 'var(--color-danger)' : 'var(--color-warning)' }};" @endif>
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line px-6 py-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="card-title" style="font-variant-numeric: tabular-nums;">{{ $entry->reference_number }}</h2>
                            @if($isReversed)
                                <span class="pill pill-danger">Reversed</span>
                            @elseif($isReversingEntry)
                                <span class="pill pill-warning">Reversing entry</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm muted">{{ $entry->description }}</p>
                    </div>

                    <div class="text-right text-sm">
                        <div class="font-medium text-ink" style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($entry->entry_date)->format('j M Y') }}</div>
                        <div class="help">{{ $entry->source_module }} · {{ $regionLabels[$entry->region_code] ?? $entry->region_code }}</div>

                        @if(!$entry->is_reversal && auth()->user()->can('finance.delete'))
                            <button type="button" wire:click="triggerReversal('{{ $entry->id }}')" class="btn btn-ghost btn-sm mt-2" style="color: var(--color-danger);">
                                <i data-lucide="rotate-ccw"></i> Reverse entry
                            </button>
                        @endif
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 60%;">Account and memo</th>
                                <th class="num text-right" style="width: 20%;">Debit</th>
                                <th class="num text-right" style="width: 20%;">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalDebit = 0; $totalCredit = 0;
                            @endphp

                            @foreach($entry->lines as $line)
                                @php
                                    $totalDebit += $line->debit_amount;
                                    $totalCredit += $line->credit_amount;

                                    // Accounting convention: credit lines are indented
                                    $isCredit = $line->credit_amount > 0;
                                @endphp
                                <tr>
                                    <td style="padding-left: {{ $isCredit ? '40px' : '12px' }};">
                                        <div>
                                            <span class="muted mr-2 inline-block" style="min-width: 80px; font-variant-numeric: tabular-nums;">{{ $line->account_code }}</span>
                                            <span class="font-medium text-ink">{{ $line->account_name }}</span>
                                        </div>

                                        @if($line->memo)
                                            <div class="help mt-1">{{ $line->memo }}</div>
                                        @endif
                                    </td>

                                    <td class="num">
                                        @if($line->debit_amount > 0)
                                            <span class="text-ink">{{ number_format($line->debit_amount, 2) }}</span>
                                        @endif
                                    </td>

                                    <td class="num">
                                        @if($line->credit_amount > 0)
                                            <span class="text-ink">{{ number_format($line->credit_amount, 2) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            <tr>
                                <td class="text-right font-semibold text-ink" style="border-top: 1px solid var(--color-line-strong);">Total</td>
                                <td class="num font-semibold text-ink" style="border-top: 1px solid var(--color-line-strong); border-bottom: 3px double var(--color-line-strong);">
                                    {{ number_format($totalDebit, 2) }}
                                </td>
                                <td class="num font-semibold text-ink" style="border-top: 1px solid var(--color-line-strong); border-bottom: 3px double var(--color-line-strong);">
                                    {{ number_format($totalCredit, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="card">
                <div class="empty-state">
                    <strong>No journal entries in this period</strong>
                    Entries are posted here when invoices are issued and payments are verified. Change the dates or region to see other entries.
                </div>
            </div>
        @endforelse
        @if($entries->hasPages())
            <div>{{ $entries->links() }}</div>
        @endif
    </div>

    @if($showReversalModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="reverse-title">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 id="reverse-title" class="modal-title">Reverse this journal entry?</h2>
                    <button type="button" wire:click="$set('showReversalModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                <div class="alert alert-warning mb-4" role="note">
                    <i data-lucide="triangle-alert"></i>
                    <span>Journal entries can’t be deleted under IFRS. Reversing posts a new entry that swaps the debits and credits, so the original no longer counts. This can’t be undone.</span>
                </div>

                <form wire:submit.prevent="executeReversingEntry" class="flex flex-col gap-4">
                    <div class="field">
                        <label class="label" for="rev-reason">Reason for the reversal</label>
                        <textarea id="rev-reason" wire:model="reversalReason" class="textarea" rows="3" placeholder="e.g. Posted to the wrong client account" required minlength="10"></textarea>
                        <span class="help">At least 10 characters. Auditors read this in the audit trail.</span>
                        @error('reversalReason') <span class="field-error">{{ $message }}</span> @enderror
                        @error('reversalEntryId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="modal-actions">
                        <button type="button" wire:click="$set('showReversalModal', false)" class="btn btn-secondary">Keep entry</button>
                        <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="executeReversingEntry">Reverse entry</span>
                            <span wire:loading wire:target="executeReversingEntry">Posting…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
