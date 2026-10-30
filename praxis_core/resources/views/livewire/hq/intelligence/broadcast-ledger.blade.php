<div>
    @php
        $stageLabels = ['Lead Captured' => 'New', 'Qualifying' => 'Qualifying', 'In Consultation' => 'Free audit', 'Proposal Sent' => 'Proposal sent', 'Won / Lost' => 'Won or lost'];
        $tabs = ['composer' => 'Write and send', 'queued' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed'];
    @endphp

    <style>
        .bl-placeholder { display: inline-block; padding: 2px 8px; margin: 0 6px 6px 0; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-mist); color: var(--color-ink); font-size: 0.8125rem; user-select: all; }
        .bl-scroll { max-height: 400px; overflow-y: auto; }
        .bl-scroll thead th { position: sticky; top: 0; background: var(--color-paper); z-index: 1; }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Broadcasts</h1>
            <p class="page-subtitle">Write an email to subscribed contacts, choose who receives it, and follow its delivery.</p>
        </div>
    </div>

    <div class="tabs" role="tablist" aria-label="Broadcasts">
        @foreach($tabs as $key => $label)
            <button type="button" role="tab" wire:click="switchTab('{{ $key }}')" class="tab {{ $activeTab === $key ? 'is-active' : '' }}" aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}">
                {{ $label }}@if($key === 'queued' && count($queuedLogs) > 0) <span class="num">({{ count($queuedLogs) }})</span>@endif
            </button>
        @endforeach
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success" role="status" style="margin-bottom: 16px;"><i data-lucide="check-circle"></i><span>{{ session('success') }}</span></div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;"><i data-lucide="circle-alert"></i><span>{{ session('error') }}</span></div>
    @endif

    @if($activeTab === 'composer')

        @if($activeLock)
            <div class="alert alert-warning" style="margin-bottom: 16px;">
                <i data-lucide="clock"></i>
                <span>Another broadcast is still sending. To protect our email reputation, new broadcasts can be sent after about <strong>{{ $lockEta }}</strong>.</span>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start" style="margin-bottom: 24px;">
            {{-- Who receives it --}}
            <section class="card">
                <h2 class="card-title" style="margin-bottom: 16px;">Recipients</h2>

                <div class="flex flex-col gap-4">
                    <div class="field">
                        <label class="label" for="bl-status">Client status</label>
                        <select id="bl-status" wire:model.live="filterStatus" class="select">
                            <option value="">All subscribed contacts</option>
                            <option value="Lead">Leads</option>
                            <option value="Active Client">Active clients</option>
                            <option value="Past Client">Past clients</option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="bl-industry">Industry</label>
                        <select id="bl-industry" wire:model.live="filterIndustry" class="select">
                            <option value="">Any industry</option>
                            @foreach($availableIndustries as $ind)
                                <option value="{{ $ind }}">{{ $ind }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="bl-turnover">Turnover bracket</label>
                        <select id="bl-turnover" wire:model.live="filterTurnover" class="select">
                            <option value="">Any turnover</option>
                            @foreach($availableTurnovers as $turn)
                                <option value="{{ $turn }}">{{ $turn }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="bl-stage">Pipeline stage</label>
                        <select id="bl-stage" wire:model.live="filterStage" class="select">
                            <option value="">Any stage</option>
                            @foreach($availableStages as $stage)
                                <option value="{{ $stage }}">{{ $stageLabels[$stage] ?? $stage }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="callout">
                        <div class="stat-label">Contacts matching</div>
                        <div class="stat-value">{{ count($targetUsers) }}</div>
                    </div>
                </div>
            </section>

            {{-- The email --}}
            <section class="card xl:col-span-2">
                <h2 class="card-title" style="margin-bottom: 16px;">Email</h2>

                <div class="flex flex-col gap-4">
                    <div class="field">
                        <label class="label" for="bl-subject">Subject</label>
                        <input id="bl-subject" type="text" wire:model="subject" class="input" placeholder="e.g. An update for @{{ company_name }}">
                        <span class="help">Sent exactly as you type it.</span>
                        @error('subject') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="label" style="margin-bottom: 6px;">Placeholders</div>
                        <span class="bl-placeholder">@{{ first_name }}</span>
                        <span class="bl-placeholder">@{{ company_name }}</span>
                        <span class="bl-placeholder">@{{ industry }}</span>
                        <span class="bl-placeholder">@{{ pipeline_stage }}</span>
                        <div class="help">Select a placeholder to copy it. Each one is replaced with the recipient’s details.</div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-4">
                            <div class="field">
                                <label class="label" for="bl-draft">Your draft</label>
                                <textarea id="bl-draft" wire:model="rawBody" class="textarea" style="min-height: 220px;" placeholder="e.g. Hello @{{ first_name }}, following our conversation about the free audit for @{{ company_name }}…"></textarea>
                                <span class="help">Write freely. The assistant rewrites it in the JBS Praxis voice.</span>
                                @error('rawBody') <span class="field-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="field">
                                <label class="label" for="bl-tone">Tone</label>
                                <select id="bl-tone" wire:model="selectedTone" class="select">
                                    <option value="Consultative & Authoritative">Consultative (default)</option>
                                    <option value="Aggressive Follow-up">Firm follow-up</option>
                                    <option value="Highly Technical & Analytical">Technical and analytical</option>
                                    <option value="Polite & Soft Check-in">Polite check-in</option>
                                </select>
                            </div>

                            <div>
                                <button type="button" wire:click="enhanceWithGemini" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="enhanceWithGemini">
                                    <span wire:loading.remove wire:target="enhanceWithGemini" class="inline-flex items-center gap-2"><i data-lucide="sparkles"></i> Rewrite with the assistant</span>
                                    <span wire:loading wire:target="enhanceWithGemini">Rewriting…</span>
                                </button>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="bl-final">Email text (HTML)</label>
                            <textarea id="bl-final" wire:model="enhancedBody" class="textarea" style="min-height: 360px; font-size: 0.8125rem;" placeholder="Rewrite your draft with the assistant, or write the HTML here."></textarea>
                            <span class="help">This is exactly what each recipient receives. The unsubscribe link and an open-tracking image are added automatically.</span>
                            @error('enhancedBody') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Choose recipients and send --}}
        <section class="card">
            <div class="card-header" style="flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <h2 class="card-title">Choose recipients</h2>
                    <p class="help" style="margin: 4px 0 0;">Up to 200 contacts are shown. Untick anyone who should not receive this email.</p>
                </div>
                <div class="flex items-center gap-4">
                    <button type="button" wire:click="toggleSelectAll" class="btn btn-secondary btn-sm">
                        <i data-lucide="check-square"></i> Select or clear all
                    </button>
                    <span class="text-small text-ink"><strong class="num">{{ $recipientCount }}</strong> selected</span>
                </div>
            </div>

            <div class="table-wrap bl-scroll" style="border: 1px solid var(--color-line); border-radius: var(--radius-card); margin-bottom: 20px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 56px; text-align: center;"><span class="sr-only">Include</span></th>
                            <th>Contact</th>
                            <th>Organisation</th>
                            <th>Job title</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($targetUsers as $user)
                            <tr>
                                <td style="text-align: center; vertical-align: middle;">
                                    <input type="checkbox" wire:model.live="selectedTargets" value="{{ $user['id'] }}" class="checkbox" aria-label="Include {{ $user['first_name'] }} {{ $user['last_name'] }}">
                                </td>
                                <td>
                                    <div class="font-semibold text-ink">{{ $user['first_name'] }} {{ $user['last_name'] }}</div>
                                    <div class="text-caption text-muted">{{ $user['email'] }}</div>
                                </td>
                                <td>{{ $user['company_name'] }}</td>
                                <td class="{{ empty($user['job_title']) ? 'text-muted' : '' }}">{{ empty($user['job_title']) ? 'Not given' : $user['job_title'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <strong>No contacts match these filters</strong>
                                        Only subscribed, active contacts can receive broadcasts. Change the filters to see more.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(!$activeLock)
                <div class="flex flex-wrap items-center gap-4">
                    <button type="button" wire:click="dispatchCampaign" class="btn btn-primary" @if($recipientCount === 0 || empty($enhancedBody)) disabled @endif>
                        <i data-lucide="send"></i> Send to {{ $recipientCount }} {{ $recipientCount === 1 ? 'recipient' : 'recipients' }}
                    </button>
                    <div class="help">
                        @if(empty($enhancedBody))
                            Add the email text before you send.
                        @elseif($recipientCount > 0)
                            Sending takes about {{ ceil($recipientCount / 30) * 5 }} minutes.
                        @endif
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if($activeTab === 'queued')
        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Emails waiting to send</h2>
                <button type="button" wire:click="checkSystemLock" class="btn btn-secondary btn-sm"><i data-lucide="refresh-cw"></i> Check status</button>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Queued at</th>
                            <th>Recipient</th>
                            <th>Organisation</th>
                            <th>Subject</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($queuedLogs as $log)
                            <tr>
                                <td style="font-variant-numeric: tabular-nums; white-space: nowrap;">{{ \Carbon\Carbon::parse($log->created_at)->format('j M Y, H:i') }}</td>
                                <td>
                                    <div class="font-semibold text-ink">{{ $log->first_name }} {{ $log->last_name }}</div>
                                    <div class="text-caption text-muted">{{ $log->email }}</div>
                                </td>
                                <td>{{ $log->company_name }}</td>
                                <td>{{ Str::limit($log->subject, 50) }}</td>
                                <td><span class="pill pill-warning">Queued</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <strong>No emails waiting</strong>
                                        When you send a broadcast, its emails wait here and go out in small batches.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($activeTab === 'sent')
        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Sent emails</h2>
                <span class="help">The latest 200</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sent at</th>
                            <th>Recipient</th>
                            <th>Organisation</th>
                            <th>Subject</th>
                            <th class="num" style="text-align: right;">Opens</th>
                            <th>Last opened from</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sentLogs as $log)
                            <tr>
                                <td style="font-variant-numeric: tabular-nums; white-space: nowrap;">{{ \Carbon\Carbon::parse($log->updated_at)->format('j M Y, H:i') }}</td>
                                <td>
                                    <div class="font-semibold text-ink">{{ $log->first_name }}</div>
                                    <div class="text-caption text-muted">{{ $log->email }}</div>
                                </td>
                                <td>{{ $log->company_name }}</td>
                                <td>{{ Str::limit($log->subject, 40) }}</td>
                                <td class="num">
                                    @if(isset($log->open_count) && $log->open_count > 0)
                                        <span class="pill pill-success">{{ $log->open_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-small">
                                    @if(isset($log->last_open_geo) && $log->last_open_geo)
                                        <div class="text-ink">{{ $log->last_open_geo }}</div>
                                        <div class="text-caption text-muted" style="font-variant-numeric: tabular-nums;">{{ $log->last_open_ip }}</div>
                                    @else
                                        <span class="text-muted">Not opened yet</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <strong>No emails sent yet</strong>
                                        Sent emails appear here with how often each one was opened.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($activeTab === 'failed')
        <section class="card" style="padding: 0;">
            <div class="card-header" style="padding: 20px 24px 0;">
                <h2 class="card-title">Failed emails</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Failed at</th>
                            <th>Recipient</th>
                            <th>Organisation</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($failedLogs as $log)
                            <tr>
                                <td style="font-variant-numeric: tabular-nums; white-space: nowrap;">{{ \Carbon\Carbon::parse($log->updated_at)->format('j M Y, H:i') }}</td>
                                <td>
                                    <div class="font-semibold text-ink">{{ $log->first_name }} {{ $log->last_name }}</div>
                                    <div class="text-caption text-muted">{{ $log->email }}</div>
                                </td>
                                <td>{{ $log->company_name }}</td>
                                <td class="text-small" style="color: var(--color-danger);">{{ $log->error_message ?? 'No reason recorded' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <strong>No failed emails</strong>
                                        Emails that bounce or can’t be sent appear here with the reason.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
