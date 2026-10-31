<div class="qn">
    <style>
        /* The Questionnaire tab (docs/OPPORTUNITIES.md section 4, row 3): only what the shared classes don't cover. Brand tokens only. */
        .qn { display: flex; flex-direction: column; gap: 16px; }
        .qn .alert, .qn .callout { margin: 0; }
        .qn-intro { margin: 4px 0 16px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .qn-form { display: flex; flex-direction: column; gap: 20px; }
        .qn-count { max-width: 120px; }
        .qn-fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
        .qn-focus { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(240px, 100%), 1fr)); gap: 10px 16px; margin-top: 4px; }
        .qn-check { display: flex; align-items: center; gap: 8px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); cursor: pointer; }
        .qn-callout { display: flex; gap: 10px; align-items: flex-start; font-size: 0.875rem; line-height: 1.375rem; }
        .qn-callout svg, .qn .alert svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; }
        .qn-buttons { display: flex; flex-wrap: wrap; gap: 12px; }
        .qn-title { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin: 0; }
        .qn-title svg { width: 18px; height: 18px; }
        .qn-progress { height: 8px; border-radius: 999px; background: var(--color-mist); overflow: hidden; }
        .qn-progress > span { display: block; height: 100%; border-radius: 999px; background: var(--color-deep-teal); transition: width 300ms ease; }
        .qn-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
        .qn-bar-text { min-width: 0; display: flex; flex-direction: column; gap: 6px; }
        .qn-status { margin: 0; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .qn-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
        .qn-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 0.8125rem; line-height: 1.25rem; font-weight: 600; color: var(--color-muted); }
        .qn-q { margin: 0 0 6px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .qn-why { margin: 0 0 16px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); }
        .qn-options { border: 0; margin: 0; padding: 0; min-width: 0; display: flex; flex-direction: column; gap: 8px; }
        .qn-option { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: start; gap: 4px 10px; padding: 10px 12px; border: 1px solid var(--color-line-strong); border-radius: var(--radius-control); background: var(--color-paper); font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); cursor: pointer; transition: background var(--motion-instant), border-color var(--motion-instant); }
        .qn-option:hover { background: var(--color-mist); }
        .qn-option:has(input:checked) { border-color: var(--color-deep-teal); background: var(--color-teal-tint); }
        .qn-option:has(input:focus-visible) { outline: 2px solid var(--color-focus); outline-offset: 2px; }
        .qn-option input { margin-top: 3px; }
        .qn-option .pill { align-self: center; }
        .qn-options:disabled .qn-option { cursor: default; }
        .qn-options:disabled .qn-option:not(:has(input:checked)) { background: var(--color-paper); color: var(--color-slate); }
        .qn-reason { display: flex; gap: 8px; margin-top: 12px; padding: 10px 12px; border-radius: var(--radius-control); background: var(--color-mist); font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-slate); }
        .qn-reason svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px; color: var(--color-deep-teal); }
        .qn-text { margin-top: 12px; }
        .qn-answer { margin-top: 12px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-ink); white-space: pre-line; overflow-wrap: anywhere; }
        .qn-answer strong { display: block; margin-bottom: 2px; font-size: 0.8125rem; color: var(--color-muted); white-space: normal; }
        .qn-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--color-line); }
        .qn-panel { margin-top: 16px; padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist); display: flex; flex-direction: column; gap: 12px; }
        .qn-panel-title { margin: 0; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); }
        .qn-busy { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 0.875rem; line-height: 1.375rem; color: var(--color-petrol); }
        .qn-busy svg, .qn-title .qn-spin { width: 18px; height: 18px; }
        .qn-spin { animation: qn-spin 1s linear infinite; }
        .qn-foot { display: flex; justify-content: flex-end; }
        @keyframes qn-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) {
            .qn-spin { animation: none; }
            .qn-progress > span, .qn-option { transition: none; }
        }
        @media (max-width: 600px) {
            .qn-option { grid-template-columns: auto minmax(0, 1fr); }
            .qn-option .pill { grid-column: 2; justify-self: start; }
            .qn-bar .qn-buttons, .qn-buttons .btn { flex: 1 1 100%; }
            .qn-foot { justify-content: stretch; }
            .qn-foot .btn { flex: 1 1 100%; }
        }
    </style>

    @php
        $locale = app()->getLocale();
        $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->locale($locale)->translatedFormat(__('opportunities.formats.date')) : '—';
        $open = $questionnaire?->status === 'open';
    @endphp

    @if ($polling)
        <div wire:poll.3s="check" hidden></div>
    @endif

    @if ($notice)
        <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ $notice }}</span></div>
    @endif

    @if ($showForm)
        @if ($canAnswer)
            <section class="card" aria-labelledby="qn-setup-title">
                <h2 class="card-title" id="qn-setup-title">{{ __('questionnaire.setup.title') }}</h2>
                <p class="qn-intro">{{ __('questionnaire.intro') }}</p>

                <div class="qn">
                    @if ($draftError)
                        <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ __('questionnaire.setup.failed', ['message' => $draftError]) }}</span></div>
                    @endif
                    @error('draft')
                        <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                    @enderror
                    @unless ($hasNotes)
                        <div class="callout qn-callout" role="note"><i data-lucide="info"></i><span>{{ __('questionnaire.setup.no_notes') }}</span></div>
                    @endunless

                    <form wire:submit="draft" class="qn-form" novalidate>
                        <div class="field">
                            <label for="qn-count" class="label">{{ __('questionnaire.setup.count') }}</label>
                            <input id="qn-count" type="number" inputmode="numeric" class="input qn-count" wire:model="questionCount"
                                   min="{{ \App\Support\Opportunities\Questionnaires::MIN_QUESTIONS }}" max="{{ \App\Support\Opportunities\Questionnaires::MAX_QUESTIONS }}" step="1"
                                   aria-describedby="qn-count-help @error('questionCount') qn-count-error @enderror" @error('questionCount') aria-invalid="true" @enderror>
                            <span class="help" id="qn-count-help">{{ __('questionnaire.setup.count_help', ['min' => \App\Support\Opportunities\Questionnaires::MIN_QUESTIONS, 'max' => \App\Support\Opportunities\Questionnaires::MAX_QUESTIONS]) }}</span>
                            @error('questionCount') <span class="field-error" id="qn-count-error">{{ $message }}</span> @enderror
                        </div>

                        <fieldset class="field qn-fieldset" aria-describedby="qn-focus-help">
                            <legend class="label">{{ __('questionnaire.setup.focus') }}</legend>
                            <span class="help" id="qn-focus-help">{{ __('questionnaire.setup.focus_help') }}</span>
                            <div class="qn-focus">
                                @foreach (array_keys(\App\Support\Opportunities\Questionnaires::FOCUS) as $area)
                                    <label class="qn-check" wire:key="focus-{{ $area }}"><input type="checkbox" class="checkbox" value="{{ $area }}" wire:model="focusAreas"> {{ __('questionnaire.focus.' . $area) }}</label>
                                @endforeach
                            </div>
                            @error('focusAreas.*') <span class="field-error">{{ $message }}</span> @enderror
                        </fieldset>

                        <div class="field">
                            <label for="qn-note" class="label">{{ __('questionnaire.setup.note') }}</label>
                            <textarea id="qn-note" class="textarea" rows="3" maxlength="1000" wire:model="focusNote"
                                      aria-describedby="qn-note-help @error('focusNote') qn-note-error @enderror" @error('focusNote') aria-invalid="true" @enderror></textarea>
                            <span class="help" id="qn-note-help">{{ __('questionnaire.setup.note_help') }}</span>
                            @error('focusNote') <span class="field-error" id="qn-note-error">{{ $message }}</span> @enderror
                        </div>

                        <p class="help" style="margin: 0">{{ __('questionnaire.setup.language', ['language' => __('questionnaire.language_names.' . $o->language)]) }} {{ __('questionnaire.setup.cost') }}</p>

                        <div class="qn-buttons">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="draft">
                                <i data-lucide="sparkles"></i>
                                <span wire:loading.remove wire:target="draft">{{ __('questionnaire.setup.submit') }}</span>
                                <span wire:loading wire:target="draft">{{ __('questionnaire.setup.submitting') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        @else
            <div class="card empty-state">
                <strong>{{ __('questionnaire.empty_title') }}</strong>
                {{ $o->isClosed() ? __('questionnaire.closed') : ($lead ? __('questionnaire.only_lead', ['name' => $lead]) : __('questionnaire.no_lead')) }}
            </div>
        @endif

    @elseif ($drafting)
        <section class="card" aria-labelledby="qn-drafting-title" aria-busy="true">
            <h2 class="card-title qn-title" id="qn-drafting-title"><i data-lucide="loader-circle" class="qn-spin"></i> {{ trans_choice('questionnaire.drafting.title', $questionnaire->question_count, ['count' => $questionnaire->question_count]) }}</h2>
            <p class="qn-intro">{{ __('questionnaire.drafting.text') }}</p>
            <div class="qn-progress" role="progressbar" aria-label="{{ __('questionnaire.drafting.progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $run->progress }}">
                <span style="width: {{ max(4, (int) $run->progress) }}%"></span>
            </div>
            @if ($run->status === 'queued')
                <p class="help" style="margin: 8px 0 0">{{ __('questionnaire.drafting.queued') }}</p>
            @endif
        </section>

    @else
        <section class="card qn-bar" aria-labelledby="qn-title">
            <div class="qn-bar-text">
                <h2 class="card-title qn-title" id="qn-title">
                    {{ __('opportunities.tabs.questionnaire') }}
                    <span @class(['pill', 'pill-success' => $questionnaire->isComplete(), 'pill-info' => ! $questionnaire->isComplete()])>{{ __('questionnaire.statuses.' . $questionnaire->status) }}</span>
                </h2>
                <p class="qn-status">
                    {{ trans_choice('questionnaire.summary', $questions->count(), ['answered' => $answered, 'count' => $questions->count()]) }}
                    @if ($canAnswer && $open)
                        · <span wire:loading.remove wire:target="answers">{{ __('questionnaire.saved') }}</span><span wire:loading wire:target="answers">{{ __('questionnaire.saving') }}</span>
                    @endif
                </p>
                <div class="qn-progress" aria-hidden="true"><span style="width: {{ $questions->count() ? round(100 * $answered / $questions->count()) : 0 }}%"></span></div>
            </div>
            @if ($canAnswer && $open)
                <div class="qn-buttons">
                    <button type="button" class="btn btn-secondary" wire:click="openAdd"><i data-lucide="plus"></i> {{ __('questionnaire.add.button') }}</button>
                    <button type="button" class="btn btn-primary" wire:click="complete" wire:confirm="{{ __('questionnaire.complete.confirm') }}"
                            @if ($answered < $questions->count() || $questions->isEmpty()) aria-disabled="true" aria-describedby="qn-complete-help" @endif>
                        <i data-lucide="lock"></i> {{ __('questionnaire.complete.button') }}
                    </button>
                </div>
            @endif
        </section>

        @if ($questionnaire->isComplete())
            <div class="alert alert-success" role="note"><i data-lucide="lock"></i><span>{{ __('questionnaire.complete.locked', ['name' => $questionnaire->completedBy ? trim($questionnaire->completedBy->first_name . ' ' . $questionnaire->completedBy->last_name) : __('opportunities.page.system'), 'date' => $day($questionnaire->completed_at)]) }}</span></div>
        @elseif (! $canAnswer)
            <div class="callout qn-callout" role="note"><i data-lucide="info"></i><span>{{ $o->isClosed() ? __('questionnaire.closed') : ($lead ? __('questionnaire.read_only', ['name' => $lead]) : __('questionnaire.no_lead')) }}</span></div>
        @elseif ($answered < $questions->count())
            <p class="help" id="qn-complete-help" style="margin: 0">{{ __('questionnaire.complete.blocked') }}</p>
        @endif
        @error('complete')
            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
        @enderror

        @if ($canAnswer && $open && $adding)
            <section class="card" aria-labelledby="qn-add-title">
                <h2 class="card-title" id="qn-add-title" style="margin-bottom: 16px">{{ __('questionnaire.add.title') }}</h2>
                <form wire:submit="addQuestion" class="qn-form" novalidate>
                    <div class="field">
                        <label for="qn-new-question" class="label">{{ __('questionnaire.add.question') }}</label>
                        <textarea id="qn-new-question" class="textarea" rows="2" maxlength="1000" wire:model="newQuestion" x-init="$nextTick(() => $el.focus())"
                                  @error('newQuestion') aria-invalid="true" aria-describedby="qn-new-question-error" @enderror></textarea>
                        @error('newQuestion') <span class="field-error" id="qn-new-question-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="qn-new-why" class="label">{{ __('questionnaire.add.why') }}</label>
                        <textarea id="qn-new-why" class="textarea" rows="2" maxlength="1000" wire:model="newWhy" aria-describedby="qn-new-why-help"></textarea>
                        <span class="help" id="qn-new-why-help">{{ __('questionnaire.add.why_help') }}</span>
                        @error('newWhy') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="qn-buttons">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="addQuestion"><i data-lucide="plus"></i> {{ __('questionnaire.add.submit') }}</button>
                        <button type="button" class="btn btn-secondary" wire:click="cancelAdd">{{ __('opportunities.cancel') }}</button>
                    </div>
                </form>
            </section>
        @endif

        <ol class="qn-list">
            @foreach ($questions as $q)
                @php
                    $busy = $q->aiRun && ! $q->aiRun->isFinished();
                    $failed = $q->aiRun?->status === 'failed' ? $q->aiRun : null;
                    $editable = $canAnswer && $open && ! $busy;
                    $options = (array) $q->options;
                    $choice = (string) ($answers[$q->id]['choice'] ?? '');
                    $own = ! $options || $choice === 'own';
                @endphp
                <li class="card" wire:key="q-{{ $q->id }}" aria-labelledby="q-{{ $q->id }}-title" @if ($busy) aria-busy="true" @endif>
                    <div class="qn-head">
                        <span>{{ __('questionnaire.question.number', ['n' => $q->position]) }}</span>
                        @if ($q->origin === 'lead')
                            <span class="tag">{{ __('questionnaire.question.added_by_lead') }}</span>
                        @endif
                        @if ($q->isAnswered())
                            <span class="pill pill-success">{{ __('questionnaire.question.answered') }}</span>
                        @else
                            <span class="pill pill-neutral">{{ __('questionnaire.question.not_answered') }}</span>
                        @endif
                    </div>
                    <h3 class="qn-q" id="q-{{ $q->id }}-title">{{ $q->question }}</h3>
                    @if ($q->why_it_matters)
                        <p class="qn-why"><strong>{{ __('questionnaire.question.why') }}</strong> {{ $q->why_it_matters }}</p>
                    @endif

                    @if ($busy)
                        <div class="qn-busy" role="status"><i data-lucide="loader-circle" class="qn-spin"></i> {{ $q->origin === 'lead' ? __('questionnaire.question.suggesting') : __('questionnaire.question.redrafting') }}</div>
                    @elseif ($failed && $canAnswer && $open)
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px">
                            <i data-lucide="circle-alert"></i>
                            <span style="flex: 1">{{ __('questionnaire.question.failed', ['message' => $failed->errorMessage()]) }}</span>
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="dismissFailure('{{ $q->id }}')" aria-label="{{ __('questionnaire.question.dismiss') }}"><i data-lucide="x"></i></button>
                        </div>
                    @endif

                    @if ($options)
                        <fieldset class="qn-options" aria-labelledby="q-{{ $q->id }}-title" @disabled(! $editable)>
                            @foreach ($options as $i => $option)
                                <label class="qn-option" wire:key="q-{{ $q->id }}-{{ $i }}">
                                    <input type="radio" name="q-{{ $q->id }}" value="{{ $i }}" wire:model.live="answers.{{ $q->id }}.choice">
                                    <span>{{ $option }}</span>
                                    @if ($q->recommended_option === $i)
                                        <span class="pill pill-info">{{ __('questionnaire.question.recommended') }}</span>
                                    @endif
                                </label>
                            @endforeach
                            <label class="qn-option" wire:key="q-{{ $q->id }}-own">
                                <input type="radio" name="q-{{ $q->id }}" value="own" wire:model.live="answers.{{ $q->id }}.choice">
                                <span>{{ __('questionnaire.question.own') }}</span>
                            </label>
                        </fieldset>
                        @if ($q->recommendation_reason)
                            <div class="qn-reason"><i data-lucide="sparkles"></i><span><strong>{{ __('questionnaire.question.reason') }}</strong> {{ $q->recommendation_reason }}</span></div>
                        @endif
                    @elseif ($editable)
                        <p class="help" style="margin: 0">{{ __('questionnaire.question.no_suggestions') }}</p>
                    @endif

                    @if ($editable && (! $options || $choice !== ''))
                        <div class="field qn-text">
                            <label for="q-{{ $q->id }}-text" class="label">{{ $own ? __('questionnaire.question.own_label') : __('questionnaire.question.detail') }}</label>
                            <textarea id="q-{{ $q->id }}-text" class="textarea" rows="3" maxlength="{{ \App\Support\Opportunities\Questionnaires::MAX_TEXT }}" wire:model.live.debounce.1000ms="answers.{{ $q->id }}.text"
                                      @error('answers.' . $q->id . '.text') aria-invalid="true" aria-describedby="q-{{ $q->id }}-error" @enderror></textarea>
                            @error('answers.' . $q->id . '.text') <span class="field-error" id="q-{{ $q->id }}-error">{{ $message }}</span> @enderror
                        </div>
                    @elseif (! $editable && filled($q->answer_text))
                        <div class="qn-answer"><strong>{{ $q->answer_option === null ? __('questionnaire.question.answer') : __('questionnaire.question.detail_label') }}</strong>{{ $q->answer_text }}</div>
                    @endif

                    @if ($editable)
                        @if ($redraftingId === $q->id)
                            <form wire:submit="redraft" class="qn-panel" novalidate aria-labelledby="qn-redraft-title">
                                <p class="qn-panel-title" id="qn-redraft-title">{{ $q->origin === 'lead' ? __('questionnaire.redraft.suggest_title', ['n' => $q->position]) : __('questionnaire.redraft.title', ['n' => $q->position]) }}</p>
                                <div class="field">
                                    <label for="qn-redraft-note" class="label">{{ $q->origin === 'lead' ? __('questionnaire.redraft.suggest_note') : __('questionnaire.redraft.note') }}</label>
                                    <textarea id="qn-redraft-note" class="textarea" rows="2" maxlength="1000" wire:model="redraftNote" x-init="$nextTick(() => $el.focus())"
                                              aria-describedby="qn-redraft-help @error('redraftNote') qn-redraft-error @enderror" @error('redraftNote') aria-invalid="true" @enderror></textarea>
                                    <span class="help" id="qn-redraft-help">{{ $q->origin === 'lead' ? __('questionnaire.redraft.suggest_help') : __('questionnaire.redraft.note_help') }}</span>
                                    @error('redraftNote') <span class="field-error" id="qn-redraft-error">{{ $message }}</span> @enderror
                                </div>
                                @if ($q->origin !== 'lead' && $q->isAnswered())
                                    <p class="help" style="margin: 0">{{ __('questionnaire.redraft.clears') }}</p>
                                @endif
                                @error('redraft')
                                    <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                                @enderror
                                <div class="qn-buttons">
                                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="redraft">
                                        <i data-lucide="sparkles"></i> {{ $q->origin === 'lead' ? __('questionnaire.redraft.suggest_submit') : __('questionnaire.redraft.submit') }}
                                    </button>
                                    <button type="button" class="btn btn-secondary" wire:click="cancelRedraft">{{ __('opportunities.cancel') }}</button>
                                </div>
                            </form>
                        @else
                            <div class="qn-actions">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="openRedraft('{{ $q->id }}')">
                                    @if ($q->origin === 'lead')
                                        <i data-lucide="sparkles"></i> {{ $options ? __('questionnaire.question.suggest_again') : __('questionnaire.question.suggest') }}
                                    @else
                                        <i data-lucide="refresh-cw"></i> {{ __('questionnaire.question.redraft') }}
                                    @endif
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="remove('{{ $q->id }}')" wire:confirm="{{ __('questionnaire.question.remove_confirm', ['n' => $q->position]) }}">
                                    <i data-lucide="trash-2"></i> {{ __('questionnaire.question.remove') }}
                                </button>
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($canAnswer && $open)
            <div class="qn-foot">
                <button type="button" class="btn btn-ghost btn-sm" wire:click="restart" wire:confirm="{{ __('questionnaire.restart.confirm') }}"><i data-lucide="rotate-ccw"></i> {{ __('questionnaire.restart.button') }}</button>
            </div>
        @endif
    @endif
</div>
