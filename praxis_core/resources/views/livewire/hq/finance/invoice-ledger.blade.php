<div>
    @php
        // Stored statuses stay as they are; these are the labels people read
        $statusLabels = ['Pending' => 'Sent', 'Overdue' => 'Overdue', 'Paid' => 'Paid', 'Cancelled' => 'Cancelled'];
        $statusPills = ['Pending' => 'pill-info', 'Overdue' => 'pill-danger', 'Paid' => 'pill-success', 'Cancelled' => 'pill-neutral'];
        $emptyStates = [
            'Pending' => ['No sent invoices awaiting payment', 'Invoices you create appear here until the client pays them.'],
            'Overdue' => ['No overdue invoices', 'Unpaid invoices past their due date appear here.'],
            'Paid' => ['No paid invoices yet', 'An invoice moves here once its payment is verified.'],
            'Cancelled' => ['No cancelled invoices', 'Invoices you cancel are kept here as a record.'],
        ];
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Invoices</h1>
            <p class="page-subtitle">Create invoices for client projects and follow them until they’re paid.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @can('finance.approve')
            {{-- Confirmation dialog (docs/BRAND.md section 12): names the action and its consequence --}}
            <div x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button" @click="open = true" class="btn btn-secondary">
                    <i data-lucide="clock"></i> Mark overdue invoices
                </button>
                <div class="modal-backdrop" x-show="open" x-cloak style="display: none;" @click.self="open = false"
                     x-effect="if (open) $nextTick(() => $refs.keepOverdue.focus())">
                    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="overdue-confirm-title" style="max-width: 500px;">
                        <h2 id="overdue-confirm-title" class="modal-title">Mark overdue invoices?</h2>
                        <p class="text-small text-muted" style="margin: 0;">Unpaid invoices past their due date are marked as overdue, and those clients’ Client Portal access goes on hold until they pay.</p>
                        <div class="modal-actions">
                            <button type="button" x-ref="keepOverdue" @click="open = false" class="btn btn-secondary">Not now</button>
                            <button type="button" wire:click="enforceGuillotineProtocol" @click="open = false" class="btn btn-primary">Mark as overdue</button>
                        </div>
                    </div>
                </div>
            </div>
            @endcan
            @can('finance.create')
            <button type="button" wire:click="$set('showCreateModal', true)" class="btn btn-primary">
                <i data-lucide="plus"></i> Create invoice
            </button>
            @endcan
        </div>
    </div>


    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setTab('Pending')" class="tab {{ $activeTab === 'Pending' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'Pending' ? 'true' : 'false' }}">Sent</button>
        <button type="button" role="tab" wire:click="setTab('Overdue')" class="tab {{ $activeTab === 'Overdue' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'Overdue' ? 'true' : 'false' }}">Overdue</button>
        <button type="button" role="tab" wire:click="setTab('Paid')" class="tab {{ $activeTab === 'Paid' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'Paid' ? 'true' : 'false' }}">Paid</button>
        <button type="button" role="tab" wire:click="setTab('Cancelled')" class="tab {{ $activeTab === 'Cancelled' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'Cancelled' ? 'true' : 'false' }}">Cancelled</button>
    </div>

    <div class="card p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Project and client</th>
                        <th class="num text-right">Total</th>
                        <th>Status and due date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                        <tr>
                            <td>
                                <div class="num text-left font-semibold text-ink">{{ $inv->invoice_number }}</div>
                                <div class="help">{{ $inv->invoice_type }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-ink">{{ $inv->project_title }}</div>
                                <div class="help flex items-center gap-1"><i data-lucide="building-2" style="width: 14px; height: 14px;"></i> {{ $inv->client_name }}</div>
                            </td>
                            @php $paidSoFar = (float) ($settled[$inv->id] ?? 0); $partPaid = $paidSoFar > 0 && in_array($inv->status, ['Pending', 'Overdue'], true); @endphp
                            <td class="num">
                                <span class="font-semibold text-ink">{{ \App\Livewire\Hq\HqMoney::format($inv->currency, $inv->total_amount) }}</span>
                                @if ($partPaid)
                                    <div class="help" style="font-variant-numeric: tabular-nums;">Paid {{ \App\Livewire\Hq\HqMoney::format($inv->currency, $paidSoFar) }} · {{ \App\Livewire\Hq\HqMoney::format($inv->currency, max(0, (float) $inv->total_amount - $paidSoFar)) }} left</div>
                                @endif
                            </td>
                            <td>
                                <span class="pill {{ $statusPills[$inv->status] ?? 'pill-neutral' }}">{{ $partPaid && $inv->status === 'Pending' ? 'Part paid' : ($statusLabels[$inv->status] ?? $inv->status) }}</span>
                                <div class="help mt-1" style="font-variant-numeric: tabular-nums;">Due {{ \Carbon\Carbon::parse($inv->due_date)->format('j M Y') }}</div>
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('hq.finance.invoice-pdf', $inv->id) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                                        <i data-lucide="file-text"></i> Download PDF
                                    </a>

                                    @if(in_array($inv->status, ['Pending', 'Overdue'], true) && auth()->user()->can('finance.approve'))
                                        <button type="button" wire:click="openRecordPayment('{{ $inv->id }}')" class="btn btn-secondary btn-sm">
                                            <i data-lucide="banknote"></i> Record payment
                                        </button>
                                    @endif

                                    @if($inv->status !== 'Cancelled' && auth()->user()->can('finance.create'))
                                        <button type="button" wire:click="emailInvoice('{{ $inv->id }}')" wire:loading.attr="disabled" wire:target="emailInvoice('{{ $inv->id }}')" class="btn btn-ghost btn-sm"
                                                title="{{ $inv->emailed_at ? 'Emailed ' . \Carbon\Carbon::parse($inv->emailed_at)->setTimezone('Africa/Lagos')->format('j M Y, H:i') : 'Not emailed yet' }}">
                                            <i data-lucide="mail"></i> {{ $inv->emailed_at ? 'Email again' : 'Email to client' }}
                                        </button>
                                    @endif

                                    @if($inv->status === 'Pending' && auth()->user()->can('finance.delete'))
                                        <div x-data="{ open: false }" @keydown.escape.window="open = false">
                                            <button type="button" @click="open = true" class="btn btn-ghost btn-sm" style="color: var(--color-danger);">
                                                <i data-lucide="ban"></i> Cancel invoice
                                            </button>
                                            <div class="modal-backdrop" x-show="open" x-cloak style="display: none; text-align: left;" @click.self="open = false"
                                                 x-effect="if (open) $nextTick(() => $refs.keepInvoice.focus())">
                                                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cancel-invoice-title-{{ $inv->id }}" style="max-width: 500px; white-space: normal;">
                                                    <h2 id="cancel-invoice-title-{{ $inv->id }}" class="modal-title">Cancel invoice {{ $inv->invoice_number }}?</h2>
                                                    <p class="text-small text-muted" style="margin: 0;">The client will no longer be asked to pay it. This can’t be undone. The cancellation stays in the audit log.</p>
                                                    <div class="modal-actions">
                                                        <button type="button" x-ref="keepInvoice" @click="open = false" class="btn btn-secondary">Keep invoice</button>
                                                        <button type="button" wire:click="cancelInvoice('{{ $inv->id }}')" @click="open = false" class="btn btn-danger">Cancel invoice</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <strong>{{ $emptyStates[$activeTab][0] ?? 'No invoices here' }}</strong>
                                    {{ $emptyStates[$activeTab][1] ?? '' }}
                                    @if($activeTab === 'Pending')
                                        @can('finance.create')
                                            <div class="mt-4"><button type="button" wire:click="$set('showCreateModal', true)" class="btn btn-primary">Create invoice</button></div>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="p-4">{{ $invoices->links() }}</div>
        @endif
    </div>

    @if($payInvoice)
        <div class="modal-backdrop" wire:keydown.escape="closeRecordPayment">
            <div class="modal" style="max-width: 520px;" role="dialog" aria-modal="true" aria-labelledby="record-pay-title">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 id="record-pay-title" class="modal-title">Record a payment on {{ $payInvoice->invoice_number }}</h2>
                    <button type="button" wire:click="closeRecordPayment" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <form wire:submit.prevent="recordPayment" class="flex flex-col gap-4">
                    <p class="help" style="margin: 0;">Money that reached the account: a transfer, cash or a cheque. It counts as verified at once and goes to the general ledger. Part payments add up; the invoice is paid when they reach {{ \App\Livewire\Hq\HqMoney::format($payInvoice->currency, $payInvoice->total_amount) }}.</p>
                    <div class="flex flex-wrap gap-4">
                        <div class="field" style="flex: 1 1 12rem;">
                            <label class="label" for="pay-amt">Amount received ({{ $payInvoice->currency }})</label>
                            <input id="pay-amt" type="number" inputmode="decimal" step="0.01" min="0.01" wire:model="pay_amount" class="input" style="font-variant-numeric: tabular-nums;" required>
                            @error('pay_amount') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field" style="flex: 1 1 10rem;">
                            <label class="label" for="pay-date">Date received</label>
                            <input id="pay-date" type="date" wire:model="pay_received_on" class="input" max="{{ now('Africa/Lagos')->toDateString() }}" required>
                            @error('pay_received_on') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <div class="field" style="flex: 1 1 10rem;">
                            <label class="label" for="pay-method">How it was paid</label>
                            <select id="pay-method" wire:model="pay_method" class="select">
                                @foreach(\App\Support\Finance\InvoicePayments::METHODS as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                            @error('pay_method') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field" style="flex: 1 1 12rem;">
                            <label class="label" for="pay-ref">Reference</label>
                            <input id="pay-ref" type="text" maxlength="250" wire:model="pay_reference" class="input" placeholder="e.g. Nomba transfer ref 4410…" required>
                            @error('pay_reference') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="pay-wht">Withholding tax deducted by the client <span class="help">(optional)</span></label>
                        <input id="pay-wht" type="number" inputmode="decimal" step="0.01" min="0" wire:model="pay_wht" class="input" style="font-variant-numeric: tabular-nums;">
                        @error('pay_wht') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label class="label" for="pay-note">Note <span class="help">(optional)</span></label>
                        <input id="pay-note" type="text" maxlength="250" wire:model="pay_note" class="input" placeholder="e.g. First part, balance due at go-live">
                        @error('pay_note') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="modal-actions">
                        <button type="button" wire:click="closeRecordPayment" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="recordPayment">
                            <span wire:loading.remove wire:target="recordPayment">Record payment</span>
                            <span wire:loading wire:target="recordPayment">Recording…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showCreateModal)
        <div class="modal-backdrop">
            <div class="modal" style="max-width: 640px;" role="dialog" aria-modal="true" aria-labelledby="create-invoice-title">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 id="create-invoice-title" class="modal-title">Create invoice</h2>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                <form wire:submit.prevent="draftSubsequentInvoice" class="flex flex-col gap-4">

                    <div class="field">
                        <label class="label" for="inv-project">Project</label>
                        <select id="inv-project" wire:model.live="new_project_id" class="select" required>
                            <option value="">Choose a project</option>
                            @foreach($activeProjects as $proj)
                                <option value="{{ $proj->id }}">{{ $proj->client_name }}: {{ $proj->title }} ({{ $proj->currency }})</option>
                            @endforeach
                        </select>
                        @error('new_project_id') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    @php $milestoneCodes = collect($projectPhases)->mapWithKeys(fn ($p) => [(string) $p->id => \App\Support\Projects\ProjectPlan::code($p) . ' · ' . \App\Support\Projects\ProjectPlan::title($p)]); @endphp

                    <fieldset class="field" aria-describedby="inv-kind-help">
                        <legend class="label">What are you invoicing?</legend>
                        <div class="flex flex-wrap gap-4" style="margin-top: 0.25rem;">
                            <label class="flex items-center gap-2"><input type="radio" class="radio" wire:model.live="new_kind" value="invoice"> Milestones or work</label>
                            <label class="flex items-center gap-2"><input type="radio" class="radio" wire:model.live="new_kind" value="advance"> An advance on a milestone</label>
                        </div>
                        <span id="inv-kind-help" class="help">An advance bills part of a milestone before it is accepted. When you invoice that milestone, the advance is taken off by itself.</span>
                    </fieldset>

                    @if($new_kind === 'advance')
                        <div class="field">
                            <label class="label" for="inv-adv-ms">Milestone</label>
                            <select id="inv-adv-ms" wire:model.live="advance_phase_id" class="select">
                                <option value="">Choose the milestone</option>
                                @foreach($projectPhases as $phase)
                                    <option value="{{ $phase->id }}">{{ $milestoneCodes[(string) $phase->id] }} ({{ $new_currency }} {{ number_format((float) $phase->due, 2) }})</option>
                                @endforeach
                            </select>
                            @error('advance_phase_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="inv-adv-amt">Advance amount before VAT ({{ $new_currency }})</label>
                            <input id="inv-adv-amt" type="number" inputmode="decimal" step="0.01" min="0.01" wire:model.live.debounce.400ms="advance_amount" class="input" style="font-variant-numeric: tabular-nums;" aria-describedby="inv-adv-amt-help">
                            @if($advanceRoom !== null)
                                <span id="inv-adv-amt-help" class="help">Up to {{ $new_currency }} {{ number_format($advanceRoom, 2) }}: the milestone’s amount less any advance already on it.</span>
                            @endif
                            @error('advance_amount') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="inv-adv-desc">Line on the invoice</label>
                            <textarea id="inv-adv-desc" wire:model="advance_description" class="textarea" rows="2" maxlength="255"></textarea>
                            @error('advance_description') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    @else
                    @if(!empty($projectPhases) && count($projectPhases) > 0)
                        <fieldset class="field" aria-describedby="inv-ms-help">
                            <legend class="label">Milestones to invoice</legend>
                            <span id="inv-ms-help" class="help">Tick one or more. Each gets its own line below, with its agreed amount less any deposit share; you can change the wording and the amount.</span>
                            <div class="flex flex-col gap-2" style="margin-top: 0.5rem;">
                                @foreach($projectPhases as $phase)
                                    <label class="flex items-start gap-3" for="inv-ms-{{ $phase->id }}" wire:key="inv-ms-{{ $phase->id }}">
                                        <input id="inv-ms-{{ $phase->id }}" type="checkbox" class="checkbox" wire:model.live="picked" value="{{ $phase->id }}" style="margin-top: 0.2rem;">
                                        <span>
                                            <span style="display: block;">{{ $milestoneCodes[(string) $phase->id] }}</span>
                                            <span class="help">{{ __('projects.columns.' . $phase->column) }} · {{ $new_currency }} {{ number_format((float) $phase->due, 2) }}@if($phase->column !== 'accepted') · not accepted yet (the SOW invoices on written acceptance)@endif</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <div class="field">
                        <div class="flex items-center justify-between gap-3">
                            <span class="label" id="inv-lines-label">Lines</span>
                            <div class="flex gap-2">
                                <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm"><i data-lucide="plus" aria-hidden="true"></i> Add line</button>
                                <button type="button" wire:click="addDiscount" class="btn btn-secondary btn-sm"><i data-lucide="badge-percent" aria-hidden="true"></i> Add discount</button>
                            </div>
                        </div>
                        @forelse($lines as $i => $line)
                            <div wire:key="inv-line-{{ $i }}-{{ $line['phase_id'] ?? 'free' }}" style="display: grid; grid-template-columns: minmax(0, 1fr) 9.5rem auto; gap: 0.5rem; align-items: start; margin-top: 0.75rem;">
                                <div>
                                    @if(!empty($line['phase_id']))
                                        <span class="help" style="display: block;">{{ $milestoneCodes[(string) $line['phase_id']] ?? 'Milestone' }}</span>
                                    @elseif(($line['kind'] ?? 'charge') === 'discount')
                                        <span class="help" style="display: block;">Discount: taken off the total</span>
                                    @endif
                                    <label class="sr-only" for="inv-line-desc-{{ $i }}">Line {{ $i + 1 }}: description</label>
                                    <textarea id="inv-line-desc-{{ $i }}" wire:model="lines.{{ $i }}.description" class="textarea" rows="2" maxlength="255" placeholder="{{ ($line['kind'] ?? 'charge') === 'discount' ? 'e.g. Long-term client discount' : 'e.g. Change order: three extra reports' }}"></textarea>
                                    @error("lines.$i.description") <span class="field-error">{{ $message }}</span> @enderror
                                    @error("lines.$i.phase_id") <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="sr-only" for="inv-line-amt-{{ $i }}">Line {{ $i + 1 }}: amount before VAT ({{ $new_currency }})</label>
                                    <input id="inv-line-amt-{{ $i }}" type="number" inputmode="decimal" wire:model.live.debounce.400ms="lines.{{ $i }}.amount" step="0.01" min="0.01" class="input" style="font-variant-numeric: tabular-nums;" placeholder="Amount">
                                    @error("lines.$i.amount") <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <button type="button" wire:click="removeLine({{ $i }})" class="btn btn-ghost btn-sm" aria-label="Remove line {{ $i + 1 }}" title="Remove line {{ $i + 1 }}"><i data-lucide="trash-2" aria-hidden="true"></i></button>
                            </div>
                        @empty
                            <p class="help" style="margin-top: 0.5rem;">Tick a milestone above, or add a line.</p>
                        @endforelse
                        @foreach($deductions as $d)
                            <div class="flex items-start justify-between gap-3" style="margin-top: 0.75rem; font-variant-numeric: tabular-nums;">
                                <span>{{ $d['description'] }}<span class="help" style="display: block;">{{ $milestoneCodes[$d['phase_id']] ?? 'Milestone' }} · taken off by itself</span></span>
                                <span>{{ $new_currency }} {{ number_format($d['amount'], 2) }}</span>
                            </div>
                        @endforeach
                        @error('lines') <span class="field-error">{{ $message }}</span> @enderror
                        @if(count($lines) > 0)
                            <div class="flex items-center justify-between gap-3" style="margin-top: 0.75rem; font-variant-numeric: tabular-nums;">
                                <span>Total before VAT</span>
                                <strong>{{ $new_currency }} {{ number_format($linesTotal, 2) }}</strong>
                            </div>
                        @endif
                    </div>
                    @endif

                    <div class="field">
                        <label class="label" for="inv-terms">Payment terms</label>
                        <select id="inv-terms" wire:model="new_payment_terms" class="select" required>
                            <option value="Due on Receipt">Due on receipt</option>
                            <option value="Net 7">7 days</option>
                            <option value="Net 10">10 days</option>
                            <option value="Net 15">15 days</option>
                            <option value="Net 30">30 days</option>
                        </select>
                        <span class="help">Unpaid invoices past this date put the client’s portal access on hold.</span>
                    </div>

                    <div class="field">
                        <label class="label" for="inv-remit">Pay into account</label>
                        <select id="inv-remit" wire:model="new_remittance_id" class="select" required aria-describedby="inv-remit-help">
                            <option value="">Choose the receiving account</option>
                            @foreach($matchingAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->provider_name }}: {{ $acc->account_name }} ({{ $acc->currency }})</option>
                            @endforeach
                        </select>
                        @if($new_project_id && $matchingAccounts->isEmpty())
                            <span id="inv-remit-help" class="help">No active account in {{ $new_currency }} held by {{ $billingCompany ?? 'the company that bills this project' }}. Add one in <a href="{{ route('hq.mdm.remittance') }}" wire:navigate>Remittance accounts</a>, with its currency and company.</span>
                        @else
                            <span id="inv-remit-help" class="help">The client sees only accounts in the invoice’s currency held by the company that bills it.</span>
                        @endif
                        @error('new_remittance_id') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="callout text-sm">
                        {{ $vat ? \App\Support\Finance\Vat::describe($vat) : __('vat.invoice_help') }} {{ $vat ? \App\Support\Finance\Vat::note($vat) : '' }} If the invoice isn’t in USD, the exchange rate is fixed when you create it.
                    </div>

                    <div class="modal-actions">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="draftSubsequentInvoice">Create invoice</span>
                            <span wire:loading wire:target="draftSubsequentInvoice">Creating…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
