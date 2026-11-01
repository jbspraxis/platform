{{-- The points raised in a milestone's handover meeting (PR 6): the AI's list, the lead's review (edit, create the issue,
     leave out), then each issue's status for the sign-off. $m meeting, $pointList (Handovers::points), $pointRuns,
     $canManage, $canReadTranscripts, $canReview, $canSeeProject (task titles), $taskOptions, $closingNotes, $signoff,
     $canQa, $day. --}}
@php
    $latest = $m->transcripts->last();
    // Under way on any of the meeting's transcripts (filing included): nothing to confirm in bulk, nothing to ask again
    $pendingRun = $m->transcripts->map(fn ($t) => $pointRuns[$t->id] ?? null)->filter()->first(fn ($r) => \App\Support\Opportunities\Handovers::isPending($r));
    $run = $pendingRun ?? ($latest ? ($pointRuns[$latest->id] ?? null) : null);
    $busy = $pendingRun !== null;
    $drafts = $pointList->where('status', 'draft')->values();
    $confirmed = $pointList->where('status', 'confirmed')->values();
    $dismissed = $pointList->where('status', 'dismissed')->values();
    $canDraft = $canManage && $canReadTranscripts && $latest;
    $issueTone = fn ($status) => match ($status) { 'Open' => 'pill-warning', 'Investigating' => 'pill-info', 'Resolved', 'Closed' => 'pill-success', default => 'pill-neutral' };
    $severityTone = fn ($severity) => match ($severity) { 'Critical' => 'pill-danger', 'Medium' => 'pill-warning', default => 'pill-neutral' };
    $taskIds = $taskOptions->pluck('id')->all();
@endphp
<section class="mtg-points" aria-labelledby="pts-{{ $m->id }}" wire:key="pts-{{ $m->id }}">
    <div class="mtg-points-head">
        <h4 class="mtg-sub" id="pts-{{ $m->id }}" style="margin: 0">{{ __('handover.points.title') }}</h4>
        <div class="mtg-buttons">
            @if ($signoff)
                <button type="button" class="btn btn-secondary btn-sm" x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($signoff)).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                    <i data-lucide="copy"></i>
                    <span x-show="!copied">{{ __('handover.actions.copy') }}</span>
                    <span x-show="copied" x-cloak role="status">{{ __('handover.actions.copied') }}</span>
                </button>
            @endif
            @if ($canReview)
                <button type="button" class="btn btn-secondary btn-sm" wire:click="openAddPoint('{{ $m->id }}')">
                    <i data-lucide="plus"></i> {{ __('handover.actions.add') }}
                </button>
                @if ($drafts->count() > 1 && ! $busy)
                    {{-- The points on screen, by id: a point that arrives after this render isn't confirmed with them --}}
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirmAll('{{ $m->id }}', [{{ $drafts->map(fn ($p) => "'" . $p->id . "'")->implode(', ') }}])" wire:confirm="{{ __('handover.actions.confirm_all_ask') }}"
                            wire:loading.attr="disabled" wire:target="confirmAll">
                        <i data-lucide="list-checks"></i> {{ trans_choice('handover.actions.confirm_all', $drafts->count(), ['count' => $drafts->count()]) }}
                    </button>
                @endif
            @endif
        </div>
    </div>
    <p class="help" style="margin: 0">{{ $canReview ? __('handover.points.help') : __('handover.points.help_read') }}</p>

    {{-- The AI's list: drafting, failed, none found, or not asked for yet --}}
    @if ($busy)
        <div>
            <div class="mtg-status" role="status"><i data-lucide="loader-circle" class="animate-spin"></i><span>{{ __('handover.points.drafting') }}</span></div>
            <div class="mtg-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $run->progress }}" aria-label="{{ __('handover.points.drafting') }}"><span style="width: {{ max(5, (int) $run->progress) }}%"></span></div>
        </div>
    @elseif (! $latest)
        <div class="mtg-status"><i data-lucide="clock"></i><span>{{ __('handover.points.waiting') }}</span></div>
    @else
        @php
            $foundNone = $run?->status === 'done' && empty($run->output['points'] ?? []) && $drafts->isEmpty();
            $listed = $run || $pointList->isNotEmpty();
        @endphp
        @if ($run?->status === 'failed' || $foundNone || ! $listed)
            <div class="mtg-status">
                <i data-lucide="{{ $run?->status === 'failed' ? 'circle-alert' : 'sparkles' }}"></i>
                <span>{{ $run?->status === 'failed' ? __('handover.points.failed', ['reason' => $run->errorMessage()]) : ($foundNone ? __('handover.points.none_found') : __('handover.points.not_drafted')) }}</span>
            </div>
        @endif
        @if ($canDraft)
            <div class="mtg-buttons">
                <button type="button" class="btn {{ $listed ? 'btn-ghost' : 'btn-secondary' }} btn-sm" wire:click="draftPoints('{{ $latest->id }}')"
                        wire:loading.attr="disabled" wire:target="draftPoints('{{ $latest->id }}')" @if ($drafts->isNotEmpty()) aria-describedby="pts-redraft-{{ $m->id }}" @endif>
                    <i data-lucide="{{ $listed ? 'refresh-cw' : 'sparkles' }}"></i>
                    {{ $run?->status === 'failed' ? __('handover.points.retry') : ($listed ? __('handover.points.redraft') : __('handover.points.draft')) }}
                </button>
                @if ($drafts->isNotEmpty())
                    <span class="help" id="pts-redraft-{{ $m->id }}" style="align-self: center">{{ __('handover.points.redraft_help') }}</span>
                @endif
            </div>
        @endif
    @endif

    {{-- To review --}}
    @if ($drafts->isNotEmpty())
        <p class="mtg-notes-label" id="pts-review-{{ $m->id }}" style="margin: 4px 0 0">{{ __('handover.points.statuses.draft') }}</p>
        <ol class="mtg-point-list" aria-labelledby="pts-review-{{ $m->id }}">
            @foreach ($drafts as $p)
                <li class="mtg-point" wire:key="pt-{{ $p->id }}">
                    @if ($canReview)
                        <div class="mtg-point-grid">
                            <div class="field mtg-point-wide">
                                <label for="pt-title-{{ $p->id }}" class="label">{{ __('handover.fields.title') }}</label>
                                <input id="pt-title-{{ $p->id }}" type="text" class="input" maxlength="{{ \App\Support\Opportunities\Handovers::MAX_TITLE }}" wire:model.blur="points.{{ $p->id }}.title"
                                       @error('points.' . $p->id . '.title') aria-invalid="true" aria-describedby="pt-title-{{ $p->id }}-error" @enderror>
                                @error('points.' . $p->id . '.title') <span class="field-error" id="pt-title-{{ $p->id }}-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="pt-sev-{{ $p->id }}" class="label">{{ __('handover.fields.severity') }}</label>
                                <select id="pt-sev-{{ $p->id }}" class="select" wire:model.blur="points.{{ $p->id }}.severity" @error('points.' . $p->id . '.severity') aria-invalid="true" @enderror>
                                    @foreach (\App\Support\Opportunities\Handovers::SEVERITIES as $s)
                                        <option value="{{ $s }}">{{ __('handover.severity.' . $s) }}</option>
                                    @endforeach
                                </select>
                                @error('points.' . $p->id . '.severity') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label for="pt-by-{{ $p->id }}" class="label">{{ __('handover.fields.raised_by') }}</label>
                                <input id="pt-by-{{ $p->id }}" type="text" class="input" maxlength="{{ \App\Support\Opportunities\Handovers::MAX_RAISED_BY }}" wire:model.blur="points.{{ $p->id }}.raised_by"
                                       @error('points.' . $p->id . '.raised_by') aria-invalid="true" @enderror>
                                @error('points.' . $p->id . '.raised_by') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field mtg-point-wide">
                                <label for="pt-task-{{ $p->id }}" class="label">{{ __('handover.fields.task') }}</label>
                                <select id="pt-task-{{ $p->id }}" class="select" wire:model.blur="points.{{ $p->id }}.task_id" @error('points.' . $p->id . '.task_id') aria-invalid="true" @enderror>
                                    <option value="">{{ __('handover.fields.no_task') }}</option>
                                    @if ($p->task_id && ! in_array($p->task_id, $taskIds, true))
                                        <option value="{{ $p->task_id }}">{{ $p->task_title }}</option>
                                    @endif
                                    @foreach ($taskOptions as $task)
                                        <option value="{{ $task->id }}">{{ $task->title }}</option>
                                    @endforeach
                                </select>
                                @error('points.' . $p->id . '.task_id') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field mtg-point-wide">
                                <label for="pt-detail-{{ $p->id }}" class="label">{{ __('handover.fields.detail') }}</label>
                                <textarea id="pt-detail-{{ $p->id }}" class="textarea" rows="3" maxlength="{{ \App\Support\Opportunities\Handovers::MAX_DETAIL }}" wire:model.blur="points.{{ $p->id }}.detail"
                                          @error('points.' . $p->id . '.detail') aria-invalid="true" @enderror></textarea>
                                @error('points.' . $p->id . '.detail') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="mtg-buttons" style="margin-top: 12px">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="confirmPoint('{{ $p->id }}')" wire:loading.attr="disabled" wire:target="confirmPoint('{{ $p->id }}')">
                                <i data-lucide="circle-check"></i> {{ __('handover.actions.confirm') }}
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="dismissPoint('{{ $p->id }}')" wire:loading.attr="disabled" wire:target="dismissPoint('{{ $p->id }}')">
                                <i data-lucide="x"></i> {{ __('handover.actions.dismiss') }}
                            </button>
                            @if ($p->origin === 'lead')<span class="tag" style="align-self: center">{{ __('handover.points.by_hand') }}</span>@endif
                        </div>
                    @else
                        <div class="mtg-point-read">
                            <strong>{{ $p->title }}</strong>
                            <div class="mtg-tags" style="margin-top: 4px">
                                <span class="pill {{ $severityTone($p->severity) }}">{{ __('handover.severity.' . $p->severity) }}</span>
                                @if ($canSeeProject && $p->task_title)<span>{{ $p->task_title }}</span>@endif
                                @if ($p->raised_by)<span>{{ $p->raised_by }}</span>@endif
                            </div>
                            @if ($p->detail)<p class="mtg-pre" style="margin-top: 6px">{{ $p->detail }}</p>@endif
                            <p class="help" style="margin: 6px 0 0">{{ __('handover.points.awaiting') }}</p>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    {{-- Confirmed: each issue and how it stands, for the sign-off --}}
    @if ($confirmed->isNotEmpty())
        <p class="mtg-notes-label" id="pts-issues-{{ $m->id }}" style="margin: 4px 0 0">{{ __('handover.points.issues') }}</p>
        <ol class="mtg-issue-list" aria-labelledby="pts-issues-{{ $m->id }}">
            @foreach ($confirmed as $p)
                @php
                    $gone = $p->ticket_status === null || $p->ticket_deleted_at !== null;
                    $closed = ! $gone && in_array($p->ticket_status, ['Resolved', 'Closed'], true);
                    $statusLabel = $gone ? __('handover.points.removed') : __('portal.status.issue.' . $p->ticket_status);
                @endphp
                <li wire:key="pi-{{ $p->id }}">
                    <div class="mtg-issue-head">
                        <strong>{{ $p->title }}</strong>
                        <span class="pill {{ $severityTone($p->severity) }}">{{ __('handover.severity.' . $p->severity) }}</span>
                        <span class="pill {{ $gone ? 'pill-neutral' : $issueTone($p->ticket_status) }}">
                            {{ $closed ? __('handover.points.closed_on', ['status' => $statusLabel, 'date' => $day($p->ticket_resolved_at ?? $p->ticket_updated_at, $m->time_zone)]) : __('handover.points.issue', ['status' => $statusLabel]) }}
                        </span>
                    </div>
                    <div class="mtg-tags">
                        @if ($canSeeProject && $p->task_title)<span>{{ __('handover.points.labelled', ['label' => __('handover.fields.task'), 'value' => $p->task_title]) }}</span>@endif
                        @if ($p->raised_by)<span>{{ __('handover.points.labelled', ['label' => __('handover.fields.raised_by'), 'value' => $p->raised_by]) }}</span>@endif
                        @if ($canQa && ! $gone)
                            <a href="{{ route('hq.ops.qa', ['ticket' => $p->qa_ticket_id]) }}" wire:navigate>{{ __('handover.points.open_issue') }}</a>
                        @endif
                    </div>
                    @if (isset($closingNotes[$p->qa_ticket_id]))
                        <p class="mtg-pre" style="margin-top: 4px">{{ __('handover.points.reply', ['reply' => $closingNotes[$p->qa_ticket_id]]) }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($dismissed->isNotEmpty())
        <details class="mtg-details">
            <summary>{{ __('handover.points.left_out', ['count' => $dismissed->count()]) }}</summary>
            <ul class="mtg-left-out">
                @foreach ($dismissed as $p)
                    <li wire:key="pd-{{ $p->id }}">
                        <span>{{ $p->title }}</span>
                        @if ($canReview)
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="restorePoint('{{ $p->id }}')" wire:loading.attr="disabled" wire:target="restorePoint('{{ $p->id }}')">
                                <i data-lucide="rotate-ccw"></i> {{ __('handover.actions.restore') }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($pointList->isEmpty() && $latest && ! $busy && $run?->status !== 'done' && ! $canDraft)
        <p class="help" style="margin: 0">{{ __('handover.points.empty') }}</p>
    @endif
</section>
