<div>
    @php
        // Display labels for the stored account types (the stored values stay as they are)
        $typeLabels = ['Bank' => 'Bank', 'Mobile Money' => 'Mobile money', 'Crypto' => 'Crypto wallet', 'Cash' => 'Cash'];
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="remittance-saved-title">
                <h2 id="remittance-saved-title" class="modal-title">Account saved</h2>
                <p class="muted">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Remittance accounts</h1>
            <p class="page-subtitle">The bank, mobile money, crypto and cash accounts JBS Praxis receives and pays money through. Each one has its own account in the chart of accounts.</p>
        </div>
    </div>

    @include('livewire.hq.mdm.partials.tabs')

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ $editMode ? 'Edit account' : 'Add an account' }}</h2>
                @if($editMode) <span class="pill pill-warning">Editing</span> @endif
            </div>

            <form wire:submit.prevent="saveAccount" class="flex flex-col gap-4">

                <div class="field">
                    <label class="label" for="ra-type">Account type</label>
                    <select id="ra-type" wire:model.live="type" class="select">
                        <option value="Bank">Bank</option>
                        <option value="Mobile Money">Mobile money</option>
                        <option value="Crypto">Crypto wallet</option>
                        <option value="Cash">Cash</option>
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="ra-provider">{{ $type === 'Crypto' ? 'Exchange or wallet provider' : 'Bank or provider name' }}</label>
                    <input id="ra-provider" type="text" wire:model="provider_name" required class="input">
                </div>

                <div class="field">
                    <label class="label" for="ra-name">Account name</label>
                    <input id="ra-name" type="text" wire:model="account_name" required placeholder="e.g. JBS Praxis LLC" class="input">
                </div>

                <div class="field">
                    <label class="label" for="ra-reference">{{ $type === 'Crypto' ? 'Public wallet address' : 'Account number or IBAN' }}</label>
                    <input id="ra-reference" type="text" wire:model="account_reference" required class="input" style="font-variant-numeric: tabular-nums;">
                    @error('account_reference') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label class="label" for="ra-routing">{{ $type === 'Crypto' ? 'Network (optional)' : ($type === 'Bank' ? 'SWIFT or routing number (optional)' : 'Country code or short code (optional)') }}</label>
                    <input id="ra-routing" type="text" wire:model="routing_code" @if($type === 'Crypto') placeholder="e.g. TRC20, ERC20" @endif class="input">
                </div>

                <div class="flex flex-wrap gap-3" style="margin-top: 8px;">
                    <button type="submit" class="btn btn-primary" style="flex: 2;">
                        {{ $editMode ? 'Save changes' : 'Add account' }}
                    </button>
                    @if($editMode)
                        <button type="button" wire:click="resetForm" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrap" style="max-height: 65vh; overflow-y: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 160px;">Provider and type</th>
                            <th style="min-width: 200px;">Account</th>
                            <th>Account code</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $acc)
                        <tr>
                            <td>
                                <div class="text-ink" style="font-weight: 600;">{{ $acc->provider_name }}</div>
                                <span class="pill pill-neutral" style="margin-top: 4px;">{{ $typeLabels[$acc->type] ?? $acc->type }}</span>
                            </td>
                            <td>
                                <div class="text-ink" style="font-variant-numeric: tabular-nums; font-weight: 600; word-break: break-all;">{{ $acc->account_reference }}</div>
                                <div class="help" style="margin-top: 2px;">
                                    {{ $acc->account_name }}@if($acc->routing_code)<span style="font-variant-numeric: tabular-nums;"> · {{ $acc->routing_code }}</span>@endif
                                </div>
                            </td>
                            <td>
                                @if($acc->ledger_code)
                                    <span class="text-ink" style="font-variant-numeric: tabular-nums; font-weight: 600;">{{ $acc->ledger_code }}</span>
                                @else
                                    <span class="pill pill-danger">Not linked</span>
                                @endif
                            </td>
                            <td>
                                @if($acc->is_active)
                                    <span class="pill pill-success">Active</span>
                                @else
                                    <span class="pill pill-neutral">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-col items-end gap-1">
                                    <button type="button" wire:click="editAccount({{ $acc->id }})" class="btn btn-ghost btn-sm">Edit</button>
                                    <button type="button" wire:click="toggleStatus({{ $acc->id }}, {{ $acc->is_active }})" class="btn btn-ghost btn-sm" @if($acc->is_active) style="color: var(--color-danger);" @endif>
                                        {{ $acc->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <strong>No remittance accounts yet</strong>
                                    Add the first bank, mobile money, crypto or cash account with the form. Its account in the chart of accounts is created for you.
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
