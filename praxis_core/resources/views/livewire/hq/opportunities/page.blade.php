<div class="opp">
    <style>
        /* One opportunity: only what the shared component classes don't cover. Brand tokens only. */
        .opp { max-width: 1120px; }
        .opp-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 0.875rem; text-decoration: none; }
        .opp-back svg { width: 16px; height: 16px; }
        .opp-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-top: 8px; font-size: 0.875rem; color: var(--color-muted); }
        .opp-meta > span { display: inline-flex; align-items: center; gap: 6px; }
        .opp-meta svg { width: 16px; height: 16px; flex-shrink: 0; }
        .opp-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr)); align-items: start; }
        .opp-dl { display: grid; grid-template-columns: minmax(110px, auto) 1fr; gap: 8px 16px; margin: 0; font-size: 0.875rem; line-height: 1.375rem; }
        .opp-dl dt { color: var(--color-muted); }
        .opp-dl dd { margin: 0; color: var(--color-ink); overflow-wrap: anywhere; }
        .opp-form { display: flex; flex-direction: column; gap: 16px; }
        .opp-actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .opp-team { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
        .opp-team li { display: flex; align-items: center; gap: 10px; }
        .opp-team-text { display: flex; flex-direction: column; min-width: 0; }
        .opp-team-name { font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); }
        .opp-history { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
        .opp-history li { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .opp-history time { display: block; font-size: 0.75rem; color: var(--color-muted); }
        .opp-radios { display: flex; flex-wrap: wrap; gap: 16px; }
        .opp-radios label { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .opp .tabs { overflow-y: hidden; } /* scroll sideways only, on narrow screens */
        .opp-callout { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 16px; }
        .opp-callout svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; }
        @media (max-width: 600px) {
            .opp-dl { grid-template-columns: 1fr; gap: 2px; }
            .opp-dl dd { margin-bottom: 8px; }
            .opp-actions .btn { flex: 1 1 100%; }
        }
    </style>

    @php
        $tone = fn (string $stage) => match ($stage) { 'won' => 'pill-success', 'lost' => 'pill-danger', 'on_hold' => 'pill-warning', default => 'pill-info' };
        $locale = app()->getLocale();
        $day = fn ($at, $tz = 'Africa/Lagos') => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->locale($locale)->translatedFormat(__('opportunities.formats.date')) : '—';
        $time = fn ($at, $tz = 'Africa/Lagos') => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone($tz)->locale($locale)->translatedFormat(__('opportunities.formats.time')) : '';
        $money = fn ($amount, $currency) => $locale === 'fr'
            ? number_format((float) $amount, 0, ',', "\u{202F}") . "\u{00A0}" . $currency
            : $currency . "\u{00A0}" . number_format((float) $amount, 0);
        $person = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : __('opportunities.no_lead');
        $tabs = [
            'overview' => __('opportunities.tabs.overview'),
            'meetings' => __('opportunities.tabs.meetings') . ($meetings->count() ? ' (' . $meetings->count() . ')' : ''),
            'questionnaire' => __('opportunities.tabs.questionnaire'),
            'proposals' => __('opportunities.tabs.proposals') . ($proposals->count() ? ' (' . $proposals->count() . ')' : ''),
            'sow' => __('opportunities.tabs.sow'),
            'project' => __('opportunities.tabs.project'),
        ];
    @endphp

    <a href="{{ route('hq.crm.opportunities') }}" class="opp-back" wire:navigate><i data-lucide="arrow-left"></i> {{ __('opportunities.page.back') }}</a>

    <div class="page-header">
        <div style="min-width: 0">
            <h1 class="page-title" style="overflow-wrap: anywhere">{{ $o->title }}</h1>
            <div class="opp-meta">
                <span class="pill {{ $tone($o->stage) }}">{{ __('opportunities.stages.' . $o->stage) }}</span>
                <span><i data-lucide="building-2"></i> {{ $o->organization?->name }}</span>
                <span><i data-lucide="user-round"></i> {{ __('opportunities.page.led_by', ['name' => $person($o->lead)]) }}</span>
                <span class="tag">{{ __('opportunities.languages.' . $o->language) }}</span>
                @if ($o->source === 'backfill')
                    <span class="tag">{{ __('opportunities.sources.backfill') }}</span>
                @elseif ($o->source === 'website_booking')
                    <span class="tag">{{ __('opportunities.sources.website_booking') }}</span>
                @endif
            </div>
        </div>
        @if ($canManage && ! $editing)
            <button type="button" class="btn btn-secondary" wire:click="edit"><i data-lucide="pencil"></i> {{ __('opportunities.page.edit') }}</button>
        @endif
    </div>

    <x-flash class="mb-6" />

    <div class="tabs" role="tablist" aria-label="{{ __('opportunities.page.sections') }}">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['tab', 'is-active' => $tab === $key]) wire:click="show('{{ $key }}')" wire:key="tab-{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>

    <div role="tabpanel" id="panel-{{ $tab }}" aria-labelledby="tab-{{ $tab }}">
        @if ($tab === 'overview')
            @if ($editing)
                <section class="card mb-6" aria-labelledby="opp-edit-title">
                    <h2 class="card-title" id="opp-edit-title" style="margin-bottom: 16px">{{ __('opportunities.page.edit') }}</h2>
                    <form wire:submit="saveDetails" class="opp-form" novalidate>
                        <div class="field">
                            <label for="opp-title" class="label">{{ __('opportunities.fields.title') }}</label>
                            <input id="opp-title" type="text" class="input" wire:model="title" maxlength="200"
                                   @error('title') aria-invalid="true" aria-describedby="opp-title-error" @enderror>
                            @error('title') <span class="field-error" id="opp-title-error">{{ $message }}</span> @enderror
                        </div>
                        <x-hq.picker id="opp-lead" :label="__('opportunities.fields.lead')" model="leadSearch" :options="$leadOptions" :selected="$leadSelected"
                                     choose="chooseLead" remove="clearLead" :placeholder="__('opportunities.create.team_hint')" :help="__('opportunities.create.lead_help')"
                                     :error="$errors->first('leadUserId')" :empty="__('opportunities.create.no_staff')" :term="$leadSearch" :change="__('opportunities.create.change')" />
                        <x-hq.picker id="opp-team" :label="__('opportunities.fields.team')" model="teamSearch" :options="$teamOptions" :selected="$teamSelected" :multiple="true"
                                     choose="addMember" remove="removeMember" :placeholder="__('opportunities.create.team_hint')" :help="__('opportunities.create.team_help')"
                                     :error="$errors->first('teamUserIds') ?: $errors->first('teamUserIds.*')" :empty="__('opportunities.create.no_staff')" :term="$teamSearch"
                                     :removeLabel="__('opportunities.create.remove_member')" />
                        <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                            <legend class="label" style="margin-bottom: 6px">{{ __('opportunities.fields.language') }}</legend>
                            <div class="opp-radios">
                                @foreach (\App\Models\Opportunity::LANGUAGES as $code)
                                    <label><input type="radio" wire:model="language" value="{{ $code }}"> {{ __('opportunities.languages.' . $code) }}</label>
                                @endforeach
                            </div>
                            <span class="help">{{ __('opportunities.create.language_help') }}</span>
                        </fieldset>
                        <div class="opp-actions">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveDetails"><i data-lucide="save"></i> {{ __('opportunities.page.save') }}</button>
                            <button type="button" class="btn btn-secondary" wire:click="cancelEdit">{{ __('opportunities.cancel') }}</button>
                        </div>
                    </form>
                </section>
            @endif

            <div class="opp-grid">
                <section class="card" aria-labelledby="opp-stage-title">
                    <h2 class="card-title" id="opp-stage-title" style="margin-bottom: 12px">{{ __('opportunities.page.stage_title') }}</h2>
                    <dl class="opp-dl" style="margin-bottom: 16px">
                        <dt>{{ __('opportunities.fields.stage') }}</dt><dd>{{ __('opportunities.stages.' . $o->stage) }}</dd>
                        <dt>{{ __('opportunities.page.opened') }}</dt><dd>{{ $day($o->created_at) }}</dd>
                        @if ($o->closed_at)
                            <dt>{{ __('opportunities.page.closed') }}</dt><dd>{{ $day($o->closed_at) }}</dd>
                        @endif
                        @if ($o->stage === 'lost' && $o->lost_reason)
                            <dt>{{ __('opportunities.fields.lost_reason') }}</dt><dd>{{ $o->lost_reason }}</dd>
                        @endif
                    </dl>
                    @if ($canManage)
                        <form wire:submit="saveStage" class="opp-form" novalidate>
                            <div class="field">
                                <label for="opp-stage" class="label">{{ __('opportunities.page.move_to') }}</label>
                                <select id="opp-stage" class="select" wire:model.live="stage" @error('stage') aria-invalid="true" aria-describedby="opp-stage-error" @enderror>
                                    @foreach (\App\Models\Opportunity::STAGES as $s)
                                        <option value="{{ $s }}" wire:key="stage-{{ $s }}">{{ __('opportunities.stages.' . $s) }}</option>
                                    @endforeach
                                </select>
                                @error('stage') <span class="field-error" id="opp-stage-error">{{ $message }}</span> @enderror
                            </div>
                            @if ($stage === 'lost')
                                <div class="field">
                                    <label for="opp-lost" class="label">{{ __('opportunities.fields.lost_reason') }}</label>
                                    <textarea id="opp-lost" class="textarea" wire:model="lostReason" rows="3" maxlength="2000"
                                              @error('lostReason') aria-invalid="true" aria-describedby="opp-lost-error" @enderror></textarea>
                                    @error('lostReason') <span class="field-error" id="opp-lost-error">{{ $message }}</span> @enderror
                                </div>
                            @endif
                            <div class="opp-actions">
                                <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveStage"><i data-lucide="check"></i> {{ __('opportunities.page.save_stage') }}</button>
                            </div>
                        </form>
                    @endif
                </section>

                <section class="card" aria-labelledby="opp-org-title">
                    <h2 class="card-title" id="opp-org-title" style="margin-bottom: 12px">{{ __('opportunities.page.organisation') }}</h2>
                    <dl class="opp-dl">
                        <dt>{{ __('opportunities.page.name') }}</dt><dd>{{ $o->organization?->name ?? '—' }}</dd>
                        @if ($o->organization?->industry)
                            <dt>{{ __('opportunities.page.industry') }}</dt><dd>{{ $o->organization->industry }}</dd>
                        @endif
                        @if ($o->organization?->country || $o->organization?->city)
                            <dt>{{ __('opportunities.page.location') }}</dt><dd>{{ collect([$o->organization->city, $o->organization->country])->filter()->implode(', ') }}</dd>
                        @endif
                        @if ($contact)
                            <dt>{{ __('opportunities.page.contact') }}</dt>
                            <dd>{{ trim($contact->first_name . ' ' . $contact->last_name) }}@if ($contact->job_title), {{ $contact->job_title }}@endif<br><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></dd>
                        @endif
                    </dl>
                </section>

                <section class="card" aria-labelledby="opp-team-title">
                    <h2 class="card-title" id="opp-team-title" style="margin-bottom: 12px">{{ __('opportunities.page.team') }}</h2>
                    @if ($team->isNotEmpty())
                        <ul class="opp-team">
                            @foreach ($team as $member)
                                <li wire:key="team-{{ $member->id }}">
                                    <x-hq.avatar :user="$member" :size="32" />
                                    <span class="opp-team-text">
                                        <span class="opp-team-name">{{ trim($member->first_name . ' ' . $member->last_name) }}</span>
                                        <span class="help">{{ $member->id === $o->lead_user_id ? __('opportunities.page.team_lead') : ($member->job_title ?: __('opportunities.page.team_member')) }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="help" style="margin: 0">{{ __('opportunities.page.team_none') }}</p>
                    @endif
                </section>

                <section class="card" aria-labelledby="opp-copq-title">
                    <h2 class="card-title" id="opp-copq-title" style="margin-bottom: 12px">{{ __('opportunities.page.copq') }}</h2>
                    @if ($copq)
                        <div class="stat-label">{{ __('opportunities.page.copq_amount') }}</div>
                        <div class="stat-value">{{ $money($copq->calculated_leakage, $copq->copq_currency) }}</div>
                        <dl class="opp-dl" style="margin-top: 12px">
                            @if ($copq->bottleneck)
                                <dt>{{ __('opportunities.page.bottleneck') }}</dt><dd>{{ $copq->bottleneck }}</dd>
                            @endif
                            <dt>{{ __('opportunities.page.estimated_on') }}</dt><dd>{{ $day($copq->created_at) }}</dd>
                        </dl>
                    @else
                        <p class="help" style="margin: 0">{{ __('opportunities.page.no_copq') }}</p>
                    @endif
                </section>

                <section class="card" aria-labelledby="opp-history-title">
                    <h2 class="card-title" id="opp-history-title" style="margin-bottom: 12px">{{ __('opportunities.page.history') }}</h2>
                    @if ($history->isNotEmpty())
                        <ol class="opp-history">
                            @foreach ($history as $h)
                                <li>
                                    <time datetime="{{ \Illuminate\Support\Carbon::parse($h->created_at)->toIso8601String() }}">{{ $day($h->created_at) }}, {{ $time($h->created_at) }} · {{ $h->first_name ? trim($h->first_name . ' ' . $h->last_name) : __('opportunities.page.system') }}</time>
                                    {{ \Illuminate\Support\Facades\Lang::has('opportunities.history.' . $h->action) ? __('opportunities.history.' . $h->action) : $h->description }}
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="help" style="margin: 0">{{ $o->source === 'backfill' ? __('opportunities.page.history_backfill') : __('opportunities.page.history_none') }}</p>
                    @endif
                </section>
            </div>

        @elseif ($tab === 'meetings')
            {{-- PR 2: App\Livewire\Hq\Opportunities\MeetingsTab --}}
            <livewire:hq.opportunities.meetings-tab :opportunity-id="$o->id" :key="'meetings-' . $o->id" />

        @elseif ($tab === 'questionnaire')
            <livewire:hq.opportunities.questionnaire-tab :opportunity-id="$o->id" :key="'questionnaire-' . $o->id" />

        @elseif ($tab === 'proposals')
            <div class="callout opp-callout mb-6" role="note"><i data-lucide="info"></i><span>{{ __('opportunities.stubs.proposals') }}</span></div>
            @if ($proposals->isNotEmpty())
                <div class="card" style="padding: 0">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('opportunities.proposals.reference') }}</th>
                                    <th scope="col">{{ __('opportunities.proposals.title') }}</th>
                                    <th scope="col">{{ __('opportunities.proposals.status') }}</th>
                                    <th scope="col" class="num">{{ __('opportunities.proposals.value') }}</th>
                                    <th scope="col">{{ __('opportunities.proposals.created') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($proposals as $p)
                                    <tr wire:key="proposal-{{ $p->id }}">
                                        <td>
                                            @if ($canOpenProposals)
                                                <a href="{{ route('hq.crm.proposal', ['id' => $p->id]) }}" wire:navigate>{{ $p->proposal_ref ?: __('opportunities.proposals.draft_ref') }}</a>
                                            @else
                                                {{ $p->proposal_ref ?: __('opportunities.proposals.draft_ref') }}
                                            @endif
                                        </td>
                                        <td>{{ $p->title ?: '—' }}</td>
                                        <td>{{ __('opportunities.proposals.statuses.' . \Illuminate\Support\Str::snake(str_replace(' ', '', $p->status))) }}</td>
                                        <td class="num">{{ $money($p->total_value, $p->currency) }}</td>
                                        <td style="white-space: nowrap">{{ $day($p->created_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="card empty-state"><strong>{{ __('opportunities.proposals.empty_title') }}</strong>{{ __('opportunities.proposals.empty_text') }}</div>
            @endif

        @elseif ($tab === 'sow')
            @if ($sows->isNotEmpty())
                <div class="card">
                    <ul style="margin: 0; padding-left: 18px">
                        @foreach ($sows as $sow)
                            <li wire:key="sow-{{ $sow->id }}">{{ $sow->reference ?: __('opportunities.tabs.sow') }} · {{ __('opportunities.sow.version', ['n' => $sow->version]) }} · {{ __('opportunities.sow.statuses.' . $sow->status) }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="card empty-state"><strong>{{ __('opportunities.sow.empty_title') }}</strong>{{ __('opportunities.stubs.sow') }}</div>
            @endif

        @elseif ($tab === 'project')
            @if ($project)
                <section class="card" aria-labelledby="opp-project-title">
                    <h2 class="card-title" id="opp-project-title" style="margin-bottom: 12px">{{ $project->title }}</h2>
                    <dl class="opp-dl" style="margin-bottom: 16px">
                        <dt>{{ __('opportunities.project.status') }}</dt><dd>{{ __('opportunities.project.statuses.' . strtolower($project->status)) }}</dd>
                        @if ($project->start_date)
                            <dt>{{ __('opportunities.project.start') }}</dt><dd>{{ $day($project->start_date) }}</dd>
                        @endif
                        @if ($project->target_end_date)
                            <dt>{{ __('opportunities.project.end') }}</dt><dd>{{ $day($project->target_end_date) }}</dd>
                        @endif
                    </dl>
                    @if ($canOpenProjects)
                        <a href="{{ route('hq.ops.board', ['id' => $project->id]) }}" class="btn btn-secondary" wire:navigate><i data-lucide="kanban"></i> {{ __('opportunities.project.open_board') }}</a>
                    @endif
                </section>
            @else
                <div class="card empty-state"><strong>{{ __('opportunities.project.empty_title') }}</strong>{{ __('opportunities.stubs.project') }}</div>
            @endif
        @endif
    </div>
</div>
