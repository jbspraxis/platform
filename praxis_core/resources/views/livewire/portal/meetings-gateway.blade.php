{{-- The client's meetings page (signed link from invitations and minutes): upcoming meetings, minutes, and booking a follow-up.
     Behaviour lives in App\Livewire\Portal\MeetingsGateway. Only client meetings and sent minutes appear here. --}}
@php
    $locale = app()->getLocale();
    $tz = \App\Support\Opportunities\Meetings::TIME_ZONE;
    $fr = $locale === 'fr';
    $day = fn ($at) => \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->locale($locale)->isoFormat($fr ? 'dddd D MMMM YYYY' : 'dddd D MMMM YYYY');
    $short = fn ($at) => \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->locale($locale)->isoFormat($fr ? 'ddd D MMM' : 'ddd D MMM');
    $clock = fn ($at) => \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->isoFormat($fr ? "H[\u{202F}h\u{202F}]mm" : 'HH:mm');
    $daysShown = $allDays ? $times : array_slice($times, 0, 5, true);
    $timeOptions = [];
    for ($h = 8; $h <= 17; $h++) { foreach (['00', '30'] as $mm) { $timeOptions[] = sprintf('%02d:%s', $h, $mm); } }
@endphp
<div class="gateway mtg-gateway">
    {{-- Static styles: sent once with the page, not again on every round trip --}}
    @assets
    <style>
        .gateway-main { max-width: 880px; margin: 0 auto; padding: 24px 16px 48px; }
        @media (min-width: 768px) { .gateway-main { padding: 32px 32px 64px; } }
        .gateway .btn { white-space: normal; text-align: center; }
        .mg-section { margin: 0 0 24px; }
        .mg-section > h2 { margin: 0 0 12px; font-size: 1.125rem; line-height: 1.625rem; font-weight: 600; color: var(--color-ink); }
        .mg-list { display: flex; flex-direction: column; gap: 12px; }
        .mg-meeting { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 24px; }
        .mg-meeting h3 { margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .mg-when { margin: 2px 0 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); font-variant-numeric: tabular-nums; }
        .mg-minutes { font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-slate); }
        .mg-minutes p { margin: 0 0 10px; white-space: pre-line; }
        .mg-minutes ul { margin: 0 0 12px; padding-left: 20px; list-style: disc; }
        .mg-minutes h4 { margin: 0 0 6px; font-size: 0.9375rem; font-weight: 600; color: var(--color-ink); }
        .mg-choices { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr)); }
        .mg-choice { display: flex; gap: 12px; align-items: flex-start; text-align: left; padding: 16px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-card); background: var(--color-paper); color: var(--color-ink); font: inherit; cursor: pointer; }
        .mg-choice:hover { background: var(--color-mist); }
        .mg-choice[aria-pressed="true"] { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .mg-choice svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; color: var(--color-deep-teal); }
        .mg-choice strong { display: block; font-size: 0.9375rem; line-height: 1.375rem; }
        .mg-choice span span { display: block; margin-top: 2px; font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
        .mg-day { margin: 0 0 16px; }
        .mg-day h3 { margin: 0 0 8px; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); }
        .mg-slots { display: flex; flex-wrap: wrap; gap: 8px; }
        .mg-slots .segmented-item { min-height: 40px; min-width: 76px; justify-content: center; font-variant-numeric: tabular-nums; }
        .mg-form { display: flex; flex-direction: column; gap: 16px; margin-top: 20px; }
        .mg-pair { display: grid; grid-template-columns: 1.4fr 1fr; gap: 12px; }
        .mg-row2 { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
        @media (max-width: 560px) { .mg-row2 { grid-template-columns: 1fr; } .mg-actions .btn { width: 100%; } }
    </style>
    @endassets

    <x-portal.gateway-header :kicker="__('meetings.portal.kicker')" :width="880" />

    <main class="gateway-main">
        <div class="page-header">
            <div class="min-w-0">
                <h1 class="page-title">{{ __('meetings.portal.title') }}</h1>
                <p class="page-subtitle">{{ __('meetings.portal.subtitle', ['company' => $o->organization?->name, 'title' => $o->title]) }}</p>
            </div>
        </div>

        {{-- What just happened arrives as a toast (resources/views/components/flash.blade.php); the meeting
             itself then shows in the lists below, so the booking is still there once the toast has gone. --}}
        <section class="mg-section" aria-labelledby="mg-upcoming">
            <h2 id="mg-upcoming">{{ __('meetings.portal.upcoming') }}</h2>
            @if ($upcoming->isEmpty() && $requested->isEmpty())
                <p class="help" style="margin: 0">{{ __('meetings.portal.none_upcoming') }}</p>
            @else
                <div class="mg-list">
                    @foreach ($upcoming as $m)
                        <div class="card mg-meeting" wire:key="up-{{ $m->id }}">
                            <div style="min-width: 0">
                                <h3>{{ $m->title }}</h3>
                                <p class="mg-when">{{ ucfirst($day($m->starts_at)) }}, {{ __('meetings.time_range', ['start' => $clock($m->starts_at), 'end' => $clock($m->ends_at)]) }}</p>
                            </div>
                            @if ($m->meet_link)
                                <a href="{{ $m->meet_link }}" class="btn btn-primary" target="_blank" rel="noopener"><i data-lucide="external-link"></i> {{ __('meetings.actions.join') }}</a>
                            @endif
                        </div>
                    @endforeach
                    @foreach ($requested as $m)
                        <div class="card mg-meeting" wire:key="rq-{{ $m->id }}">
                            <div style="min-width: 0">
                                <h3>{{ $m->title }}</h3>
                                <p class="mg-when">{{ __('meetings.portal.waiting') }}</p>
                            </div>
                            <span class="pill pill-warning">{{ __('opportunities.meetings.statuses.requested') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="mg-section card" aria-labelledby="mg-book">
            <h2 id="mg-book" style="margin: 0 0 4px; font-size: 1.125rem; line-height: 1.625rem; font-weight: 600; color: var(--color-ink)">{{ __('meetings.portal.book_title') }}</h2>
            <p class="help" style="margin: 0 0 16px">{{ __('meetings.portal.book_intro') }}</p>

            @error('mode') <div class="alert alert-warning" role="alert" style="margin-bottom: 16px"><i data-lucide="triangle-alert"></i><span>{{ $message }}</span></div> @enderror

            <div class="mg-choices" role="group" aria-label="{{ __('meetings.portal.book_title') }}">
                <button type="button" class="mg-choice" aria-pressed="{{ $mode === 'pick' ? 'true' : 'false' }}" wire:click="choose('pick')">
                    <i data-lucide="calendar"></i><span><strong>{{ __('meetings.portal.pick') }}</strong><span>{{ __('meetings.portal.pick_help') }}</span></span>
                </button>
                <button type="button" class="mg-choice" aria-pressed="{{ $mode === 'propose' ? 'true' : 'false' }}" wire:click="choose('propose')">
                    <i data-lucide="clock"></i><span><strong>{{ __('meetings.portal.propose') }}</strong><span>{{ __('meetings.portal.propose_help') }}</span></span>
                </button>
            </div>
            <div wire:loading wire:target="choose" class="help" role="status" style="margin-top: 12px">{{ __('meetings.portal.loading') }}</div>

            @if ($mode === 'pick' && $times)
                <form wire:submit="book" class="mg-form" novalidate>
                    <fieldset style="border: 0; padding: 0; margin: 0; min-width: 0">
                        <legend class="label" style="margin-bottom: 8px">{{ __('meetings.portal.times', ['minutes' => \App\Support\Opportunities\FreeTimes::MINUTES]) }}</legend>
                        @foreach ($daysShown as $date => $slots)
                            <div class="mg-day" wire:key="day-{{ $date }}">
                                <h3>{{ ucfirst($day($slots[0])) }}</h3>
                                <div class="mg-slots">
                                    @foreach ($slots as $s)
                                        <button type="button" class="segmented-item" aria-pressed="{{ $chosen === $s->toIso8601String() ? 'true' : 'false' }}"
                                                wire:click="pick('{{ $s->toIso8601String() }}')" wire:key="slot-{{ $s->timestamp }}"
                                                aria-label="{{ $short($s) }} {{ $clock($s) }}">{{ $clock($s) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        @if (! $allDays && count($times) > 5)
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('allDays', true)">{{ __('meetings.portal.more_days') }}</button>
                        @endif
                        <span class="help" style="display: block; margin-top: 8px">{{ __('meetings.portal.time_zone') }}</span>
                        @error('chosen') <span class="field-error" role="alert" style="display: block">{{ $message }}</span> @enderror
                    </fieldset>
                    @include('livewire.portal.partials.meeting-person')
                    <div class="mg-actions">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="book" @disabled($chosen === '')>
                            <span wire:loading.remove wire:target="book">{{ $chosen ? __('meetings.portal.book_at', ['time' => $short($chosen) . ' ' . $clock($chosen)]) : __('meetings.portal.book') }}</span>
                            <span wire:loading wire:target="book">{{ __('meetings.portal.booking') }}</span>
                        </button>
                    </div>
                </form>
            @elseif ($mode === 'propose')
                <form wire:submit="propose" class="mg-form" novalidate>
                    <fieldset style="border: 0; padding: 0; margin: 0; min-width: 0">
                        <legend class="label" style="margin-bottom: 8px">{{ __('meetings.portal.proposed') }}</legend>
                        <div style="display: flex; flex-direction: column; gap: 12px">
                            @foreach ($proposed as $i => $p)
                                <div wire:key="prop-{{ $i }}">
                                    <div class="mg-pair">
                                        <div class="field">
                                            <label for="prop-date-{{ $i }}" class="label">{{ __('meetings.portal.choice', ['n' => $i + 1]) }}</label>
                                            <input id="prop-date-{{ $i }}" type="date" class="input" wire:model="proposed.{{ $i }}.date" min="{{ now($tz)->addDay()->format('Y-m-d') }}"
                                                   @error('proposed.' . $i) aria-invalid="true" aria-describedby="prop-{{ $i }}-error" @enderror>
                                        </div>
                                        <div class="field">
                                            <label for="prop-time-{{ $i }}" class="label">{{ __('meetings.portal.time') }}</label>
                                            <select id="prop-time-{{ $i }}" class="select" wire:model="proposed.{{ $i }}.time">
                                                <option value="">{{ __('meetings.portal.choose_time') }}</option>
                                                @foreach ($timeOptions as $opt)
                                                    <option value="{{ $opt }}">{{ $fr ? str_replace(':', "\u{202F}h\u{202F}", ltrim($opt, '0') ?: '0') : $opt }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    @error('proposed.' . $i) <span class="field-error" id="prop-{{ $i }}-error">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                        <span class="help" style="display: block; margin-top: 8px">{{ __('meetings.portal.time_zone') }}</span>
                        @error('proposed') <span class="field-error" role="alert" style="display: block">{{ $message }}</span> @enderror
                    </fieldset>
                    @include('livewire.portal.partials.meeting-person')
                    <div class="mg-actions">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="propose">
                            <span wire:loading.remove wire:target="propose">{{ __('meetings.portal.send_times') }}</span>
                            <span wire:loading wire:target="propose">{{ __('meetings.portal.booking') }}</span>
                        </button>
                    </div>
                </form>
            @endif
        </section>

        @if ($minutes->isNotEmpty())
            <section class="mg-section" aria-labelledby="mg-minutes">
                <h2 id="mg-minutes">{{ __('meetings.portal.minutes') }}</h2>
                <div class="mg-list">
                    @foreach ($minutes as $m)
                        @foreach ($m->transcripts as $t)
                            <article class="card" wire:key="min-{{ $t->id }}">
                                <h3 style="margin: 0; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink)">{{ $m->title }}</h3>
                                <p class="mg-when" style="margin-bottom: 12px">{{ ucfirst($day($m->starts_at)) }}</p>
                                <div class="mg-minutes">
                                    @foreach (\App\Mail\MeetingMinutesMail::blocks((string) $t->minutes) as $block)
                                        @if (isset($block['items']))
                                            @if ($block['heading'])<h4>{{ $block['heading'] }}</h4>@endif
                                            <ul>@foreach ($block['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                                        @else
                                            <p>{{ $block['text'] }}</p>
                                        @endif
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    @endforeach
                </div>
            </section>
        @endif

        <p class="help" style="margin: 24px 0 0">{{ __('meetings.portal.footer') }}</p>
    </main>
</div>
