<div class="mtg-all">
    @include('livewire.hq.opportunities.partials.meetings-styles')
    @php
        $locale = app()->getLocale();
        $day = fn ($at, $zone) => \Illuminate\Support\Carbon::parse($at)->setTimezone($zone ?: 'Africa/Lagos')->locale($locale)->translatedFormat(__('opportunities.formats.date'));
    @endphp
    @if ($transcripts->isEmpty())
        <div class="card empty-state"><strong>{{ __('meetings.all.empty_title') }}</strong>{{ __('meetings.all.empty_text') }}</div>
    @else
        <p class="help" style="margin: 0 0 12px">{{ $canReadTranscripts ? __('meetings.all.intro') : __('meetings.all.intro_restricted') }}</p>
        <div class="mtg-list">
            @foreach ($transcripts as $t)
                @php($m = $t->meeting)
                <article class="card mtg-item" wire:key="all-{{ $t->id }}" aria-labelledby="all-title-{{ $t->id }}">
                    <div style="display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 12px">
                        <h3 class="mtg-title" id="all-title-{{ $t->id }}">{{ $m->title }}</h3>
                        <span class="tag">{{ __('opportunities.meetings.kinds.' . $m->kind) }}</span>
                        <span class="muted" style="font-size: 0.875rem">{{ $day($m->starts_at, $m->time_zone) }} · {{ __('meetings.transcript.sources.' . $t->source) }}</span>
                    </div>
                    <div class="mtg-body" style="margin-top: 12px; padding-top: 12px">
                        @if (is_array($t->notes) && $t->notes)
                            <div class="mtg-block">
                                <h4>{{ __('meetings.notes.title') }}</h4>
                                @include('livewire.hq.opportunities.partials.meeting-notes', ['notes' => $t->notes])
                                <div style="margin-top: 8px">
                                    @include('livewire.hq.opportunities.partials.meeting-notes-download', ['where' => 'all'])
                                </div>
                            </div>
                        @else
                            <p class="help" style="margin: 0">{{ __('meetings.notes.none') }}</p>
                        @endif
                        @if ($m->kind === 'client' && $t->minutes_sent_at)
                            <div class="mtg-block mtg-sent">
                                <h4>{{ __('meetings.minutes.title') }}</h4>
                                <p class="muted" style="margin: 0">{{ __('meetings.minutes.sent_on', ['date' => $day($t->minutes_sent_at, $m->time_zone)]) }}</p>
                                <p class="mtg-pre">{{ $t->minutes }}</p>
                            </div>
                        @endif
                        @if ($canReadTranscripts)
                            <details class="mtg-details">
                                <summary>{{ __('meetings.transcript.show', ['count' => number_format(mb_strlen($t->text))]) }}</summary>
                                <div class="mtg-text" tabindex="0">{{ $t->text }}</div>
                            </details>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
