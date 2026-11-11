@php
    use App\Livewire\Hq\Campaigns\Outreach\OutreachReplies;
    use App\Models\Campaigns\OutreachReply;
    use App\Support\Campaigns\Outreach\Senders;
    use App\Support\Campaigns\Outreach\Sorting;

    $wat = fn ($at) => $at ? $at->copy()->timezone('Africa/Lagos')->format('j M, H:i') : '';
    $day = fn ($date) => $date ? $date->format('D j M Y') : '';
    $page = $campaignId === null;
    $n = fn (int $v) => number_format($v);
    // The label cell of a row: [tone, text, is it Claude's suggestion still to confirm]
    $tag = function (OutreachReply $r) {
        if ($r->label) {
            return [OutreachReply::TONES[$r->label] ?? 'pill-neutral', OutreachReply::labelName($r->label), false];
        }
        if ($r->ai_label) {
            return ['pill-neutral', OutreachReply::labelName($r->ai_label), true];
        }

        return ['pill-warning', $r->ai_run_id ? 'Sorting…' : 'To sort', false];
    };
@endphp
<div class="orp" @if ($busy) wire:poll.4s="tick" @endif>
    @assets
    <style>
        /* Outreach replies (docs/CAMPAIGNS.md section 12): only what the shared component classes don't cover. Brand tokens only. */
        .orp-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 0.875rem; color: var(--color-deep-teal); text-decoration: none; }
        .orp-back svg { width: 16px; height: 16px; }
        .orp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .orp-stats .card { padding: 14px 16px; }
        .orp-stats .stat-value { font-size: 1.25rem; line-height: 1.75rem; }
        .orp-stats small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .orp-filters { margin-bottom: 16px; max-width: 100%; overflow-x: auto; }
        .orp-filters .segmented-item { white-space: nowrap; }
        .orp-filters .segmented-item span { color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .orp-table tbody tr { cursor: pointer; }
        .orp-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .orp-who { all: unset; cursor: pointer; font-weight: 600; color: var(--color-ink); }
        .orp-who:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; border-radius: 2px; }
        .orp-table td:nth-child(1) { min-width: 160px; }
        .orp-why { max-width: 340px; color: var(--color-slate); }
        .orp-ai { display: inline-flex; align-items: center; gap: 4px; border-style: dashed; }
        .orp-ai svg { width: 12px; height: 12px; }
        .orp-modal { max-width: 720px; }
        .orp-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .orp-close { all: unset; cursor: pointer; display: grid; place-items: center; width: 32px; height: 32px; border-radius: var(--radius-control); color: var(--color-muted); }
        .orp-close:hover { background: var(--color-mist); color: var(--color-ink); }
        .orp-close:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; }
        .orp-close svg { width: 18px; height: 18px; }
        .orp-meta { margin: 0 0 16px; font-size: 0.875rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .orp-claude { margin: 0 0 12px; padding: 12px 14px; border-radius: var(--radius-card); background: var(--color-mist); font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .orp-claude p { margin: 0 0 4px; }
        .orp-claude p:last-child { margin: 0; }
        .orp-claude strong { color: var(--color-ink); font-weight: 600; }
        .orp-claude .orp-by { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--color-ink); }
        .orp-claude .orp-by svg { width: 14px; height: 14px; color: var(--color-deep-teal); }
        .orp-labels { display: flex; flex-wrap: wrap; gap: 8px; }
        .orp-labels .btn[aria-pressed="true"] { box-shadow: 0 0 0 2px var(--color-teal-tint); }
        .orp-suggested { border-style: dashed; }
        .orp-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-line); }
        .orp-row { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: flex-end; }
        .orp-row .field { margin: 0; }
        .orp-row .input { width: 180px; }
        .orp-lead { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; justify-content: space-between; }
        .orp-lead p { margin: 0; font-size: 0.875rem; color: var(--color-slate); flex: 1 1 260px; }
        .orp-mail { border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; margin-top: 16px; }
        .orp-mail h3 { margin: 0 0 4px; font-size: 0.8125rem; font-weight: 600; color: var(--color-muted); }
        .orp-subj { margin: 0 0 12px; font-size: 1rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .orp-frame { display: block; width: 100%; height: 160px; border: 0; background: var(--color-paper); }
        .orp-text .mr-text { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 0.875rem; line-height: 1.55; color: var(--color-ink); }
        .orp-text .hq-quote { margin-top: 12px; }
        .orp-text .hq-quote > summary { cursor: pointer; display: inline-block; padding: 2px 10px; border-radius: 999px; background: var(--color-mist); color: var(--color-slate); font-size: 0.75rem; list-style: none; }
        .orp-text .hq-quote .mr-text { color: var(--color-slate); border-left: 2px solid var(--color-line); padding-left: 12px; margin-top: 8px; }
        .orp-body { font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-ink); overflow-wrap: anywhere; }
        .orp-body p { margin: 0 0 10px; }
        .orp-orig { margin-top: 12px; }
        .orp-orig > summary { cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--color-deep-teal); }
        .orp-orig .orp-mail { margin-top: 8px; }
        .orp-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 20px; }
        .orp-actions .orp-left { margin-right: auto; }
        @media (max-width: 1023px) { .orp-hide-md { display: none; } }
        @media (max-width: 767px) {
            .orp-hide-sm { display: none; }
            .orp-labels .btn { flex: 1 1 calc(50% - 8px); justify-content: center; }
            .orp-row .input { width: 100%; }
            .orp-row .field, .orp-row .btn { flex: 1 1 100%; }
            .orp-actions .btn { flex: 1 1 auto; }
            .orp-actions .orp-left { margin-right: 0; flex-basis: 100%; }
        }
    </style>
    @endassets

    @if ($page)
        <a href="{{ route('hq.campaigns.outreach') }}" class="orp-back" wire:navigate><i data-lucide="arrow-left"></i> Outreach</a>
        <div class="page-header">
            <div>
                <h1 class="page-title">Replies</h1>
                <p class="page-subtitle">Replies to the outreach emails of {{ $campaigns === 1 ? 'your campaign' : 'your ' . $campaigns . ' campaigns' }}. Claude suggests a label for each; nothing happens until you confirm it or choose another. Bounce notices and spam reports are filed by themselves.</p>
            </div>
        </div>
        <div class="orp-stats">
            @foreach ([['Replies', $n($stats['replies']), $stats['sent'] ? $stats['reply_rate'] . '% of ' . $n($stats['sent']) . ' delivered' : null], ['To sort', $n($stats['to_sort']), null],
                       ['Interested', $n($stats['interested']), null], ['Not now', $n($stats['not_now']), $reminders ? $reminders . ' ' . ($reminders === 1 ? 'reminder' : 'reminders') . ' set' : null],
                       ['Not interested', $n($stats['not_interested']), null], ['Leads made', $n($stats['leads']), null]] as [$label, $value, $sub])
                <div class="card"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div>@if ($sub)<small>{{ $sub }}</small>@endif</div>
            @endforeach
        </div>
    @else
    @endif

    <div class="segmented orp-filters" role="group" aria-label="Show replies">
        @foreach (OutreachReplies::FILTERS as $key => $label)
            @if ($key !== 'notices' || $counts['notices'] > 0)
                <button type="button" class="segmented-item" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" wire:click="$set('filter', '{{ $key }}')">{{ $label }} <span>{{ $n($counts[$key]) }}</span></button>
            @endif
        @endforeach
    </div>

    <div class="card" style="padding: 0">
        @if ($rows->isEmpty())
            <div class="empty-state">
                @if ($counts['all'] === 0 && $counts['notices'] === 0)
                    <strong>No replies yet</strong>
                    <p>When someone answers an outreach email, their reply shows here within a minute or two of reaching the mailbox, with Claude’s suggested label. Their follow-up is cancelled as soon as it arrives.</p>
                @elseif ($filter === 'sort')
                    <strong>All sorted</strong>
                    <p>Every reply has a label a person confirmed.</p>
                @else
                    <p>No replies {{ $filter === 'all' ? '' : 'sorted as ' . strtolower(OutreachReplies::FILTERS[$filter]) }} here.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="table orp-table">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Label</th>
                            <th class="orp-hide-md">Why</th>
                            <th class="orp-hide-sm">Received</th>
                            <th class="orp-hide-sm">Next</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php $p = $r->prospect; [$tone, $text, $suggested] = $tag($r); @endphp
                            <tr wire:key="orp-{{ $r->id }}" wire:click="openReply({{ $r->id }})" @class(['is-selected' => $openId === $r->id])>
                                <td>
                                    <button type="button" class="orp-who" wire:click.stop="openReply({{ $r->id }})">{{ $p?->fullName() ?: ($r->from_name ?: $r->from_email) }}</button>
                                    <small>{{ $p?->company }}@if ($page && $r->campaign) · {{ $r->campaign->name }}@endif</small>
                                </td>
                                <td>
                                    @if ($suggested)
                                        <span class="pill {{ $tone }} orp-ai" title="Claude’s suggestion: confirm it or choose another"><i data-lucide="sparkles" aria-hidden="true"></i> {{ $text }}?</span>
                                        <small>To confirm</small>
                                    @else
                                        <span class="pill {{ $tone }}">{{ $text }}</span>
                                    @endif
                                </td>
                                <td class="orp-hide-md"><div class="orp-why">{{ $r->kind === 'reply' ? ($r->ai_reason ?? ($r->ai_error ? 'Claude couldn’t sort it' : '')) : $r->note }}</div></td>
                                <td class="orp-hide-sm">{{ $wat($r->received_at) }}</td>
                                <td class="orp-hide-sm">
                                    @if ($p?->organization_id)
                                        <a href="{{ route('hq.crm.lead', ['organization' => $p->organization_id]) }}" wire:navigate wire:click.stop>Open the lead</a>
                                    @elseif ($p?->reply_label === 'not_now' && $p->remind_on)
                                        <small>Reminder {{ $p->remind_on->format('j M Y') }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($more > 0)
                <div class="p-4 text-center"><button type="button" class="btn btn-secondary" wire:click="showMore">Show {{ min($more, 100) }} more</button></div>
            @endif
        @endif
    </div>

    {{-- One reply in a small dialog: Claude's label and reason, confirm or change, Not now, Make a lead, the text, Open in Mail --}}
    @if ($open && $openProspect)
        @php
            $isReply = $open->isReply();
            $isBusy = Sorting::busy($open);
            $senderName = Senders::firstName($openSender);
            $first = $sent->firstWhere('kind', 'first');
        @endphp
        <div class="modal-backdrop" wire:click.self="closeReply" @keydown.escape.window="$wire.closeReply()">
            <div class="modal orp-modal" role="dialog" aria-modal="true" aria-labelledby="orp-title" wire:key="orp-dialog-{{ $open->id }}">
                <div class="orp-head">
                    <h2 class="modal-title" id="orp-title">{{ $isReply ? 'Reply from' : ($open->kind === 'bounce' ? 'Bounce notice for' : 'Spam report about') }} {{ $openProspect->fullName() }}</h2>
                    <button type="button" class="orp-close" wire:click="closeReply" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <p class="orp-meta">{{ $openProspect->company }} · {{ $open->from_email }} · {{ $wat($open->received_at) }}
                    @if ($page && $openCampaign) · {{ $openCampaign->name }} @endif</p>

                @error('dialog') <div class="alert alert-danger mb-4" role="alert"><span>{{ $message }}</span></div> @enderror

                <div class="orp-claude" role="status">
                    @if (! $isReply)
                        <p><span class="pill {{ OutreachReply::TONES[$open->label] ?? 'pill-danger' }}">{{ OutreachReply::labelName($open->label) }}</span>
                            Filed by HQ from the mail server’s {{ $open->kind === 'bounce' ? 'bounce notice' : 'spam report' }}{{ $open->label_by ? '' : ', ' . $wat($open->label_at) }}.</p>
                        @if ($open->note)<p>{{ $open->note }}</p>@endif
                        <p>{{ $open->kind === 'bounce' ? 'The address is on the do-not-email list as bounced, and nothing more goes to it.' : 'The address is on the do-not-email list, and ' . $senderName . '’s outreach paused until the owner resumes it.' }}</p>
                    @elseif ($isBusy)
                        <p class="orp-by"><i data-lucide="loader"></i> Claude is reading the reply. Its label shows here in a moment.</p>
                    @elseif ($open->ai_label)
                        <p><span class="orp-by"><i data-lucide="sparkles"></i> Claude suggests</span> <span class="pill {{ OutreachReply::TONES[$open->ai_label] }}">{{ OutreachReply::labelName($open->ai_label) }}</span></p>
                        @if ($open->ai_reason)<p>{{ $open->ai_reason }}</p>@endif
                    @elseif ($open->ai_error)
                        <p><strong>Claude couldn’t sort this reply.</strong> {{ __($open->ai_error) }} Choose the label yourself, or
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="askAgain"><i data-lucide="rotate-ccw"></i> Ask Claude again</button></p>
                    @else
                        <p>Waiting for Claude’s suggestion.</p>
                    @endif
                    @if ($isReply && $open->label)
                        <p><strong>{{ $open->label === 'complained' ? 'Recorded as a spam complaint' : 'Sorted as ' . OutreachReply::labelName($open->label) }}</strong>
                            by {{ \App\Models\User::find($open->label_by)?->name ?? 'someone' }}, {{ $wat($open->label_at) }}@if ($open->ai_label && $open->ai_label !== $open->label && $open->label !== 'complained') (Claude suggested {{ OutreachReply::labelName($open->ai_label) }})@endif.</p>
                    @endif
                </div>

                @if ($isReply && $open->label !== 'complained')
                    <div class="orp-labels" role="group" aria-label="{{ $open->label ? 'Change the label' : 'Confirm or choose the label' }}">
                        @foreach (OutreachReply::LABELS as $key => $label)
                            @php $chosen = $open->label === $key; $suggested = ! $open->label && $open->ai_label === $key; @endphp
                            <button type="button" @class(['btn', 'btn-primary' => $chosen || $suggested, 'btn-secondary' => ! $chosen && ! $suggested])
                                    aria-pressed="{{ $chosen ? 'true' : 'false' }}" wire:click="choose('{{ $key }}')" wire:loading.attr="disabled" wire:target="choose"
                                    @if ($key === 'unsubscribe') title="Puts their address on the do-not-email list for good: only they can undo it" @endif>
                                @if ($chosen)<i data-lucide="check"></i>@endif
                                {{ $suggested ? 'Confirm: ' . $label : $label }}
                            </button>
                        @endforeach
                    </div>
                    <p class="help mt-2 mb-0">One click {{ $open->label ? 'changes it' : 'confirms it' }}. Unsubscribe puts {{ $openProspect->first_name }}’s address on the do-not-email list for good.@if ($suppressed && in_array($suppressed->reason, ['unsubscribed', 'complained'], true) && $open->label !== 'unsubscribe') Their address is already on it ({{ $suppressed->reason }}): only they can undo that.@endif</p>

                    <div class="orp-section">
                        <div class="orp-row">
                            <div class="field">
                                <label class="label" for="orp-remind">{{ $openProspect->reply_label === 'not_now' ? 'Reminder' : 'Not now: remind me on' }}</label>
                                <input id="orp-remind" type="date" class="input" wire:model="remindOn" min="{{ now('Africa/Lagos')->addDay()->format('Y-m-d') }}"
                                       @error('remindOn') aria-invalid="true" aria-describedby="orp-remind-error" @enderror>
                            </div>
                            @if ($openProspect->reply_label === 'not_now')
                                <button type="button" class="btn btn-secondary" wire:click="saveReminder"><i data-lucide="bell"></i> Save the date</button>
                            @endif
                        </div>
                        @error('remindOn') <p class="field-error" id="orp-remind-error">{{ $message }}</p> @enderror
                        <p class="help mt-2 mb-0">
                            @if ($openProspect->reply_label === 'not_now' && $openProspect->remind_on)
                                {{ $openProspect->reminded_at ? 'The bell rang on ' . $wat($openProspect->reminded_at) . '. A new date rings again.' : $senderName . ' gets a bell on ' . $day($openProspect->remind_on) . ' at 07:30 (Lagos), once.' }}
                                Nothing goes to them by itself: write again from Mail when the time comes.
                            @else
                                Used when you choose Not now: {{ $open->ai_remind_on ? 'the day they named' : 'in ' . Sorting::REMIND_MONTHS . ' months' }}, unless you change it. {{ $senderName }} gets one bell that day.
                            @endif
                        </p>
                    </div>
                @endif

                @if ($isReply)
                    <div class="orp-section orp-lead">
                        @if ($lead)
                            <p><strong>{{ $lead->name }}</strong> is {{ $lead->status === 'Lead' ? 'a lead' : 'in HQ (' . strtolower($lead->status) . ')' }} with {{ $openProspect->first_name }} as a contact, since {{ $openProspect->lead_made_at?->copy()->timezone('Africa/Lagos')->format('j M Y') }}. Their emails show on its Emails tab.</p>
                            <a href="{{ route('hq.crm.lead', ['organization' => $lead->id]) }}" class="btn btn-secondary" wire:navigate><i data-lucide="building-2"></i> Open the lead</a>
                        @else
                            <p>@if ($existing)<strong>{{ $existing['name'] }}</strong> is already {{ $existing['status'] === 'Lead' ? 'a lead' : 'in HQ' }}@if ($existing['owner']) ({{ $existing['owner'] }})@endif: {{ $openProspect->first_name }} will be added there as a contact.
                               @else A new lead, {{ $openProspect->company }}, owned by {{ $openSender?->name ?? 'the sender' }}, with {{ $openProspect->first_name }} as its contact and the research as its first note. @endif</p>
                            <button type="button" class="btn btn-primary" wire:click="makeLead" wire:loading.attr="disabled" wire:target="makeLead"><i data-lucide="user-plus"></i> Make a lead</button>
                        @endif
                    </div>
                @endif

                <section class="orp-mail" aria-labelledby="orp-text-title">
                    <h3 id="orp-text-title">{{ $isReply ? 'Their reply' : 'The notice' }}</h3>
                    @if ($copy)
                        <p class="orp-subj">{{ $open->subject ?: '(no subject)' }}</p>
                        @switch($body['kind'] ?? 'empty')
                            @case('html')
                                <iframe class="orp-frame" title="Reply from {{ $openProspect->fullName() }}" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer"
                                        wire:key="orp-frame-{{ $copy->id }}" srcdoc="{{ $body['doc'] }}"
                                        x-data="{ fit() { try { this.$el.style.height = Math.max(80, this.$el.contentDocument.documentElement.scrollHeight + 8) + 'px' } catch (e) {} } }"
                                        x-init="$el.addEventListener('load', () => fit()); if ($el.contentDocument && $el.contentDocument.readyState === 'complete') fit()"></iframe>
                                @break
                            @case('text')
                                <div class="orp-text">{!! $body['html'] !!}</div>
                                @break
                            @case('error')
                                <div class="alert alert-warning" role="status"><span>The mail server didn’t send this message’s text. Try again in a minute, or open it in Mail.</span></div>
                                @break
                            @default
                                <p class="help m-0">This message has no text.</p>
                        @endswitch
                        <p class="mt-3 mb-0"><a href="{{ route('hq.mail.inbox', array_filter(['box' => $copy->account_id, 'thread' => $copy->thread_id])) }}" class="btn btn-ghost btn-sm" wire:navigate><i data-lucide="external-link"></i> Open in Mail</a></p>
                    @else
                        <p class="help m-0">This {{ $isReply ? 'reply' : 'notice' }} is in {{ $senderName }}’s own mailbox, and personal mailboxes are private: only {{ $senderName }} can read it in HQ. Claude’s label and reason are above.</p>
                    @endif
                </section>

                @if ($first)
                    <details class="orp-orig">
                        <summary>Our email: {{ $first->subject }} (sent {{ $wat($first->sent_at) }})</summary>
                        @foreach ($sent as $e)
                            <div class="orp-mail">
                                <h3>{{ $e->kind === 'first' ? 'Email' : 'Follow-up' }}, sent {{ $wat($e->sent_at) }}@if ($e->status === 'bounced') · bounced @endif</h3>
                                @if ($e->kind === 'first')<p class="orp-subj">{{ $e->subject }}</p>@endif
                                <div class="orp-body">{!! $e->body_html !!}</div>
                            </div>
                        @endforeach
                    </details>
                @endif

                @if ($confirmingSpam)
                    <div class="alert alert-danger mt-4" role="alertdialog" aria-labelledby="orp-spam">
                        <div>
                            <p id="orp-spam" class="m-0"><strong>Record a spam complaint from {{ $openProspect->fullName() }}?</strong> Their address goes on the do-not-email list for good, and outreach from {{ $senderName }}’s mailbox pauses until the owner resumes it (the owner’s rule for any spam complaint).</p>
                            <div class="orp-actions" style="margin-top: 12px">
                                <button type="button" class="btn btn-secondary" wire:click="cancelSpam">Cancel</button>
                                <button type="button" class="btn btn-danger" wire:click="markSpam">Record the complaint</button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="orp-actions">
                    @if ($isReply && $open->label !== 'complained' && ! $confirmingSpam)
                        <button type="button" class="btn btn-ghost orp-left" wire:click="askSpam"><i data-lucide="shield-alert"></i> They call it spam</button>
                    @endif
                    <button type="button" class="btn btn-secondary" wire:click="closeReply">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
