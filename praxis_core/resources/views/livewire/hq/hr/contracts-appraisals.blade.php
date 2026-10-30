<div>
    @php
        // Stored values stay as they are; these are the labels people read
        $appraisalStatuses = ['Submitted_by_Manager' => 'Awaiting sign-off', 'Finalized' => 'Final'];
        $regionLabels = ['CM' => 'Cameroon', 'NG' => 'Nigeria', 'KE' => 'Kenya'];
    @endphp

    @if($showSuccessModal)
        <div class="modal-backdrop">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="hr-saved-title">
                <h2 id="hr-saved-title" class="modal-title">{{ $activeTab === 'contracts' ? 'Contract saved' : 'Appraisal saved' }}</h2>
                <p class="text-sm text-slate">{{ $successMessage }}</p>
                <div class="modal-actions">
                    <button type="button" wire:click="closeSuccessModal" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h1 class="page-title">Contracts and appraisals</h1>
            <p class="page-subtitle">Set each staff member’s employment contract terms and record their performance appraisals.</p>
        </div>
    </div>

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="setTab('contracts')" class="tab {{ $activeTab === 'contracts' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'contracts' ? 'true' : 'false' }}">Contracts</button>
        <button type="button" role="tab" wire:click="setTab('appraisals')" class="tab {{ $activeTab === 'appraisals' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'appraisals' ? 'true' : 'false' }}">Appraisals</button>
    </div>

    @if(session()->has('error'))
        <div class="alert alert-danger mb-6" role="alert"><i data-lucide="circle-alert"></i><span>{{ session('error') }}</span></div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">

        <div class="card overflow-y-auto p-0" style="max-height: 700px;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th class="text-right">{{ $activeTab === 'contracts' ? 'Contract' : '' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($activeTab === 'contracts')
                            @forelse($employees as $emp)
                                <tr wire:click="selectContract('{{ $emp->id }}')" class="cursor-pointer {{ $selectedEmployeeId === $emp->id ? 'is-selected' : '' }}">
                                    <td>
                                        <div class="font-medium text-ink">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                        <div class="help" style="font-variant-numeric: tabular-nums;">{{ $emp->employee_code }} · {{ $regionLabels[$emp->tax_region] ?? $emp->tax_region }}</div>
                                    </td>
                                    <td class="text-right">
                                        @if($emp->contract_type)
                                            <span class="pill pill-success">{{ $emp->contract_type }}</span>
                                        @else
                                            <span class="pill pill-warning">No contract</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2"><div class="empty-state"><strong>No active staff members</strong>Staff members appear here once they’re active in the staff directory.</div></td></tr>
                            @endforelse
                        @else
                            @forelse($appraisals as $emp)
                                <tr wire:click="selectAppraisal('{{ $emp->id }}')" class="cursor-pointer {{ $selectedEmployeeId === $emp->id ? 'is-selected' : '' }}">
                                    <td>
                                        <div class="font-medium text-ink">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                        <div class="help" style="font-variant-numeric: tabular-nums;">{{ $emp->employee_code }}</div>
                                    </td>
                                    <td class="text-right muted">
                                        <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2"><div class="empty-state"><strong>No active staff members</strong>Staff members appear here once they’re active in the staff directory.</div></td></tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            @if(!$selectedEmployeeId)
                <div class="empty-state">
                    <strong>{{ $activeTab === 'contracts' ? 'Choose a staff member to set their contract' : 'Choose a staff member to record an appraisal' }}</strong>
                    Select a name in the list on the left.
                </div>
            @else

                @if($activeTab === 'contracts')
                    <form wire:submit.prevent="saveContract">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h2 class="section-title mb-0">Employment contract</h2>
                            <span class="help flex items-center gap-1"><i data-lucide="file-signature" style="width: 14px; height: 14px;"></i> Saving changes sends the contract for signature again</span>
                        </div>

                        <h3 class="label mb-3 mt-2">Type of employment (OHADA and ECOWAS rules)</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <label class="label" for="ct-type">Contract type</label>
                                <select id="ct-type" wire:model.live="contract_type" class="select" required>
                                    <option value="CDI">Permanent (CDI)</option>
                                    <option value="CDD">Fixed term (CDD)</option>
                                    <option value="Probation">Probation or internship (stage)</option>
                                    <option value="Freelance">Freelance or consultant</option>
                                </select>
                                @error('contract_type') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ct-cat">Professional category (optional)</label>
                                <input id="ct-cat" type="text" wire:model="professional_category" class="input" placeholder="e.g. Category 8B">
                                @error('professional_category') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ct-hours">Weekly hours</label>
                                <input id="ct-hours" type="number" wire:model="standard_weekly_hours" class="input" style="font-variant-numeric: tabular-nums;" required min="1" max="60">
                                @error('standard_weekly_hours') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ct-rate">Hourly rate (optional)</label>
                                <input id="ct-rate" type="number" step="0.01" wire:model="hourly_rate_offered" class="input" style="font-variant-numeric: tabular-nums;">
                                @error('hourly_rate_offered') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <hr class="divider my-5">
                        <h3 class="label mb-3">Dates and notice</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <label class="label" for="ct-prob">Probation end date (optional)</label>
                                <input id="ct-prob" type="date" wire:model="probation_end_date" class="input">
                                @error('probation_end_date') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ct-end">Contract end date{{ $contract_type === 'CDD' ? '' : ' (optional)' }}</label>
                                <input id="ct-end" type="date" wire:model="contract_end_date" class="input" {{ $contract_type === 'CDD' ? 'required' : '' }}>
                                @if($contract_type === 'CDD') <span class="help">A fixed-term contract needs an end date.</span> @endif
                                @error('contract_end_date') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field sm:col-span-2">
                                <label class="label" for="ct-notice">Notice period (days)</label>
                                <input id="ct-notice" type="number" wire:model="notice_period_days" class="input" style="font-variant-numeric: tabular-nums;" required min="0">
                                @error('notice_period_days') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field sm:col-span-2">
                                <label class="label" for="ct-terms">Severance and special terms (optional)</label>
                                <textarea id="ct-terms" wire:model="severance_terms" class="textarea" placeholder="e.g. Agreed severance or a non-compete clause"></textarea>
                                @error('severance_terms') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end border-t border-line pt-5">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveContract" class="inline-flex items-center gap-2"><i data-lucide="save"></i> Save contract terms</span>
                                <span wire:loading wire:target="saveContract">Saving…</span>
                            </button>
                        </div>
                    </form>

                @else
                    <form wire:submit.prevent="submitAppraisal">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h2 class="section-title mb-0">Performance appraisal</h2>
                            <span class="help">Submitted by the staff member’s line manager</span>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="field">
                                <label class="label" for="ap-start">Review period start</label>
                                <input id="ap-start" type="date" wire:model="review_period_start" class="input" required>
                                @error('review_period_start') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="ap-end">Review period end</label>
                                <input id="ap-end" type="date" wire:model="review_period_end" class="input" required>
                                @error('review_period_end') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field sm:col-span-2">
                                <label class="label" for="ap-score">Overall score (0 to 5)</label>
                                <input id="ap-score" type="number" step="0.01" min="0" max="5" wire:model="kpi_score_overall" class="input" style="max-width: 160px; font-variant-numeric: tabular-nums;" required>
                                @error('kpi_score_overall') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field sm:col-span-2">
                                <label class="label" for="ap-comments">Line manager’s comments (optional)</label>
                                <textarea id="ap-comments" wire:model="manager_comments" class="textarea" placeholder="What went well, what to improve, and why you gave this score"></textarea>
                                @error('manager_comments') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-line pt-5">
                            <p class="help flex items-center gap-1"><i data-lucide="info" style="width: 14px; height: 14px;"></i> A high score can support a performance bonus in Variable pay.</p>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submitAppraisal" class="inline-flex items-center gap-2"><i data-lucide="send"></i> Submit appraisal</span>
                                <span wire:loading wire:target="submitAppraisal">Submitting…</span>
                            </button>
                        </div>
                    </form>

                    <div class="mt-8">
                        <h3 class="label mb-3">Appraisals on record</h3>
                        @php
                            $userAppraisals = collect($appraisals)->where('id', $selectedEmployeeId)->whereNotNull('appraisal_id');
                        @endphp
                        @if($userAppraisals->isEmpty())
                            <p class="help">No appraisals recorded for this staff member yet. Submit the first one above.</p>
                        @else
                            <div class="table-wrap rounded-lg border border-line">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Period ending</th>
                                            <th class="num text-right">Score</th>
                                            <th>Status</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($userAppraisals as $app)
                                            <tr>
                                                <td style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($app->review_period_end)->format('M Y') }}</td>
                                                <td class="num font-semibold text-ink">{{ number_format($app->kpi_score_overall, 2) }}</td>
                                                <td><span class="pill {{ $app->status === 'Finalized' ? 'pill-success' : 'pill-warning' }}">{{ $appraisalStatuses[$app->status] ?? str_replace('_', ' ', $app->status) }}</span></td>
                                                <td class="text-right">
                                                    @if($app->status !== 'Finalized')
                                                        <button type="button" wire:click="finalizeAppraisal('{{ $app->appraisal_id }}')" class="btn btn-secondary btn-sm" wire:confirm="Finalise this appraisal? The score can no longer be changed afterwards.">Finalise</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
