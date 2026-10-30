<div
     @consultation-closed.window="isSynthesizing = false; if(orgId) localStorage.removeItem('praxis_notes_' + orgId); document.getElementById('synthesisModal').style.display = 'none';"
     @open-transmission-modal.window="document.getElementById('transmissionModal').style.display = 'grid';"
     @consultation-error.window="isSynthesizing = false; document.getElementById('synthesisModal').style.display = 'none';"
     x-data="{
         notes: { context: '', vision: '', pain: '', sla: '' },
         orgId: @entangle('activeOrgId'),
         isSynthesizing: false,

         init() {
             this.$watch('orgId', (val) => {
                 if(val) this.loadNotes();
             });
             if(this.orgId) this.loadNotes();
         },
         saveNotes() {
             if(!this.orgId) return;
             localStorage.setItem('praxis_notes_' + this.orgId, JSON.stringify(this.notes));
         },
         loadNotes() {
             let saved = localStorage.getItem('praxis_notes_' + this.orgId);
             if(saved) { this.notes = JSON.parse(saved); }
             else { this.notes = { context: '', vision: '', pain: '', sla: '' }; }
         },
         clearNotes() {
             if(!confirm('Clear all notes for this free audit? They are kept only in this browser, so they cannot be recovered.')) return;
             localStorage.removeItem('praxis_notes_' + this.orgId);
             this.notes = { context: '', vision: '', pain: '', sla: '' };
         },
         dispatchWhatsApp(token) {
             if(!token) {
                 alert('This lead has no report yet. Calculate the cost of poor quality estimate first.');
                 return;
             }
             const url = @js(str(route('dossier.show', ['token' => 'TOKEN']))->beforeLast('TOKEN')->toString()) + token + '?exec=true';
             const message = encodeURIComponent('JBS Praxis: your cost of poor quality report is ready. You can read it here:\n\n' + url);
             window.open('https://wa.me/?text=' + message, '_blank');
             document.getElementById('transmissionModal').style.display = 'none';
         },
         async closeAndSynthesize() {
             if(!this.notes.context && !this.notes.pain) {
                 alert('Add your notes on the context or the bottlenecks before you close the free audit.');
                 return;
             }

             this.isSynthesizing = true;
             document.getElementById('synthesisModal').style.display = 'grid';

             // Call Backend via native Livewire integration
             await $wire.closeAndSynthesize(this.notes);
         }
     }"
>
    @php
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 0);
        // Appointment times are stored as Lagos wall-clock time (WAT)
        $when = fn ($time) => \Carbon\Carbon::parse($time)->format('D j M Y, H:i') . ' WAT';
    @endphp

    <style>
        .privacy-on .sensitive-data { filter: blur(6px); user-select: none; }
        .privacy-on .sensitive-data:hover { filter: none; }
        .lc-option { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px 12px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-paper); color: var(--color-ink); cursor: pointer; font: inherit; text-align: center; }
        .lc-option:hover { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .lc-option svg { width: 24px; height: 24px; color: var(--color-deep-teal); }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Free audits</h1>
            <p class="page-subtitle">
                @if($activeView === 'terminal')
                    Take notes during the free audit, then close it to draft the proposal.
                @else
                    Approve requests, run each free audit and draft the proposal from your notes.
                @endif
            </p>
        </div>

        @if($activeView === 'terminal')
            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="togglePrivacy" class="btn btn-secondary" aria-pressed="{{ $privacyMode ? 'true' : 'false' }}">
                    <i data-lucide="{{ $privacyMode ? 'eye' : 'eye-off' }}"></i>
                    {{ $privacyMode ? 'Show amounts' : 'Hide amounts' }}
                </button>
                <button type="button" onclick="document.getElementById('disqualifyModal').style.display = 'grid'" class="btn btn-secondary">
                    <i data-lucide="circle-x"></i> Mark as lost
                </button>
                <button type="button" @click="closeAndSynthesize" x-bind:disabled="isSynthesizing" class="btn btn-primary">
                    <span x-show="!isSynthesizing" class="inline-flex items-center gap-2"><i data-lucide="file-text"></i> Close and draft proposal</span>
                    <span x-show="isSynthesizing" x-cloak class="inline-flex items-center gap-2"><i data-lucide="loader" class="animate-spin"></i> Drafting…</span>
                </button>
            </div>
        @else
            <a href="{{ route('hq.crm.leads') }}" wire:navigate class="btn btn-secondary"><i data-lucide="arrow-left"></i> Go to leads</a>
        @endif
    </div>

    <x-flash style="margin-bottom: 20px;" />

    @if($activeView === 'lobby')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Requests to approve</h2>
                    <span class="tag num">{{ count($requestedAppointments) }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @forelse($requestedAppointments as $req)
                            <tr>
                                <td>
                                    <div class="font-semibold text-ink">{{ $req->company_name }}</div>
                                    <div class="text-caption text-muted" style="font-variant-numeric: tabular-nums;">{{ $when($req->start_time) }}</div>
                                </td>
                                <td style="text-align: right; vertical-align: middle; white-space: nowrap;">
                                    <button type="button" onclick="openDeclineModal(@js($req->id), @js($req->company_name), @js($when($req->start_time)))" class="btn btn-ghost btn-sm">Decline</button>
                                    <button type="button" onclick="openApproveModal(@js($req->id), @js($req->company_name))" class="btn btn-secondary btn-sm">Approve</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2">
                                    <div class="empty-state">
                                        <strong>No requests to approve</strong>
                                        Requests appear here when someone books a free audit on the website, or when you book one from Leads.
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Scheduled</h2>
                    <span class="tag num">{{ count($scheduledAppointments) }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                            @forelse($scheduledAppointments as $sch)
                            <tr>
                                <td>
                                    <div class="font-semibold text-ink">{{ $sch->company_name }}</div>
                                    <div class="text-caption text-muted" style="font-variant-numeric: tabular-nums;">{{ $when($sch->start_time) }}</div>
                                </td>
                                <td style="text-align: right; vertical-align: middle;">
                                    <button type="button" wire:click="enterTerminal('{{ $sch->organization_id }}', '{{ $sch->id }}')" class="btn btn-primary btn-sm">Start free audit</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2">
                                    <div class="empty-state">
                                        <strong>No free audits scheduled</strong>
                                        Approve a request to schedule it. It then appears here, ready to start.
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @endif

    @if($activeView === 'terminal')
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start {{ $privacyMode ? 'privacy-on' : '' }}">

            <div class="flex flex-col gap-6">
                <section class="card">
                    <div class="stat-label">Organisation</div>
                    <h2 class="section-title" style="margin: 2px 0 4px;">{{ $leadData->name }}</h2>
                    <div class="text-small text-muted">Industry: <span class="text-ink">{{ $leadData->industry ?: 'Not given' }}</span></div>
                </section>

                <section class="card">
                    <h2 class="card-title" style="margin-bottom: 16px;">Cost of poor quality estimate</h2>

                    @if($copqData)
                        <dl class="flex flex-col gap-3 text-small" style="margin: 0 0 16px;">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Annual turnover</dt>
                                <dd class="sensitive-data font-semibold text-ink" style="margin: 0; font-variant-numeric: tabular-nums;">{{ $money($copqData->copq_currency, $copqData->copq_turnover) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Main bottleneck</dt>
                                <dd class="font-semibold text-ink" style="margin: 0; text-align: right;">{{ $copqData->bottleneck }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 pt-3" style="border-top: 1px solid var(--color-line);">
                                <dt class="text-ink font-semibold">Estimated cost a year</dt>
                                <dd class="sensitive-data stat-value" style="margin: 0;">{{ $money($copqData->copq_currency, $copqData->calculated_leakage) }}</dd>
                            </div>
                        </dl>

                        @if($copqData->ai_friction_analysis)
                            <p class="text-small" style="margin: 0 0 16px;">{{ $copqData->ai_friction_analysis }}</p>
                        @endif

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('dossier.show', ['token' => $copqData->report_token]) }}?exec=true" target="_blank" rel="noopener" class="btn btn-secondary"><i data-lucide="external-link"></i> Open report</a>
                            <button type="button" wire:click="dispatchCopqEmail" class="btn btn-secondary"><i data-lucide="send"></i> Send report</button>
                        </div>
                    @else
                        <p class="text-small text-muted" style="margin: 0 0 16px;">This lead has no estimate yet. Enter its figures to calculate one.</p>
                        <form wire:submit.prevent="generateInlineCopq" class="flex flex-col gap-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="field">
                                    <label class="label" for="lc-industry">Industry</label>
                                    <select id="lc-industry" wire:model="inlineIndustry" required class="select"><option value="">Choose an industry</option>@foreach($dbIndustries as $ind) <option value="{{ $ind->industry_name }}">{{ $ind->industry_name }}</option> @endforeach</select>
                                    @error('inlineIndustry') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-bottleneck">Main bottleneck</label>
                                    <select id="lc-bottleneck" wire:model="inlineBottleneck" required class="select"><option value="">Choose a bottleneck</option>@foreach($dbBottlenecks as $bn) <option value="{{ $bn->bottleneck_name }}">{{ $bn->bottleneck_name }}</option> @endforeach</select>
                                    @error('inlineBottleneck') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-currency">Currency</label>
                                    <select id="lc-currency" wire:model="inlineCurrency" required class="select">@foreach($dbCurrencies as $curr) <option value="{{ $curr->currency_code }}">{{ $curr->currency_code }}</option> @endforeach</select>
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-tool">Systems in use</label>
                                    <select id="lc-tool" wire:model="inlineTool" required class="select"><option value="">Choose the main system</option>@foreach($dbArchitectures as $arch) <option value="{{ $arch->tech_key }}">{{ $arch->display_name }}</option> @endforeach</select>
                                    @error('inlineTool') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-turnover">Annual turnover</label>
                                    <input id="lc-turnover" type="number" wire:model="inlineTurnover" required class="input">
                                    @error('inlineTurnover') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-acv">Average contract value (optional)</label>
                                    <input id="lc-acv" type="number" wire:model="inlineAcv" class="input">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="field">
                                    <label class="label" for="lc-headcount">Headcount</label>
                                    <input id="lc-headcount" type="number" wire:model="inlineHeadcount" required class="input">
                                    @error('inlineHeadcount') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-managers">Managers who approve work</label>
                                    <input id="lc-managers" type="number" wire:model="inlineMgmtNodes" required class="input">
                                    @error('inlineMgmtNodes') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-hours">Manual hours a week</label>
                                    <input id="lc-hours" type="number" wire:model="inlineManualHrs" required class="input">
                                    @error('inlineManualHrs') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-hourly">Hourly cost of a manager</label>
                                    <input id="lc-hourly" type="number" wire:model="inlineHourlyCost" required class="input">
                                    @error('inlineHourlyCost') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field">
                                    <label class="label" for="lc-error">Error rate (%)</label>
                                    <input id="lc-error" type="number" step="0.1" wire:model="inlineErrorRate" required class="input">
                                    @error('inlineErrorRate') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div>
                                <button type="submit" wire:loading.attr="disabled" wire:target="generateInlineCopq" class="btn btn-primary">
                                    <span wire:loading.remove wire:target="generateInlineCopq">Calculate estimate</span>
                                    <span wire:loading wire:target="generateInlineCopq">Calculating…</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </section>

                <section class="card">
                    <h2 class="card-title" style="margin-bottom: 16px;">History</h2>
                    @if($historySummary)
                        <div class="callout" style="margin-bottom: 16px;">
                            <div class="stat-label" style="margin-bottom: 4px;">Summary of earlier notes, written by the assistant</div>
                            <p class="text-small" style="margin: 0;">{{ $historySummary }}</p>
                        </div>
                    @endif

                    <div class="flex flex-col gap-3">
                        @forelse($historyData as $note)
                            <div style="padding: 12px 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist);">
                                <div class="flex justify-between gap-4 text-caption text-muted" style="margin-bottom: 4px;">
                                    <span>{{ $note->interaction_type }}</span>
                                    <span style="font-variant-numeric: tabular-nums;">{{ \Carbon\Carbon::parse($note->created_at)->format('j M Y') }}</span>
                                </div>
                                <p class="text-small" style="margin: 0;">{{ Str::limit($note->note_text, 150) }}</p>
                            </div>
                        @empty
                            <div class="empty-state" style="padding: 16px;">No earlier notes for this organisation.</div>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">Notes from the free audit</h2>
                    <button type="button" @click="clearNotes" class="btn btn-ghost btn-sm" style="color: var(--color-danger);">Clear notes</button>
                </div>
                <p class="text-small text-muted" style="margin: 0 0 20px;">Write freely; the formatting doesn’t matter. Notes are saved in this browser as you type. When you close the free audit, the assistant drafts a proposal from them.</p>

                <div class="flex flex-col gap-5">
                    <div class="field">
                        <label class="label" for="lc-notes-context">1. Context and operations</label>
                        <span class="help">What does the organisation do, and which systems does it use?</span>
                        <textarea id="lc-notes-context" x-model="notes.context" @input.debounce.500ms="saveNotes" class="textarea" placeholder="e.g. A distributor using 5 separate spreadsheets"></textarea>
                    </div>

                    <div class="field">
                        <label class="label" for="lc-notes-vision">2. Growth plans</label>
                        <span class="help">Where do they want to be in 5 years?</span>
                        <textarea id="lc-notes-vision" x-model="notes.vision" @input.debounce.500ms="saveNotes" class="textarea" placeholder="e.g. Opening in 2 more countries next year; needs several currencies"></textarea>
                    </div>

                    <div class="field">
                        <label class="label" for="lc-notes-pain">3. Bottlenecks</label>
                        <span class="help">Where do time and money go?</span>
                        <textarea id="lc-notes-pain" x-model="notes.pain" @input.debounce.500ms="saveNotes" class="textarea" placeholder="e.g. The managing director spends 10 hours a week approving paper purchase orders"></textarea>
                    </div>

                    <div class="field">
                        <label class="label" for="lc-notes-sla">4. Requirements and budget</label>
                        <span class="help">What must we deliver? Hosting, standards, budget?</span>
                        <textarea id="lc-notes-sla" x-model="notes.sla" @input.debounce.500ms="saveNotes" class="textarea" placeholder="e.g. Must be hosted in Nigeria; budget to be agreed by the board"></textarea>
                    </div>
                </div>
            </section>
        </div>
    @endif

    {{-- Approve a request --}}
    <div class="modal-backdrop" id="approveModal" style="display: none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="approve-title" style="max-width: 500px;">
            <h2 id="approve-title" class="modal-title">Approve free audit</h2>
            <p class="text-small text-muted" style="margin: 0 0 16px;">Approve the free audit for <strong id="appClientName" class="text-ink"></strong>. Paste the meeting link and we’ll email it to the client.</p>
            <input type="hidden" id="appIdInput">
            <div class="field">
                <label class="label" for="appLinkInput">Meeting link</label>
                <input type="url" id="appLinkInput" class="input" placeholder="e.g. https://meet.google.com/abc-defg-hij">
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('approveModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="button" onclick="submitApproval()" class="btn btn-primary">Approve and send link</button>
            </div>
        </div>
    </div>

    {{-- Decline a request --}}
    <div class="modal-backdrop" id="declineModal" style="display: none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="decline-title" style="max-width: 500px;">
            <h2 id="decline-title" class="modal-title">Decline this request?</h2>
            <p class="text-small text-muted" style="margin: 0 0 16px;">Decline the free audit for <strong id="decClientName" class="text-ink"></strong> on <span id="decWhen"></span>. The time becomes free again on the website.</p>
            <input type="hidden" id="decIdInput">
            <div class="field">
                <label class="label" for="decReasonInput">Reason</label>
                <textarea id="decReasonInput" class="textarea" maxlength="500" style="min-height: 80px;" placeholder="e.g. Test booking, spam, the client asked to cancel"></textarea>
                <span class="help">For the team only. The client never sees it.</span>
            </div>
            <label class="flex items-start gap-2" style="margin-top: 12px;">
                <input type="checkbox" id="decNotifyInput" checked style="margin-top: 3px;">
                <span class="text-small">Email the client to offer another time<br><span class="text-muted">Untick for spam or test bookings.</span></span>
            </label>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('declineModal').style.display = 'none'" class="btn btn-secondary">Keep request</button>
                <button type="button" onclick="submitDecline()" class="btn btn-danger">Decline request</button>
            </div>
        </div>
    </div>

    {{-- Mark the lead as lost --}}
    <div class="modal-backdrop" id="disqualifyModal" style="display: none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="lost-title" style="max-width: 500px;">
            <h2 id="lost-title" class="modal-title">Mark this lead as lost?</h2>
            <p class="text-small text-muted" style="margin: 0 0 16px;">The lead moves to Won or lost and this free audit is marked as completed. Give the reason so the team knows why.</p>
            <div class="field">
                <label class="label" for="dqReasonInput">Reason</label>
                <textarea id="dqReasonInput" class="textarea" style="min-height: 80px;" placeholder="e.g. Budget too low, no reply, outside our scope"></textarea>
                <span class="help">Only approvers can leave this blank.</span>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('disqualifyModal').style.display = 'none'" class="btn btn-secondary">Keep lead</button>
                <button type="button" onclick="submitDisqualify()" class="btn btn-danger">Mark as lost</button>
            </div>
        </div>
    </div>

    {{-- Send the report --}}
    <div class="modal-backdrop" id="transmissionModal" style="display: none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="send-title" style="max-width: 460px;">
            <div class="flex items-start justify-between gap-4">
                <h2 id="send-title" class="modal-title">Send the report</h2>
                <button type="button" onclick="document.getElementById('transmissionModal').style.display = 'none'" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
            </div>
            <p class="text-small text-muted" style="margin: 0 0 20px;">Choose how to send the cost of poor quality report to the client.</p>

            <div class="grid grid-cols-2 gap-4">
                <button type="button" @click="dispatchWhatsApp('{{ $copqData->report_token ?? '' }}')" class="lc-option">
                    <i data-lucide="message-circle"></i>
                    <span class="font-semibold">WhatsApp</span>
                    <span class="text-caption text-muted">Opens WhatsApp with the report link</span>
                </button>

                <button type="button" onclick="alert('Sending the report by email is not available yet. Use WhatsApp, or open the report and share its link.'); document.getElementById('transmissionModal').style.display = 'none';" class="lc-option">
                    <i data-lucide="mail"></i>
                    <span class="font-semibold">Email</span>
                    <span class="text-caption text-muted">Not available yet</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Drafting the proposal --}}
    <div class="modal-backdrop" id="synthesisModal" style="display: none;">
        <div class="modal" role="status" aria-live="polite" style="max-width: 440px; text-align: center;">
            <i data-lucide="loader" class="animate-spin" style="width: 32px; height: 32px; color: var(--color-deep-teal); margin: 8px auto 16px; display: block;"></i>
            <h2 class="modal-title">Drafting the proposal</h2>
            <p class="text-small text-muted" style="margin: 0;">The assistant is writing a draft from your notes and the cost of poor quality estimate. This can take up to 15 seconds. Keep this page open.</p>
        </div>
    </div>

    <script>
        // 1. Approve a request
        function openApproveModal(id, name) {
            document.getElementById('appIdInput').value = id;
            document.getElementById('appClientName').innerText = name;
            document.getElementById('approveModal').style.display = 'grid';
        }
        function submitApproval() {
            const id = document.getElementById('appIdInput').value;
            const link = document.getElementById('appLinkInput').value;
            @this.approveMeeting(id, link);
            document.getElementById('approveModal').style.display = 'none';
        }

        // 1b. Decline a request (the reason stays with the team; the email is optional)
        function openDeclineModal(id, name, when) {
            document.getElementById('decIdInput').value = id;
            document.getElementById('decClientName').innerText = name;
            document.getElementById('decWhen').innerText = when;
            document.getElementById('decReasonInput').value = '';
            document.getElementById('decNotifyInput').checked = true;
            document.getElementById('declineModal').style.display = 'grid';
            document.getElementById('decReasonInput').focus();
        }
        function submitDecline() {
            const id = document.getElementById('decIdInput').value;
            const reason = document.getElementById('decReasonInput').value;
            const notify = document.getElementById('decNotifyInput').checked;
            @this.declineRequest(id, reason, notify);
            document.getElementById('declineModal').style.display = 'none';
        }

        // 2. Mark the lead as lost
        function submitDisqualify() {
            const reason = document.getElementById('dqReasonInput').value;
            @this.disqualifyLead(reason);
            document.getElementById('disqualifyModal').style.display = 'none';
        }
    </script>
</div>
