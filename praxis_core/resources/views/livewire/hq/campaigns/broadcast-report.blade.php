@php
    use App\Support\Campaigns\Broadcasts;
    $legacy = $broadcast->isLegacy();
    $sending = $broadcast->status === 'Sending';
    $statusPill = ['Queued' => ['pill-neutral', 'Waiting'], 'Sending' => ['pill-info', 'Sending'], 'Sent' => ['pill-success', 'Sent'], 'Failed' => ['pill-danger', 'Failed'],
        'Bounced' => ['pill-danger', 'Bounced'], 'Skipped' => ['pill-warning', 'Not sent']];
    $kinds = ['client' => 'Client', 'lead' => 'Lead', 'kaizen' => 'Kaizen writer', 'subscriber' => 'Subscriber'];
    $filters = ['all' => 'Everyone', 'sent' => 'Sent', 'opened' => 'Opened', 'clicked' => 'Clicked', 'unsubscribed' => 'Unsubscribed', 'problems' => 'Not delivered'] + ($stats['queued'] > 0 ? ['queued' => 'Waiting'] : []);
    $day = fn ($at) => $at ? \Carbon\Carbon::parse($at)->timezone('Africa/Lagos')->format('j M, H:i') : '—';
@endphp
<div class="bcr" @if ($sending) wire:poll.15s @endif>
    <style>
        /* Broadcast report (docs/CAMPAIGNS.md 4.7): only what the shared component classes don't cover. Brand tokens only. */
        .bcr-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 0.875rem; color: var(--color-deep-teal); text-decoration: none; font-weight: 500; }
        .bcr-back svg { width: 16px; height: 16px; }
        .bcr-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .bcr-kpis .card { padding: 16px 20px; }
        .bcr-kpis small { display: block; margin-top: 2px; font-size: 0.8125rem; color: var(--color-muted); }
        .bcr-bar { height: 8px; border-radius: 999px; background: var(--color-mist); overflow: hidden; margin-top: 12px; }
        .bcr-bar span { display: block; height: 100%; background: var(--color-deep-teal); }
        .bcr-tools { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; margin-bottom: 16px; }
        .bcr-tools .field { flex: 1 1 240px; max-width: 360px; }
        .bcr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .bcr-url { max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bcr-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        .bcr-frame { width: 100%; height: 720px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: #F2F5F7; }
    </style>

    <div class="page-header">
        <div>
            <a href="{{ route('hq.campaigns.broadcasts') }}" class="bcr-back" wire:navigate><i data-lucide="arrow-left"></i> All broadcasts</a>
            <h1 class="page-title">{{ $broadcast->title() }}</h1>
            <p class="page-subtitle">
                @if ($sending) <span class="pill pill-info">Sending</span> @else <span class="pill pill-success">Sent</span> @endif
                {{ $broadcast->segment?->name ? 'To “' . $broadcast->segment->name . '”' : ($legacy ? 'Contacts chosen by hand' : '') }}
                · started {{ Broadcasts::wat($broadcast->started_at ?? $broadcast->created_at) }}
                @if ($broadcast->completed_at) · finished {{ Broadcasts::wat($broadcast->completed_at) }} @endif
            </p>
        </div>
        @unless ($legacy)
            <button type="button" class="btn btn-secondary" wire:click="$toggle('showEmail')"><i data-lucide="mail"></i> {{ $showEmail ? 'Hide the email' : 'View the email' }}</button>
        @endunless
    </div>


    @if ($sending)
        <div class="alert alert-info mb-6" role="status"><i data-lucide="send"></i>
            @php $lastAt = $broadcast->estimated_completion_time?->copy()->timezone('Africa/Lagos')->format('H:i'); @endphp
            <span>{{ $stats['queued'] }} still to go, 30 every 5 minutes{{ $lastAt ? ': the last should go at about ' . $lastAt . ' WAT' : '' }}. This page updates by itself.</span>
        </div>
    @endif

    <div class="bcr-kpis">
        <div class="card">
            <div class="stat-label">Sent</div>
            <div class="stat-value">{{ $stats['sent'] }}</div>
            <small>of {{ $stats['total'] }} · {{ $stats['en'] }} in English, {{ $stats['fr'] }} in French</small>
            @if ($stats['total'] > 0)<div class="bcr-bar" aria-hidden="true"><span style="width: {{ round(100 * ($stats['total'] - $stats['queued']) / $stats['total']) }}%"></span></div>@endif
        </div>
        <div class="card">
            <div class="stat-label">Opened, approximately</div>
            <div class="stat-value">{{ $stats['open_rate'] }}%</div>
            <small>{{ $stats['opened'] }} {{ $stats['opened'] === 1 ? 'person' : 'people' }}</small>
        </div>
        <div class="card">
            <div class="stat-label">Clicked</div>
            <div class="stat-value">{{ $stats['click_rate'] }}%</div>
            <small>{{ $stats['clicked'] }} {{ $stats['clicked'] === 1 ? 'person' : 'people' }}, {{ $stats['clicks'] }} {{ $stats['clicks'] === 1 ? 'click' : 'clicks' }}</small>
        </div>
        <div class="card">
            <div class="stat-label">Unsubscribed</div>
            <div class="stat-value">{{ $stats['unsubscribed'] }}</div>
            <small>{{ $stats['unsubscribe_rate'] }}% of those sent</small>
        </div>
        <div class="card">
            <div class="stat-label">Not delivered</div>
            <div class="stat-value">{{ $stats['failed'] + $stats['bounced'] + $stats['skipped'] }}</div>
            <small>{{ $stats['bounced'] }} bounced · {{ $stats['failed'] }} failed · {{ $stats['skipped'] }} on the do-not-email list</small>
        </div>
    </div>
    <p class="help mb-6">Opens are approximate: Apple Mail opens every email it receives to protect privacy, and some inboxes hide images, so the real number can be higher or lower. A click also counts as an open. Clicks and unsubscribes are exact.</p>

    @if ($showEmail && $emailHtml)
        <section class="card mb-6" aria-labelledby="bcr-email">
            <div class="card-header">
                <h2 class="card-title" id="bcr-email">The email, as sent</h2>
                @if (count($broadcast->locales()) > 1)
                    <div class="segmented" role="group" aria-label="Version">
                        <button type="button" class="segmented-item" aria-pressed="{{ $emailLocale === 'en' ? 'true' : 'false' }}" wire:click="$set('emailLocale', 'en')">English</button>
                        <button type="button" class="segmented-item" aria-pressed="{{ $emailLocale === 'fr' ? 'true' : 'false' }}" wire:click="$set('emailLocale', 'fr')">French</button>
                    </div>
                @endif
            </div>
            <iframe class="bcr-frame" title="The email as sent" sandbox="allow-same-origin" srcdoc="{{ $emailHtml }}"></iframe>
        </section>
    @endif

    @if ($links->isNotEmpty())
        <section class="card mb-6" aria-labelledby="bcr-links" style="padding: 0">
            <h2 class="card-title" id="bcr-links" style="padding: 20px 24px 0">Links</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">Address</th><th scope="col">Version</th><th scope="col" class="bcr-num">People</th><th scope="col" class="bcr-num">Clicks</th></tr></thead>
                    <tbody>
                        @foreach ($links as $link)
                            <tr wire:key="k-{{ $link->id }}">
                                <td><div class="bcr-url" title="{{ $link->url }}">{{ $link->url }}</div></td>
                                <td>{{ $link->locale === 'fr' ? 'French' : 'English' }}</td>
                                <td class="bcr-num">{{ $link->people }}</td>
                                <td class="bcr-num">{{ $link->clicks }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section aria-labelledby="bcr-people">
        <h2 class="card-title mb-4" id="bcr-people">Recipients</h2>
        <div class="bcr-tools">
            <div class="field">
                <label for="bcr-search" class="label">Search</label>
                <input id="bcr-search" type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="Name, email or organisation">
            </div>
            <div class="segmented" role="group" aria-label="Show">
                @foreach ($filters as $key => $name)
                    <button type="button" class="segmented-item" aria-pressed="{{ $who === $key ? 'true' : 'false' }}" wire:click="$set('who', '{{ $key }}')">{{ $name }}</button>
                @endforeach
            </div>
        </div>
        <div class="card" style="padding: 0">
            @if ($recipients->isEmpty())
                <p class="empty-state">Nobody here{{ $search !== '' ? ' matches “' . $search . '”' : '' }}.</p>
            @else
                <div class="table-wrap">
                    <table class="table bcr-table">
                        <thead>
                            <tr><th scope="col">Recipient</th><th scope="col">Group</th><th scope="col">Language</th><th scope="col">Status</th><th scope="col">Opened</th><th scope="col">Clicked</th><th scope="col">Unsubscribed</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recipients as $r)
                                @php
                                    $email = $r->email ?? $r->contact_email;
                                    $name = trim(($r->first_name ?? $r->contact_first) . ' ' . ($r->last_name ?? $r->contact_last));
                                    [$pill, $text] = $statusPill[$r->status] ?? ['pill-neutral', $r->status];
                                @endphp
                                <tr wire:key="r-{{ $r->id }}">
                                    <td>{{ $name !== '' ? $name : $email }}<small>{{ $name !== '' ? $email : '' }}{{ ($r->company ?? $r->organisation) ? ' · ' . ($r->company ?? $r->organisation) : '' }}</small></td>
                                    <td>{{ $kinds[$r->kind] ?? '—' }}</td>
                                    <td>{{ ($r->locale ?? $broadcast->language) === 'fr' ? 'French' : 'English' }}</td>
                                    <td><span class="pill {{ $pill }}" @if ($r->error_message) title="{{ $r->error_message }}" @endif>{{ $text }}</span>@if ($r->error_message && $r->status !== 'Sent')<small>{{ \Illuminate\Support\Str::limit($r->error_message, 80) }}</small>@endif</td>
                                    <td>{{ $r->status === 'Sent' && ($r->open_count > 0 || $r->click_count > 0) ? $day($r->first_opened_at ?? $r->last_opened_at) : '—' }}</td>
                                    <td>{{ $r->click_count > 0 ? $r->click_count . ($r->click_count === 1 ? ' click' : ' clicks') : '—' }}</td>
                                    <td>{{ $r->unsubscribed_at ? $day($r->unsubscribed_at) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="mt-4">{{ $recipients->links() }}</div>
    </section>
</div>
