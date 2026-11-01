<div class="pp">
    <style>
        /* The Proposals tab (docs/OPPORTUNITIES.md section 4, row 4): only what the shared classes don't cover. Brand tokens only. */
        .pp { display: flex; flex-direction: column; gap: 16px; }
        .pp .alert, .pp .callout { margin: 0; }
        .pp .alert svg, .pp-callout svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; }
        .pp-callout { display: flex; gap: 10px; align-items: flex-start; font-size: 0.875rem; line-height: 1.375rem; }
        .pp-intro { margin: 4px 0 16px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .pp-form { display: flex; flex-direction: column; gap: 16px; }
        .pp-buttons { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .pp-bar { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px 16px; }
        .pp-bar-text { min-width: 0; display: flex; flex-direction: column; gap: 4px; }
        .pp-title { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin: 0; overflow-wrap: anywhere; }
        .pp-title svg { width: 18px; height: 18px; }
        .pp-sub { margin: 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .pp-versions { min-width: 180px; }
        .pp-progress { height: 8px; border-radius: 999px; background: var(--color-mist); overflow: hidden; margin-top: 12px; }
        .pp-progress > span { display: block; height: 100%; border-radius: 999px; background: var(--color-deep-teal); transition: width 300ms ease; }
        .pp-spin { width: 18px; height: 18px; animation: pp-spin 1s linear infinite; }
        @keyframes pp-spin { to { transform: rotate(360deg); } }
        .pp-sections { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
        .pp-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; font-size: 0.8125rem; line-height: 1.25rem; font-weight: 600; color: var(--color-muted); }
        .pp-tools { display: flex; gap: 4px; }
        .pp-tools .btn { padding: 6px; }
        .pp-tools svg { width: 16px; height: 16px; }
        .pp-h { margin: 0 0 8px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .pp-text { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .pp-text p { margin: 0 0 10px; }
        .pp-text ul { margin: 0 0 10px; padding-left: 20px; list-style: disc; }
        .pp-text strong { display: block; margin-bottom: 4px; color: var(--color-ink); }
        .pp-comments { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
        .pp-comment { padding: 10px 12px; border-radius: var(--radius-control); background: var(--color-mist); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-ink); }
        .pp-comment.is-resolved { background: var(--color-paper); border: 1px solid var(--color-line); color: var(--color-slate); }
        .pp-comment-meta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px 8px; margin-bottom: 4px; color: var(--color-muted); }
        .pp-comment p { margin: 0; white-space: pre-line; overflow-wrap: anywhere; }
        .pp-lines { display: flex; flex-direction: column; gap: 12px; }
        .pp-line { display: grid; grid-template-columns: minmax(0, 1fr) 130px 140px 120px auto; gap: 8px 12px; align-items: end; padding-bottom: 12px; border-bottom: 1px solid var(--color-line); }
        .pp-line .field { margin: 0; }
        .pp-line .label { font-size: 0.75rem; }
        .pp-amount { font-size: 0.875rem; line-height: 2.5rem; text-align: right; color: var(--color-ink); font-variant-numeric: tabular-nums; white-space: nowrap; }
        .pp-reason { grid-column: 1 / -1; display: flex; gap: 6px; font-size: 0.75rem; line-height: 1.125rem; color: var(--color-slate); }
        .pp-reason svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; color: var(--color-deep-teal); }
        .pp-add { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 8px 12px; align-items: end; }
        .pp-totals { margin: 0; display: grid; grid-template-columns: 1fr auto; gap: 6px 24px; max-width: 360px; margin-left: auto; font-size: 0.875rem; line-height: 1.375rem; font-variant-numeric: tabular-nums; }
        .pp-totals dt { color: var(--color-slate); }
        .pp-totals dd { margin: 0; text-align: right; color: var(--color-ink); white-space: nowrap; }
        .pp-totals .is-total { padding-top: 6px; border-top: 1px solid var(--color-ink); font-weight: 600; color: var(--color-ink); }
        .pp-terms { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr)); gap: 16px; }
        .pp-table td, .pp-table th { vertical-align: top; }
        .pp-people { display: flex; flex-direction: column; gap: 8px; }
        .pp-check { display: flex; align-items: flex-start; gap: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); cursor: pointer; overflow-wrap: anywhere; }
        .pp-check input { margin-top: 4px; }
        .pp-check small { color: var(--color-muted); }
        .pp-dl { display: grid; grid-template-columns: minmax(120px, auto) 1fr; gap: 6px 16px; margin: 0; font-size: 0.875rem; line-height: 1.375rem; }
        .pp-dl dt { color: var(--color-muted); }
        .pp-dl dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; }
        .pp-saving { font-size: 0.8125rem; color: var(--color-muted); }
        @media (prefers-reduced-motion: reduce) { .pp-spin { animation: none; } .pp-progress > span { transition: none; } }
        @media (max-width: 760px) {
            .pp-line { grid-template-columns: 1fr 1fr; }
            .pp-line .pp-desc { grid-column: 1 / -1; }
            .pp-amount { text-align: left; line-height: 1.375rem; }
            .pp-add { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .pp-buttons .btn, .pp-bar .btn { flex: 1 1 100%; }
            .pp-versions { min-width: 0; width: 100%; }
            .pp-dl { grid-template-columns: 1fr; gap: 2px; }
            .pp-dl dd { margin-bottom: 8px; }
            .pp-totals { max-width: none; }
        }
    </style>

    @php
        $locale = app()->getLocale();
        $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->locale($locale)->translatedFormat(__('opportunities.formats.date')) : '—';
        $money = fn ($amount, $currency) => $locale === 'fr'
            ? number_format((float) $amount, 2, ',', "\u{202F}") . "\u{00A0}" . $currency
            : $currency . "\u{00A0}" . number_format((float) $amount, 2);
        $person = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : __('opportunities.page.system');
        $tone = fn ($status) => match ($status) {
            'Client Approved' => 'pill-success', 'Changes Requested' => 'pill-warning', 'Superseded' => 'pill-neutral', 'Sent' => 'pill-info', default => 'pill-neutral',
        };
        $statusLabel = fn ($p) => $p->status === 'Draft' && $p->approved_at ? __('proposals.statuses.approved') : __('proposals.statuses.' . \Illuminate\Support\Str::snake(str_replace(' ', '', $p->status)));
        $currency = $proposal?->isEditable() ? $this->currency : $proposal?->currency;
        $sectionTitles = $proposal ? collect((array) $proposal->sections)->pluck('title', 'key') : collect();
    @endphp

    @if ($polling)
        <div wire:poll.3s="check" hidden></div>
    @endif

    @if ($notice)
        <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ $notice }}</span></div>
    @endif
    @error('proposal')
        <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
    @enderror

    @if (! $proposal)
        {{-- No proposal v2 yet --}}
        @if ($canEdit)
            <section class="card" aria-labelledby="pp-start-title">
                <h2 class="card-title" id="pp-start-title">{{ __('proposals.start.title') }}</h2>
                <p class="pp-intro">{{ __('proposals.start.intro') }}</p>
                <div class="pp-form">
                    @error('draft')
                        <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                    @enderror
                    @unless ($questionnaireComplete)
                        <div class="callout pp-callout" role="note"><i data-lucide="info"></i><span>{{ __('proposals.start.no_questionnaire') }}</span></div>
                    @endunless
                    @unless ($canDraft)
                        <div class="callout pp-callout" role="note"><i data-lucide="lock"></i><span>{{ __('proposals.start.no_transcripts') }}</span></div>
                    @endunless
                    <div class="field" style="max-width: 240px">
                        <label for="pp-currency" class="label">{{ __('proposals.terms.currency') }}</label>
                        <select id="pp-currency" class="select" wire:model="currency">
                            @foreach ($currencies as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                        @error('currency') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    @if ($canDraft)
                        <div class="field">
                            <label for="pp-instructions" class="label">{{ __('proposals.start.instructions') }}</label>
                            <textarea id="pp-instructions" class="textarea" rows="3" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_NOTE }}" wire:model="instructions"
                                      aria-describedby="pp-instructions-help @error('instructions') pp-instructions-error @enderror" @error('instructions') aria-invalid="true" @enderror></textarea>
                            <span class="help" id="pp-instructions-help">{{ __('proposals.start.instructions_help') }}</span>
                            @error('instructions') <span class="field-error" id="pp-instructions-error">{{ $message }}</span> @enderror
                        </div>
                        <p class="help" style="margin: 0">{{ __('proposals.start.language', ['language' => __('proposals.language_names.' . $o->language)]) }} {{ __('proposals.start.cost') }}</p>
                    @endif
                    <div class="pp-buttons">
                        @if ($canDraft)
                            <button type="button" class="btn btn-primary" wire:click="start(true)" wire:loading.attr="disabled" wire:target="start">
                                <i data-lucide="sparkles"></i> {{ __('proposals.start.ai') }}
                            </button>
                        @endif
                        <button type="button" @class(['btn', 'btn-secondary' => $canDraft, 'btn-primary' => ! $canDraft]) wire:click="start(false)" wire:loading.attr="disabled" wire:target="start">
                            <i data-lucide="file-pen-line"></i> {{ __('proposals.start.blank') }}
                        </button>
                    </div>
                </div>
            </section>
        @else
            <div class="card empty-state">
                <strong>{{ __('proposals.empty_title') }}</strong>
                {{ $o->isClosed() ? __('proposals.closed') : __('proposals.empty_text') }}
            </div>
        @endif

    @else
        {{-- The version on screen --}}
        <section class="card pp-bar" aria-labelledby="pp-title">
            <div class="pp-bar-text">
                <h2 class="card-title pp-title" id="pp-title">
                    {{ __('proposals.heading', ['ref' => $proposal->proposal_ref, 'n' => $proposal->version]) }}
                    <span class="pill {{ $proposal->status === 'Draft' && $proposal->approved_at ? 'pill-info' : $tone($proposal->status) }}">{{ $statusLabel($proposal) }}</span>
                </h2>
                <p class="pp-sub">
                    @if ($proposal->status === 'Draft' && ! $proposal->approved_at)
                        {{ __('proposals.bar.draft', ['date' => $day($proposal->updated_at)]) }}
                        @if ($editing)
                            · <span class="pp-saving" wire:loading.remove wire:target="title,sections,lines,vatRate,schedule,validityDays,changesSummary,currency">{{ __('proposals.editor.saved') }}</span><span class="pp-saving" wire:loading wire:target="title,sections,lines,vatRate,schedule,validityDays,changesSummary,currency">{{ __('proposals.editor.saving') }}</span>
                        @endif
                    @elseif ($proposal->status === 'Draft')
                        {{ __('proposals.bar.approved', ['name' => $person($proposal->approver), 'date' => $day($proposal->approved_at)]) }}
                    @else
                        {{ __('proposals.bar.sent', ['date' => $day($proposal->sent_at), 'until' => $day($proposal->validUntil())]) }}
                    @endif
                </p>
            </div>
            <div class="pp-buttons">
                @if ($versions->count() > 1)
                    <label for="pp-version" class="sr-only">{{ __('proposals.versions.label') }}</label>
                    <select id="pp-version" class="select pp-versions" wire:change="show($event.target.value)">
                        @foreach ($versions as $v)
                            <option value="{{ $v->id }}" @selected($v->id === $proposal->id) wire:key="v-{{ $v->id }}">{{ __('proposals.version', ['n' => $v->version]) }} · {{ $statusLabel($v) }}</option>
                        @endforeach
                    </select>
                @endif
                <a class="btn btn-secondary" href="{{ route('hq.crm.proposal-pdf', ['opportunity' => $o->id, 'proposal' => $proposal->id]) }}" target="_blank" rel="noopener">
                    <i data-lucide="file-text"></i> {{ $proposal->sent_at ? __('proposals.bar.pdf') : __('proposals.bar.preview') }}
                </a>
            </div>
        </section>

        {{-- Where it stands, and the next step --}}
        @if ($drafting)
            <section class="card" aria-labelledby="pp-drafting-title" aria-busy="true">
                <h2 class="card-title pp-title" id="pp-drafting-title"><i data-lucide="loader-circle" class="pp-spin"></i> {{ __('proposals.drafting.title', ['n' => $proposal->version]) }}</h2>
                <p class="pp-intro" style="margin-bottom: 0">{{ __('proposals.drafting.text') }}</p>
                <div class="pp-progress" role="progressbar" aria-label="{{ __('proposals.drafting.progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $proposal->aiRun->progress }}">
                    <span style="width: {{ max(4, (int) $proposal->aiRun->progress) }}%"></span>
                </div>
                @if ($proposal->aiRun->status === 'queued')
                    <p class="help" style="margin: 8px 0 0">{{ __('proposals.drafting.queued') }}</p>
                @endif
            </section>
        @elseif ($draftError)
            <div class="alert alert-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <span>{{ __('proposals.drafting.failed', ['message' => $draftError]) }}
                    @if ($canEdit) <button type="button" class="btn btn-ghost btn-sm" wire:click="dismissFailure('ai_run_id')">{{ __('proposals.dismiss') }}</button> @endif
                </span>
            </div>
        @endif

        @if ($proposal->status === 'Client Approved')
            <div class="alert alert-success" role="note"><i data-lucide="badge-check"></i>
                <span>{{ __('proposals.client.approved', ['name' => $proposal->client_approved_name, 'title' => $proposal->client_approved_title, 'email' => $proposal->client_approved_email, 'date' => $day($proposal->client_approved_at)]) }} {{ __('proposals.client.next') }}</span></div>
        @elseif ($proposal->status === 'Changes Requested')
            <div class="alert alert-warning" role="note"><i data-lucide="message-square-warning"></i><span>{{ __('proposals.client.changes') }}</span></div>
        @elseif ($proposal->status === 'Superseded')
            <div class="callout pp-callout" role="note"><i data-lucide="history"></i><span>{{ __('proposals.client.superseded') }}</span></div>
        @elseif ($proposal->status === 'Sent')
            <div class="callout pp-callout" role="note"><i data-lucide="send"></i><span>{{ $proposal->hasExpired() ? __('proposals.client.expired', ['date' => $day($proposal->validUntil())]) : __('proposals.client.waiting') }}</span></div>
        @endif

        @if ($canStartVersion && $proposal->id === $latest->id)
            <div class="pp-buttons">
                <button type="button" @class(["btn", "btn-primary" => $latest->status === "Changes Requested", "btn-secondary" => $latest->status !== "Changes Requested"]) wire:click="newVersion" wire:loading.attr="disabled" wire:target="newVersion">
                    <i data-lucide="copy-plus"></i> {{ __('proposals.versions.new', ['n' => $latest->version + 1]) }}
                </button>
                <span class="help">{{ __('proposals.versions.new_help') }}</span>
            </div>
        @endif

        @if ($proposal->sent_at)
            <section class="card" aria-labelledby="pp-sent-title">
                <h2 class="card-title" id="pp-sent-title" style="margin-bottom: 12px">{{ __('proposals.send.sent_title') }}</h2>
                <dl class="pp-dl">
                    <dt>{{ __('proposals.send.sent_on') }}</dt><dd>{{ $day($proposal->sent_at) }}</dd>
                    <dt>{{ __('proposals.send.sent_to') }}</dt><dd>{{ collect((array) $proposal->recipients)->map(fn ($r) => ($r['name'] ?? '') . ' <' . ($r['email'] ?? '') . '>')->implode(', ') }}</dd>
                    <dt>{{ __('proposals.terms.valid_until') }}</dt><dd>{{ $day($proposal->validUntil()) }}</dd>
                </dl>
                @if ($proposal->recipients_unsent)
                    <div class="alert alert-warning" role="alert" style="margin-top: 12px">
                        <i data-lucide="mail-warning"></i>
                        <span>{{ __('proposals.send.unsent', ['emails' => implode(', ', (array) $proposal->recipients_unsent)]) }}
                            @if ($canEdit && $proposal->status === 'Sent')
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="resend" wire:loading.attr="disabled" wire:target="resend">{{ __('proposals.send.again') }}</button>
                            @endif
                        </span>
                    </div>
                @endif
                @error('send')
                    <div class="alert alert-danger" role="alert" style="margin-top: 12px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                @enderror
            </section>
        @endif

        {{-- Approved, not sent yet: who it goes to --}}
        @if ($proposal->status === 'Draft' && $proposal->approved_at)
            <section class="card" aria-labelledby="pp-send-title">
                <h2 class="card-title" id="pp-send-title">{{ __('proposals.send.title') }}</h2>
                <p class="pp-intro">{{ __('proposals.send.intro', ['language' => __('proposals.language_names.' . $o->language)]) }}</p>
                @if ($canEdit)
                    <form wire:submit="send" class="pp-form" novalidate>
                        @error('send')
                            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                        @enderror
                        @if ($people)
                            <fieldset class="field" style="border: 0; padding: 0; margin: 0; min-width: 0" aria-describedby="pp-people-help">
                                <legend class="label">{{ __('proposals.send.to') }}</legend>
                                <span class="help" id="pp-people-help">{{ __('proposals.send.to_help') }}</span>
                                <div class="pp-people" style="margin-top: 8px">
                                    @foreach ($people as $email => $p)
                                        <label class="pp-check" wire:key="to-{{ md5($email) }}">
                                            <input type="checkbox" class="checkbox" value="{{ $email }}" wire:model="sendTo">
                                            <span>{{ $p['name'] }} <small>{{ $email }}</small></span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('sendTo') <span class="field-error">{{ $message }}</span> @enderror
                                @error('sendTo.*') <span class="field-error">{{ $message }}</span> @enderror
                            </fieldset>
                            <div class="pp-buttons">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="send">
                                    <i data-lucide="send"></i>
                                    <span wire:loading.remove wire:target="send">{{ __('proposals.send.submit') }}</span>
                                    <span wire:loading wire:target="send">{{ __('proposals.send.sending') }}</span>
                                </button>
                                @if ($canApprove)
                                    <button type="button" class="btn btn-secondary" wire:click="reopen">{{ __('proposals.approve.reopen') }}</button>
                                @endif
                            </div>
                        @else
                            <div class="callout pp-callout" role="note"><i data-lucide="info"></i><span>{{ __('proposals.send.no_people') }}</span></div>
                        @endif
                    </form>
                @else
                    <p class="help" style="margin: 0">{{ __('proposals.send.read_only') }}</p>
                @endif
            </section>
        @endif

        {{-- The content: the editor while it is a draft, else as the client reads it --}}
        @if ($editing)
            @if ($redrafting)
                <section class="card" aria-labelledby="pp-redraft-title">
                    <h2 class="card-title" id="pp-redraft-title">{{ __('proposals.redraft.title') }}</h2>
                    <p class="pp-intro">{{ $proposal->version > 1 ? __('proposals.redraft.intro_version', ['n' => $proposal->version - 1]) : __('proposals.redraft.intro') }}</p>
                    <div class="pp-form">
                        @error('draft')
                            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                        @enderror
                        <div class="field">
                            <label for="pp-redraft-note" class="label">{{ __('proposals.start.instructions') }}</label>
                            <textarea id="pp-redraft-note" class="textarea" rows="3" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_NOTE }}" wire:model="instructions" x-init="$nextTick(() => $el.focus())"
                                      aria-describedby="pp-redraft-help"></textarea>
                            <span class="help" id="pp-redraft-help">{{ __('proposals.start.instructions_help') }} {{ __('proposals.start.cost') }}</span>
                            @error('instructions') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="pp-buttons">
                            <button type="button" class="btn btn-primary" wire:click="draft" wire:loading.attr="disabled" wire:target="draft"><i data-lucide="sparkles"></i> {{ __('proposals.redraft.submit') }}</button>
                            <button type="button" class="btn btn-secondary" wire:click="cancelRedraft">{{ __('opportunities.cancel') }}</button>
                        </div>
                    </div>
                </section>
            @endif

            <section class="card" aria-labelledby="pp-edit-title">
                <div class="pp-head">
                    <h2 class="card-title" id="pp-edit-title" style="margin: 0">{{ __('proposals.editor.title') }}</h2>
                    @if ($canDraft && ! $redrafting)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openRedraft"><i data-lucide="sparkles"></i> {{ __('proposals.redraft.button') }}</button>
                    @endif
                </div>
                <div class="pp-form">
                    <div class="field">
                        <label for="pp-proposal-title" class="label">{{ __('proposals.editor.proposal_title') }}</label>
                        <input id="pp-proposal-title" type="text" class="input" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_TITLE }}" wire:model.blur="title">
                    </div>
                    @if ($proposal->version > 1)
                        <div class="field">
                            <label for="pp-changes" class="label">{{ __('proposals.changes.label', ['n' => $proposal->version - 1]) }}</label>
                            <textarea id="pp-changes" class="textarea" rows="4" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_NOTE }}" wire:model.blur="changesSummary" aria-describedby="pp-changes-help"
                                      @if ($summarising) disabled @endif></textarea>
                            <span class="help" id="pp-changes-help">{{ __('proposals.changes.help') }}</span>
                            @if ($changesError)
                                <span class="field-error">{{ __('proposals.changes.failed', ['message' => $changesError]) }} <button type="button" class="btn btn-ghost btn-sm" wire:click="dismissFailure('changes_run_id')">{{ __('proposals.dismiss') }}</button></span>
                            @endif
                            @error('changes') <span class="field-error">{{ $message }}</span> @enderror
                            <div class="pp-buttons" style="margin-top: 8px">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="summariseChanges" wire:loading.attr="disabled" wire:target="summariseChanges" @disabled($summarising)>
                                    @if ($summarising) <i data-lucide="loader-circle" class="pp-spin"></i> {{ __('proposals.changes.running') }} @else <i data-lucide="sparkles"></i> {{ __('proposals.changes.button') }} @endif
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            @if ($previousComments->has(\App\Support\Opportunities\Proposals::GENERAL))
                <section class="card" aria-labelledby="pp-general-title">
                    <h2 class="card-title" id="pp-general-title">{{ __('proposals.comments.general_previous', ['n' => $proposal->version - 1]) }}</h2>
                    @include('livewire.hq.opportunities.partials.proposal-comments', ['list' => $previousComments[\App\Support\Opportunities\Proposals::GENERAL], 'resolvable' => false])
                </section>
            @endif

            <ol class="pp-sections" aria-label="{{ __('proposals.editor.sections') }}">
                @foreach ($sections as $i => $section)
                    <li class="card" wire:key="s-{{ $i }}-{{ $section['key'] ?? $i }}">
                        <div class="pp-head">
                            <span>{{ __('proposals.editor.section', ['n' => $i + 1]) }}</span>
                            <span class="pp-tools">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="moveSection({{ $i }}, -1)" @disabled($i === 0) aria-label="{{ __('proposals.editor.up', ['n' => $i + 1]) }}" title="{{ __('proposals.editor.up', ['n' => $i + 1]) }}"><i data-lucide="arrow-up"></i></button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="moveSection({{ $i }}, 1)" @disabled($i === count($sections) - 1) aria-label="{{ __('proposals.editor.down', ['n' => $i + 1]) }}" title="{{ __('proposals.editor.down', ['n' => $i + 1]) }}"><i data-lucide="arrow-down"></i></button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="removeSection({{ $i }})" wire:confirm="{{ __('proposals.editor.remove_confirm') }}" aria-label="{{ __('proposals.editor.remove', ['n' => $i + 1]) }}" title="{{ __('proposals.editor.remove', ['n' => $i + 1]) }}"><i data-lucide="trash-2"></i></button>
                            </span>
                        </div>
                        <div class="pp-form">
                            <div class="field">
                                <label for="pp-s-{{ $i }}-title" class="label">{{ __('proposals.editor.section_title') }}</label>
                                <input id="pp-s-{{ $i }}-title" type="text" class="input" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_TITLE }}" wire:model.blur="sections.{{ $i }}.title">
                            </div>
                            <div class="field">
                                <label for="pp-s-{{ $i }}-body" class="label">{{ __('proposals.editor.section_body') }}</label>
                                <textarea id="pp-s-{{ $i }}-body" class="textarea" rows="8" maxlength="{{ \App\Support\Opportunities\Proposals::MAX_BODY }}" wire:model.blur="sections.{{ $i }}.body" aria-describedby="pp-body-help-{{ $i }}"></textarea>
                                <span class="help" id="pp-body-help-{{ $i }}">{{ __('proposals.editor.body_help') }}</span>
                            </div>
                        </div>
                        @if ($previousComments->has($section['key'] ?? ''))
                            <p class="help" style="margin: 12px 0 0">{{ __('proposals.comments.previous', ['n' => $proposal->version - 1]) }}</p>
                            @include('livewire.hq.opportunities.partials.proposal-comments', ['list' => $previousComments[$section['key']], 'resolvable' => false])
                        @endif
                    </li>
                @endforeach
            </ol>
            @if (count($sections) < \App\Support\Opportunities\Proposals::MAX_SECTIONS)
                <div class="pp-buttons"><button type="button" class="btn btn-secondary" wire:click="addSection"><i data-lucide="plus"></i> {{ __('proposals.editor.add_section') }}</button></div>
            @endif

            <section class="card" aria-labelledby="pp-lines-title">
                <h2 class="card-title" id="pp-lines-title">{{ __('proposals.lines.title') }}</h2>
                <p class="pp-intro">{{ __('proposals.lines.intro') }}</p>
                <div class="pp-lines">
                    @foreach ($lines as $i => $line)
                        <div class="pp-line" wire:key="l-{{ $i }}-{{ $line['code'] ?? '' }}">
                            <div class="field pp-desc">
                                <label for="pp-l-{{ $i }}-desc" class="label">{{ __('proposals.lines.item') }}@if (($line['code'] ?? 'CUSTOM') !== 'CUSTOM') · {{ $line['code'] }}@endif</label>
                                <input id="pp-l-{{ $i }}-desc" type="text" class="input" maxlength="255" wire:model.blur="lines.{{ $i }}.description">
                            </div>
                            <div class="field">
                                <label for="pp-l-{{ $i }}-qty" class="label">{{ __('proposals.lines.quantity') }}</label>
                                <input id="pp-l-{{ $i }}-qty" type="text" class="input" maxlength="50" wire:model.blur="lines.{{ $i }}.quantity">
                            </div>
                            <div class="field">
                                <label for="pp-l-{{ $i }}-price" class="label">{{ __('proposals.lines.unit_price') }} ({{ $currency }})</label>
                                <input id="pp-l-{{ $i }}-price" type="number" inputmode="decimal" min="0" step="0.01" class="input" wire:model.blur="lines.{{ $i }}.unit_price">
                            </div>
                            <div class="pp-amount" aria-label="{{ __('proposals.lines.amount') }}">{{ $money(\App\Support\Opportunities\Proposals::units((string) ($line['quantity'] ?? '')) * (float) ($line['unit_price'] ?? 0), $currency) }}</div>
                            <div class="pp-tools">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="removeLine({{ $i }})" aria-label="{{ __('proposals.lines.remove', ['item' => $line['description'] ?? '']) }}" title="{{ __('proposals.lines.remove', ['item' => $line['description'] ?? '']) }}"><i data-lucide="trash-2"></i></button>
                            </div>
                            @if (! empty($reasons[$line['code'] ?? '']))
                                <div class="pp-reason"><i data-lucide="sparkles"></i><span>{{ __('proposals.lines.why', ['reason' => $reasons[$line['code']]]) }}</span></div>
                            @endif
                        </div>
                    @endforeach
                    @if (! $lines)
                        <p class="help" style="margin: 0">{{ __('proposals.lines.none') }}</p>
                    @endif
                </div>

                @if (count($lines) < \App\Support\Opportunities\Proposals::MAX_LINES)
                    <div class="pp-add" style="margin-top: 16px">
                        <div class="field" style="margin: 0">
                            <label for="pp-add-code" class="label">{{ __('proposals.lines.add_label') }}</label>
                            <select id="pp-add-code" class="select" wire:model="addCode" @error('addCode') aria-invalid="true" @enderror>
                                <option value="">{{ __('proposals.lines.choose') }}</option>
                                @foreach ($dictionary as $category => $items)
                                    <optgroup label="{{ $category }}">
                                        @foreach ($items as $item)
                                            <option value="{{ $item->item_code }}">{{ $item->description }} ({{ $item->item_code }})</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('addCode') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <button type="button" class="btn btn-secondary" wire:click="addDictionaryLine"><i data-lucide="plus"></i> {{ __('proposals.lines.add') }}</button>
                        <button type="button" class="btn btn-ghost" wire:click="addCustomLine">{{ __('proposals.lines.add_custom') }}</button>
                    </div>
                @endif

                <div class="pp-terms" style="margin-top: 20px">
                    <div class="field">
                        <label for="pp-terms-currency" class="label">{{ __('proposals.terms.currency') }}</label>
                        <select id="pp-terms-currency" class="select" wire:model.live="currency" aria-describedby="pp-currency-help">
                            @foreach ($currencies as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                        <span class="help" id="pp-currency-help">{{ __('proposals.terms.currency_help') }}</span>
                    </div>
                    <div class="field">
                        <label for="pp-terms-vat" class="label">{{ __('proposals.terms.vat') }}</label>
                        <input id="pp-terms-vat" type="number" inputmode="decimal" min="0" max="100" step="0.01" class="input" wire:model.blur="vatRate">
                    </div>
                    <div class="field">
                        <label for="pp-terms-schedule" class="label">{{ __('proposals.terms.schedule') }}</label>
                        <select id="pp-terms-schedule" class="select" wire:model.live="schedule">
                            @foreach (\App\Support\Opportunities\Proposals::SCHEDULES as $s)
                                <option value="{{ $s }}">{{ __('proposals.schedules.' . $s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="pp-terms-validity" class="label">{{ __('proposals.terms.validity_input') }}</label>
                        <input id="pp-terms-validity" type="number" inputmode="numeric" min="7" max="90" step="1" class="input" wire:model.blur="validityDays">
                    </div>
                </div>
                @include('livewire.hq.opportunities.partials.proposal-totals')
            </section>

            {{-- Approve (owner or lead), or discard this draft --}}
            <section class="card pp-bar" aria-labelledby="pp-approve-title">
                <div class="pp-bar-text">
                    <h2 class="card-title" id="pp-approve-title" style="margin: 0">{{ __('proposals.approve.title') }}</h2>
                    <p class="pp-sub">{{ $canApprove ? __('proposals.approve.intro') : __('proposals.approve.who', ['name' => $person($o->lead)]) }}</p>
                    @error('approve') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="pp-buttons">
                    @if ($versions->count() > 1 || $proposal->version > 1)
                        <button type="button" class="btn btn-ghost" wire:click="discard" wire:confirm="{{ __('proposals.versions.discard_confirm', ['n' => $proposal->version]) }}">{{ __('proposals.versions.discard') }}</button>
                    @endif
                    @if ($canApprove)
                        <button type="button" class="btn btn-primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve"><i data-lucide="check"></i> {{ __('proposals.approve.button') }}</button>
                    @endif
                </div>
            </section>

        @elseif (! $drafting)
            {{-- As the client reads it, with their comments under each section --}}
            <section class="card" aria-labelledby="pp-read-title">
                <h2 class="card-title" id="pp-read-title" style="overflow-wrap: anywhere">{{ $proposal->title }}</h2>
                @if ($proposal->version > 1 && filled($proposal->changes_summary))
                    <div class="pp-text" style="margin-top: 12px">
                        <strong>{{ __('proposals.changes.label', ['n' => $proposal->version - 1]) }}</strong>
                        @include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $proposal->changes_summary])
                    </div>
                @endif
                @if ($comments->has(\App\Support\Opportunities\Proposals::GENERAL))
                    <p class="help" style="margin: 12px 0 0">{{ __('proposals.comments.general') }}</p>
                    @include('livewire.hq.opportunities.partials.proposal-comments', ['list' => $comments[\App\Support\Opportunities\Proposals::GENERAL], 'resolvable' => $canEdit])
                @endif
            </section>
            <ol class="pp-sections" aria-label="{{ __('proposals.editor.sections') }}">
                @foreach ((array) $proposal->sections as $section)
                    <li class="card" wire:key="r-{{ $section['key'] }}">
                        <h3 class="pp-h">{{ $section['title'] }}</h3>
                        <div class="pp-text">@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $section['body']])</div>
                        @if ($comments->has($section['key']))
                            @include('livewire.hq.opportunities.partials.proposal-comments', ['list' => $comments[$section['key']], 'resolvable' => $canEdit])
                        @endif
                    </li>
                @endforeach
            </ol>
            <section class="card" aria-labelledby="pp-lines-read-title">
                <h2 class="card-title" id="pp-lines-read-title">{{ __('proposals.lines.title') }}</h2>
                @if ($readLines->isNotEmpty())
                    <div class="table-wrap" style="margin-top: 8px">
                        <table class="table pp-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('proposals.lines.item') }}</th>
                                    <th scope="col">{{ __('proposals.lines.quantity') }}</th>
                                    <th scope="col" class="num">{{ __('proposals.lines.unit_price') }}</th>
                                    <th scope="col" class="num">{{ __('proposals.lines.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($readLines as $line)
                                    <tr>
                                        <td>{{ $line->description }}@if ($line->code !== 'CUSTOM') <span class="help">· {{ $line->code }}</span>@endif</td>
                                        <td>{{ $line->quantity }}</td>
                                        <td class="num">{{ $money($line->unit_price, $proposal->currency) }}</td>
                                        <td class="num">{{ $money($line->total_price, $proposal->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="help" style="margin: 8px 0 0">{{ __('proposals.lines.none') }}</p>
                @endif
                @include('livewire.hq.opportunities.partials.proposal-totals')
                <dl class="pp-dl" style="margin-top: 16px">
                    <dt>{{ __('proposals.terms.schedule') }}</dt><dd>{{ \Illuminate\Support\Facades\Lang::has('proposals.schedules.' . $proposal->payment_schedule_type) ? __('proposals.schedules.' . $proposal->payment_schedule_type) : $proposal->payment_schedule_type }}</dd>
                    <dt>{{ __('proposals.terms.validity_days') }}</dt><dd>{{ trans_choice('proposals.terms.days', (int) $proposal->validity_days, ['count' => $proposal->validity_days]) }}</dd>
                </dl>
            </section>

            @if ($proposal->status === 'Draft' && ! $proposal->approved_at && ! $canEdit)
                <p class="help" style="margin: 0">{{ $o->isClosed() ? __('proposals.closed') : __('proposals.read_only') }}</p>
            @endif
        @endif
    @endif

    @if ($legacy->isNotEmpty())
        <section class="card" style="padding: 0" aria-labelledby="pp-legacy-title">
            <div style="padding: 16px 16px 0">
                <h2 class="card-title" id="pp-legacy-title">{{ __('proposals.legacy.title') }}</h2>
                <p class="pp-intro" style="margin-bottom: 8px">{{ __('proposals.legacy.intro') }}</p>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('opportunities.proposals.reference') }}</th>
                            <th scope="col">{{ __('opportunities.proposals.title') }}</th>
                            <th scope="col">{{ __('opportunities.proposals.status') }}</th>
                            <th scope="col" class="num">{{ __('opportunities.proposals.value') }}</th>
                            <th scope="col">{{ __('opportunities.proposals.created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($legacy as $p)
                            <tr wire:key="legacy-{{ $p->id }}">
                                <td>
                                    @if ($canOpenLegacy)
                                        <a href="{{ route('hq.crm.proposal', ['id' => $p->id]) }}" wire:navigate>{{ $p->proposal_ref ?: __('opportunities.proposals.draft_ref') }}</a>
                                    @else
                                        {{ $p->proposal_ref ?: __('opportunities.proposals.draft_ref') }}
                                    @endif
                                </td>
                                <td>{{ $p->title ?: '—' }}</td>
                                <td>{{ __('opportunities.proposals.statuses.' . \Illuminate\Support\Str::snake(str_replace(' ', '', $p->status))) }}</td>
                                <td class="num">{{ $money($p->total_value, $p->currency) }}</td>
                                <td style="white-space: nowrap">{{ $day($p->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
