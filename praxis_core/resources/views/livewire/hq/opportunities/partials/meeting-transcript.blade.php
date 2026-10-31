{{-- One transcript in the Meetings tab: where it came from, the AI's notes, the client minutes (edit and send) and the full text.
     $t transcript, $m its meeting, $runs latest AI runs by purpose, $canManage, $canReadTranscripts, $at (date formatter). --}}
@php
    $notesRun = $runs['meeting_notes'] ?? null;
    $minutesRun = $runs['client_minutes'] ?? null;
    $busy = fn ($run) => $run && ! $run->isFinished();
@endphp
<section class="mtg-transcript" aria-label="{{ __('meetings.transcript.label') }}" wire:key="t-{{ $t->id }}">
    <div class="mtg-source">
        <i data-lucide="file-text"></i>
        <span>{{ __('meetings.transcript.sources.' . $t->source) }} · {{ $at($t->collected_at ?? $t->created_at, $m->time_zone) }}</span>
    </div>

    <div class="mtg-block">
        <h4>{{ __('meetings.notes.title') }}</h4>
        @if (is_array($t->notes) && $t->notes && ! $busy($notesRun))
            @include('livewire.hq.opportunities.partials.meeting-notes', ['notes' => $t->notes])
            @if ($canManage)
                <button type="button" class="btn btn-ghost btn-sm" style="margin-top: 8px" wire:click="draftNotes('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="draftNotes('{{ $t->id }}')">
                    <i data-lucide="refresh-cw"></i> {{ __('meetings.notes.redraft') }}
                </button>
            @endif
        @elseif ($busy($notesRun))
            <div class="mtg-status" role="status"><i data-lucide="loader-circle" class="animate-spin"></i><span>{{ __('meetings.notes.drafting') }}</span></div>
            <div class="mtg-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $notesRun->progress }}" aria-label="{{ __('meetings.notes.drafting') }}"><span style="width: {{ max(5, (int) $notesRun->progress) }}%"></span></div>
        @else
            <div class="mtg-status">
                <i data-lucide="{{ $notesRun?->status === 'failed' ? 'circle-alert' : 'sparkles' }}"></i>
                <span>{{ $notesRun?->status === 'failed' ? __('meetings.notes.failed', ['reason' => $notesRun->errorMessage()]) : __('meetings.notes.none') }}</span>
            </div>
            @if ($canManage)
                <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 8px" wire:click="draftNotes('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="draftNotes('{{ $t->id }}')">
                    <i data-lucide="sparkles"></i> {{ $notesRun?->status === 'failed' ? __('meetings.notes.retry') : __('meetings.notes.draft') }}
                </button>
            @endif
        @endif
    </div>

    @if ($m->kind === 'client')
        <div class="mtg-block">
            <h4>{{ __('meetings.minutes.title') }}</h4>
            @if ($t->minutes_sent_at)
                <div class="mtg-sent">
                    <div class="mtg-status"><i data-lucide="circle-check"></i><span>{{ __('meetings.minutes.sent_on', ['date' => $at($t->minutes_sent_at, $m->time_zone)]) }}</span></div>
                    @foreach (\App\Mail\MeetingMinutesMail::blocks((string) $t->minutes) as $block)
                        @if (isset($block['items']))
                            @if ($block['heading'])<p><strong>{{ $block['heading'] }}</strong></p>@endif
                            <ul>@foreach ($block['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                        @else
                            <p class="mtg-pre">{{ $block['text'] }}</p>
                        @endif
                    @endforeach
                </div>
            @elseif ($busy($minutesRun))
                <div class="mtg-status" role="status"><i data-lucide="loader-circle" class="animate-spin"></i><span>{{ __('meetings.minutes.drafting') }}</span></div>
                <div class="mtg-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $minutesRun->progress }}" aria-label="{{ __('meetings.minutes.drafting') }}"><span style="width: {{ max(5, (int) $minutesRun->progress) }}%"></span></div>
            @elseif (filled($t->minutes) || array_key_exists($t->id, $minutes))
                @php($recipients = $m->attendees->whereIn('role', \App\Support\Opportunities\Meetings::EMAILED_ROLES))
                @if ($canManage)
                    <div class="field">
                        <label for="minutes-{{ $t->id }}" class="label">{{ __('meetings.minutes.edit') }}</label>
                        <textarea id="minutes-{{ $t->id }}" class="textarea" rows="10" maxlength="10000" wire:model="minutes.{{ $t->id }}"
                                  @error('minutes.' . $t->id) aria-invalid="true" aria-describedby="minutes-{{ $t->id }}-error" @else aria-describedby="minutes-{{ $t->id }}-help" @enderror></textarea>
                        @error('minutes.' . $t->id)
                            <span class="field-error" id="minutes-{{ $t->id }}-error">{{ $message }}</span>
                        @else
                            <span class="help" id="minutes-{{ $t->id }}-help">{{ $recipients->isNotEmpty() ? __('meetings.minutes.recipients', ['emails' => $recipients->pluck('email')->implode(', ')]) : __('meetings.errors.no_recipients') }}</span>
                        @enderror
                    </div>
                    <div class="mtg-buttons" style="margin-top: 12px">
                        <button type="button" class="btn btn-primary btn-sm" wire:click="sendMinutes('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="sendMinutes('{{ $t->id }}')" @disabled($recipients->isEmpty())>
                            <i data-lucide="send"></i> {{ __('meetings.minutes.send') }}
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="saveMinutes('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="saveMinutes('{{ $t->id }}')">
                            <i data-lucide="save"></i> {{ __('meetings.minutes.save') }}
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="draftMinutes('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="draftMinutes('{{ $t->id }}')">
                            <i data-lucide="refresh-cw"></i> {{ __('meetings.minutes.redraft') }}
                        </button>
                    </div>
                @else
                    <p class="mtg-pre">{{ $t->minutes }}</p>
                    <p class="help" style="margin: 8px 0 0">{{ __('meetings.minutes.not_sent') }}</p>
                @endif
            @else
                <div class="mtg-status">
                    <i data-lucide="{{ $minutesRun?->status === 'failed' ? 'circle-alert' : 'sparkles' }}"></i>
                    <span>{{ $minutesRun?->status === 'failed' ? __('meetings.minutes.failed', ['reason' => $minutesRun->errorMessage()]) : __('meetings.minutes.none') }}</span>
                </div>
                @if ($canManage)
                    <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 8px" wire:click="draftMinutes('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="draftMinutes('{{ $t->id }}')">
                        <i data-lucide="sparkles"></i> {{ $minutesRun?->status === 'failed' ? __('meetings.minutes.retry') : __('meetings.minutes.draft') }}
                    </button>
                @endif
            @endif
        </div>
    @endif

    <div class="mtg-block">
        @if ($canReadTranscripts)
            <details class="mtg-details">
                <summary>{{ __('meetings.transcript.show', ['count' => number_format(mb_strlen($t->text))]) }}</summary>
                <div class="mtg-text" tabindex="0">{{ $t->text }}</div>
            </details>
        @else
            <p class="help" style="margin: 0">{{ __('meetings.transcript.restricted') }}</p>
        @endif
    </div>
</section>
