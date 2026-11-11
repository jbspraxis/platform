@php
    $tone = fn (string $status) => ['active' => 'pill-success', 'paused' => 'pill-warning', 'archived' => 'pill-neutral'][$status] ?? 'pill-neutral';
    $n = fn (int $v) => number_format($v);
@endphp
<div class="orl">
    @assets
    <style>
        /* Outreach list (docs/CAMPAIGNS.md section 10): only what the shared component classes don't cover. Brand tokens only. */
        .orl-day { display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center; margin-bottom: 24px; }
        .orl-day p { margin: 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .orl-day strong { color: var(--color-ink); }
        .orl-meter { min-width: 140px; text-align: right; }
        .orl-meter .stat-value { margin: 0; }
        .orl-filters { margin-bottom: 16px; }
        .orl-filters .segmented-item span { color: var(--color-muted); font-variant-numeric: tabular-nums; }
        .orl-table td a.orl-name { font-weight: 600; color: var(--color-ink); text-decoration: none; }
        .orl-table td a.orl-name:hover { text-decoration: underline; }
        .orl-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .orl-modal { max-width: 600px; }
        .orl-badge { display: inline-block; margin-left: 2px; padding: 0 7px; border-radius: 999px; background: var(--color-deep-teal); color: var(--color-paper); font-size: 0.75rem; line-height: 1.25rem; font-weight: 600; }
        .orl-modal .field + .field { margin-top: 16px; }
        @media (max-width: 767px) {
            .orl-day { grid-template-columns: 1fr; }
            .orl-meter { text-align: left; }
            .orl-hide-sm { display: none; }
        }
    </style>
    @endassets

    <div class="page-header">
        <div>
            <h1 class="page-title">Outreach</h1>
            <p class="page-subtitle">One-to-one emails to researched prospects, written by Claude and read by you before they go. They leave from your own mailbox, a few a day, with one follow-up about {{ $followUpDays }} working days later unless they reply. Prospects never get broadcasts.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canCreate || $canApprove)
                <a href="{{ route('hq.campaigns.outreach.replies') }}" class="btn btn-secondary" wire:navigate><i data-lucide="message-square-reply"></i> Replies
                    @if ($toSort > 0)<span class="orl-badge" aria-label="{{ $toSort }} to sort">{{ $toSort }} to sort</span>@endif</a>
            @endif
            @if ($canApprove)
                <a href="{{ route('hq.campaigns.outreach.limits') }}" class="btn btn-secondary" wire:navigate><i data-lucide="settings"></i> Sending limits</a>
            @endif
            @if ($canCreate)
                <button type="button" class="btn btn-primary" wire:click="startCreating"><i data-lucide="plus"></i> New campaign</button>
            @endif
        </div>
    </div>


    @if ($me)
        <div class="card orl-day">
            <div>
                <h2 class="card-title">Your sending</h2>
                @if ($me['problem'])
                    <div class="alert alert-warning mt-3" role="status"><i data-lucide="mail-warning"></i><span>{{ $me['problem'] }}</span></div>
                @elseif ($me['paused'])
                    <div class="alert alert-danger mt-3" role="status"><i data-lucide="pause"></i>
                        <span><strong>Paused: {{ \App\Support\Campaigns\Outreach\Senders::PAUSES[$me['paused']->paused_reason] ?? 'paused' }}.</strong> {{ $me['paused']->paused_note }}
                            {{ $canApprove ? 'Resume it in Sending limits once it is sorted.' : 'The owner resumes it once it is sorted.' }}</span>
                    </div>
                @else
                    <p class="mt-1">From <strong>{{ $me['mailbox'] }}</strong>, {{ $window }}, at least {{ $gap }} minutes apart. Follow-ups count toward the day’s limit.</p>
                @endif
            </div>
            <div class="orl-meter">
                <div class="stat-label">Sent today</div>
                <div class="stat-value">{{ $me['tried'] }} <span class="muted" style="font-size: 1rem; font-weight: 400">of {{ $me['limit'] }}</span></div>
            </div>
        </div>
    @endif

    <div class="segmented orl-filters" role="group" aria-label="Show">
        <button type="button" class="segmented-item" aria-pressed="{{ $list === 'active' ? 'true' : 'false' }}" wire:click="$set('list', 'active')">Campaigns</button>
        <button type="button" class="segmented-item" aria-pressed="{{ $list === 'archived' ? 'true' : 'false' }}" wire:click="$set('list', 'archived')">Archived <span>{{ $archived }}</span></button>
    </div>

    <div class="card" style="padding: 0">
        @if ($campaigns->isEmpty())
            <div class="empty-state">
                @if ($list === 'active')
                    <strong>No outreach campaigns yet</strong>
                    <p>A campaign is a list of prospects you researched. Import them from the Excel template, read Claude’s drafts, approve them, and they go out from your mailbox at a safe pace.</p>
                    @if ($canCreate)
                        <button type="button" class="btn btn-primary mt-3" wire:click="startCreating"><i data-lucide="plus"></i> New campaign</button>
                    @endif
                @else
                    <p>No archived campaigns.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table class="table orl-table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th class="orl-hide-sm">Sent from</th>
                            <th>Status</th>
                            <th class="num">Prospects</th>
                            <th class="num">To review</th>
                            <th class="num">Sent</th>
                            <th class="num orl-hide-sm">Follow-ups</th>
                            <th class="num orl-hide-sm">Bounced</th>
                            <th class="num">Replies</th>
                            <th class="num orl-hide-sm">Leads</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($campaigns as $c)
                            @php $s = $stats[$c->id]; @endphp
                            <tr wire:key="orl-{{ $c->id }}">
                                <td>
                                    <a class="orl-name" href="{{ route('hq.campaigns.outreach.campaign', ['campaign' => $c->id]) }}" wire:navigate>{{ $c->name }}</a>
                                    <small>Made {{ $c->created_at->copy()->timezone('Africa/Lagos')->format('j M Y') }}</small>
                                </td>
                                <td class="orl-hide-sm">{{ $c->sender?->name ?? '—' }}</td>
                                <td><span class="pill {{ $tone($c->status) }}">{{ \App\Models\Campaigns\OutreachCampaign::STATUSES[$c->status] }}</span></td>
                                <td class="num">{{ $n($s['prospects']) }}</td>
                                <td class="num">{{ $n($s['to_review']) }}@if ($s['writing'])<small>{{ $s['writing'] }} writing</small>@endif</td>
                                <td class="num">{{ $n($s['sent']) }}</td>
                                <td class="num orl-hide-sm">{{ $n($s['follow_ups_sent']) }}</td>
                                <td class="num orl-hide-sm">{{ $n($s['bounced']) }}</td>
                                <td class="num">
                                    @if ($s['replies'] && in_array($c->id, $sorts, true))
                                        <a href="{{ route('hq.campaigns.outreach.campaign', ['campaign' => $c->id, 'show' => 'replies']) }}" wire:navigate>{{ $n($s['replies']) }}</a>
                                    @else
                                        {{ $n($s['replies']) }}
                                    @endif
                                    @if ($s['sent'])<small>{{ $s['reply_rate'] }}%@if ($s['to_sort']) · {{ $s['to_sort'] }} to sort @endif</small>@endif
                                </td>
                                <td class="num orl-hide-sm">{{ $n($s['leads']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($creating)
        <div class="modal-backdrop" wire:click.self="$set('creating', false)" @keydown.escape.window="$wire.set('creating', false)">
            <form class="modal orl-modal" role="dialog" aria-modal="true" aria-labelledby="orl-new" wire:submit="create" novalidate>
                <h2 class="modal-title" id="orl-new">New outreach campaign</h2>
                <p class="help m-0">Next you import your prospects. Nothing is sent until you approve each email.</p>
                <div class="field mt-4">
                    <label class="label" for="orl-name">Name</label>
                    <input id="orl-name" type="text" class="input" wire:model="name" maxlength="150" autofocus placeholder="Lagos logistics, November"
                           @error('name') aria-invalid="true" aria-describedby="orl-name-error" @enderror>
                    @error('name') <p class="field-error" id="orl-name-error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label class="label" for="orl-brief">What to offer <span class="help">(optional)</span></label>
                    <textarea id="orl-brief" class="textarea" rows="3" wire:model="brief" maxlength="2000"
                              @error('brief') aria-invalid="true" aria-describedby="orl-brief-error" @enderror></textarea>
                    <span class="help">Claude reads this for every email of the campaign, e.g. “Offer the free operations audit; we have done OHADA finance for a logistics company.”</span>
                    @error('brief') <p class="field-error" id="orl-brief-error">{{ $message }}</p> @enderror
                </div>
                @if ($senders)
                    <div class="field">
                        <label class="label" for="orl-sender">Sent from</label>
                        <select id="orl-sender" class="select" wire:model="senderId">
                            @foreach ($senders as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="help">Their own mailbox sends it, and only they or you can approve its emails.</span>
                        @error('senderId') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="$set('creating', false)">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create campaign</button>
                </div>
            </form>
        </div>
    @endif
</div>
