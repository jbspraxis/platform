{{-- A lead's Opportunities tab (App\Livewire\Hq\Leads\OpportunitiesTab, docs/LEADS.md section 9). HQ is English. --}}
<div class="lo">
    <style>
        .lo-sum { display: flex; flex-wrap: wrap; gap: 8px 24px; margin: 0 0 16px; font-size: 0.875rem; color: var(--color-slate); }
        .lo-sum strong { color: var(--color-ink); font-variant-numeric: tabular-nums; }
        .lo-group { margin: 20px 0 8px; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-muted); }
        .lo-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .lo-list > li { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px 16px; align-items: start; padding: 14px 0; border-top: 1px solid var(--color-line); }
        .lo-list > li:first-child { border-top: 0; padding-top: 0; }
        .lo-title { font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .lo-facts { display: flex; flex-wrap: wrap; gap: 2px 14px; margin-top: 4px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .lo-facts span { display: inline-flex; align-items: center; gap: 4px; }
        .lo-facts svg { width: 14px; height: 14px; flex-shrink: 0; }
        .lo-side { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; text-align: right; }
        .lo-value { font-size: 0.875rem; font-weight: 600; color: var(--color-ink); font-variant-numeric: tabular-nums; white-space: nowrap; }
        @media (max-width: 600px) {
            .lo-list > li { grid-template-columns: 1fr; }
            .lo-side { align-items: flex-start; text-align: left; flex-direction: row; flex-wrap: wrap; }
            .ld-card-head .btn { flex: 1 1 100%; justify-content: center; }
        }
    </style>
    @php
        $tz = 'Africa/Lagos';
        $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->format('j M Y') : '—';
        $stageLabel = fn ($s) => __('opportunities.stages.' . $s);
        $stageTone = fn ($s) => match ($s) { 'won' => 'pill-success', 'lost' => 'pill-danger', 'on_hold' => 'pill-warning', default => 'pill-info' };
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 0);
        $name = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : null;
    @endphp

    <section class="card" aria-labelledby="lo-title">
        <div class="ld-card-head">
            <h2 class="card-title" id="lo-title">Opportunities</h2>
            @if ($canOpen)
                <a href="{{ route('hq.crm.opportunities', ['new' => 1, 'organization' => $lead->id]) }}" class="btn btn-secondary btn-sm" wire:navigate><i data-lucide="folder-plus"></i> Open an opportunity</a>
            @endif
        </div>

        @if ($count === 0)
            <div class="empty-state">
                <strong>No opportunities yet</strong>
                Open one for each piece of work you discuss with them: a lead can have several, some won and some lost. After a meeting, open it from the meeting on the Meetings tab, and the meeting and its notes come with it.
            </div>
        @else
            <p class="lo-sum">
                <span><strong>{{ $groups['Open']->count() }}</strong> open</span>
                <span><strong>{{ $groups['Won']->count() }}</strong> won</span>
                <span><strong>{{ $groups['Lost']->count() }}</strong> lost</span>
                @foreach ($openValue as $currency => $total)
                    <span>Open proposals: <strong>{{ $money($currency, $total) }}</strong></span>
                @endforeach
            </p>

            @foreach ($groups as $group => $list)
                @continue($list->isEmpty())
                <h3 class="lo-group">{{ $group }}</h3>
                <ul class="lo-list">
                    @foreach ($list as $o)
                        @php
                            $canSee = $visible[$o->id] ?? false;
                            $p = $canSee ? ($proposals[$o->id] ?? null) : null;
                            $mine = $canSee ? ($meetings[$o->id] ?? collect()) : collect();
                            $next = $mine->where('status', 'scheduled')->filter(fn ($m) => $m->starts_at->isFuture())->sortBy('starts_at')->first();
                        @endphp
                        <li wire:key="lo-{{ $o->id }}">
                            <div style="min-width: 0">
                                @if ($canSee)
                                    <a href="{{ route('hq.crm.opportunity', ['opportunity' => $o->id]) }}" class="lo-title" wire:navigate>{{ $o->title }}</a>
                                @else
                                    <span class="lo-title">{{ $o->title }}</span>
                                @endif
                                <div class="lo-facts">
                                    <span><i data-lucide="calendar"></i> {{ in_array($o->stage, \App\Models\Opportunity::CLOSED, true) && $o->closed_at ? ($o->stage === 'won' ? 'Won ' : 'Lost ') . $day($o->closed_at) : 'Opened ' . $day($o->created_at) }}</span>
                                    @if ($name($o->lead))<span><i data-lucide="user-round"></i> Led by {{ $name($o->lead) }}</span>@endif
                                    @if ($o->source === 'website_booking')<span><i data-lucide="globe"></i> From a website booking</span>@endif
                                    @if ($canSee)
                                        <span><i data-lucide="users"></i> {{ $mine->count() === 1 ? '1 meeting' : $mine->count() . ' meetings' }}</span>
                                        @if ($next)<span><i data-lucide="calendar-clock"></i> Next: {{ \Illuminate\Support\Carbon::parse($next->starts_at)->setTimezone($tz)->format('D j M, H:i') }}</span>@endif
                                    @endif
                                    @if ($o->stage === 'lost' && filled($o->lost_reason))<span>{{ \Illuminate\Support\Str::limit($o->lost_reason, 80) }}</span>@endif
                                </div>
                            </div>
                            <div class="lo-side">
                                <span class="pill {{ $stageTone($o->stage) }}">{{ $stageLabel($o->stage) }}</span>
                                @if ($p)
                                    <span class="lo-value">{{ $p->total_value !== null ? $money($p->currency, $p->total_value) : '' }}</span>
                                    <span class="help" style="margin: 0">Proposal {{ $p->proposal_ref ?: 'v' . $p->version }} · {{ $p->status === 'Draft' && $p->approved_at ? 'Approved, not sent' : $p->status }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </section>
</div>
