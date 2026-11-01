<div class="pp">
    @include('livewire.hq.opportunities.partials.document-styles')

    @php
        $locale = app()->getLocale();
        $day = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->setTimezone('Africa/Lagos')->locale($locale)->translatedFormat(__('opportunities.formats.date')) : '—';
        $money = fn ($amount, $currency) => $locale === 'fr'
            ? number_format((float) $amount, 2, ',', "\u{202F}") . "\u{00A0}" . $currency
            : $currency . "\u{00A0}" . number_format((float) $amount, 2);
        $percent = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, $locale === 'fr' ? ',' : '.', ''), '0'), '.,') . ($locale === 'fr' ? "\u{202F}%" : '%');
        $person = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : __('opportunities.page.system');
        $tone = fn ($status) => match ($status) {
            'signed' => 'pill-success', 'changes_requested' => 'pill-warning', 'superseded' => 'pill-neutral', 'sent' => 'pill-info', default => 'pill-neutral',
        };
        $statusLabel = fn ($s) => $s->status === 'draft' && $s->approved_at !== null ? __('sow.statuses.approved') : __('sow.statuses.' . $s->status);
    @endphp

    @if ($polling)
        <div wire:poll.3s="check" hidden></div>
    @endif

    @if ($notice)
        <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ $notice }}</span></div>
    @endif
    @error('sow')
        <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
    @enderror

    @if (! $sow)
        @if (! $proposal)
            <div class="card empty-state">
                <strong>{{ __('sow.empty_title') }}</strong>
                {{ $o->isClosed() ? __('sow.closed') : __('sow.no_proposal') }}
                <div style="margin-top: 12px"><button type="button" class="btn btn-secondary btn-sm" wire:click="$parent.show('proposals')"><i data-lucide="file-text"></i> {{ __('sow.open_proposals') }}</button></div>
            </div>
        @elseif ($canEdit)
            <section class="card" aria-labelledby="sw-start-title">
                <h2 class="card-title" id="sw-start-title">{{ __('sow.start.title') }}</h2>
                <p class="pp-intro">{{ __('sow.start.intro', ['ref' => $proposal->proposal_ref, 'n' => $proposal->version, 'name' => $proposal->client_approved_name]) }}</p>
                <div class="pp-form">
                    @error('draft')
                        <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                    @enderror
                    @unless ($legalName)
                        <div class="alert alert-warning" role="note"><i data-lucide="triangle-alert"></i><span>{{ __('sow.legal_name.missing') }}</span></div>
                    @endunless
                    @unless ($canDraft)
                        <div class="callout pp-callout" role="note"><i data-lucide="lock"></i><span>{{ __('sow.start.no_transcripts') }}</span></div>
                    @endunless
                    @if ($canDraft)
                        @include('livewire.hq.opportunities.partials.sow-instructions', ['id' => 'sw-instructions'])
                    @endif
                    <div class="pp-buttons">
                        @if ($canDraft)
                            <button type="button" class="btn btn-primary" wire:click="start(true)" wire:loading.attr="disabled" wire:target="start"><i data-lucide="sparkles"></i> {{ __('sow.start.ai') }}</button>
                        @endif
                        <button type="button" @class(['btn', 'btn-secondary' => $canDraft, 'btn-primary' => ! $canDraft]) wire:click="start(false)" wire:loading.attr="disabled" wire:target="start">
                            <i data-lucide="file-pen-line"></i> {{ __('sow.start.blank') }}
                        </button>
                    </div>
                </div>
            </section>
        @else
            <div class="card empty-state"><strong>{{ __('sow.empty_title') }}</strong>{{ $o->isClosed() ? __('sow.closed') : __('sow.empty_text') }}</div>
        @endif

    @else
        {{-- The version on screen --}}
        <section class="card pp-bar" aria-labelledby="sw-title">
            <div class="pp-bar-text">
                <h2 class="card-title pp-title" id="sw-title">
                    {{ __('sow.heading', ['ref' => $sow->reference, 'n' => $sow->version]) }}
                    <span class="pill {{ $sow->status === 'draft' && $sow->approved_at ? 'pill-info' : $tone($sow->status) }}">{{ $statusLabel($sow) }}</span>
                </h2>
                <p class="pp-sub">
                    @if ($sow->status === 'draft' && ! $sow->approved_at)
                        {{ __('sow.bar.draft', ['date' => $day($sow->updated_at)]) }}
                        @if ($editing)
                            · <span class="pp-saving" wire:loading.remove wire:target="title,sections,milestones,validityDays,changesSummary">{{ __('proposals.editor.saved') }}</span><span class="pp-saving" wire:loading wire:target="title,sections,milestones,validityDays,changesSummary">{{ __('proposals.editor.saving') }}</span>
                        @endif
                    @elseif ($sow->status === 'draft')
                        {{ __('sow.bar.approved', ['name' => $sow->hq_signer_name, 'date' => $day($sow->approved_at)]) }}
                    @elseif ($sow->status === 'signed')
                        {{ __('sow.bar.signed', ['name' => $sow->signed_by_name, 'date' => $day($sow->signed_at)]) }}
                    @else
                        {{ __('sow.bar.sent', ['date' => $day($sow->sent_at), 'until' => $day($sow->validUntil())]) }}
                    @endif
                </p>
                <p class="pp-sub">{{ __('sow.bar.parties', ['party' => $partyName, 'client' => $o->organization?->name]) }}</p>
            </div>
            <div class="pp-buttons">
                @if ($versions->count() > 1)
                    <label for="sw-version" class="sr-only">{{ __('sow.versions.label') }}</label>
                    <select id="sw-version" class="select pp-versions" wire:change="show($event.target.value)">
                        @foreach ($versions as $v)
                            <option value="{{ $v->id }}" @selected($v->id === $sow->id) wire:key="sv-{{ $v->id }}">{{ __('sow.version', ['n' => $v->version]) }} · {{ $statusLabel($v) }}</option>
                        @endforeach
                    </select>
                @endif
                <a class="btn btn-secondary" href="{{ route('hq.crm.sow-pdf', ['opportunity' => $o->id, 'sow' => $sow->id]) }}" target="_blank" rel="noopener">
                    <i data-lucide="file-text"></i> {{ $sow->sent_at ? __('sow.bar.pdf') : __('sow.bar.preview') }}
                </a>
            </div>
        </section>

        @if ($drafting)
            <section class="card" aria-labelledby="sw-drafting-title" aria-busy="true">
                <h2 class="card-title pp-title" id="sw-drafting-title"><i data-lucide="loader-circle" class="pp-spin"></i> {{ __('sow.drafting.title', ['n' => $sow->version]) }}</h2>
                <p class="pp-intro" style="margin-bottom: 0">{{ __('sow.drafting.text') }}</p>
                <div class="pp-progress" role="progressbar" aria-label="{{ __('sow.drafting.progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $sow->aiRun->progress }}">
                    <span style="width: {{ max(4, (int) $sow->aiRun->progress) }}%"></span>
                </div>
                @if ($sow->aiRun->status === 'queued')
                    <p class="help" style="margin: 8px 0 0">{{ __('proposals.drafting.queued') }}</p>
                @endif
            </section>
        @elseif ($draftError)
            <div class="alert alert-danger" role="alert">
                <i data-lucide="circle-alert"></i>
                <span>{{ __('sow.drafting.failed', ['message' => $draftError]) }}
                    @if ($canEdit) <button type="button" class="btn btn-ghost btn-sm" wire:click="dismissFailure">{{ __('proposals.dismiss') }}</button> @endif
                </span>
            </div>
        @endif

        @if ($sow->status === 'signed')
            <div class="alert alert-success" role="note"><i data-lucide="badge-check"></i>
                <span>{{ __('sow.client.signed', ['name' => $sow->signed_by_name, 'title' => $sow->signed_by_title, 'email' => $sow->signed_by_email, 'date' => $day($sow->signed_at)]) }}
                    @if ($depositInvoice) {{ __('sow.client.deposit', ['number' => $depositInvoice->invoice_number, 'amount' => $money($depositInvoice->total_amount, $depositInvoice->currency)]) }} @endif
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$parent.show('project')"><i data-lucide="folder-kanban"></i> {{ __('sow.client.open_project') }}</button>
                </span>
            </div>
        @elseif ($sow->status === 'changes_requested')
            <div class="alert alert-warning" role="note"><i data-lucide="message-square-warning"></i>
                <span>{{ __('sow.client.changes', ['who' => $sow->changes_requested_by, 'date' => $day($sow->changes_requested_at)]) }}
                    @if (filled($sow->changes_request)) <br><q style="white-space: pre-line">{{ $sow->changes_request }}</q> @endif
                </span>
            </div>
        @elseif ($sow->status === 'superseded')
            <div class="callout pp-callout" role="note"><i data-lucide="history"></i><span>{{ $signedVersion && $signedVersion->version !== $sow->version ? __('sow.client.set_aside', ['n' => $signedVersion->version]) : __('sow.client.superseded') }}</span></div>
        @elseif ($sow->status === 'sent')
            <div class="callout pp-callout" role="note"><i data-lucide="send"></i><span>{{ $sow->hasExpired() ? __('sow.client.expired', ['date' => $day($sow->validUntil())]) : __('sow.client.waiting') }}</span></div>
        @endif

        @if ($canStartVersion && $sow->id === $latest->id)
            <div class="pp-buttons">
                <button type="button" @class(['btn', 'btn-primary' => $latest->status === 'changes_requested', 'btn-secondary' => $latest->status !== 'changes_requested']) wire:click="newVersion" wire:loading.attr="disabled" wire:target="newVersion">
                    <i data-lucide="copy-plus"></i> {{ __('sow.versions.new', ['n' => $latest->version + 1]) }}
                </button>
                <span class="help">{{ __('sow.versions.new_help') }}</span>
            </div>
        @endif

        @if ($sow->sent_at)
            <section class="card" aria-labelledby="sw-sent-title">
                <h2 class="card-title" id="sw-sent-title" style="margin-bottom: 12px">{{ __('proposals.send.sent_title') }}</h2>
                <dl class="pp-dl">
                    <dt>{{ __('proposals.send.sent_on') }}</dt><dd>{{ $day($sow->sent_at) }}</dd>
                    <dt>{{ __('proposals.send.sent_to') }}</dt><dd>{{ collect((array) $sow->recipients)->map(fn ($r) => ($r['name'] ?? '') . ' <' . ($r['email'] ?? '') . '>')->implode(', ') }}</dd>
                    @if ($sow->status !== 'signed')
                        <dt>{{ __('sow.terms.sign_by') }}</dt><dd>{{ $day($sow->validUntil()) }}</dd>
                    @endif
                </dl>
                @if ($sow->recipients_unsent)
                    <div class="alert alert-warning" role="alert" style="margin-top: 12px">
                        <i data-lucide="mail-warning"></i>
                        <span>{{ __('proposals.send.unsent', ['emails' => implode(', ', (array) $sow->recipients_unsent)]) }}
                            @if ($canEdit && $sow->status === 'sent')
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="resend" wire:loading.attr="disabled" wire:target="resend">{{ __('proposals.send.again') }}</button>
                            @endif
                        </span>
                    </div>
                @endif
                @error('send')
                    <div class="alert alert-danger" role="alert" style="margin-top: 12px"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                @enderror

                @if ($canEdit && $sow->status === 'sent' && ! $sow->hasExpired() && $people)
                    <form wire:submit="sendLinkAgain" class="pp-form" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-line)" novalidate>
                        <fieldset class="field" style="border: 0; padding: 0; margin: 0; min-width: 0" aria-describedby="sw-link-help">
                            <legend class="label">{{ __('proposals.send.link_title') }}</legend>
                            <span class="help" id="sw-link-help">{{ __('sow.send.link_intro', ['date' => $day($sow->validUntil())]) }}</span>
                            <div class="pp-people" style="margin-top: 8px">
                                @foreach ($people as $email => $p)
                                    <label class="pp-check" wire:key="sagain-{{ md5($email) }}">
                                        <input type="checkbox" class="checkbox" value="{{ $email }}" wire:model="linkTo">
                                        <span>{{ $p['name'] }} <small>{{ $email }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('linkTo') <span class="field-error">{{ $message }}</span> @enderror
                            @error('linkTo.*') <span class="field-error">{{ $message }}</span> @enderror
                        </fieldset>
                        <div class="pp-buttons">
                            <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="sendLinkAgain"><i data-lucide="send"></i> {{ __('proposals.send.link_submit') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        {{-- Approved (signed for JBS Praxis), not sent yet: who it goes to --}}
        @if ($sow->status === 'draft' && $sow->approved_at)
            <section class="card" aria-labelledby="sw-send-title">
                <h2 class="card-title" id="sw-send-title">{{ __('sow.send.title') }}</h2>
                <p class="pp-intro">{{ __('sow.send.intro', ['language' => __('proposals.language_names.' . $o->language)]) }}</p>
                @if ($canEdit)
                    <form wire:submit="send" class="pp-form" novalidate>
                        @error('send')
                            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                        @enderror
                        @if ($people)
                            <fieldset class="field" style="border: 0; padding: 0; margin: 0; min-width: 0" aria-describedby="sw-people-help">
                                <legend class="label">{{ __('proposals.send.to') }}</legend>
                                <span class="help" id="sw-people-help">{{ __('sow.send.to_help') }}</span>
                                <div class="pp-people" style="margin-top: 8px">
                                    @foreach ($people as $email => $p)
                                        <label class="pp-check" wire:key="sto-{{ md5($email) }}">
                                            <input type="checkbox" class="checkbox" value="{{ $email }}" wire:model="sendTo">
                                            <span>{{ $p['name'] }} <small>{{ $email }}</small></span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('sendTo') <span class="field-error">{{ $message }}</span> @enderror
                                @error('sendTo.*') <span class="field-error">{{ $message }}</span> @enderror
                            </fieldset>
                            <div class="pp-buttons">
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="send">
                                    <i data-lucide="send"></i>
                                    <span wire:loading.remove wire:target="send">{{ __('sow.send.submit') }}</span>
                                    <span wire:loading wire:target="send">{{ __('proposals.send.sending') }}</span>
                                </button>
                                @if ($canApprove)
                                    <button type="button" class="btn btn-secondary" wire:click="reopen" wire:confirm="{{ __('sow.approve.reopen_confirm') }}">{{ __('sow.approve.reopen') }}</button>
                                @endif
                            </div>
                        @else
                            <div class="callout pp-callout" role="note"><i data-lucide="info"></i><span>{{ __('proposals.send.no_people') }}</span></div>
                        @endif
                    </form>
                @else
                    <p class="help" style="margin: 0">{{ __('proposals.send.read_only') }}</p>
                @endif
            </section>
        @endif

        @if ($editing)
            @if ($redrafting)
                <section class="card" aria-labelledby="sw-redraft-title">
                    <h2 class="card-title" id="sw-redraft-title">{{ __('sow.redraft.title') }}</h2>
                    <p class="pp-intro">{{ $sow->version > 1 ? __('sow.redraft.intro_version', ['n' => $sow->version - 1]) : __('sow.redraft.intro') }}</p>
                    <div class="pp-form">
                        @error('draft')
                            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                        @enderror
                        @include('livewire.hq.opportunities.partials.sow-instructions', ['id' => 'sw-redraft-note'])
                        <div class="pp-buttons">
                            <button type="button" class="btn btn-primary" wire:click="draft" wire:loading.attr="disabled" wire:target="draft"><i data-lucide="sparkles"></i> {{ __('sow.redraft.submit') }}</button>
                            <button type="button" class="btn btn-secondary" wire:click="cancelRedraft">{{ __('opportunities.cancel') }}</button>
                        </div>
                    </div>
                </section>
            @endif

            <section class="card" aria-labelledby="sw-edit-title">
                <div class="pp-head">
                    <h2 class="card-title" id="sw-edit-title" style="margin: 0">{{ __('sow.editor.title') }}</h2>
                    @if ($canDraft && ! $redrafting)
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openRedraft"><i data-lucide="sparkles"></i> {{ __('sow.redraft.button') }}</button>
                    @endif
                </div>
                <div class="pp-form">
                    <div class="field">
                        <label for="sw-doc-title" class="label">{{ __('sow.editor.doc_title') }}</label>
                        <input id="sw-doc-title" type="text" class="input" maxlength="{{ \App\Support\Opportunities\Sows::MAX_TITLE }}" wire:model.blur="title">
                    </div>
                    @if ($sow->version > 1)
                        <div class="field">
                            <label for="sw-changes" class="label">{{ __('sow.changes.label', ['n' => $sow->version - 1]) }}</label>
                            <textarea id="sw-changes" class="textarea" rows="4" maxlength="{{ \App\Support\Opportunities\Sows::MAX_NOTE }}" wire:model.blur="changesSummary" aria-describedby="sw-changes-help"></textarea>
                            <span class="help" id="sw-changes-help">{{ __('sow.changes.help') }}</span>
                        </div>
                        @if ($sow->previousVersion && filled($sow->previousVersion->changes_request))
                            <div class="callout pp-callout" role="note"><i data-lucide="message-square-quote"></i><span>{{ __('sow.changes.asked', ['n' => $sow->version - 1]) }} <q style="white-space: pre-line">{{ $sow->previousVersion->changes_request }}</q></span></div>
                        @endif
                    @endif
                    <div class="field" style="max-width: 240px">
                        <label for="sw-validity" class="label">{{ __('sow.terms.validity_input') }}</label>
                        <input id="sw-validity" type="number" inputmode="numeric" min="7" max="90" step="1" class="input" wire:model.blur="validityDays">
                    </div>
                </div>
            </section>

            <ol class="pp-sections" aria-label="{{ __('sow.editor.sections') }}">
                @foreach ($sections as $i => $section)
                    <li class="card" wire:key="ss-{{ $i }}-{{ $section['key'] ?? $i }}">
                        <div class="pp-head">
                            <span>{{ __('proposals.editor.section', ['n' => $i + 1]) }}</span>
                            <span class="pp-tools">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="moveSection({{ $i }}, -1)" @disabled($i === 0) aria-label="{{ __('proposals.editor.up', ['n' => $i + 1]) }}" title="{{ __('proposals.editor.up', ['n' => $i + 1]) }}"><i data-lucide="arrow-up"></i></button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="moveSection({{ $i }}, 1)" @disabled($i === count($sections) - 1) aria-label="{{ __('proposals.editor.down', ['n' => $i + 1]) }}" title="{{ __('proposals.editor.down', ['n' => $i + 1]) }}"><i data-lucide="arrow-down"></i></button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="removeSection({{ $i }})" wire:confirm="{{ __('proposals.editor.remove_confirm') }}" aria-label="{{ __('proposals.editor.remove', ['n' => $i + 1]) }}" title="{{ __('proposals.editor.remove', ['n' => $i + 1]) }}"><i data-lucide="trash-2"></i></button>
                            </span>
                        </div>
                        <div class="pp-form">
                            <div class="field">
                                <label for="sw-s-{{ $i }}-title" class="label">{{ __('proposals.editor.section_title') }}</label>
                                <input id="sw-s-{{ $i }}-title" type="text" class="input" maxlength="{{ \App\Support\Opportunities\Sows::MAX_TITLE }}" wire:model.blur="sections.{{ $i }}.title">
                            </div>
                            <div class="field">
                                <label for="sw-s-{{ $i }}-body" class="label">{{ __('proposals.editor.section_body') }}</label>
                                <textarea id="sw-s-{{ $i }}-body" class="textarea" rows="8" maxlength="{{ \App\Support\Opportunities\Sows::MAX_BODY }}" wire:model.blur="sections.{{ $i }}.body" aria-describedby="sw-body-help-{{ $i }}"></textarea>
                                <span class="help" id="sw-body-help-{{ $i }}">{{ __('proposals.editor.body_help') }}</span>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
            @if (count($sections) < \App\Support\Opportunities\Sows::MAX_SECTIONS)
                <div class="pp-buttons"><button type="button" class="btn btn-secondary" wire:click="addSection"><i data-lucide="plus"></i> {{ __('proposals.editor.add_section') }}</button></div>
            @endif

            <section class="card" aria-labelledby="sw-ms-title">
                <h2 class="card-title" id="sw-ms-title">{{ __('sow.milestones.title') }}</h2>
                <p class="pp-intro">{{ __('sow.milestones.intro') }}</p>
                <div class="pp-lines">
                    @foreach ($milestones as $i => $m)
                        <div class="pp-ms" wire:key="sm-{{ $i }}-{{ $m['key'] ?? $i }}">
                            <div class="field">
                                <label for="sw-m-{{ $i }}-title" class="label">{{ __('sow.milestones.code', ['n' => $i + 1]) }} · {{ __('sow.milestones.milestone') }}</label>
                                <input id="sw-m-{{ $i }}-title" type="text" class="input" maxlength="140" wire:model.blur="milestones.{{ $i }}.title">
                            </div>
                            <div class="field">
                                <label for="sw-m-{{ $i }}-weeks" class="label">{{ __('sow.milestones.weeks') }}</label>
                                <input id="sw-m-{{ $i }}-weeks" type="number" inputmode="numeric" min="1" max="104" step="1" class="input" wire:model.blur="milestones.{{ $i }}.weeks">
                            </div>
                            <div class="field">
                                <label for="sw-m-{{ $i }}-weight" class="label">{{ __('sow.milestones.weight_input') }}</label>
                                <input id="sw-m-{{ $i }}-weight" type="number" inputmode="decimal" min="0" max="100" step="0.01" class="input" wire:model.blur="milestones.{{ $i }}.weight">
                            </div>
                            <div class="pp-amount" aria-label="{{ __('sow.milestones.amount') }}">{{ $money($amounts[$i] ?? 0, $currency) }}</div>
                            <div class="pp-tools">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="moveMilestone({{ $i }}, -1)" @disabled($i === 0) aria-label="{{ __('sow.milestones.up', ['n' => $i + 1]) }}" title="{{ __('sow.milestones.up', ['n' => $i + 1]) }}"><i data-lucide="arrow-up"></i></button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="moveMilestone({{ $i }}, 1)" @disabled($i === count($milestones) - 1) aria-label="{{ __('sow.milestones.down', ['n' => $i + 1]) }}" title="{{ __('sow.milestones.down', ['n' => $i + 1]) }}"><i data-lucide="arrow-down"></i></button>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="removeMilestone({{ $i }})" wire:confirm="{{ __('sow.milestones.remove_confirm') }}" aria-label="{{ __('sow.milestones.remove', ['n' => $i + 1]) }}" title="{{ __('sow.milestones.remove', ['n' => $i + 1]) }}"><i data-lucide="trash-2"></i></button>
                            </div>
                            <div class="field pp-wide">
                                <label for="sw-m-{{ $i }}-desc" class="label">{{ __('sow.milestones.deliverables') }}</label>
                                <textarea id="sw-m-{{ $i }}-desc" class="textarea" rows="3" maxlength="{{ \App\Support\Opportunities\Sows::MAX_NOTE }}" wire:model.blur="milestones.{{ $i }}.description"></textarea>
                            </div>
                        </div>
                    @endforeach
                    @if (! $milestones)
                        <p class="help" style="margin: 0">{{ __('sow.milestones.none') }}</p>
                    @endif
                </div>
                @include('livewire.hq.opportunities.partials.sow-weight-sum')
                @if (count($milestones) < \App\Support\Opportunities\Sows::MAX_MILESTONES)
                    <div class="pp-buttons" style="margin-top: 12px"><button type="button" class="btn btn-secondary" wire:click="addMilestone"><i data-lucide="plus"></i> {{ __('sow.milestones.add') }}</button></div>
                @endif
            </section>

            @include('livewire.hq.opportunities.partials.sow-fees')

            {{-- Approve: the owner or the lead signs it for JBS Praxis --}}
            <section class="card" aria-labelledby="sw-approve-title">
                <h2 class="card-title" id="sw-approve-title">{{ __('sow.approve.title') }}</h2>
                @if ($canApprove)
                    <p class="pp-intro">{{ __('sow.approve.intro', ['party' => $partyName]) }}</p>
                    @unless ($legalName)
                        <div class="alert alert-warning" role="note" style="margin-bottom: 16px"><i data-lucide="triangle-alert"></i><span>{{ __('sow.legal_name.missing') }}</span></div>
                    @endunless
                    <form wire:submit="approve" class="pp-form" novalidate>
                        @error('approve')
                            <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div>
                        @enderror
                        <div class="field" style="max-width: 360px">
                            <label for="sw-signer" class="label">{{ __('sow.approve.name') }}</label>
                            <input id="sw-signer" type="text" class="input" maxlength="150" autocomplete="name" wire:model="signerName" @error('signerName') aria-invalid="true" @enderror>
                            @error('signerName') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div style="max-width: 480px">
                            <x-signature-pad model="signature" id="sw-hq-signature" :label="__('sow.approve.signature')" :help="__('sow.approve.signature_help')" :clear="__('sow.signature.clear')" :error="$errors->first('signature')" />
                        </div>
                        <div class="pp-buttons">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="approve"><i data-lucide="pen-line"></i> {{ __('sow.approve.button') }}</button>
                            @if ($versions->count() > 1 || $sow->version > 1)
                                <button type="button" class="btn btn-ghost" wire:click="discard" wire:confirm="{{ __('sow.versions.discard_confirm', ['n' => $sow->version]) }}">{{ __('sow.versions.discard') }}</button>
                            @endif
                        </div>
                    </form>
                @else
                    <p class="pp-sub">{{ __('sow.approve.who', ['name' => $person($o->lead)]) }}</p>
                    @if ($versions->count() > 1 || $sow->version > 1)
                        <div class="pp-buttons" style="margin-top: 12px"><button type="button" class="btn btn-ghost" wire:click="discard" wire:confirm="{{ __('sow.versions.discard_confirm', ['n' => $sow->version]) }}">{{ __('sow.versions.discard') }}</button></div>
                    @endif
                @endif
            </section>

        @elseif (! $drafting)
            {{-- As the client reads it --}}
            <section class="card" aria-labelledby="sw-read-title">
                <h2 class="card-title" id="sw-read-title" style="overflow-wrap: anywhere">{{ $sow->title }}</h2>
                @if ($sow->version > 1 && filled($sow->changes_summary))
                    <div class="pp-text" style="margin-top: 12px">
                        <strong>{{ __('sow.changes.label', ['n' => $sow->version - 1]) }}</strong>
                        @include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $sow->changes_summary])
                    </div>
                @endif
            </section>
            <ol class="pp-sections" aria-label="{{ __('sow.editor.sections') }}">
                @foreach ((array) $sow->sections as $section)
                    <li class="card" wire:key="sr-{{ $section['key'] }}">
                        <h3 class="pp-h">{{ $section['title'] }}</h3>
                        <div class="pp-text">@include('livewire.hq.opportunities.partials.proposal-blocks', ['text' => $section['body']])</div>
                    </li>
                @endforeach
            </ol>
            <section class="card" aria-labelledby="sw-ms-read-title">
                <h2 class="card-title" id="sw-ms-read-title">{{ __('sow.milestones.title') }}</h2>
                @include('livewire.hq.opportunities.partials.sow-milestones-table', ['list' => $milestoneList])
                @include('livewire.hq.opportunities.partials.sow-weight-sum', ['milestones' => $milestoneList])
            </section>
            @include('livewire.hq.opportunities.partials.sow-fees')

            @if ($sow->hq_signed_at || $sow->signed_at)
                <section class="card" aria-labelledby="sw-sigs-title">
                    <h2 class="card-title" id="sw-sigs-title" style="margin-bottom: 12px">{{ __('sow.signatures.title') }}</h2>
                    <div class="pp-sigs">
                        <div class="pp-sig">
                            <span class="help">{{ __('sow.signatures.for', ['party' => $partyName]) }}</span>
                            @if ($sow->hq_signed_at)
                                <strong>{{ $sow->hq_signer_name }}</strong>
                                {{ __('sow.signatures.on', ['date' => $day($sow->hq_signed_at)]) }}
                                <span class="pp-hash">{{ $sow->hq_signature_hash }}</span>
                            @endif
                        </div>
                        <div class="pp-sig">
                            <span class="help">{{ __('sow.signatures.for', ['party' => $o->organization?->name]) }}</span>
                            @if ($sow->signed_at)
                                <strong>{{ $sow->signed_by_name }}</strong>
                                {{ $sow->signed_by_title }} · {{ __('sow.signatures.on', ['date' => $day($sow->signed_at)]) }}
                                <span class="pp-hash">{{ $sow->client_signature_hash }}</span>
                            @else
                                <span>{{ __('sow.signatures.waiting') }}</span>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            @if ($sow->status === 'draft' && ! $sow->approved_at && ! $canEdit)
                <p class="help" style="margin: 0">{{ $o->isClosed() ? __('sow.closed') : __('sow.read_only') }}</p>
            @endif
        @endif
    @endif
</div>
