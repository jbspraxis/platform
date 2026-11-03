{{-- A lead's Meetings tab (App\Livewire\Hq\Leads\MeetingsTab, docs/LEADS.md section 9). HQ is English. The meeting styles,
     the AI's notes and the transcript block are the opportunity Meetings tab's partials. --}}
<div class="mtg" @if ($pending) wire:poll.4s @endif>
    @include('livewire.hq.opportunities.partials.meetings-styles')
    <style>
        .lm-typed { border: 1px solid var(--color-line); border-radius: var(--radius-card); padding: 16px; display: flex; flex-direction: column; gap: 8px; }
        .lm-typed-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
        .lm-typed-head h4 { margin: 0; font-size: 0.9375rem; line-height: 1.375rem; font-weight: 600; color: var(--color-ink); }
        .lm-opp-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; }
        .lm-next { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-top: 8px; font-size: 0.875rem; color: var(--color-slate); }
        .lm-where { display: flex; align-items: flex-start; gap: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); overflow-wrap: anywhere; }
        .lm-where svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 3px; }
        .lm-contact-off { color: var(--color-muted); }
        @media (max-width: 600px) {
            .mtg-toolbar-actions .btn, .mtg-buttons .btn { flex: 1 1 100%; justify-content: center; }
            .mtg-row { grid-template-columns: 1fr; }
        }
    </style>
    @php
        $tz = \App\Support\Opportunities\Meetings::TIME_ZONE;
        $day = fn ($at, $zone = null) => \Illuminate\Support\Carbon::parse($at)->setTimezone($zone ?: $tz)->format('D j M Y');
        $clock = fn ($at, $zone = null) => \Illuminate\Support\Carbon::parse($at)->setTimezone($zone ?: $tz)->format('H:i');
        $at = fn ($at, $zone = null) => $day($at, $zone) . ', ' . $clock($at, $zone);
        $tone = fn ($status) => match ($status) { 'scheduled' => 'pill-info', 'requested' => 'pill-warning', 'held' => 'pill-success', default => 'pill-neutral' };
        $statusLabel = fn ($m) => match ($m->status) { 'requested' => 'Waiting for approval', 'scheduled' => 'Scheduled', 'held' => 'Held', default => 'Cancelled' };
        $person = fn ($p) => trim($p->first_name . ' ' . $p->last_name);
        $noticeLook = ['success' => ['alert-success', 'circle-check', 'status'], 'warning' => ['alert-warning', 'triangle-alert', 'alert'], 'error' => ['alert-danger', 'circle-alert', 'alert']];
        $roles = ['staff' => 'JBS Praxis', 'client' => 'Their side', 'guest' => 'Guest'];
        // The opportunity transcript partial reads these
        $minutes = [];
    @endphp

    @if ($notice)
        @php [$noticeClass, $noticeIcon, $noticeRole] = $noticeLook[$noticeTone] ?? $noticeLook['success']; @endphp
        <div class="alert {{ $noticeClass }} mb-6" role="{{ $noticeRole }}" wire:key="lm-notice"><i data-lucide="{{ $noticeIcon }}"></i><span>{{ $notice }}</span></div>
    @endif

    <div class="mtg-toolbar">
        <p class="help" style="margin: 0; max-width: 560px">Meetings with {{ $lead->name }} before any opportunity: each keeps its transcript, the AI’s notes and your own. Open an opportunity from one when there is work to discuss.</p>
        @if ($canManage)
            <div class="mtg-toolbar-actions">
                <button type="button" class="btn btn-secondary" wire:click="openLog"><i data-lucide="notebook-pen"></i> Log a meeting</button>
                <button type="button" class="btn btn-primary" wire:click="openSchedule" @disabled(! $googleConnected)><i data-lucide="calendar-plus"></i> Schedule a meeting</button>
            </div>
        @endif
    </div>

    @if ($canManage && ! $googleConnected)
        <div class="callout mb-6" role="note" style="display: flex; gap: 10px; align-items: flex-start">
            <i data-lucide="plug" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px"></i>
            <span>
                Google isn’t connected, so HQ can’t send invitations yet. You can still log a meeting after it happens.
                @if ($canOpenIntegrations)
                    <a href="{{ route('hq.system.integrations') }}" wire:navigate>Connect Google</a>
                @endif
            </span>
        </div>
    @endif

    @if ($upcoming->isEmpty() && $past->isEmpty() && $inOpportunities->isEmpty())
        <div class="card empty-state">
            <strong>No meetings yet</strong>
            {{ $canManage ? 'Schedule one with a Google invite and a Meet link, or in person at an address. Had one already? Log it, with your notes and the transcript if you have it.' : 'Meetings with this lead show here once someone schedules or logs one.' }}
        </div>
    @endif

    @foreach (['Upcoming' => $upcoming, 'Past' => $past] as $group => $list)
        @continue($list->isEmpty())
        <h2 class="mtg-section">{{ $group }}</h2>
        <div class="mtg-list">
            @foreach ($list as $m)
                @php
                    $isOpen = $open === $m->id;
                    $started = $m->starts_at->isPast();
                    $changeable = \App\Support\Opportunities\Meetings::canChange($m) && $m->status !== 'requested';
                    $latest = $m->transcripts->last();
                @endphp
                <article class="card mtg-item" wire:key="lm-{{ $m->id }}" aria-labelledby="lm-title-{{ $m->id }}">
                    <div class="mtg-head">
                        <div class="mtg-when">
                            <strong>{{ $day($m->starts_at, $m->time_zone) }}</strong>
                            <span class="muted">{{ $clock($m->starts_at, $m->time_zone) }}–{{ $clock($m->ends_at, $m->time_zone) }} WAT</span>
                        </div>
                        <div style="min-width: 0">
                            <h3 class="mtg-title" id="lm-title-{{ $m->id }}">{{ $m->title }}</h3>
                            <div class="mtg-tags">
                                <span class="pill {{ $tone($m->status) }}">{{ $statusLabel($m) }}</span>
                                @if ($m->appointment_id)<span class="tag">Website booking</span>@endif
                                <span>{{ $m->location ? 'In person' : ($m->meet_link ? 'Google Meet' : ($m->status === 'requested' ? 'Meet link once approved' : 'Call or video')) }}</span>
                                @if ($m->status === 'held' || ($m->status === 'scheduled' && $started))
                                    <span>{{ __('meetings.transcript_status.' . $m->transcript_status) }}</span>
                                @endif
                                <span>{{ trans_choice('opportunities.meetings.attendees', $m->attendees->count()) }}</span>
                            </div>
                        </div>
                        <div class="mtg-actions">
                            @if ($m->meet_link && $m->status === 'scheduled' && $m->ends_at->isFuture())
                                <a href="{{ $m->meet_link }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener"><i data-lucide="external-link"></i> Join</a>
                            @endif
                            <button type="button" class="btn btn-ghost btn-sm mtg-toggle" wire:click="toggle('{{ $m->id }}')"
                                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="lm-body-{{ $m->id }}">
                                {{ $isOpen ? 'Hide' : 'Details' }} <i data-lucide="chevron-down"></i>
                            </button>
                        </div>
                    </div>

                    @if ($isOpen)
                        <div class="mtg-body" id="lm-body-{{ $m->id }}">
                            <div class="mtg-cols">
                                <div>
                                    <h4 class="mtg-sub">Who</h4>
                                    <ul class="mtg-people">
                                        @forelse ($m->attendees as $a)
                                            <li wire:key="lma-{{ $a->id }}">
                                                <span>{{ $a->name ?: $a->email }}</span>
                                                <span class="tag">{{ $roles[$a->role] ?? $a->role }}</span>
                                                @if ($a->name)<span class="muted">{{ $a->email }}</span>@endif
                                                @if ($a->response && $a->response !== 'needsAction')<span class="muted">· {{ __('meetings.responses.' . $a->response) }}</span>@endif
                                            </li>
                                        @empty
                                            <li class="muted">Nobody recorded.</li>
                                        @endforelse
                                    </ul>
                                </div>
                                <div>
                                    <h4 class="mtg-sub">Where</h4>
                                    @if ($m->location)
                                        <p class="lm-where"><i data-lucide="map-pin"></i><span>{{ $m->location }}</span></p>
                                    @elseif ($m->meet_link)
                                        <a class="mtg-link" href="{{ $m->meet_link }}" target="_blank" rel="noopener"><i data-lucide="external-link" style="width: 16px; height: 16px"></i>{{ $m->meet_link }}</a>
                                    @else
                                        <p class="mtg-pre">{{ $m->status === 'requested' ? 'The Meet link comes when Free audits approves it.' : 'No link or address recorded.' }}</p>
                                    @endif
                                    @if (filled($m->agenda))
                                        <h4 class="mtg-sub" style="margin-top: 16px">Agenda</h4>
                                        <p class="mtg-pre">{{ $m->agenda }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($m->status === 'requested')
                                <div class="callout" role="note" style="display: flex; gap: 10px; align-items: flex-start">
                                    <i data-lucide="calendar-clock" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px"></i>
                                    <span>Booked on the website for a free audit. Approve it or decline it in <a href="{{ route('hq.crm.consult') }}" wire:navigate>Free audits</a>: approving sends the Meet link and keeps this meeting here.</span>
                                </div>
                            @endif

                            @php $canOpenFrom = $canOpenOpportunity && \App\Support\Leads\LeadMeetings::canOpenOpportunity($m); @endphp
                            @if ($canManage && ($changeable || $canOpenFrom))
                                <div class="mtg-buttons">
                                    @if ($canOpenFrom)
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="openOpportunity('{{ $m->id }}')"><i data-lucide="folder-plus"></i> Open an opportunity from it</button>
                                    @endif
                                    @if ($changeable)
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openEdit('{{ $m->id }}')" @disabled(! $googleConnected)><i data-lucide="pencil"></i> Change</button>
                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="confirmCancel('{{ $m->id }}')"><i data-lucide="x"></i> Cancel the meeting</button>
                                    @endif
                                </div>
                            @endif

                            @if ($m->status !== 'requested' && $m->status !== 'cancelled')
                                <section class="lm-typed" aria-labelledby="lm-notes-{{ $m->id }}">
                                    <div class="lm-typed-head">
                                        <h4 id="lm-notes-{{ $m->id }}">Your notes</h4>
                                        <div class="mtg-buttons">
                                            @if (! $latest && filled($m->typed_notes))
                                                <a href="{{ route('hq.crm.lead-meeting-notes', ['organization' => $lead->id, 'meeting' => $m->id, 'what' => 'notes']) }}" class="btn btn-ghost btn-sm" download><i data-lucide="download"></i> Download</a>
                                            @endif
                                            @if ($canManage && $notesFor !== $m->id)
                                                <button type="button" class="btn btn-ghost btn-sm" wire:click="editNotes('{{ $m->id }}')"><i data-lucide="pencil"></i> {{ filled($m->typed_notes) ? 'Edit' : 'Add notes' }}</button>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($notesFor === $m->id)
                                        <form wire:submit="saveNotes" class="mtg-form" novalidate>
                                            <div class="field">
                                                <label for="lm-notes-text" class="label">What was said and agreed <span class="ld-optional">(optional)</span></label>
                                                <textarea id="lm-notes-text" class="textarea" rows="6" maxlength="{{ \App\Support\Leads\LeadMeetings::MAX_NOTES }}" wire:model="notesText"
                                                          @error('notesText') aria-invalid="true" aria-describedby="lm-notes-error" @enderror></textarea>
                                                @error('notesText') <span class="field-error" id="lm-notes-error">{{ $message }}</span> @enderror
                                            </div>
                                            <div class="mtg-buttons">
                                                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveNotes"><i data-lucide="save"></i> Save notes</button>
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="closeNotes">Cancel</button>
                                            </div>
                                        </form>
                                    @elseif (filled($m->typed_notes))
                                        <p class="mtg-pre">{{ $m->typed_notes }}</p>
                                    @else
                                        <p class="help" style="margin: 0">Nothing typed yet. Your notes stay in HQ and go with the meeting if you open an opportunity from it.</p>
                                    @endif
                                </section>
                            @endif

                            @if ($started && in_array($m->status, ['scheduled', 'held'], true))
                                <div>
                                    <h4 class="mtg-sub">Transcript and the AI’s notes</h4>
                                    @forelse ($m->transcripts as $t)
                                        @include('livewire.hq.opportunities.partials.meeting-transcript', ['t' => $t, 'runs' => $runs[$t->id] ?? []])
                                        @php $firstStep = collect((array) ($t->notes['next_steps'] ?? []))->pluck('action')->first(fn ($a) => filled($a)); @endphp
                                        @if ($canManage && $firstStep)
                                            <div class="lm-next">
                                                <span>Their next step for the lead?</span>
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="useNextStep('{{ $t->id }}')" wire:loading.attr="disabled" wire:target="useNextStep">
                                                    <i data-lucide="clock"></i> Make “{{ \Illuminate\Support\Str::limit($firstStep, 60) }}” the next step
                                                </button>
                                            </div>
                                        @endif
                                    @empty
                                        <p class="mtg-status" style="margin: 0 0 12px"><i data-lucide="clock"></i><span>{{ __('meetings.transcript_help.' . $m->transcript_status) }}</span></p>
                                    @endforelse
                                    @if ($canManage)
                                        <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 12px" wire:click="openTranscript('{{ $m->id }}')">
                                            <i data-lucide="upload"></i> {{ $m->transcripts->isEmpty() ? 'Add the transcript' : 'Add another transcript' }}
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

    @if ($inOpportunities->isNotEmpty())
        <h2 class="mtg-section">In opportunities</h2>
        <div class="card" style="padding: 16px 20px">
            <ul class="ld-opps">
                @foreach ($inOpportunities as $m)
                    <li wire:key="lmo-{{ $m->id }}">
                        <span style="min-width: 0">
                            <span class="font-semibold text-ink" style="overflow-wrap: anywhere">{{ $m->title }}</span>
                            <span class="help" style="display: block">
                                {{ $at($m->starts_at, $m->time_zone) }} WAT · {{ $statusLabel($m) }}
                                @if ($m->opportunity)
                                    · @if ($canSeeOpportunity($m->opportunity))<a href="{{ route('hq.crm.opportunity', ['opportunity' => $m->opportunity_id, 'tab' => 'meetings', 'meeting' => $m->id]) }}" wire:navigate>{{ $m->opportunity->title }}</a>@else{{ $m->opportunity->title }}@endif
                                @endif
                            </span>
                        </span>
                        <span class="pill {{ $tone($m->status) }}">{{ $statusLabel($m) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Schedule, log or change a meeting --}}
    @if ($form !== '')
        @php
            $logging = $form === 'log';
            $whereNow = $editing ? ($editing->location ? 'in_person' : 'meet') : $where;
        @endphp
        <div class="modal-backdrop" wire:key="lm-form" x-data x-on:keydown.escape.window="$wire.closeForm()">
            <div class="modal mtg-modal" role="dialog" aria-modal="true" aria-labelledby="lm-form-title">
                <h2 class="modal-title" id="lm-form-title">{{ $editing ? 'Change the meeting' : ($logging ? 'Log a meeting' : 'Schedule a meeting') }}</h2>
                <p class="help" style="margin: 0 0 16px">
                    {{ $editing ? 'Google tells everyone invited; new guests get the invitation.' : ($logging ? 'A meeting you already had. Nobody is emailed. Add your notes, and the transcript if you have one: the AI then writes the notes and next steps.' : 'HQ creates it in the company calendar. Google sends the invitations; their contacts also get our email in the lead’s language.') }}
                </p>
                @error('google')
                    <div class="alert alert-danger" role="alert" style="margin-bottom: 16px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                @enderror
                <form wire:submit="save" class="mtg-form" novalidate>
                    <div class="field">
                        <label for="lm-title" class="label">Title</label>
                        <input id="lm-title" type="text" class="input" wire:model="title" maxlength="200"
                               @error('title') aria-invalid="true" aria-describedby="lm-title-error" @enderror>
                        @error('title') <span class="field-error" id="lm-title-error">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="mtg-row">
                            <div class="field">
                                <label for="lm-date" class="label">Date</label>
                                <input id="lm-date" type="date" class="input" wire:model="date" @if ($logging) max="{{ now($tz)->format('Y-m-d') }}" @else min="{{ now($tz)->format('Y-m-d') }}" @endif
                                       @error('date') aria-invalid="true" aria-describedby="lm-date-error" @else aria-describedby="lm-tz-help" @enderror>
                            </div>
                            <div class="field">
                                <label for="lm-time" class="label">{{ $logging ? 'Started at' : 'Time' }}</label>
                                <input id="lm-time" type="time" class="input" wire:model="time" step="300"
                                       @error('time') aria-invalid="true" aria-describedby="lm-time-error" @else aria-describedby="lm-tz-help" @enderror>
                            </div>
                            <div class="field">
                                <label for="lm-duration" class="label">How long</label>
                                <select id="lm-duration" class="select" wire:model="duration" @error('duration') aria-invalid="true" @enderror>
                                    @foreach (collect([15, ...\App\Support\Leads\LeadMeetings::DURATIONS])->push((int) $duration)->unique()->sort() as $mins)
                                        <option value="{{ $mins }}">{{ $mins < 60 || $mins % 60 ? $mins . ' minutes' : ($mins / 60) . ' hour' . ($mins > 60 ? 's' : '') }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @error('date') <span class="field-error" id="lm-date-error">{{ $message }}</span> @enderror
                        @error('time') <span class="field-error" id="lm-time-error">{{ $message }}</span> @enderror
                        @error('duration') <span class="field-error">{{ $message }}</span> @enderror
                        <span class="help" id="lm-tz-help" style="display: block; margin-top: 4px">West Africa Time (Lagos), as everyone in HQ sees it.</span>
                    </div>

                    @unless ($editing)
                        <fieldset class="field mtg-fieldset">
                            <legend class="label">Where</legend>
                            <div class="mtg-radios">
                                <label><input type="radio" wire:model.live="where" value="meet"> {{ $logging ? 'A call or video' : 'Google Meet' }}</label>
                                <label><input type="radio" wire:model.live="where" value="in_person"> In person</label>
                            </div>
                        </fieldset>
                    @endunless
                    @if ($whereNow === 'in_person')
                        <div class="field">
                            <label for="lm-location" class="label">Address</label>
                            <input id="lm-location" type="text" class="input" wire:model="location" maxlength="255" autocomplete="street-address"
                                   @error('location') aria-invalid="true" aria-describedby="lm-location-error" @else aria-describedby="lm-location-help" @enderror>
                            @error('location')
                                <span class="field-error" id="lm-location-error">{{ $message }}</span>
                            @else
                                <span class="help" id="lm-location-help">{{ $logging ? 'Where you met.' : 'The invitation carries it; nothing is recorded in person, so add the transcript or your notes afterwards.' }}</span>
                            @enderror
                        </div>
                    @elseif ($logging)
                        <div class="field">
                            <label for="lm-link" class="label">Link of the call <span class="ld-optional">(optional)</span></label>
                            <input id="lm-link" type="url" class="input" wire:model="meetLink" maxlength="255" placeholder="https://meet.google.com/…"
                                   @error('meetLink') aria-invalid="true" aria-describedby="lm-link-error" @enderror>
                            @error('meetLink') <span class="field-error" id="lm-link-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <fieldset class="field mtg-fieldset">
                        <legend class="label">{{ $logging ? 'Who was there from JBS Praxis' : 'Who takes part from JBS Praxis' }}</legend>
                        <div class="mtg-checks" @error('staff') aria-invalid="true" aria-describedby="lm-staff-error" @enderror>
                            @foreach ($staffList as $s)
                                <label wire:key="lms-{{ $s->id }}"><input type="checkbox" wire:model="staff" value="{{ $s->id }}">
                                    <span>{{ $person($s) }}@if ($s->job_title)<span class="muted"> · {{ $s->job_title }}</span>@endif</span></label>
                            @endforeach
                        </div>
                        @error('staff') <span class="field-error" id="lm-staff-error">{{ $message }}</span> @enderror
                        @error('staff.*') <span class="field-error">{{ $message }}</span> @enderror
                    </fieldset>

                    <fieldset class="field mtg-fieldset">
                        <legend class="label">{{ $logging ? 'Who was there from ' . $lead->name : 'Their contacts to invite' }}</legend>
                        @if ($contactList->isNotEmpty())
                            <div class="mtg-checks">
                                @foreach ($contactList as $c)
                                    @if (filled($c->email))
                                        <label wire:key="lmc-{{ $c->id }}"><input type="checkbox" wire:model="contacts" value="{{ $c->id }}">
                                            <span>{{ $c->full_name ?: $c->email }}<span class="muted"> · {{ $c->email }}</span></span></label>
                                    @else
                                        <label class="lm-contact-off" wire:key="lmc-{{ $c->id }}"><input type="checkbox" disabled>
                                            <span>{{ $c->full_name }}<span class="muted"> · no email yet: add one on the Overview to {{ $logging ? 'record' : 'invite' }} them</span></span></label>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="help" style="margin: 0">This lead has no contacts yet. Add them on the Overview, or write their email as a guest below.</p>
                        @endif
                        @error('contacts') <span class="field-error">{{ $message }}</span> @enderror
                        @error('contacts.*') <span class="field-error">{{ $message }}</span> @enderror
                    </fieldset>

                    <div class="field">
                        <label for="lm-guests" class="label">{{ $logging ? 'Anyone else' : 'Other guests' }} <span class="ld-optional">(optional)</span></label>
                        <textarea id="lm-guests" class="textarea" rows="2" wire:model="guests" placeholder="name@example.com"
                                  @error('guests') aria-invalid="true" aria-describedby="lm-guests-error" @else aria-describedby="lm-guests-help" @enderror></textarea>
                        @error('guests')
                            <span class="field-error" id="lm-guests-error">{{ $message }}</span>
                        @else
                            <span class="help" id="lm-guests-help">One email per line.{{ $logging ? '' : ' They get the invitation and our email, as their contacts do.' }}</span>
                        @enderror
                    </div>

                    @if ($logging)
                        <div class="field">
                            <label for="lm-typed" class="label">Your notes <span class="ld-optional">(optional)</span></label>
                            <textarea id="lm-typed" class="textarea" rows="5" maxlength="{{ \App\Support\Leads\LeadMeetings::MAX_NOTES }}" wire:model="typedNotes"
                                      @error('typedNotes') aria-invalid="true" aria-describedby="lm-typed-error" @else aria-describedby="lm-typed-help" @enderror></textarea>
                            @error('typedNotes')
                                <span class="field-error" id="lm-typed-error">{{ $message }}</span>
                            @else
                                <span class="help" id="lm-typed-help">What was said and agreed. It goes on the lead’s timeline.</span>
                            @enderror
                        </div>
                        <div class="field" x-data="{ fileName: '', fileError: '' }">
                            <label for="lm-log-file" class="label">Transcript <span class="ld-optional">(optional)</span></label>
                            <input id="lm-log-file" type="file" class="input" accept=".txt,.vtt,text/plain,text/vtt" aria-describedby="lm-log-file-help"
                                   x-on:change="
                                       fileError = ''; const f = $event.target.files[0]; if (!f) return;
                                       if (f.size > 2000000) { fileError = 'This file is over 2 MB. Paste the text instead.'; $event.target.value = ''; return; }
                                       const reader = new FileReader();
                                       reader.onload = () => { $refs.logText.value = reader.result; $refs.logText.dispatchEvent(new Event('input')); $wire.transcriptSource = 'upload'; fileName = f.name; };
                                       reader.onerror = () => { fileError = 'This file couldn’t be read. Paste the text instead.'; };
                                       reader.readAsText(f);">
                            <span class="help" id="lm-log-file-help">A .txt or .vtt file (Google Meet, Zoom, Teams), or paste the text below.</span>
                            <span class="field-error" x-show="fileError" x-text="fileError" x-cloak role="alert"></span>
                            <span class="help" x-show="fileName" x-cloak role="status" x-text="'Read from ' + fileName"></span>
                            <label for="lm-log-text" class="label" style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Transcript text</label>
                            <textarea id="lm-log-text" x-ref="logText" class="textarea" rows="4" wire:model="transcriptText" placeholder="Or paste the transcript here"
                                      @error('transcriptText') aria-invalid="true" aria-describedby="lm-log-text-error" @enderror></textarea>
                            @error('transcriptText') <span class="field-error" id="lm-log-text-error">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div class="field">
                            <label for="lm-agenda" class="label">Agenda <span class="ld-optional">(optional)</span></label>
                            <textarea id="lm-agenda" class="textarea" rows="4" maxlength="5000" wire:model="agenda"
                                      @error('agenda') aria-invalid="true" aria-describedby="lm-agenda-error" @else aria-describedby="lm-agenda-help" @enderror></textarea>
                            @error('agenda')
                                <span class="field-error" id="lm-agenda-error">{{ $message }}</span>
                            @else
                                <span class="help" id="lm-agenda-help">They see it in the invitation.</span>
                            @enderror
                        </div>
                    @endif

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeForm">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">{{ $editing ? 'Save and tell them' : ($logging ? 'Log the meeting' : 'Schedule and invite') }}</span>
                            <span wire:loading wire:target="save">{{ $logging ? 'Saving…' : 'Asking Google…' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Cancel a meeting --}}
    @if ($cancelling)
        <div class="modal-backdrop" wire:key="lm-cancel" x-data x-on:keydown.escape.window="$wire.keepMeeting()">
            <div class="modal mtg-modal" role="alertdialog" aria-modal="true" aria-labelledby="lm-cancel-title" aria-describedby="lm-cancel-text">
                <h2 class="modal-title" id="lm-cancel-title">Cancel “{{ $cancelling->title }}”?</h2>
                <p class="help" id="lm-cancel-text" style="margin: 0">The meeting of {{ $at($cancelling->starts_at, $cancelling->time_zone) }} WAT is removed from everyone’s calendar. Google tells everyone invited, and their contacts get our email.</p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="keepMeeting" autofocus>Keep the meeting</button>
                    <button type="button" class="btn btn-danger" wire:click="cancelMeeting" wire:loading.attr="disabled" wire:target="cancelMeeting">Cancel the meeting</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Add a transcript by hand: a .txt or .vtt file is read in the browser, or the text is pasted --}}
    @if ($addingTo)
        <div class="modal-backdrop" wire:key="lm-transcript" x-data="{ fileName: '', fileError: '' }" x-on:keydown.escape.window="$wire.closeTranscript()">
            <div class="modal mtg-modal" role="dialog" aria-modal="true" aria-labelledby="lm-transcript-title">
                <h2 class="modal-title" id="lm-transcript-title">Add the transcript</h2>
                <p class="help" style="margin: 0 0 16px">Of “{{ $addingTo->title }}”, {{ $day($addingTo->starts_at, $addingTo->time_zone) }}. The AI then writes the notes and next steps; the transcript stays in HQ.</p>
                <form wire:submit="saveTranscript" class="mtg-form" novalidate>
                    <div class="field">
                        <label for="lm-file" class="label">File <span class="ld-optional">(optional)</span></label>
                        <input id="lm-file" type="file" class="input" accept=".txt,.vtt,text/plain,text/vtt" aria-describedby="lm-file-help"
                               x-on:change="
                                   fileError = ''; const f = $event.target.files[0]; if (!f) return;
                                   if (f.size > 2000000) { fileError = 'This file is over 2 MB. Paste the text instead.'; $event.target.value = ''; return; }
                                   const reader = new FileReader();
                                   reader.onload = () => { $refs.text.value = reader.result; $refs.text.dispatchEvent(new Event('input')); $wire.transcriptSource = 'upload'; fileName = f.name; };
                                   reader.onerror = () => { fileError = 'This file couldn’t be read. Paste the text instead.'; };
                                   reader.readAsText(f);">
                        <span class="help" id="lm-file-help">A .txt or .vtt file (Google Meet, Zoom, Teams). It is read here in your browser.</span>
                        <span class="field-error" x-show="fileError" x-text="fileError" x-cloak role="alert"></span>
                        <span class="help" x-show="fileName" x-cloak role="status" x-text="'Read from ' + fileName"></span>
                    </div>
                    <div class="field">
                        <label for="lm-text" class="label">Transcript</label>
                        <textarea id="lm-text" x-ref="text" class="textarea" rows="10" wire:model="transcriptText"
                                  @error('transcriptText') aria-invalid="true" aria-describedby="lm-text-error" @enderror></textarea>
                        @error('transcriptText') <span class="field-error" id="lm-text-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeTranscript">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveTranscript"><i data-lucide="upload"></i> Add the transcript</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Open an opportunity from a meeting: the AI's title arrives as soon as the dialog opens --}}
    @if ($opportunityMeeting)
        <div class="modal-backdrop" wire:key="lm-opp" x-data x-init="$wire.suggestTitle()" x-on:keydown.escape.window="$wire.closeOpportunity()">
            <div class="modal mtg-modal" role="dialog" aria-modal="true" aria-labelledby="lm-opp-title">
                <h2 class="modal-title" id="lm-opp-title">Open an opportunity</h2>
                <p class="help" style="margin: 0 0 16px">For {{ $lead->name }}, from “{{ $opportunityMeeting->title }}”. The meeting moves to the opportunity with its transcript, the AI’s notes and your notes; the people on it from JBS Praxis join its team.</p>
                <form wire:submit="createOpportunity" class="mtg-form" novalidate>
                    <div class="field">
                        <label for="lm-opp-name" class="label">Title</label>
                        <input id="lm-opp-name" type="text" class="input" wire:model="oppTitle" maxlength="200"
                               @error('oppTitle') aria-invalid="true" aria-describedby="lm-opp-name-error" @else aria-describedby="lm-opp-name-help" @enderror>
                        @error('oppTitle')
                            <span class="field-error" id="lm-opp-name-error">{{ $message }}</span>
                        @else
                            <span class="help" id="lm-opp-name-help" role="status">
                                <span wire:loading wire:target="suggestTitle"><i data-lucide="loader-circle" class="animate-spin mtg-inline-icon"></i>The AI is reading the meeting for a title…</span>
                                <span wire:loading.remove wire:target="suggestTitle">{{ $titleNote ?? 'The piece of work you discussed, in a few words.' }}</span>
                            </span>
                        @enderror
                    </div>
                    <div class="mtg-row" style="grid-template-columns: 1fr 1fr">
                        <div class="field">
                            <label for="lm-opp-lead" class="label">Led by</label>
                            <select id="lm-opp-lead" class="select" wire:model="oppLead" @error('oppLead') aria-invalid="true" aria-describedby="lm-opp-lead-error" @enderror>
                                @foreach ($leadOptions as $u)
                                    <option value="{{ $u->id }}">{{ $person($u) }}</option>
                                @endforeach
                            </select>
                            @error('oppLead') <span class="field-error" id="lm-opp-lead-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="lm-opp-language" class="label">Documents and emails in</label>
                            <select id="lm-opp-language" class="select" wire:model="oppLanguage" @error('oppLanguage') aria-invalid="true" @enderror>
                                <option value="en">English</option>
                                <option value="fr">French</option>
                            </select>
                            @error('oppLanguage') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeOpportunity">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="createOpportunity,suggestTitle">
                            <i data-lucide="folder-plus"></i> Open the opportunity
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
