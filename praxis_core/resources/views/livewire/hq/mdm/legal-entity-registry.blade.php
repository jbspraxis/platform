<div>
    @php
        $regionNames = ['CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya', 'GLOBAL' => 'Head office'];
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="entity-saved-title">
                <h2 id="entity-saved-title" class="modal-title">Legal entity saved</h2>
                <p class="muted">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Legal entities</h1>
            <p class="page-subtitle">The companies JBS Praxis employs people through, one per country. Payroll, contracts and tax filings use these details.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-6" role="alert">
            <i data-lucide="circle-alert"></i>
            <div>
                <strong>We couldn't save the legal entity.</strong> Fix the following and save again:
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ $editMode ? 'Edit legal entity' : 'Add a legal entity' }}</h2>
                @if($editMode) <span class="pill pill-warning">Editing</span> @endif
            </div>

            <form wire:submit.prevent="saveEntity" class="flex flex-col gap-4">

                <h3 class="label" style="font-weight: 600; margin: 0;">Country and name</h3>

                <div class="flex flex-col gap-4">
                    <div class="field">
                        <label class="label" for="le-region">Country</label>
                        <select id="le-region" wire:model="region_code" required class="select" {{ $editMode ? 'disabled' : '' }}>
                            <option value="">Choose a country</option>
                            <option value="CM">CM: Cameroon</option>
                            <option value="NG">NG: Nigeria</option>
                            <option value="KE">KE: Kenya</option>
                            <option value="GLOBAL">Global: head office</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="le-name">Registered company name</label>
                        <input id="le-name" type="text" wire:model="legal_name" required class="input" placeholder="e.g. JBS Praxis SARL">
                    </div>
                </div>

                <hr class="divider" style="margin: 4px 0;">
                <h3 class="label" style="font-weight: 600; margin: 0;">Registration and tax numbers</h3>

                <div class="field">
                    <label class="label" for="le-rc">Company registration number (RC)</label>
                    <input id="le-rc" type="text" wire:model="registration_number" required class="input" style="font-variant-numeric: tabular-nums;">
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="field">
                        <label class="label" for="le-tax">Tax ID (NIU or TIN)</label>
                        <input id="le-tax" type="text" wire:model="tax_identification_number" required class="input" style="font-variant-numeric: tabular-nums;">
                    </div>
                    <div class="field">
                        <label class="label" for="le-social">Employer social security number</label>
                        <input id="le-social" type="text" wire:model="employer_social_security_code" required class="input" style="font-variant-numeric: tabular-nums;" aria-describedby="le-social-help">
                        <span id="le-social-help" class="help">CNPS in Cameroon, NSITF in Nigeria.</span>
                    </div>
                </div>

                <hr class="divider" style="margin: 4px 0;">
                <h3 class="label" style="font-weight: 600; margin: 0;">Legal representative</h3>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="field">
                        <label class="label" for="le-rep">Name</label>
                        <input id="le-rep" type="text" wire:model="legal_representative_name" required class="input">
                    </div>
                    <div class="field">
                        <label class="label" for="le-rep-title">Job title</label>
                        <input id="le-rep-title" type="text" wire:model="legal_representative_title" required class="input" placeholder="e.g. Managing Director">
                    </div>
                </div>

                <hr class="divider" style="margin: 4px 0;">
                <h3 class="label" style="font-weight: 600; margin: 0;">Contact details and address</h3>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="field">
                        <label class="label" for="le-email">Email address (optional)</label>
                        <input id="le-email" type="email" wire:model="contact_email" class="input">
                    </div>
                    <div class="field">
                        <label class="label" for="le-phone">Phone number (optional)</label>
                        <input id="le-phone" type="text" wire:model="contact_phone" class="input" placeholder="e.g. +237 6 99 00 00 00">
                    </div>
                </div>
                <div class="field">
                    <label class="label" for="le-address">Registered office address</label>
                    <textarea id="le-address" wire:model="registered_address" required class="textarea" rows="2" aria-describedby="le-address-help"></textarea>
                    <span id="le-address-help" class="help">Printed on contracts and payslips.</span>
                </div>

                <div class="flex flex-wrap gap-3" style="border-top: 1px solid var(--color-line); padding-top: 16px;">
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary" style="flex: 2;">
                        <span wire:loading.remove wire:target="saveEntity">{{ $editMode ? 'Save changes' : 'Add legal entity' }}</span>
                        <span wire:loading wire:target="saveEntity">Saving…</span>
                    </button>
                    @if($editMode)
                        <button type="button" wire:click="resetForm" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="min-width: 200px;">Country and company</th>
                            <th style="min-width: 170px;">Registration numbers</th>
                            <th class="num" style="min-width: 150px; white-space: normal;">Active staff and monthly pay</th>
                            <th>Status</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entities as $entity)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2" style="margin-bottom: 4px;">
                                        <span class="pill pill-neutral" title="{{ $regionNames[$entity->region_code] ?? $entity->region_code }}">{{ $entity->region_code === 'GLOBAL' ? 'Global' : $entity->region_code }}</span>
                                        <span class="text-ink" style="font-weight: 600;">{{ $entity->legal_name }}</span>
                                    </div>
                                    <div class="help flex items-center gap-1"><i data-lucide="map-pin" style="width: 14px; height: 14px;"></i> {{ \Illuminate\Support\Str::limit($entity->registered_address, 35) }}</div>
                                    <div class="help flex items-center gap-1" style="margin-top: 2px;"><i data-lucide="user" style="width: 14px; height: 14px;"></i> Representative: {{ $entity->legal_representative_name }}</div>
                                </td>

                                <td style="font-variant-numeric: tabular-nums;">
                                    <div><span class="muted">RC</span> <span class="text-ink">{{ $entity->registration_number }}</span></div>
                                    <div style="margin-top: 2px;"><span class="muted">Tax ID</span> <span class="text-ink">{{ $entity->tax_identification_number }}</span></div>
                                    <div style="margin-top: 2px;"><span class="muted" style="white-space: nowrap;">Employer no.</span> <span class="text-ink">{{ $entity->employer_social_security_code }}</span></div>
                                </td>

                                <td class="num">
                                    <div class="help">{{ $entity->active_headcount }} active {{ $entity->active_headcount == 1 ? 'staff member' : 'staff members' }}</div>
                                    <div class="text-ink" style="font-weight: 600;">
                                        {{ number_format($entity->monthly_liability, 2) }} <span class="help">{{ $entity->local_currency }} a month</span>
                                    </div>
                                    <div class="help">Gross pay and allowances</div>
                                </td>

                                <td>
                                    @if($entity->is_active)
                                        <span class="pill pill-success">Active</span>
                                    @else
                                        <span class="pill pill-neutral">Inactive</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="flex flex-col items-end gap-1">
                                        <button type="button" wire:click="editEntity('{{ $entity->id }}')" class="btn btn-ghost btn-sm">Edit</button>
                                        <button type="button" wire:click="toggleStatus('{{ $entity->id }}', {{ $entity->is_active }})" class="btn btn-ghost btn-sm" @if($entity->is_active) style="color: var(--color-danger);" @endif>
                                            {{ $entity->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No legal entities yet</strong>
                                        Payroll needs at least one. Start with the head office entity using the form.
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
