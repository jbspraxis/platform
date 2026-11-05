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
                            <td class="num">
                                <span class="font-semibold text-ink">{{ \App\Livewire\Hq\HqMoney::format($inv->currency, $inv->total_amount) }}</span>
                            </td>
                            <td>
                                <span class="pill {{ $statusPills[$inv->status] ?? 'pill-neutral' }}">{{ $statusLabels[$inv->status] ?? $inv->status }}</span>
                                <div class="help mt-1" style="font-variant-numeric: tabular-nums;">Due {{ \Carbon\Carbon::parse($inv->due_date)->format('j M Y') }}</div>
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('hq.finance.invoice-pdf', $inv->id) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                                        <i data-lucide="file-text"></i> Download PDF
                                    </a>

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
    </div>

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

                    @if(!empty($projectPhases) && count($projectPhases) > 0)
                        <div class="field">
                            <label class="label" for="inv-phase">Phase (optional)</label>
                            <select id="inv-phase" wire:model="new_phase_id" class="select">
                                <option value="">Whole project</option>
                                @foreach($projectPhases as $phase)
                                    <option value="{{ $phase->id }}">Phase {{ $phase->phase_number }}: {{ $phase->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="field">
                            <label class="label" for="inv-amount">Amount before VAT ({{ $new_currency }})</label>
                            <input id="inv-amount" type="number" wire:model="new_amount_due" step="0.01" min="0.01" class="input" style="font-variant-numeric: tabular-nums;" required>
                            @error('new_amount_due') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
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

                    <div class="field">
                        <label class="label" for="inv-desc">Description</label>
                        <textarea id="inv-desc" wire:model="new_description" class="textarea" rows="2" placeholder="e.g. Phase 2 sign-off" required></textarea>
                        @error('new_description') <span class="field-error">{{ $message }}</span> @enderror
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
