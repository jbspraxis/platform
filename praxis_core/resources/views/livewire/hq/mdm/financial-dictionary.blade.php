<div>
    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dictionary-saved-title">
                <h2 id="dictionary-saved-title" class="modal-title">Item saved</h2>
                <p class="muted">Item <strong class="text-ink" style="font-variant-numeric: tabular-nums;">{{ $item_code }}</strong> is saved in the financial dictionary and can now be used on proposals, project budgets and cash requests.</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Financial dictionary</h1>
            <p class="page-subtitle">The standard items JBS Praxis sells and buys, with their base rate and the income and expense accounts they post to.</p>
        </div>
    </div>

    @include('livewire.hq.mdm.partials.tabs')

    <x-flash class="mb-6" />

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">

        <div class="card" style="position: relative;">

            <div wire:loading.flex wire:target="saveCode" class="flex-col items-center justify-center gap-3" style="position: absolute; inset: 0; z-index: 5; background: rgba(255, 255, 255, 0.85); border-radius: inherit;">
                <i data-lucide="loader-circle" class="animate-spin" style="width: 24px; height: 24px; color: var(--color-deep-teal);"></i>
                <span class="help">Saving…</span>
            </div>

            <div class="card-header">
                <h2 class="card-title">{{ $editMode ? 'Edit item' : 'Add an item' }}</h2>
                @if($editMode) <span class="pill pill-warning">Editing</span> @endif
            </div>

            <form wire:submit.prevent="saveCode" class="flex flex-col gap-4">

                <div class="field">
                    <label for="input_item_code" class="label">Item code</label>
                    <div style="position: relative;">
                        <i data-lucide="lock" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--color-muted);"></i>
                        <input type="text" id="input_item_code" name="item_code" wire:model="item_code" readonly class="input" style="padding-left: 36px; font-variant-numeric: tabular-nums; background: var(--color-mist); cursor: not-allowed;" aria-describedby="item-code-help">
                    </div>
                    <span id="item-code-help" class="help">Set automatically from the category.</span>
                </div>

                <div class="field">
                    <label for="input_category" class="label">Category</label>
                    <select id="input_category" name="category" wire:model.live="category" {{ $editMode ? 'disabled' : '' }} class="select">
                        @foreach($availableCategories as $val => $data)
                            <option value="{{ $val }}">{{ $data['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="input_description" class="label">Description</label>
                    <input type="text" id="input_description" name="description" wire:model="description" required class="input">
                    @error('description') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="field">
                        <label for="input_uom" class="label">Unit</label>
                        <input type="text" id="input_uom" name="uom" wire:model="uom" required class="input" placeholder="e.g. Month">
                        @error('uom') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="input_rate" class="label">Base rate (USD)</label>
                        <div style="position: relative;">
                            <span class="muted" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%);">$</span>
                            <input type="number" id="input_rate" name="default_unit_rate_usd" step="0.01" wire:model="default_unit_rate_usd" required class="input" style="padding-left: 26px; font-variant-numeric: tabular-nums;">
                        </div>
                        @error('default_unit_rate_usd') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap gap-3" style="margin-top: 8px;">
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary" style="flex: 1;">
                        {{ $editMode ? 'Save changes' : 'Add item' }}
                    </button>
                    @if($editMode)
                        <button type="button" wire:click="resetForm" class="btn btn-secondary">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="card-header" style="padding: 16px 20px 0;">
                <h2 class="card-title">Items</h2>
                <span class="help" style="font-variant-numeric: tabular-nums;">{{ count($dictionary) }} {{ count($dictionary) == 1 ? 'item' : 'items' }}</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th style="min-width: 200px;">Description and category</th>
                            <th>Income and expense accounts</th>
                            <th class="num text-right">Base rate</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dictionary as $item)
                        <tr>
                            <td style="white-space: nowrap;">
                                <div class="text-ink" style="font-weight: 600; font-variant-numeric: tabular-nums;">{{ $item->item_code }}</div>
                                <div class="help" style="margin-top: 2px;">Unit: {{ $item->default_quantity }}</div>
                            </td>

                            <td>
                                <div class="text-ink">{{ $item->description }}</div>
                                <span class="tag" style="margin-top: 6px;">{{ $availableCategories[$item->category]['label'] ?? $item->category }}</span>
                            </td>

                            <td style="font-variant-numeric: tabular-nums; white-space: nowrap;">
                                <div><span class="muted">Income</span> <span class="{{ $item->income_code ? 'text-ink' : 'muted' }}">{{ $item->income_code ?: 'none' }}</span></div>
                                <div><span class="muted">Expense</span> <span class="{{ $item->expense_code ? 'text-ink' : 'muted' }}">{{ $item->expense_code ?: 'none' }}</span></div>
                            </td>

                            <td class="num text-ink" style="font-weight: 600;">${{ number_format($item->default_unit_rate_usd, 2) }}</td>

                            <td>
                                @if($item->is_active)
                                    <span class="pill pill-success">Active</span>
                                @else
                                    <span class="pill pill-neutral">Inactive</span>
                                @endif
                            </td>

                            <td>
                                <div class="flex flex-col items-end gap-1">
                                    <button type="button" wire:click="editCode({{ $item->id }})" class="btn btn-ghost btn-sm">Edit</button>
                                    <button type="button" wire:click="toggleStatus({{ $item->id }}, {{ $item->is_active }})" class="btn btn-ghost btn-sm" @if($item->is_active) style="color: var(--color-danger);" @endif>
                                        {{ $item->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>No items yet</strong>
                                    Items you add here can be used on proposals, project budgets and cash requests. Add the first one with the form.
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
