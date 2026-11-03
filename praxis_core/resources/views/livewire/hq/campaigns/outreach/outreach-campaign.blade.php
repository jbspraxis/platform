@php
    use App\Models\Campaigns\OutreachCampaign;
    use App\Models\Campaigns\OutreachEmail;
    use App\Support\Campaigns\Outreach\Drafts;
    use App\Support\Campaigns\Outreach\Outreach;
    use App\Support\Campaigns\Outreach\Senders;

    // Text formats for the rich-text editor (the Broadcasts editor's: Lucide paths drawn inline, no build step)
    $formats = [
        ['bold', 'Bold', 'Ctrl+B', '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/>'],
        ['italic', 'Italic', 'Ctrl+I', '<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>'],
        ['underline', 'Underline', 'Ctrl+U', '<path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" x2="20" y1="20" y2="20"/>'],
        ['insertUnorderedList', 'Bulleted list', null, '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>'],
        ['link', 'Link', 'Ctrl+K', '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>'],
        ['removeFormat', 'Clear formatting', null, '<path d="M4 7V4h16v3"/><path d="M5 20h6"/><path d="M13 4 8 20"/><path d="m15 15 5 5"/><path d="m20 15-5 5"/>'],
    ];
    $pill = function (OutreachEmail $e, ?\App\Models\Campaigns\OutreachProspect $p) {
        if ($p && in_array($p->status, ['replied', 'unsubscribed'], true)) {
            return [$p->status === 'replied' ? 'pill-success' : 'pill-neutral', $p->status === 'replied' ? 'Replied' : 'Unsubscribed'];
        }
        return match ($e->status) {
            'writing' => ['pill-neutral', 'Writing'], 'not_written' => ['pill-danger', 'Not written'], 'draft' => ['pill-warning', 'To review'],
            'approved', 'sending' => ['pill-info', $e->status === 'sending' ? 'Sending' : 'Approved'], 'sent' => ['pill-success', 'Sent'],
            'skipped' => ['pill-neutral', 'Skipped'], 'bounced' => ['pill-danger', 'Bounced'], 'failed' => ['pill-danger', 'Not sent'],
            default => ['pill-neutral', OutreachEmail::STATUSES[$e->status] ?? $e->status],
        };
    };
    $wat = fn ($at) => $at ? $at->copy()->timezone('Africa/Lagos')->format('j M, H:i') : '';
    $followText = function (?OutreachEmail $f) use ($wat) {
        if (! $f) return null;
        return match ($f->status) {
            'scheduled' => 'Follow-up ' . ($f->send_after ? 'from ' . $wat($f->send_after) : 'scheduled'),
            'sent' => 'Follow-up sent ' . $wat($f->sent_at),
            'cancelled' => 'Follow-up cancelled' . ($f->note ? ': ' . strtolower($f->note) : ''),
            'skipped' => $f->note === 'No follow-up written' ? 'No follow-up' : null,
            'bounced' => 'Follow-up bounced',
            'failed' => 'Follow-up not sent',
            default => null,
        };
    };
    $langName = ['en' => 'English', 'fr' => 'French'];
    $status = $campaign->status;
    $canRest = $handApproved >= Outreach::HAND_APPROVALS;
@endphp
<div class="orc" @if ($busy) wire:poll.4s @endif>
    @assets
        <script src="{{ route('hq.campaigns.outreach.script', ['v' => filemtime(resource_path('campaigns/editor.js'))]) }}"></script>
    @endassets
    <style>
        /* An outreach campaign (docs/CAMPAIGNS.md section 10): only what the shared component classes don't cover. Brand tokens only. */
        .orc-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 0.875rem; color: var(--color-deep-teal); text-decoration: none; }
        .orc-back svg { width: 16px; height: 16px; }
        .orc-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .orc-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .orc-stats .card { padding: 14px 16px; }
        .orc-stats .stat-value { font-size: 1.25rem; line-height: 1.75rem; }
        .orc-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .orc-toolbar .segmented-item span { color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .orc-search { max-width: 260px; }
        .orc-rest { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .orc-table tbody tr { cursor: pointer; }
        .orc-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .orc-who { all: unset; cursor: pointer; font-weight: 600; color: var(--color-ink); }
        .orc-who:focus-visible { outline: 2px solid var(--color-focus); outline-offset: 2px; border-radius: 2px; }
        .orc-subject { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        @media (max-width: 1199px) { .orc-hide-md { display: none; } }
        .orc-table td:nth-child(1) { min-width: 170px; }
        .orc-table td:nth-child(2) { min-width: 140px; }
        .orc-modal { max-width: 680px; }
        .orc-meta { margin: 0 0 16px; font-size: 0.875rem; color: var(--color-slate); }
        .orc-research { margin: 0 0 16px; padding: 12px 14px; border-radius: var(--radius-card); background: var(--color-mist); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-slate); }
        .orc-research p { margin: 0 0 6px; }
        .orc-research p:last-child { margin: 0; }
        .orc-research strong { color: var(--color-ink); font-weight: 600; }
        .orc-mail { border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; }
        .orc-mail + .orc-mail { margin-top: 12px; }
        .orc-mail h3 { margin: 0 0 4px; font-size: 0.8125rem; font-weight: 600; color: var(--color-muted); text-transform: none; }
        .orc-mail .orc-subj { margin: 0 0 12px; font-size: 1rem; font-weight: 600; color: var(--color-ink); }
        .orc-body { font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-ink); overflow-wrap: anywhere; }
        .orc-body p { margin: 0 0 10px; }
        .orc-body ul, .orc-body ol { margin: 0 0 10px; padding-left: 22px; }
        .orc-body a { color: var(--color-deep-teal); }
        .orc-note { margin-top: 8px; font-size: 0.8125rem; color: var(--color-muted); }
        .orc-modal-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-top: 20px; }
        .orc-modal-actions .orc-left { margin-right: auto; }
        .orc-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .orc-close { all: unset; cursor: pointer; display: grid; place-items: center; width: 32px; height: 32px; border-radius: var(--radius-control); color: var(--color-muted); }
        .orc-close:hover { background: var(--color-mist); color: var(--color-ink); }
        .orc-close svg { width: 18px; height: 18px; }
        /* The rich-text editor (the Broadcasts editor's look) */
        .orc-toolbar-rt { display: flex; flex-wrap: wrap; gap: 2px; padding: 4px; border: 1px solid var(--color-line-strong); border-bottom: 0; border-radius: var(--radius-control) var(--radius-control) 0 0; background: var(--color-mist); }
        .orc-icon { display: grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: var(--radius-control); background: none; color: var(--color-slate); cursor: pointer; }
        .orc-icon:hover { background: var(--color-paper); color: var(--color-ink); }
        .orc-icon svg { width: 16px; height: 16px; }
        .orc-editor { min-height: 140px; padding: 10px 12px; border: 1px solid var(--color-line-strong); border-radius: 0 0 var(--radius-control) var(--radius-control); background: var(--color-paper); font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-ink); outline: none; overflow-wrap: anywhere; }
        .orc-editor:focus { border-color: var(--color-deep-teal); box-shadow: 0 0 0 2px var(--color-teal-tint); }
        .orc-editor p { margin: 0 0 10px; }
        .orc-editor ul, .orc-editor ol { margin: 0 0 10px; padding-left: 22px; }
        .orc-linkbar { display: flex; gap: 8px; padding: 8px; border: 1px solid var(--color-line-strong); border-bottom: 0; background: var(--color-paper); }
        @media (max-width: 767px) {
            .orc-hide-sm { display: none; }
            .orc-rest { width: 100%; }
            .orc-search { max-width: none; width: 100%; }
            .orc-modal-actions .btn { flex: 1 1 auto; }
            .orc-modal-actions .orc-left { margin-right: 0; flex-basis: 100%; }
        }
    </style>

    <a href="{{ route('hq.campaigns.outreach') }}" class="orc-back" wire:navigate><i data-lucide="arrow-left"></i> Outreach</a>
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $campaign->name }}</h1>
            <p class="page-subtitle">
                <span class="pill {{ ['active' => 'pill-success', 'paused' => 'pill-warning', 'archived' => 'pill-neutral'][$status] }}">{{ OutreachCampaign::STATUSES[$status] }}</span>
                Sent from {{ $senderDay['mailbox'] ?? ($sender?->name ?? 'nobody yet') }}@if ($sender && $senderDay['mailbox']) ({{ $sender->name }})@endif.
                @if ($campaign->brief) What to offer: {{ \Illuminate\Support\Str::limit($campaign->brief, 160) }} @endif
            </p>
        </div>
        @if ($canReview)
            <div class="orc-actions">
                @if ($status !== 'archived')
                    <livewire:hq.import.import-dialog schema="prospects" :key="'import-' . $campaign->id" />
                @endif
                <button type="button" class="btn btn-secondary" wire:click="startDetails"><i data-lucide="pencil"></i> Edit details</button>
                @if ($status === 'active')
                    <button type="button" class="btn btn-secondary" wire:click="pause"><i data-lucide="pause"></i> Pause</button>
                @else
                    <button type="button" class="btn btn-secondary" wire:click="resume"><i data-lucide="play"></i> {{ $status === 'archived' ? 'Bring back' : 'Resume' }}</button>
                @endif
            </div>
        @endif
    </div>

    <x-flash class="mb-6" />

    @if ($senderDay['problem'])
        <div class="alert alert-warning mb-6" role="status"><i data-lucide="mail-warning"></i><span>{{ $senderDay['problem'] }}</span></div>
    @elseif ($senderDay['paused'])
        <div class="alert alert-danger mb-6" role="status"><i data-lucide="pause"></i>
            <span><strong>Outreach is paused for {{ Senders::firstName($sender) }}: {{ strtolower(Senders::PAUSES[$senderDay['paused']->paused_reason] ?? 'paused') }}.</strong> {{ $senderDay['paused']->paused_note }}
                @if ($canApprove) <a href="{{ route('hq.campaigns.outreach.limits') }}" wire:navigate>Resume it in Sending limits</a>. @else The owner resumes it once it is sorted. @endif</span>
        </div>
    @elseif ($status === 'paused')
        <div class="alert alert-warning mb-6" role="status"><i data-lucide="pause"></i><span>This campaign is paused: approved emails and follow-ups wait until it is resumed.</span></div>
    @else
        <div class="alert alert-info mb-6" role="status"><i data-lucide="clock"></i>
            <span>Approved emails go out {{ $window }}, at least {{ $gap }} minutes apart, up to {{ $senderDay['limit'] }} a day for {{ Senders::firstName($sender) }} now ({{ $senderDay['tried'] }} today, follow-ups included). Each gets one follow-up about {{ $followUpDays }} working days later, unless they reply.</span>
        </div>
    @endif

    <div class="orc-stats">
        @foreach ([['Prospects', $stats['prospects']], ['To review', $stats['to_review']], ['Approved', $stats['approved']], ['Sent', $stats['sent']],
                   ['Follow-ups sent', $stats['follow_ups_sent']], ['Bounced', $stats['bounced']], ['Unsubscribed', $stats['unsubscribed']]] as [$label, $value])
            <div class="card"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ number_format($value) }}</div></div>
        @endforeach
    </div>

    @if ($stats['writing'] > 0)
        <div class="alert alert-info mb-4" role="status"><i data-lucide="loader"></i>
            <span>Claude is writing {{ $stats['writing'] }} {{ $stats['writing'] === 1 ? 'email' : 'emails' }}. They appear under To review within a few minutes; this page updates by itself.</span>
        </div>
    @endif

    <div class="orc-toolbar">
        <div class="segmented" role="group" aria-label="Show">
            @foreach (\App\Livewire\Hq\Campaigns\Outreach\OutreachCampaignPage::FILTERS as $key => $label)
                <button type="button" class="segmented-item" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" wire:click="$set('filter', '{{ $key }}')">{{ $label }} <span>{{ number_format($counts[$key]) }}</span></button>
            @endforeach
        </div>
        <div class="orc-rest">
            <label class="sr-only" for="orc-search">Search prospects</label>
            <input id="orc-search" type="search" class="input orc-search" placeholder="Search company, name or email" wire:model.live.debounce.400ms="search">
            @if ($canReview && $stats['to_review'] > 0)
                <button type="button" class="btn btn-secondary" wire:click="askApproveRest" @disabled(! $canRest)
                        title="{{ $canRest ? '' : 'Approve ' . Outreach::HAND_APPROVALS . ' emails one by one first' }}">
                    <i data-lucide="list-checks"></i> Approve the rest ({{ number_format($stats['to_review']) }})
                </button>
                @unless ($canRest)
                    <span class="help">Unlocks after {{ Outreach::HAND_APPROVALS }} approved by hand ({{ $handApproved }} of {{ Outreach::HAND_APPROVALS }})</span>
                @endunless
            @endif
        </div>
    </div>

    <div class="card" style="padding: 0">
        @if ($rows->isEmpty())
            <div class="empty-state">
                @if ($stats['prospects'] === 0)
                    <strong>No prospects yet</strong>
                    <p>Download the template from Import prospects, fill in one row per person (with what you found about their company, or its website), and upload it. Claude writes each one an email and a follow-up for you to read.</p>
                @elseif ($search !== '')
                    <p>Nobody matches “{{ $search }}”.</p>
                @else
                    <p>Nothing {{ strtolower(\App\Livewire\Hq\Campaigns\Outreach\OutreachCampaignPage::FILTERS[$filter]) }} here.
                        @if ($filter === 'review' && $stats['writing'] > 0) Claude is still writing. @endif</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="table orc-table">
                    <thead>
                        <tr>
                            <th>Prospect</th>
                            <th>Status</th>
                            <th class="orc-hide-md">Subject</th>
                            <th class="orc-hide-sm">Email</th>
                            <th class="orc-hide-sm">Language</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $e)
                            @php $p = $e->prospect; [$tone, $label] = $pill($e, $p); $f = $followText($followUps[$e->prospect_id] ?? null); @endphp
                            <tr wire:key="orc-{{ $e->id }}" wire:click="openEmail({{ $e->id }})" @class(['is-selected' => $openId === $e->id])>
                                <td>
                                    <button type="button" class="orc-who" wire:click.stop="openEmail({{ $e->id }})">{{ $p?->fullName() }}</button>
                                    <small>{{ $p?->company }}@if ($p?->job_title) · {{ $p->job_title }}@endif</small>
                                </td>
                                <td>
                                    <span class="pill {{ $tone }}">{{ $label }}</span>
                                    @if ($e->status === 'sent')<small>{{ $wat($e->sent_at) }}</small>@endif
                                    @if ($f)<small>{{ $f }}</small>@endif
                                    @if ($e->status === 'draft' && $e->ai_run_id)<small>Rewriting…</small>@endif
                                </td>
                                <td class="orc-hide-md"><div class="orc-subject">{{ $e->subject ?: '—' }}</div></td>
                                <td class="orc-hide-sm">{{ $p?->email }}</td>
                                <td class="orc-hide-sm">{{ $langName[$p?->language] ?? '' }}</td>
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

    {{-- The email in a small dialog: Approve, Edit, Rewrite, Skip --}}
    @if ($open && $openProspect)
        @php
            $isBusy = Drafts::busy($open);
            $canAct = $canReview && $status !== 'archived';
            $f = $openFollowUp;
        @endphp
        <div class="modal-backdrop" wire:click.self="closeEmail" @keydown.escape.window="$wire.closeEmail()">
            <div class="modal orc-modal" role="dialog" aria-modal="true" aria-labelledby="orc-title" wire:key="orc-dialog-{{ $open->id }}">
                <div class="orc-head">
                    <h2 class="modal-title" id="orc-title">To {{ $openProspect->fullName() }}</h2>
                    <button type="button" class="orc-close" wire:click="closeEmail" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <p class="orc-meta">{{ $openProspect->company }} · {{ $openProspect->email }} · {{ $langName[$openProspect->language] ?? '' }}@if ($openProspect->job_title) · {{ $openProspect->job_title }}@endif
                    · <span class="pill {{ $pill($open, $openProspect)[0] }}">{{ $pill($open, $openProspect)[1] }}</span></p>

                <div class="orc-research">
                    @if ($openProspect->findings)<p><strong>What we found:</strong> {{ $openProspect->findings }}</p>@endif
                    @if ($openProspect->website)
                        <p><strong>Website:</strong> {{ preg_replace('~^https?://~', '', $openProspect->website) }}
                            — {{ $openProspect->website_read_at ? ($openProspect->website_note ?: 'read for Claude') : 'not read yet' }}</p>
                    @endif
                    @if ($open->insight)<p><strong>Claude’s insight:</strong> {{ $open->insight }}</p>@endif
                </div>

                @error('dialog') <div class="alert alert-danger mb-4" role="alert"><span>{{ $message }}</span></div> @enderror
                @if ($open->ai_error && ! $isBusy)
                    <div class="alert alert-danger mb-4" role="alert"><span>{{ $open->status === 'not_written' ? 'Not written yet.' : 'This is the earlier draft: the rewrite didn’t work.' }} {{ __($open->ai_error) }}</span></div>
                @endif
                @if (in_array($open->status, ['skipped', 'bounced', 'failed'], true) && $open->note)
                    <div class="alert alert-warning mb-4" role="status"><span>{{ $open->note }}</span></div>
                @endif

                @if ($isBusy)
                    <div class="alert alert-info" role="status"><i data-lucide="loader"></i>
                        <span>Claude is {{ $open->status === 'writing' ? 'writing' : 'rewriting' }} this email{{ $open->rewrite_note ? ' (“' . \Illuminate\Support\Str::limit($open->rewrite_note, 80) . '”)' : '' }}. It shows here in a minute or two.</span></div>
                @elseif ($mode === 'edit')
                    <div class="field">
                        <label class="label" for="orc-subject">Subject</label>
                        <input id="orc-subject" type="text" class="input" wire:model="editSubject" maxlength="200" @error('editSubject') aria-invalid="true" aria-describedby="orc-subject-error" @enderror>
                        @error('editSubject') <p class="field-error" id="orc-subject-error">{{ $message }}</p> @enderror
                    </div>
                    @foreach (['first' => 'Email', 'follow_up' => 'Follow-up, about ' . $followUpDays . ' working days later if they don’t reply'] as $part => $label)
                        <div class="field mt-4">
                            <span class="label" id="orc-label-{{ $part }}">{{ $label }}</span>
                            <div wire:ignore wire:key="orc-ed-{{ $open->id }}-{{ $part }}" x-data="campaignText({ id: @js((string) $open->id), locale: @js($part), html: @js($editBody[$part]) })">
                                <div class="orc-toolbar-rt" role="toolbar" aria-label="Text format">
                                    @foreach ($formats as [$command, $flabel, $keys, $paths])
                                        <button type="button" class="orc-icon" @mousedown.prevent @click="{{ $command === 'link' ? 'openLink()' : "cmd('{$command}')" }}" aria-label="{{ $flabel }}" title="{{ $flabel }}{{ $keys ? " ({$keys})" : '' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="orc-linkbar" x-show="linkOpen" x-cloak>
                                    <label class="sr-only" for="orc-link-{{ $part }}">Link address</label>
                                    <input type="url" class="input" x-ref="link" id="orc-link-{{ $part }}" x-model="linkUrl" @keydown.enter.prevent="applyLink()" @keydown.escape.prevent.stop="cancelLink()" placeholder="https://">
                                    <button type="button" class="btn btn-primary btn-sm" @click="applyLink()">Add link</button>
                                    <button type="button" class="btn btn-secondary btn-sm" @click="cancelLink()">Cancel</button>
                                </div>
                                <div class="orc-editor" x-ref="ed" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="orc-label-{{ $part }}" spellcheck="true" lang="{{ $openProspect->language }}"></div>
                            </div>
                            @if ($part === 'follow_up')<span class="help">Leave it empty to send no follow-up.</span>@endif
                            @error('editBody.' . $part) <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                    <p class="orc-note">Your signature and a line to unsubscribe are added below each email when it goes.</p>
                @else
                    <div class="orc-mail">
                        <h3>Email</h3>
                        <p class="orc-subj">{{ $open->subject ?: 'No subject yet' }}</p>
                        <div class="orc-body">{!! $open->body_html ?: '<p class="muted">Not written yet.</p>' !!}</div>
                    </div>
                    <div class="orc-mail">
                        <h3>Follow-up, about {{ $followUpDays }} working days later if they don’t reply @if ($f && $followText($f)) · {{ $followText($f) }} @endif</h3>
                        <div class="orc-body">{!! $f && ! \App\Support\Campaigns\RichText::isBlank($f->body_html) ? $f->body_html : '<p class="muted">No follow-up.</p>' !!}</div>
                    </div>
                    <p class="orc-note">Your signature and a line to unsubscribe are added below each email when it goes.</p>
                    @if ($mode === 'rewrite')
                        <div class="field mt-4">
                            <label class="label" for="orc-note">What should change? <span class="help">(optional)</span></label>
                            <textarea id="orc-note" class="textarea" rows="2" wire:model="rewriteNote" maxlength="500" placeholder="Shorter, and mention the free operations audit"
                                      @error('rewriteNote') aria-invalid="true" @enderror></textarea>
                            @error('rewriteNote') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @endif

                <div class="orc-modal-actions">
                    @if (! $canAct || $isBusy)
                        <button type="button" class="btn btn-secondary" wire:click="closeEmail">Close</button>
                    @elseif ($mode === 'edit')
                        <button type="button" class="btn btn-secondary" wire:click="cancelMode">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveEdit">Save changes</button>
                    @elseif ($mode === 'rewrite')
                        <button type="button" class="btn btn-secondary" wire:click="cancelMode">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="rewrite"><i data-lucide="sparkles"></i> Rewrite with Claude</button>
                    @elseif ($open->status === 'draft')
                        <button type="button" class="btn btn-ghost orc-left" wire:click="skip">Skip this prospect</button>
                        <button type="button" class="btn btn-secondary" wire:click="startRewrite"><i data-lucide="sparkles"></i> Rewrite</button>
                        <button type="button" class="btn btn-secondary" wire:click="startEdit"><i data-lucide="pencil"></i> Edit</button>
                        <button type="button" class="btn btn-primary" wire:click="approve"><i data-lucide="check"></i> Approve</button>
                    @elseif ($open->status === 'not_written')
                        <button type="button" class="btn btn-ghost orc-left" wire:click="skip">Skip this prospect</button>
                        <button type="button" class="btn btn-secondary" wire:click="startEdit"><i data-lucide="pencil"></i> Write it myself</button>
                        <button type="button" class="btn btn-primary" wire:click="startRewrite"><i data-lucide="rotate-ccw"></i> Try again</button>
                    @elseif (in_array($open->status, ['approved', 'skipped'], true))
                        <button type="button" class="btn btn-secondary" wire:click="closeEmail">Close</button>
                        <button type="button" class="btn btn-primary" wire:click="backToReview"><i data-lucide="rotate-ccw"></i> Back to review</button>
                    @else
                        <button type="button" class="btn btn-secondary" wire:click="closeEmail">Close</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingRest)
        <div class="modal-backdrop" wire:click.self="$set('confirmingRest', false)" @keydown.escape.window="$wire.set('confirmingRest', false)">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="orc-rest">
                <h2 class="modal-title" id="orc-rest">Approve the other {{ number_format($stats['to_review']) }} {{ $stats['to_review'] === 1 ? 'email' : 'emails' }}?</h2>
                <p class="help m-0">They go out from {{ $senderDay['mailbox'] ?? 'the sender’s mailbox' }} as Claude wrote them, without anyone reading them first, at up to {{ $senderDay['limit'] }} a day, {{ $window }}. Their follow-ups are approved too. You can still open any of them and move it back to review until it goes.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="$set('confirmingRest', false)">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="approveRest">Approve {{ number_format($stats['to_review']) }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($editingDetails)
        <div class="modal-backdrop" wire:click.self="$set('editingDetails', false)" @keydown.escape.window="$wire.set('editingDetails', false)">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="orc-details" wire:submit="saveDetails" novalidate>
                <h2 class="modal-title" id="orc-details">Campaign details</h2>
                <div class="field mt-4">
                    <label class="label" for="orc-dname">Name</label>
                    <input id="orc-dname" type="text" class="input" wire:model="detailName" maxlength="150" @error('detailName') aria-invalid="true" aria-describedby="orc-dname-error" @enderror>
                    @error('detailName') <p class="field-error" id="orc-dname-error">{{ $message }}</p> @enderror
                </div>
                <div class="field mt-4">
                    <label class="label" for="orc-dbrief">What to offer <span class="help">(optional)</span></label>
                    <textarea id="orc-dbrief" class="textarea" rows="3" wire:model="detailBrief" maxlength="2000"></textarea>
                    <span class="help">Claude reads this for every email of the campaign.</span>
                    @error('detailBrief') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="modal-actions">
                    @if ($status !== 'archived')
                        <button type="button" class="btn btn-ghost" style="margin-right: auto" wire:click="archive">Archive campaign</button>
                    @endif
                    <button type="button" class="btn btn-secondary" wire:click="$set('editingDetails', false)">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    @endif
</div>
