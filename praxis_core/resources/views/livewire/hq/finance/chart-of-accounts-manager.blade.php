<div>
    @php
        $typeHeadings = ['Asset' => 'Assets', 'Liability' => 'Liabilities', 'Equity' => 'Equity', 'Revenue' => 'Revenue', 'Expense' => 'Expenses'];
        $hasAccounts = collect($accounts)->flatten(1)->isNotEmpty();
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Chart of accounts</h1>
            <p class="page-subtitle">The IFRS accounts the general ledger posts to, grouped by type.</p>
        </div>
        @can('finance.create')
        <button type="button" wire:click="$set('showModal', true)" class="btn btn-primary">
            <i data-lucide="plus"></i> Add account
        </button>
        @endcan
    </div>


    @if(! $hasAccounts)
        <div class="card">
            <div class="empty-state">
                <strong>No accounts yet</strong>
                The chart of accounts lists every account the general ledger posts to. Add the first account to start.
            </div>
        </div>
    @endif

    <div class="flex flex-col gap-6">
    @foreach(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'] as $type)
        @if(isset($accounts[$type]) && count($accounts[$type]) > 0)
            <section class="card p-0">
                <div class="card-header mb-0 border-b border-line px-6 py-4">
                    <h2 class="card-title">{{ $typeHeadings[$type] }}</h2>
                    <span class="help" style="font-variant-numeric: tabular-nums;">{{ count($accounts[$type]) }} {{ count($accounts[$type]) === 1 ? 'account' : 'accounts' }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 160px;">Code</th>
                                <th>Account name</th>
                                <th style="width: 200px;">Status</th>
                                <th class="text-right" style="width: 180px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts[$type] as $acc)
                                <tr>
                                    <td style="font-variant-numeric: tabular-nums;">
                                        @if(is_null($acc->parent_account_id))
                                            <strong class="text-ink">{{ $acc->account_code }}</strong>
                                        @else
                                            <span class="pl-6">{{ $acc->account_code }}</span>
                                        @endif
                                    </td>

                                    <td class="{{ is_null($acc->parent_account_id) ? 'font-semibold text-ink' : '' }}">
                                        @if(!is_null($acc->parent_account_id))
                                            <i data-lucide="corner-down-right" class="muted" style="width: 14px; height: 14px; display: inline-block; vertical-align: -2px; margin-right: 6px;"></i>
                                        @endif
                                        {{ $acc->account_name }}

                                        @if($acc->client_id)
                                            <span class="pill pill-info ml-2">Client account</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            @if($acc->is_active)
                                                <span class="pill pill-success">Active</span>
                                            @else
                                                <span class="pill pill-neutral">Inactive</span>
                                            @endif

                                            @if($acc->is_system_locked)
                                                <span class="pill pill-neutral" title="Built into the system; can’t be deactivated">Built in</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="text-right">
                                        @if(!$acc->is_system_locked)
                                            @can('finance.edit')
                                            <button type="button" wire:click="toggleStatus({{ $acc->id }})" class="btn btn-sm {{ $acc->is_active ? 'btn-ghost' : 'btn-secondary' }}" @if($acc->is_active) style="color: var(--color-danger);" @endif>
                                                <i data-lucide="power"></i>
                                                {{ $acc->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                            @endcan
                                        @else
                                            <span class="help">No changes allowed</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endforeach
    </div>

    @if($showModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="add-account-title">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 id="add-account-title" class="modal-title">Add account</h2>
                    <button type="button" wire:click="$set('showModal', false)" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>

                <form wire:submit.prevent="saveAccount" class="flex flex-col gap-4">

                    <div class="field">
                        <label class="label" for="coa-parent">Parent account</label>
                        <select id="coa-parent" wire:model.live="parent_account_id" class="select">
                            <option value="">None: a new top-level account (administrators only)</option>
                            @foreach($parentAccounts as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->account_code }}: {{ $parent->account_name }} ({{ $parent->account_type }})</option>
                            @endforeach
                        </select>
                        <span class="help">The new account gets the next code under its parent.</span>
                    </div>

                    <div class="field">
                        <label class="label" for="coa-type">Account type</label>
                        <select id="coa-type" wire:model="account_type" class="select" {{ !empty($parent_account_id) ? 'disabled' : '' }}>
                            <option value="Asset">Asset (1000)</option>
                            <option value="Liability">Liability (2000)</option>
                            <option value="Equity">Equity (3000)</option>
                            <option value="Revenue">Revenue (4000)</option>
                            <option value="Expense">Expense (5000 and up)</option>
                        </select>
                        @if(!empty($parent_account_id)) <span class="help">Taken from the parent account.</span> @endif
                        @error('account_type') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label class="label" for="coa-name">Account name</label>
                        <input id="coa-name" type="text" wire:model="account_name" class="input" placeholder="e.g. Acme Bank operating account" required>
                        @error('account_name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="modal-actions">
                        <button type="button" wire:click="$set('showModal', false)" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveAccount">Add account</span>
                            <span wire:loading wire:target="saveAccount">Adding…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
