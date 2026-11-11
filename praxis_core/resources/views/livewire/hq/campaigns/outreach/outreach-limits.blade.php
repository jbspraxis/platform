@php
    use App\Support\Campaigns\Outreach\Senders;
    $times = ['window_start', 'window_end'];
    $groups = [
        'How many a day' => ['first_weeks_limit', 'first_weeks_days', 'daily_limit'],
        'When they go' => ['window_start', 'window_end', 'gap_minutes', 'follow_up_days'],
        'Pausing by itself' => ['bounce_pause_percent'],
    ];
    $helps = [
        'first_weeks_limit' => 'A new mailbox sending cold email builds its reputation slowly. Follow-ups count.',
        'first_weeks_days' => 'From the sender’s first outreach email.',
        'daily_limit' => 'Per sender, follow-ups included.',
        'window_start' => 'Lagos time, Monday to Friday only.',
        'window_end' => 'Nothing starts at or after this time.',
        'gap_minutes' => 'So a mailbox never sends in bursts.',
        'follow_up_days' => 'Monday to Friday, then within the window. Cancelled if they reply, bounce or unsubscribe.',
        'bounce_pause_percent' => 'Of that sender’s emails that day, once at least ' . Senders::MIN_BOUNCES_TO_PAUSE . ' have bounced that day, so one out-of-date address alone never pauses them. Any spam complaint pauses them too. You and they get a bell.',
    ];
@endphp
<div class="orx">
    @assets
    <style>
        /* Outreach sending limits (docs/CAMPAIGNS.md section 10): only what the shared component classes don't cover. Brand tokens only. */
        .orx-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px; font-size: 0.875rem; color: var(--color-deep-teal); text-decoration: none; }
        .orx-back svg { width: 16px; height: 16px; }
        .orx-group + .orx-group { margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--color-line); }
        .orx-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); gap: 16px; }
        .orx-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-top: 24px; }
        .orx-table td small { display: block; color: var(--color-muted); font-size: 0.8125rem; }
        @media (max-width: 767px) { .orx-hide-sm { display: none; } }
    </style>
    @endassets

    <a href="{{ route('hq.campaigns.outreach') }}" class="orx-back" wire:navigate><i data-lucide="arrow-left"></i> Outreach</a>
    <div class="page-header">
        <div>
            <h1 class="page-title">Sending limits</h1>
            <p class="page-subtitle">How fast outreach goes out from each salesperson’s own mailbox. Slow and steady keeps jbspraxis.com out of spam folders. Only you can change these.</p>
        </div>
    </div>


    <form class="card mb-6" wire:submit="save" novalidate>
        @foreach ($groups as $title => $keys)
            <div class="orx-group">
                <h2 class="card-title mb-4">{{ $title }}</h2>
                <div class="orx-fields">
                    @foreach ($keys as $key)
                        <div class="field">
                            <label class="label" for="orx-{{ $key }}">{{ $labels[$key] }}</label>
                            <input id="orx-{{ $key }}" class="input" wire:model="values.{{ $key }}"
                                   @if (in_array($key, $times, true)) type="time" step="300" @else type="number" inputmode="numeric" min="0" @endif
                                   @error('values.' . $key) aria-invalid="true" aria-describedby="orx-{{ $key }}-error" @enderror>
                            <span class="help">{{ $helps[$key] }}</span>
                            @error('values.' . $key) <p class="field-error" id="orx-{{ $key }}-error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="orx-actions">
            <button type="button" class="btn btn-secondary" wire:click="defaults">Use the defaults</button>
            <button type="submit" class="btn btn-primary">Save limits</button>
        </div>
    </form>

    <div class="card" style="padding: 0">
        <div class="card-header" style="padding: 16px 16px 0"><h2 class="card-title">Senders</h2></div>
        @if ($senders->isEmpty())
            <div class="empty-state"><p>Nobody has an outreach campaign yet.</p></div>
        @else
            <div class="table-wrap">
                <table class="table orx-table">
                    <thead>
                        <tr><th>Sender</th><th class="orx-hide-sm">Mailbox</th><th class="num">Today</th><th class="num orx-hide-sm">Waiting</th><th>State</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($senders as $s)
                            <tr wire:key="orx-{{ $s['user']->id }}">
                                <td>{{ $s['user']->name }}@if ($s['first'])<small>Since {{ $s['first']->copy()->timezone('Africa/Lagos')->format('j M Y') }}</small>@endif</td>
                                <td class="orx-hide-sm">{{ $s['mailbox'] ?? 'Not connected' }}</td>
                                <td class="num">{{ $s['tried'] }} of {{ $s['limit'] }}</td>
                                <td class="num orx-hide-sm">{{ $s['waiting'] }}</td>
                                <td>
                                    @if ($s['paused'])
                                        <span class="pill pill-danger">Paused</span>
                                        <small>{{ Senders::PAUSES[$s['paused']->paused_reason] ?? '' }}. {{ $s['paused']->paused_note }}</small>
                                        <button type="button" class="btn btn-secondary btn-sm mt-2" wire:click="resumeSender('{{ $s['user']->id }}')"><i data-lucide="play"></i> Resume</button>
                                    @elseif ($s['problem'])
                                        <span class="pill pill-warning">Can’t send</span><small>{{ $s['problem'] }}</small>
                                    @else
                                        <span class="pill pill-success">Sending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
