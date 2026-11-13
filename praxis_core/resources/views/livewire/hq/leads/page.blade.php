<div class="ld">
    @assets
    <style>
        /* One lead (Lead 360): only what the shared component classes don't cover. Brand tokens only. */
        .ld { max-width: 1120px; }
        .ld-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 0.875rem; text-decoration: none; }
        .ld-back svg { width: 16px; height: 16px; }
        .ld-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-top: 8px; font-size: 0.875rem; color: var(--color-muted); }
        .ld-meta > span { display: inline-flex; align-items: center; gap: 6px; }
        .ld-meta svg { width: 16px; height: 16px; flex-shrink: 0; }
        .ld-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); align-items: start; }
        .ld-col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .ld-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
        .ld-card-head .card-title { margin: 0; }
        .ld-dl { display: grid; grid-template-columns: minmax(110px, auto) 1fr; gap: 8px 16px; margin: 0; font-size: 0.875rem; line-height: 1.375rem; }
        .ld-dl dt { color: var(--color-muted); }
        .ld-dl dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; }
        .ld-form { display: flex; flex-direction: column; gap: 16px; }
        .ld-row { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr)); align-items: start; }
        .ld-actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .ld-optional { font-weight: 400; color: var(--color-muted); }
        .ld-next { display: flex; gap: 12px; align-items: flex-start; }
        .ld-next svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; color: var(--color-muted); }
        .ld-next.is-overdue { color: var(--color-danger); }
        .ld-next.is-overdue svg { color: var(--color-danger); }
        .ld-contacts { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .ld-contacts > li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px; padding: 12px 0; border-top: 1px solid var(--color-line); }
        .ld-contacts > li:first-child { border-top: 0; padding-top: 0; }
        .ld-contact-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; font-size: 0.875rem; }
        .ld-contact-name { font-weight: 600; color: var(--color-ink); display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .ld-contact-ways { display: flex; flex-wrap: wrap; gap: 4px 12px; color: var(--color-slate); }
        .ld-contact-ways a { overflow-wrap: anywhere; }
        .ld-contact-edit { width: 100%; padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist); }
        .ld-feed { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .ld-feed > li { display: grid; grid-template-columns: 32px 1fr; gap: 12px; padding: 12px 0; border-top: 1px solid var(--color-line); }
        .ld-feed > li:first-child { border-top: 0; }
        .ld-feed-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; background: var(--color-mist); color: var(--color-slate); }
        .ld-feed-icon svg { width: 16px; height: 16px; }
        .ld-feed-text { min-width: 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); }
        .ld-feed-meta { display: block; font-size: 0.75rem; color: var(--color-muted); }
        .ld-feed-body { margin: 6px 0 0; white-space: pre-line; color: var(--color-slate); overflow-wrap: anywhere; }
        .ld-radios { display: flex; flex-wrap: wrap; gap: 16px; }
        .ld-radios label { display: inline-flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .ld .tabs { overflow-y: hidden; }
        .ld-merge-row { display: grid; grid-template-columns: 160px 1fr 1fr; gap: 12px; align-items: center; }
        .ld-choice { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--color-line); border-radius: var(--radius-control); cursor: pointer; font-size: 0.875rem; }
        .ld-choice.is-chosen { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .ld-opps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
        .ld-opps > li { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; padding: 12px 0; border-top: 1px solid var(--color-line); font-size: 0.875rem; }
        .ld-opps > li:first-child { border-top: 0; padding-top: 0; }
        @media (max-width: 900px) { .ld-grid { grid-template-columns: 1fr; } }
        @media (max-width: 600px) {
            .ld-dl { grid-template-columns: 1fr; gap: 2px; }
            .ld-dl dd { margin-bottom: 8px; }
            .ld-actions .btn { flex: 1 1 100%; }
            .ld-merge-row { grid-template-columns: 1fr; }
        }
    </style>
    @endassets

    @php
        $tz = 'Africa/Lagos';
        $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->format('j M Y') : '—';
        $when = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->format('j M Y, H:i') : '';
        $money = fn ($currency, $amount) => \App\Livewire\Hq\HqMoney::format($currency, $amount, 0);
        $statusLabel = fn ($s) => \App\Support\Leads\LeadStatus::label($s);
        $statusTone = fn ($s) => \App\Support\Leads\LeadStatus::tone($s);
        $isClient = $lead->lead_status === 'client';
        // The tab counts come from LeadPage::render() (PR 2): meetings, emails this person may read, opportunities
        $tabs = [
            'overview' => 'Overview',
            'meetings' => 'Meetings' . (($counts['meetings'] ?? 0) ? ' (' . $counts['meetings'] . ')' : ''),
            'emails' => 'Emails' . (($counts['emails'] ?? 0) ? ' (' . ($counts['emails'] >= \App\Support\Leads\LeadEmails::LIMIT ? $counts['emails'] . '+' : $counts['emails']) . ')' : ''),
            'opportunities' => 'Opportunities' . (($counts['opportunities'] ?? 0) ? ' (' . $counts['opportunities'] . ')' : ''),
        ] + ($canEdit ? ['intel' => 'Intel' . (($counts['intel'] ?? 0) ? ' (' . $counts['intel'] . ')' : '')] : []) + [
            'activity' => 'Activity',
        ];
    @endphp

    <a href="{{ route('hq.crm.leads') }}" class="ld-back" wire:navigate><i data-lucide="arrow-left"></i> All leads</a>

    <div class="page-header">
        <div style="min-width: 0">
            <h1 class="page-title" style="overflow-wrap: anywhere">{{ $lead->name }}</h1>
            <div class="ld-meta">
                <span class="pill {{ $statusTone($lead->lead_status) }}">{{ $statusLabel($lead->lead_status) }}</span>
                <span><i data-lucide="user-round"></i> {{ $lead->owner ? 'Owner: ' . trim($lead->owner->first_name . ' ' . $lead->owner->last_name) : 'No owner yet' }}</span>
                @if ($lead->country || $lead->city)
                    <span><i data-lucide="map-pin"></i> {{ collect([$lead->city, $lead->country])->filter()->implode(', ') }}</span>
                @endif
                <span class="tag">{{ \App\Support\Leads\Sources::label($lead->source) }}</span>
                @if ($lead->is_duplicate_suspect)
                    <span class="pill pill-warning">Possible duplicate</span>
                @endif
            </div>
        </div>
        <div class="ld-actions">
            {{-- A client's invoice details and Client Portal accounts are on its page under Clients (docs/CLIENTS.md) --}}
            @if ($lead->status === 'Active Client' && \App\Support\Clients\ClientAccess::canView(auth()->user()))
                <a href="{{ route('hq.client', ['organization' => $lead->id]) }}" class="btn btn-secondary" wire:navigate><i data-lucide="building-2"></i> {{ __('clients.open_client') }}</a>
            @endif
            @if ($canEdit && ! $lead->assigned_to)
                <button type="button" class="btn btn-secondary" wire:click="claim" wire:loading.attr="disabled" wire:target="claim"><i data-lucide="user-round"></i> Assign to me</button>
            @endif
            @if ($canOpenOpportunity)
                <a href="{{ route('hq.crm.opportunities', ['new' => 1, 'organization' => $lead->id]) }}" class="btn btn-primary" wire:navigate><i data-lucide="folder-plus"></i> Open an opportunity</a>
            @endif
        </div>
    </div>


    <div class="tabs" role="tablist" aria-label="Lead sections">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['tab', 'is-active' => $tab === $key]) wire:click="show('{{ $key }}')" wire:key="tab-{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>

    <div role="tabpanel" id="panel-{{ $tab }}" aria-labelledby="tab-{{ $tab }}">
        @if ($tab === 'overview')
            <div class="ld-grid">
                <div class="ld-col">
                    {{-- Status and next step --}}
                    <section class="card" aria-labelledby="ld-status-title">
                        <div class="ld-card-head">
                            <h2 class="card-title" id="ld-status-title">Status and next step</h2>
                            <span class="pill {{ $statusTone($lead->lead_status) }}">{{ $statusLabel($lead->lead_status) }}</span>
                        </div>

                        @if ($isClient)
                            <dl class="ld-dl" style="margin-bottom: 16px">
                                <dt>Client since</dt><dd>{{ $lead->client_since ? $day($lead->client_since) : 'Date not recorded' }}</dd>
                                <dt>How</dt><dd>{{ \App\Support\Leads\LeadStatus::clientHow($lead) }}</dd>
                            </dl>
                        @elseif ($lead->lead_status === 'lost')
                            <dl class="ld-dl" style="margin-bottom: 16px">
                                <dt>Lost on</dt><dd>{{ $day($lead->lost_at) }}</dd>
                                <dt>Why</dt><dd>{{ \App\Support\Leads\LeadStatus::LOST_REASONS[$lead->lost_reason] ?? 'Not recorded' }}@if ($lead->lost_note): {{ $lead->lost_note }}@endif</dd>
                            </dl>
                        @endif

                        @if ($canEdit && ($lead->client_via === 'manual' || ! $isClient))
                            <form wire:submit="saveStatus" class="ld-form" novalidate>
                                <div class="ld-row">
                                    <div class="field">
                                        <label for="ld-status" class="label">Status</label>
                                        <select id="ld-status" class="select" wire:model.live="status" @error('status') aria-invalid="true" aria-describedby="ld-status-error" @enderror>
                                            @foreach (\App\Support\Leads\LeadStatus::LABELS as $key => $label)
                                                <option value="{{ $key }}" wire:key="status-{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('status') <span class="field-error" id="ld-status-error">{{ $message }}</span> @enderror
                                    </div>
                                    @if ($status === 'lost')
                                        <div class="field">
                                            <label for="ld-lost" class="label">Why it was lost</label>
                                            <select id="ld-lost" class="select" wire:model.live="lostReason" @error('lostReason') aria-invalid="true" aria-describedby="ld-lost-error" @enderror>
                                                <option value="">Choose a reason</option>
                                                @foreach (\App\Support\Leads\LeadStatus::LOST_REASONS as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('lostReason') <span class="field-error" id="ld-lost-error">{{ $message }}</span> @enderror
                                        </div>
                                    @endif
                                </div>
                                @if ($status === 'lost')
                                    <div class="field">
                                        <label for="ld-lost-note" class="label">Note @if ($lostReason !== 'other')<span class="ld-optional">(optional)</span>@endif</label>
                                        <textarea id="ld-lost-note" class="textarea" rows="2" maxlength="2000" wire:model="lostNote" @error('lostNote') aria-invalid="true" aria-describedby="ld-lost-note-error" @enderror></textarea>
                                        @error('lostNote') <span class="field-error" id="ld-lost-note-error">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                                <div class="ld-actions">
                                    @if ($status !== ($lead->lead_status ?: 'new') || ($status === 'lost' && ($lostReason !== (string) $lead->lost_reason || $lostNote !== (string) $lead->lost_note)))
                                        <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveStatus"><i data-lucide="check"></i> Save status</button>
                                    @endif
                                    @if (! $isClient)
                                        <button type="button" class="btn btn-ghost" wire:click="askMarkClient"><i data-lucide="badge-check"></i> Mark as client</button>
                                    @endif
                                </div>
                            </form>
                        @endif

                        @if ($confirmingClient)
                            <div class="alert alert-info" role="alertdialog" aria-labelledby="ld-client-q" style="margin-top: 16px; flex-wrap: wrap; justify-content: space-between">
                                <span id="ld-client-q">Mark {{ $lead->name }} as a client? It becomes an Active Client from today, marked by hand. Signing a proposal or SOW does this by itself.</span>
                                <span class="ld-actions">
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="markClient" wire:loading.attr="disabled" wire:target="markClient">Mark as client</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelMarkClient">Keep as {{ mb_strtolower($statusLabel($lead->lead_status)) }}</button>
                                </span>
                            </div>
                        @endif

                        <hr class="divider" style="margin: 16px 0">

                        <h3 class="label" style="margin: 0 0 8px">Next step</h3>
                        @if ($editingNextStep)
                            <form wire:submit="saveNextStep" class="ld-form" novalidate>
                                <div class="ld-row">
                                    <div class="field">
                                        <label class="label" for="ld-next">What</label>
                                        <input id="ld-next" type="text" class="input" maxlength="255" wire:model="nextStep" placeholder="e.g. Call to book a free audit" @error('nextStep') aria-invalid="true" @enderror>
                                        @error('nextStep') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label class="label" for="ld-next-due">By when</label>
                                        <input id="ld-next-due" type="date" class="input" wire:model="nextStepDue" @error('nextStepDue') aria-invalid="true" aria-describedby="ld-next-due-error" @enderror>
                                        @error('nextStepDue') <span class="field-error" id="ld-next-due-error">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <p class="help" style="margin: 0">Leave both empty to clear it.</p>
                                <div class="ld-actions">
                                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveNextStep"><i data-lucide="save"></i> Save next step</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelNextStep">Cancel</button>
                                </div>
                            </form>
                        @else
                            <div @class(['ld-next', 'is-overdue' => $overdue])>
                                <i data-lucide="clock"></i>
                                <div style="flex: 1; min-width: 0">
                                    @if ($lead->next_step)
                                        <div class="font-semibold" style="overflow-wrap: anywhere">{{ $lead->next_step }}</div>
                                        <div class="text-small">By {{ \App\Support\Leads\NextStep::day($lead->next_step_due) }}@if ($overdue) · overdue @endif</div>
                                    @else
                                        <div class="text-small text-muted">{{ \App\Support\Leads\LeadStatus::isOpen($lead->lead_status) ? 'No next step yet. Every open lead needs one.' : 'None: this lead is closed.' }}</div>
                                    @endif
                                </div>
                                @if ($canEdit)
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="editNextStep"><i data-lucide="pencil"></i> {{ $lead->next_step ? 'Change' : 'Set next step' }}</button>
                                @endif
                            </div>
                        @endif
                    </section>

                    {{-- Contacts --}}
                    <section class="card" aria-labelledby="ld-contacts-title">
                        <div class="ld-card-head">
                            <h2 class="card-title" id="ld-contacts-title">Contacts</h2>
                            @if ($canEdit && $contactEditing !== 'new')
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="newContact"><i data-lucide="user-plus"></i> Add contact</button>
                            @endif
                        </div>

                        @if ($contactEditing === 'new')
                            @include('livewire.hq.leads.partials.contact-form', ['portal' => false, 'title' => 'New contact'])
                        @endif

                        @if ($contacts->isEmpty() && $contactEditing !== 'new')
                            <p class="help" style="margin: 0">No contacts yet. Add the people you talk to there, and mark who to reach first.</p>
                        @else
                            <ul class="ld-contacts">
                                @foreach ($contacts as $c)
                                    <li wire:key="contact-{{ $c->id }}">
                                        @if ($contactEditing === $c->id)
                                            @include('livewire.hq.leads.partials.contact-form', ['portal' => \App\Support\Leads\Contacts::isPortalAccount($c), 'title' => 'Change ' . $c->full_name])
                                        @else
                                            <div class="ld-contact-text">
                                                <span class="ld-contact-name">
                                                    {{ $c->full_name ?: 'Name not given' }}
                                                    @if ($c->is_main_contact) <span class="pill pill-info">Main contact</span> @endif
                                                    @if ($c->deal_role) <span class="tag">{{ \App\Support\Leads\Contacts::DEAL_ROLES[$c->deal_role] ?? $c->deal_role }}</span> @endif
                                                    @if (\App\Support\Leads\Contacts::isPortalAccount($c)) <span class="tag">Portal account</span> @endif
                                                </span>
                                                @if ($c->job_title) <span class="text-muted">{{ $c->job_title }}</span> @endif
                                                <span class="ld-contact-ways">
                                                    @if ($c->email) <a href="mailto:{{ $c->email }}">{{ $c->email }}</a> @endif
                                                    @if ($c->phone) <a href="tel:{{ preg_replace('/[^0-9+]/', '', $c->phone) }}">{{ $c->phone }}</a> @endif
                                                    @if (! $c->email && ! $c->phone) <span class="text-muted">No email or phone</span> @endif
                                                </span>
                                            </div>
                                            @if ($canEdit)
                                                <div class="flex flex-wrap items-center gap-2">
                                                    @if (! $c->is_main_contact)
                                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="makeMainContact('{{ $c->id }}')">Make main</button>
                                                    @endif
                                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="editContact('{{ $c->id }}')" aria-label="Change {{ $c->full_name }}"><i data-lucide="pencil"></i> Change</button>
                                                    @unless (\App\Support\Leads\Contacts::isPortalAccount($c))
                                                        <button type="button" class="btn btn-ghost btn-sm" wire:click="removeContact('{{ $c->id }}')"
                                                                wire:confirm="Remove {{ $c->full_name }} from this lead? Their past activity stays on the timeline." aria-label="Remove {{ $c->full_name }}"><i data-lucide="trash-2"></i></button>
                                                    @endunless
                                                </div>
                                            @endif
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    {{-- Log activity and the latest lines --}}
                    <section class="card" aria-labelledby="ld-log-title">
                        <h2 class="card-title" id="ld-log-title" style="margin-bottom: 12px">Log activity</h2>
                        @if ($canEdit)
                            <form wire:submit="logActivity" class="ld-form" novalidate>
                                <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                                    <legend class="label" style="margin-bottom: 6px">What happened</legend>
                                    <div class="ld-radios">
                                        @foreach (\App\Support\Leads\LeadTimeline::LOGGABLE as $key => $label)
                                            <label><input type="radio" value="{{ $key }}" wire:model="activityType"> {{ $label }}</label>
                                        @endforeach
                                    </div>
                                    @error('activityType') <span class="field-error">{{ $message }}</span> @enderror
                                </fieldset>
                                <div class="field">
                                    <label class="label" for="ld-activity">What was said or agreed</label>
                                    <textarea id="ld-activity" class="textarea" rows="3" maxlength="5000" wire:model="activityText" @error('activityText') aria-invalid="true" aria-describedby="ld-activity-error" @enderror></textarea>
                                    @error('activityText') <span class="field-error" id="ld-activity-error">{{ $message }}</span> @enderror
                                </div>
                                @if ($contacts->isNotEmpty())
                                    <div class="field">
                                        <label class="label" for="ld-activity-contact">With <span class="ld-optional">(optional)</span></label>
                                        <select id="ld-activity-contact" class="select" wire:model="activityContact">
                                            <option value="">No one in particular</option>
                                            @foreach ($contacts as $c)
                                                <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                                            @endforeach
                                        </select>
                                        @error('activityContact') <span class="field-error">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                                <div class="ld-actions">
                                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="logActivity"><i data-lucide="plus"></i> Log it</button>
                                </div>
                            </form>
                        @endif

                        @if ($recent->isNotEmpty())
                            <h3 class="label" style="margin: 20px 0 4px">Latest</h3>
                            @include('livewire.hq.leads.partials.timeline', ['rows' => $recent, 'bodies' => false])
                            <button type="button" class="btn btn-ghost btn-sm" style="padding-left: 0" wire:click="show('activity')">See all activity <i data-lucide="arrow-right"></i></button>
                        @endif
                    </section>
                </div>

                <div class="ld-col">
                    {{-- Details, owner and source --}}
                    <section class="card" aria-labelledby="ld-details-title">
                        <div class="ld-card-head">
                            <h2 class="card-title" id="ld-details-title">Details</h2>
                            @if ($canEdit && ! $editingDetails)
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="editDetails"><i data-lucide="pencil"></i> Edit details</button>
                            @endif
                        </div>
                        @if ($editingDetails)
                            @include('livewire.hq.leads.partials.details-form')
                        @else
                            <dl class="ld-dl">
                                <dt>Industry</dt><dd>{{ $lead->industry ?: 'Not given' }}</dd>
                                <dt>Country</dt><dd>{{ collect([$lead->city, $lead->country])->filter()->implode(', ') ?: 'Not given' }}</dd>
                                @if ($lead->phone)
                                    <dt>Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->phone) }}">{{ $lead->phone }}</a></dd>
                                @endif
                                @if ($lead->email)
                                    <dt>Email</dt><dd><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></dd>
                                @endif
                                @if ($lead->billing_address)
                                    <dt>Address</dt><dd style="white-space: pre-line">{{ $lead->billing_address }}</dd>
                                @endif
                                @if ($lead->registration_number)
                                    <dt>Company number</dt><dd>{{ $lead->registration_number }}</dd>
                                @endif
                                @if ($lead->tax_identification_number)
                                    <dt>Tax number</dt><dd>{{ $lead->tax_identification_number }}</dd>
                                @endif
                                <dt>Emails in</dt><dd>{{ strtoupper((string) $lead->preferred_language) === 'FR' ? 'French' : 'English' }}</dd>
                                <dt>Source</dt><dd>{{ \App\Support\Leads\Sources::label($lead->source) }}@if ($lead->source_detail) · {{ $lead->source_detail }}@endif</dd>
                                <dt>Added</dt><dd>{{ $day($lead->created_at) }}</dd>
                                @if (! empty($lead->start_timing))
                                    <dt>Would like to start</dt><dd>{{ \App\Services\Site\EnquiryService::START_TIMINGS[$lead->start_timing] ?? $lead->start_timing }}</dd>
                                @endif
                            </dl>
                        @endif

                        <hr class="divider" style="margin: 16px 0">
                        @if ($canAssign)
                            <x-hq.picker id="lead-owner" label="Owner" model="ownerSearch" :options="$ownerOptions" :selected="$ownerSelected"
                                         choose="assignLead" remove="startReassign" placeholder="Type a name" :empty="'No one matches ‘:term’.'" :term="$ownerSearch"
                                         :error="$errors->first('owner')" :help="$lead->assigned_to ? null : 'Who follows this lead up. Choose yourself or a colleague.'" />
                            @if ($ownerPicking && $lead->assigned_to)
                                <button type="button" class="btn btn-ghost btn-sm" style="margin-top: 4px; padding-left: 0" wire:click="cancelReassign">Keep {{ $lead->owner?->first_name }}</button>
                            @endif
                        @else
                            <div class="field">
                                <span class="label">Owner</span>
                                <div class="flex items-center gap-2" style="min-height: 40px">
                                    @if ($lead->owner)
                                        <x-hq.avatar :user="$lead->owner" :size="28" />
                                        <span class="text-ink">{{ trim($lead->owner->first_name . ' ' . $lead->owner->last_name) }}</span>
                                    @else
                                        <span class="text-muted">No owner yet</span>
                                    @endif
                                </div>
                                @if ($lead->owner && $canEdit)
                                    <span class="help">Only {{ $lead->owner->first_name }} or a partner can hand this lead to someone else.</span>
                                @endif
                            </div>
                        @endif
                    </section>

                    {{-- Cost of poor quality --}}
                    <section class="card" aria-labelledby="ld-copq-title">
                        <h2 class="card-title" id="ld-copq-title" style="margin-bottom: 12px">Cost of poor quality</h2>
                        @if ($lead->is_duplicate_suspect && $canEdit)
                            <div class="alert alert-warning" style="flex-wrap: wrap; justify-content: space-between; margin-bottom: 12px">
                                <span><strong>Possible duplicate.</strong> This organisation has more than one assessment. Review them and keep one set of values.</span>
                                <button type="button" wire:click="openDiffViewer" wire:loading.attr="disabled" wire:target="openDiffViewer" class="btn btn-secondary btn-sm">Review duplicates</button>
                            </div>
                        @endif
                        @if ($calculating)
                            @include('livewire.hq.leads.partials.copq-form')
                        @elseif ($copqLatest)
                            <div class="stat-label">Estimated, a year</div>
                            <div class="stat-value">{{ $money($copqLatest->copq_currency, $copqLatest->calculated_leakage) }}</div>
                            <dl class="ld-dl" style="margin-top: 12px">
                                <dt>Annual turnover</dt><dd>{{ $money($copqLatest->copq_currency, $copqLatest->copq_turnover) }}</dd>
                                @if ($copqLatest->bottleneck)
                                    <dt>Main bottleneck</dt><dd>{{ $copqLatest->bottleneck }}</dd>
                                @endif
                                <dt>Tier</dt><dd>{{ match ((int) $copqLatest->lead_tier) { 1 => 'Tier 1: enterprise', 2 => 'Tier 2: mid-market', default => 'Tier 3: small business' } }}</dd>
                                <dt>Estimated on</dt><dd>{{ $day($copqLatest->created_at) }}</dd>
                            </dl>
                            @if ($copqLatest->ai_friction_analysis)
                                <p class="text-small" style="margin: 12px 0 0">{{ $copqLatest->ai_friction_analysis }}</p>
                            @endif
                            <div class="ld-actions" style="margin-top: 12px">
                                @if ($copqLatest->report_token)
                                    <a href="{{ route('dossier.show', ['token' => $copqLatest->report_token]) }}?exec=true" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i data-lucide="external-link"></i> Open the report</a>
                                @endif
                                @if ($canEdit)
                                    <button type="button" class="btn btn-ghost btn-sm" wire:click="startCalculate"><i data-lucide="calculator"></i> Calculate again</button>
                                @endif
                            </div>
                        @else
                            <p class="help" style="margin: 0 0 12px">Not estimated yet. Calculate it from their figures, or book a free audit to collect them.</p>
                            @if ($canEdit)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="startCalculate"><i data-lucide="calculator"></i> Calculate</button>
                            @endif
                        @endif
                    </section>
                </div>
            </div>

        @elseif ($tab === 'meetings')
            @include('livewire.hq.leads.tabs.meetings')

        @elseif ($tab === 'emails')
            @include('livewire.hq.leads.tabs.emails')

        @elseif ($tab === 'opportunities')
            @include('livewire.hq.leads.tabs.opportunities')

        @elseif ($tab === 'intel' && $canEdit)
            @include('livewire.hq.leads.tabs.intel')

        @elseif ($tab === 'activity')
            <section class="card" aria-labelledby="ld-activity-title">
                <div class="ld-card-head">
                    <h2 class="card-title" id="ld-activity-title">Activity</h2>
                    <div class="field" style="min-width: 220px">
                        <label class="label" for="ld-activity-filter" style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Show</label>
                        <select id="ld-activity-filter" class="select" wire:model.live="activityFilter">
                            <option value="">Everything</option>
                            @foreach ($timelineTypes as $type)
                                <option value="{{ $type }}">{{ \App\Support\Leads\LeadTimeline::label($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if ($timeline->isEmpty())
                    <div class="empty-state"><strong>Nothing here yet</strong>Calls, notes, status changes and website messages show here as they happen.</div>
                @else
                    @include('livewire.hq.leads.partials.timeline', ['rows' => $timeline, 'bodies' => true])
                @endif
            </section>
        @endif
    </div>

    {{-- Merge duplicate assessments --}}
    @if ($showDiffModal && $diffOld && $diffNew)
        @php $old = (object) $diffOld; $new = (object) $diffNew; @endphp
        <div class="modal-backdrop" style="z-index: 60">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="merge-title" style="max-width: 880px">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="merge-title" class="modal-title">Merge duplicate records</h2>
                        <p class="text-small text-muted" style="margin: 0">Choose which value to keep for each field. The newest record is updated with your choices and the duplicate flag is cleared.</p>
                    </div>
                    <button type="button" wire:click="closeDiffViewer" class="btn btn-ghost btn-sm" aria-label="Close"><i data-lucide="x"></i></button>
                </div>
                <div class="ld-merge-row text-small font-semibold text-ink" style="margin: 20px 0 8px">
                    <div>Field</div>
                    <div>Earlier record <span class="block font-normal text-muted">{{ $when($old->created_at) }}</span></div>
                    <div>Newest record <span class="block font-normal text-muted">{{ $when($new->created_at) }}</span></div>
                </div>
                <div class="flex flex-col gap-2">
                    @foreach ($diffFields as $field => $label)
                        <div class="ld-merge-row" style="padding: 8px 0; border-top: 1px solid var(--color-line)">
                            <div class="text-small text-muted">{{ $label }}</div>
                            @foreach (['old' => $old, 'new' => $new] as $side => $rec)
                                <label class="ld-choice {{ ($mergeSelections[$field] ?? '') === $side ? 'is-chosen' : '' }}">
                                    <input type="radio" wire:model="mergeSelections.{{ $field }}" value="{{ $side }}">
                                    <span class="{{ $old->$field != $new->$field ? 'text-ink' : 'text-muted' }}">
                                        {{ is_numeric($rec->$field) && in_array($field, ['copq_turnover', 'calculated_leakage']) ? number_format($rec->$field) : $rec->$field }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="modal-actions">
                    <button type="button" wire:click="closeDiffViewer" class="btn btn-secondary">Cancel</button>
                    <button type="button" wire:click="executeMerge" wire:loading.attr="disabled" wire:target="executeMerge" class="btn btn-primary">Merge records</button>
                </div>
            </div>
        </div>
    @endif
</div>
