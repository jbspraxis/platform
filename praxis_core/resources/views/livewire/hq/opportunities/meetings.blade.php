<div class="mtg" @if ($pending) wire:poll.4s @endif>
    @include('livewire.hq.opportunities.partials.meetings-styles')
    @php
        $locale = app()->getLocale();
        $tz = \App\Support\Opportunities\Meetings::TIME_ZONE;
        $day = fn ($at, $zone = null) => \Illuminate\Support\Carbon::parse($at)->setTimezone($zone ?: $tz)->locale($locale)->translatedFormat(__('opportunities.formats.date'));
        $clock = fn ($at, $zone = null) => \Illuminate\Support\Carbon::parse($at)->setTimezone($zone ?: $tz)->locale($locale)->translatedFormat(__('opportunities.formats.time'));
        $at = fn ($at, $zone = null) => $day($at, $zone) . ', ' . $clock($at, $zone);
        $tone = fn ($status) => match ($status) { 'scheduled' => 'pill-info', 'requested' => 'pill-warning', 'held' => 'pill-success', default => 'pill-neutral' };
        $person = fn ($p) => trim($p->first_name . ' ' . $p->last_name);
        $noticeLook = ['success' => ['alert-success', 'circle-check', 'status'], 'warning' => ['alert-warning', 'triangle-alert', 'alert'], 'error' => ['alert-danger', 'circle-alert', 'alert']];
    @endphp

    @if ($notice)
        @php
            [$noticeClass, $noticeIcon, $noticeRole] = $noticeLook[$noticeTone] ?? $noticeLook['success'];
        @endphp
        <div class="alert {{ $noticeClass }} mb-6" role="{{ $noticeRole }}" wire:key="mtg-notice"><i data-lucide="{{ $noticeIcon }}"></i><span>{{ $notice }}</span></div>
    @endif

    <div class="mtg-toolbar">
        <div class="segmented" role="group" aria-label="{{ __('meetings.views.label') }}">
            <button type="button" class="segmented-item" aria-pressed="{{ $view === 'meetings' ? 'true' : 'false' }}" wire:click="show('meetings')">
                <i data-lucide="calendar"></i> {{ __('meetings.views.meetings') }}
            </button>
            <button type="button" class="segmented-item" aria-pressed="{{ $view === 'transcripts' ? 'true' : 'false' }}" wire:click="show('transcripts')">
                <i data-lucide="file-text"></i> {{ __('meetings.views.transcripts', ['count' => $transcriptCount]) }}
            </button>
        </div>
        @if ($canManage)
            <div class="mtg-toolbar-actions">
                @if ($bookingLink)
                    <button type="button" class="btn btn-secondary" x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($bookingLink)).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                        <i data-lucide="link"></i>
                        <span x-show="!copied">{{ __('meetings.actions.copy_booking') }}</span>
                        <span x-show="copied" x-cloak role="status">{{ __('meetings.actions.copied') }}</span>
                    </button>
                @endif
                <button type="button" class="btn btn-primary" wire:click="openSchedule('client')" @disabled(! $googleConnected)>
                    <i data-lucide="calendar-plus"></i> {{ __('meetings.actions.schedule') }}
                </button>
            </div>
        @endif
    </div>

    @if ($canManage && ! $googleConnected)
        <div class="callout mb-6" role="note" style="display: flex; gap: 10px; align-items: flex-start">
            <i data-lucide="plug" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px"></i>
            <span>
                {{ __('meetings.google_off') }}
                @if ($canOpenIntegrations)
                    <a href="{{ route('hq.system.integrations') }}" wire:navigate>{{ __('meetings.google_connect') }}</a>
                @endif
            </span>
        </div>
    @endif

    @if ($view === 'transcripts')
        <livewire:hq.opportunities.transcript-list :opportunity-id="$opportunityId" :key="'transcripts-' . $opportunityId" />
    @elseif ($upcoming->isEmpty() && $past->isEmpty())
        <div class="card empty-state">
            <strong>{{ __('opportunities.meetings.empty_title') }}</strong>
            {{ $canManage ? __('meetings.empty_manage') : __('opportunities.meetings.empty_text') }}
        </div>
    @else
        @foreach (['upcoming' => $upcoming, 'past' => $past] as $group => $list)
            @continue($list->isEmpty())
            <h2 class="mtg-section">{{ __('meetings.groups.' . $group) }}</h2>
            <div class="mtg-list">
                @foreach ($list as $m)
                    @php
                        $isOpen = $open === $m->id;
                        $started = $m->starts_at->isPast();
                        $changeable = \App\Support\Opportunities\Meetings::canChange($m);
                    @endphp
                    <article class="card mtg-item" wire:key="m-{{ $m->id }}" aria-labelledby="m-title-{{ $m->id }}">
                        <div class="mtg-head">
                            <div class="mtg-when">
                                <strong>{{ $day($m->starts_at, $m->time_zone) }}</strong>
                                <span class="muted">{{ __('meetings.time_range', ['start' => $clock($m->starts_at, $m->time_zone), 'end' => $clock($m->ends_at, $m->time_zone)]) }}</span>
                            </div>
                            <div style="min-width: 0">
                                <h3 class="mtg-title" id="m-title-{{ $m->id }}">{{ $m->title }}</h3>
                                <div class="mtg-tags">
                                    <span class="tag">{{ __('opportunities.meetings.kinds.' . $m->kind) }}</span>
                                    <span class="pill {{ $tone($m->status) }}">{{ __('opportunities.meetings.statuses.' . $m->status) }}</span>
                                    @if ($m->status === 'held' || ($m->status === 'scheduled' && $started))
                                        <span>{{ __('meetings.transcript_status.' . $m->transcript_status) }}</span>
                                    @endif
                                    <span>{{ trans_choice('opportunities.meetings.attendees', $m->attendees->count()) }}</span>
                                </div>
                            </div>
                            <div class="mtg-actions">
                                @if ($m->meet_link && in_array($m->status, ['scheduled'], true) && $m->ends_at->isFuture())
                                    <a href="{{ $m->meet_link }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                                        <i data-lucide="external-link"></i> {{ __('meetings.actions.join') }}
                                    </a>
                                @endif
                                <button type="button" class="btn btn-ghost btn-sm mtg-toggle" wire:click="toggle('{{ $m->id }}')"
                                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="m-body-{{ $m->id }}">
                                    {{ $isOpen ? __('meetings.actions.hide') : __('meetings.actions.details') }} <i data-lucide="chevron-down"></i>
                                </button>
                            </div>
                        </div>

                        @if ($isOpen)
                            <div class="mtg-body" id="m-body-{{ $m->id }}">
                                <div class="mtg-cols">
                                    <div>
                                        <h4 class="mtg-sub">{{ __('meetings.attendees') }}</h4>
                                        <ul class="mtg-people">
                                            @forelse ($m->attendees as $a)
                                                <li wire:key="a-{{ $a->id }}">
                                                    <span>{{ $a->name ?: $a->email }}</span>
                                                    <span class="tag">{{ __('meetings.roles.' . $a->role) }}</span>
                                                    @if ($a->name)<span class="muted">{{ $a->email }}</span>@endif
                                                    @if ($a->response && $a->response !== 'needsAction')<span class="muted">· {{ __('meetings.responses.' . $a->response) }}</span>@endif
                                                </li>
                                            @empty
                                                <li class="muted">{{ trans_choice('opportunities.meetings.attendees', 0) }}</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                    <div>
                                        <h4 class="mtg-sub">{{ __('meetings.agenda.title') }}</h4>
                                        <p class="mtg-pre">{{ $m->agenda ?: __('meetings.agenda.none') }}</p>
                                        @if ($m->meet_link)
                                            <h4 class="mtg-sub" style="margin-top: 16px">{{ __('meetings.link') }}</h4>
                                            <a class="mtg-link" href="{{ $m->meet_link }}" target="_blank" rel="noopener"><i data-lucide="external-link" style="width: 16px; height: 16px"></i>{{ $m->meet_link }}</a>
                                        @endif
                                        @if ($m->kind === 'internal')
                                            <p class="help" style="margin: 12px 0 0">{{ __('meetings.internal_note') }}</p>
                                        @endif
                                    </div>
                                </div>

                                @if ($m->status === 'requested')
                                    <div class="callout" role="note">{{ __('meetings.requested_note') }}</div>
                                @endif

                                @if ($canManage && $changeable)
                                    <div class="mtg-buttons">
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openEdit('{{ $m->id }}')" @disabled(! $googleConnected)>
                                            <i data-lucide="{{ $m->status === 'requested' ? 'calendar-plus' : 'pencil' }}"></i>
                                            {{ $m->status === 'requested' ? __('meetings.actions.confirm_request') : __('meetings.actions.change') }}
                                        </button>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="confirmCancel('{{ $m->id }}')">
                                            <i data-lucide="x"></i> {{ $m->status === 'requested' ? __('meetings.actions.decline') : __('meetings.actions.cancel') }}
                                        </button>
                                    </div>
                                @endif

                                @if ($started && in_array($m->status, ['scheduled', 'held'], true))
                                    <div>
                                        <h4 class="mtg-sub">{{ __('meetings.transcript.title') }}</h4>
                                        @forelse ($m->transcripts as $t)
                                            @include('livewire.hq.opportunities.partials.meeting-transcript', ['t' => $t, 'runs' => $runs[$t->id] ?? []])
                                        @empty
                                            <p class="mtg-status" style="margin: 0 0 12px"><i data-lucide="clock"></i><span>{{ __('meetings.transcript_help.' . $m->transcript_status) }}</span></p>
                                        @endforelse
                                        @if ($canManage)
                                            <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 12px" wire:click="openTranscript('{{ $m->id }}')">
                                                <i data-lucide="upload"></i> {{ $m->transcripts->isEmpty() ? __('meetings.actions.add_transcript') : __('meetings.actions.add_another') }}
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endforeach
    @endif

    {{-- Schedule or change a meeting --}}
    @if ($formOpen)
        <div class="modal-backdrop" wire:key="mtg-form" x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="modal mtg-modal" role="dialog" aria-modal="true" aria-labelledby="mtg-form-title">
                <h2 class="modal-title" id="mtg-form-title">
                    {{ $editing ? ($editing->status === 'requested' ? __('meetings.form.confirm_title') : __('meetings.form.change_title')) : __('meetings.form.title') }}
                </h2>
                <p class="help" style="margin: 0 0 16px">{{ $editing ? __('meetings.form.change_intro') : __('meetings.form.intro') }}</p>
                @error('google')
                    <div class="alert alert-danger" role="alert" style="margin-bottom: 16px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                @enderror
                <form wire:submit="save" class="mtg-form" novalidate>
                    @unless ($editing)
                        <fieldset class="field mtg-fieldset">
                            <legend class="label">{{ __('meetings.form.kind') }}</legend>
                            <div class="mtg-radios">
                                @foreach (\App\Support\Opportunities\Meetings::KINDS as $k)
                                    <label><input type="radio" wire:model.live="kind" value="{{ $k }}"> {{ __('opportunities.meetings.kinds.' . $k) }}</label>
                                @endforeach
                            </div>
                            <span class="help">{{ __('meetings.form.kind_help.' . $kind) }}</span>
                        </fieldset>
                    @endunless

                    <div class="field">
                        <label for="mtg-title" class="label">{{ __('meetings.form.title_field') }}</label>
                        <input id="mtg-title" type="text" class="input" wire:model="title" maxlength="200"
                               @error('title') aria-invalid="true" aria-describedby="mtg-title-error" @enderror>
                        @error('title') <span class="field-error" id="mtg-title-error">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="mtg-row">
                            <div class="field">
                                <label for="mtg-date" class="label">{{ __('meetings.form.date') }}</label>
                                <input id="mtg-date" type="date" class="input" wire:model="date" min="{{ now($tz)->format('Y-m-d') }}"
                                       @error('date') aria-invalid="true" aria-describedby="mtg-date-error" @else aria-describedby="mtg-tz-help" @enderror>
                            </div>
                            <div class="field">
                                <label for="mtg-time" class="label">{{ __('meetings.form.time') }}</label>
                                <input id="mtg-time" type="time" class="input" wire:model="time" step="300"
                                       @error('time') aria-invalid="true" aria-describedby="mtg-time-error" @else aria-describedby="mtg-tz-help" @enderror>
                            </div>
                            <div class="field">
                                <label for="mtg-duration" class="label">{{ __('meetings.form.duration') }}</label>
                                <select id="mtg-duration" class="select" wire:model="duration" @error('duration') aria-invalid="true" @enderror>
                                    @foreach (collect(\App\Support\Opportunities\Meetings::DURATIONS)->push((int) $duration)->unique()->sort() as $minutes)
                                        <option value="{{ $minutes }}">{{ trans_choice('meetings.form.minutes', $minutes, ['count' => $minutes]) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @error('date') <span class="field-error" id="mtg-date-error">{{ $message }}</span> @enderror
                        @error('time') <span class="field-error" id="mtg-time-error">{{ $message }}</span> @enderror
                        @error('duration') <span class="field-error">{{ $message }}</span> @enderror
                        <span class="help" id="mtg-tz-help" style="display: block; margin-top: 4px">{{ __('meetings.form.time_zone') }}</span>
                    </div>

                    <fieldset class="field mtg-fieldset">
                        <legend class="label">{{ __('meetings.form.staff') }}</legend>
                        <div class="mtg-checks" @error('staff') aria-invalid="true" aria-describedby="mtg-staff-error" @enderror>
                            @foreach ($staffList as $s)
                                <label wire:key="staff-{{ $s->id }}"><input type="checkbox" wire:model="staff" value="{{ $s->id }}">
                                    <span>{{ $person($s) }}@if ($s->job_title)<span class="muted"> · {{ $s->job_title }}</span>@endif</span></label>
                            @endforeach
                        </div>
                        @error('staff') <span class="field-error" id="mtg-staff-error">{{ $message }}</span> @enderror
                        @error('staff.*') <span class="field-error">{{ $message }}</span> @enderror
                    </fieldset>

                    @if (($editing?->kind ?? $kind) === 'client')
                        <fieldset class="field mtg-fieldset">
                            <legend class="label">{{ __('meetings.form.contacts', ['organisation' => $o->organization?->name]) }}</legend>
                            @if ($contactList->isNotEmpty())
                                <div class="mtg-checks">
                                    @foreach ($contactList as $c)
                                        <label wire:key="contact-{{ $c->id }}"><input type="checkbox" wire:model="contacts" value="{{ $c->id }}">
                                            <span>{{ $person($c) ?: $c->email }}<span class="muted"> · {{ $c->email }}</span></span></label>
                                    @endforeach
                                </div>
                            @else
                                <p class="help" style="margin: 0">{{ __('meetings.form.no_contacts') }}</p>
                            @endif
                            @error('contacts') <span class="field-error">{{ $message }}</span> @enderror
                            @error('contacts.*') <span class="field-error">{{ $message }}</span> @enderror
                        </fieldset>
                    @endif

                    <div class="field">
                        <label for="mtg-guests" class="label">{{ __('meetings.form.guests') }}</label>
                        <textarea id="mtg-guests" class="textarea" rows="2" wire:model="guests" placeholder="name@example.com"
                                  @error('guests') aria-invalid="true" aria-describedby="mtg-guests-error" @else aria-describedby="mtg-guests-help" @enderror></textarea>
                        @error('guests')
                            <span class="field-error" id="mtg-guests-error">{{ $message }}</span>
                        @else
                            <span class="help" id="mtg-guests-help">{{ __('meetings.form.guests_help.' . ($editing?->kind ?? $kind)) }}</span>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="mtg-agenda" class="label">{{ __('meetings.form.agenda') }}</label>
                        <textarea id="mtg-agenda" class="textarea" rows="5" maxlength="5000" wire:model="agenda"
                                  @error('agenda') aria-invalid="true" aria-describedby="mtg-agenda-error" @else aria-describedby="mtg-agenda-help" @enderror></textarea>
                        @error('agenda')
                            <span class="field-error" id="mtg-agenda-error">{{ $message }}</span>
                        @else
                            <span class="help" id="mtg-agenda-help">{{ (($editing?->kind ?? $kind) === 'client') ? __('meetings.form.agenda_help.client') : __('meetings.form.agenda_help.internal') }}</span>
                        @enderror
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeForm">{{ __('opportunities.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">{{ $editing ? ($editing->status === 'requested' ? __('meetings.form.confirm_submit') : __('meetings.form.change_submit')) : __('meetings.form.submit') }}</span>
                            <span wire:loading wire:target="save">{{ __('meetings.form.submitting') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Cancel a meeting or decline a request --}}
    @if ($cancelling)
        @php($request = $cancelling->status === 'requested')
        <div class="modal-backdrop" wire:key="mtg-cancel" x-data x-on:keydown.escape.window="$wire.keepMeeting()">
            <div class="modal mtg-modal" role="alertdialog" aria-modal="true" aria-labelledby="mtg-cancel-title" aria-describedby="mtg-cancel-text">
                <h2 class="modal-title" id="mtg-cancel-title">{{ $request ? __('meetings.cancel.decline_title') : __('meetings.cancel.title') }}</h2>
                <p class="help" id="mtg-cancel-text" style="margin: 0">
                    {{ __($request ? 'meetings.cancel.decline_text' : 'meetings.cancel.text_' . $cancelling->kind, ['title' => $cancelling->title, 'date' => $at($cancelling->starts_at, $cancelling->time_zone)]) }}
                </p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="keepMeeting" autofocus>{{ $request ? __('meetings.cancel.keep_request') : __('meetings.cancel.keep') }}</button>
                    <button type="button" class="btn btn-danger" wire:click="cancelMeeting" wire:loading.attr="disabled" wire:target="cancelMeeting">
                        {{ $request ? __('meetings.cancel.decline_confirm') : __('meetings.cancel.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Add a transcript by hand: a .txt or .vtt file is read in the browser, or the text is pasted --}}
    @if ($addingTo)
        <div class="modal-backdrop" wire:key="mtg-transcript" x-data="{ fileName: '', fileError: '' }" x-on:keydown.escape.window="$wire.closeTranscript()">
            <div class="modal mtg-modal" role="dialog" aria-modal="true" aria-labelledby="mtg-transcript-title">
                <h2 class="modal-title" id="mtg-transcript-title">{{ __('meetings.add.title') }}</h2>
                <p class="help" style="margin: 0 0 16px">{{ __('meetings.add.intro', ['title' => $addingTo->title, 'date' => $day($addingTo->starts_at, $addingTo->time_zone)]) }}</p>
                <form wire:submit="saveTranscript" class="mtg-form" novalidate>
                    <div class="field">
                        <label for="mtg-file" class="label">{{ __('meetings.add.file') }}</label>
                        <input id="mtg-file" type="file" class="input" accept=".txt,.vtt,text/plain,text/vtt" aria-describedby="mtg-file-help"
                               x-on:change="
                                   fileError = ''; const f = $event.target.files[0]; if (!f) return;
                                   if (f.size > 2000000) { fileError = @js(__('meetings.add.file_big')); $event.target.value = ''; return; }
                                   const reader = new FileReader();
                                   reader.onload = () => { $refs.text.value = reader.result; $refs.text.dispatchEvent(new Event('input')); $wire.transcriptSource = 'upload'; fileName = f.name; };
                                   reader.onerror = () => { fileError = @js(__('meetings.add.file_unreadable')); };
                                   reader.readAsText(f);">
                        <span class="help" id="mtg-file-help">{{ __('meetings.add.file_help') }}</span>
                        <span class="field-error" x-show="fileError" x-text="fileError" x-cloak role="alert"></span>
                        <span class="help" x-show="fileName" x-cloak role="status" x-text="@js(__('meetings.add.file_read')) + ' ' + fileName"></span>
                    </div>
                    <div class="field">
                        <label for="mtg-text" class="label">{{ __('meetings.add.text') }}</label>
                        <textarea id="mtg-text" x-ref="text" class="textarea" rows="10" wire:model="transcriptText"
                                  @error('transcriptText') aria-invalid="true" aria-describedby="mtg-text-error" @else aria-describedby="mtg-text-help" @enderror></textarea>
                        @error('transcriptText')
                            <span class="field-error" id="mtg-text-error">{{ $message }}</span>
                        @else
                            <span class="help" id="mtg-text-help">{{ __('meetings.add.text_help') }}</span>
                        @enderror
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeTranscript">{{ __('opportunities.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveTranscript">
                            <i data-lucide="upload"></i> {{ __('meetings.add.submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
