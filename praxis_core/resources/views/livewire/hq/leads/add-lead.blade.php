@php
    $from = fn (string $key) => in_array($key, $fromReport, true);
    $pathLabels = ['intel' => 'an intel report', 'copq' => 'cost of poor quality discussed', 'blank' => 'a blank lead'];
@endphp
<div class="lf">
    <style>
        /* Add a lead: only what the shared component classes don't cover. Brand tokens only. */
        .lf { max-width: 880px; }
        .lf-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 0.875rem; text-decoration: none; }
        .lf-back svg { width: 16px; height: 16px; }
        .lf-form { display: flex; flex-direction: column; gap: 24px; }
        .lf-card { display: flex; flex-direction: column; gap: 16px; }
        .lf-row { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); align-items: start; }
        .lf-optional { font-weight: 400; color: var(--color-muted); }
        .lf-contact { display: flex; flex-direction: column; gap: 16px; padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); background: var(--color-mist); }
        .lf-contact-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
        .lf-contact-head h3 { margin: 0; font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .lf-radio { display: inline-flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--color-ink); }
        .lf-radios { display: flex; flex-wrap: wrap; gap: 16px; }
        .lf-matches { border: 1px solid var(--color-line); border-left: 3px solid var(--color-warning, #B7791F); border-radius: var(--radius-card); padding: 12px 16px; background: var(--color-paper); }
        .lf-matches h3 { margin: 0 0 4px; font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
        .lf-matches ul { list-style: none; margin: 8px 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
        .lf-matches li { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.875rem; }
        .lf-match-text { display: flex; flex-direction: column; min-width: 0; }
        .lf-match-name { font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
        .lf-more summary { cursor: pointer; font-weight: 600; color: var(--color-ink); list-style: none; display: flex; align-items: center; gap: 8px; }
        .lf-more summary::-webkit-details-marker { display: none; }
        .lf-more summary svg { width: 18px; height: 18px; transition: transform var(--motion-fade, 150ms) ease-out; }
        .lf-more[open] summary svg { transform: rotate(90deg); }
        .lf-more-body { display: flex; flex-direction: column; gap: 16px; margin-top: 16px; }
        .lf-actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .lf-start { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr)); }
        .lf-choice { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; padding: 20px; text-align: left; font: inherit; cursor: pointer; background: var(--color-paper, #fff); border: 1px solid var(--color-line); border-radius: var(--radius-card); transition: border-color var(--motion-fade, 150ms) ease-out; }
        .lf-choice:hover, .lf-choice:focus-visible { border-color: var(--color-deep-teal); }
        .lf-choice svg { width: 24px; height: 24px; color: var(--color-deep-teal); }
        .lf-choice-title { font-weight: 600; color: var(--color-ink); }
        .lf-choice-text { font-size: 0.875rem; color: var(--color-slate); }
        .lf-pathbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-bottom: 16px; font-size: 0.875rem; color: var(--color-slate); }
        .lf-fill { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; }
        .lf-from { margin-left: 6px; padding: 1px 6px; border-radius: 999px; background: var(--color-teal-tint); color: var(--color-deep-teal); font-size: 0.75rem; font-weight: 600; }
        @media (max-width: 600px) { .lf-actions .btn, .lf-fill .btn { flex: 1 1 100%; } }
        @media (prefers-reduced-motion: reduce) { .lf-more summary svg, .lf-choice { transition: none; } }
    </style>

    <a href="{{ route('hq.crm.leads') }}" class="lf-back" wire:navigate><i data-lucide="arrow-left"></i> All leads</a>

    <div class="page-header">
        <div>
            <h1 class="page-title">Add a lead</h1>
            <p class="page-subtitle">The organisation, who to talk to, and what happens next. The lead is yours once it is added.</p>
        </div>
    </div>

    @if ($path === '')
        {{-- What do you have? (docs/LEADS.md section 13): each card opens this same form with the sections it needs --}}
        <section aria-labelledby="lf-start-title">
            <h2 class="card-title" id="lf-start-title" style="margin-bottom: 12px">What do you have on this company?</h2>
            <div class="lf-start">
                @if ($canIntel)
                    <button type="button" class="lf-choice" wire:click="choose('intel')">
                        <i data-lucide="microscope"></i>
                        <span class="lf-choice-title">An intel report</span>
                        <span class="lf-choice-text">Upload or paste what you found. Claude can fill the form from it, then lays the report out for you and the AI.</span>
                    </button>
                @endif
                <button type="button" class="lf-choice" wire:click="choose('copq')">
                    <i data-lucide="calculator"></i>
                    <span class="lf-choice-title">Cost of poor quality discussed</span>
                    <span class="lf-choice-text">You have the figures from a call. The cost form opens straight away.</span>
                </button>
                <button type="button" class="lf-choice" wire:click="choose('blank')">
                    <i data-lucide="user-plus"></i>
                    <span class="lf-choice-title">A blank lead</span>
                    <span class="lf-choice-text">Just the basics: name, country, industry, and a phone or email. Add the rest later from the lead’s page.</span>
                </button>
            </div>
        </section>
    @else
    <div class="lf-pathbar">
        <span>Starting with <strong>{{ $pathLabels[$path] }}</strong>.</span>
        <button type="button" class="btn btn-ghost btn-sm" wire:click="changePath">Change</button>
    </div>
    <form wire:submit="save" class="lf-form" novalidate>
        @if ($path === 'intel')
            {{-- The intel report: Claude can fill the empty fields below from it, and reads it in full once the lead is added --}}
            <section class="card lf-card" aria-labelledby="lf-intel-title">
                <div>
                    <h2 class="card-title" id="lf-intel-title">Intel report</h2>
                    <p class="help" style="margin: 4px 0 0">Upload what you found or paste your notes. Claude can fill the empty fields below from it, and reads the whole report once the lead is added. If you give both a file and notes, the file is used.</p>
                </div>
                <div class="field">
                    <label class="label" for="lf-intel-file">Report file <span class="lf-optional">(PDF, Word, text or Markdown, up to {{ $maxMb }} MB)</span></label>
                    <input id="lf-intel-file" type="file" class="input" wire:model="intelFile" accept=".pdf,.docx,.txt,.md"
                           @error('intelFile') aria-invalid="true" aria-describedby="lf-intel-file-error" @enderror>
                    <span class="help" wire:loading wire:target="intelFile">Uploading…</span>
                    @if ($intelStored)
                        <span class="help" role="status">Using “{{ $intelStored['name'] }}”. Choose another file to replace it.</span>
                    @endif
                    @error('intelFile') <span class="field-error" id="lf-intel-file-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label" for="lf-intel-text">Or paste your notes</label>
                    <textarea id="lf-intel-text" class="textarea" rows="8" wire:model="intelText" maxlength="{{ \App\Support\Leads\LeadIntel::MAX_TEXT * 2 }}"
                              @error('intelText') aria-invalid="true" aria-describedby="lf-intel-text-error" @enderror></textarea>
                    @error('intelText') <span class="field-error" id="lf-intel-text-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label" for="lf-intel-title-input">Title <span class="lf-optional">(optional)</span></label>
                    <input id="lf-intel-title-input" type="text" class="input" wire:model="intelTitle" maxlength="200" placeholder="For example: Findings, first round">
                    @error('intelTitle') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="lf-fill">
                    <button type="button" class="btn btn-secondary" wire:click="fillFromReport" wire:loading.attr="disabled" wire:target="fillFromReport,intelFile">
                        <i data-lucide="sparkles"></i>
                        <span wire:loading.remove wire:target="fillFromReport">Fill the empty fields from the report</span>
                        <span wire:loading wire:target="fillFromReport">Reading the report…</span>
                    </button>
                    <span class="help">Takes a few seconds. It never changes a field you have already filled.</span>
                </div>
                @if ($fillNotice)
                    <div class="alert alert-success" role="status"><i data-lucide="circle-check"></i><span>{{ $fillNotice }}</span></div>
                @endif
                @if ($fillWarning)
                    <div class="alert alert-warning" role="alert"><i data-lucide="triangle-alert"></i><span>{{ $fillWarning }}</span></div>
                @endif
            </section>
        @endif

        {{-- Organisation --}}
        <section class="card lf-card" aria-labelledby="lf-org-title">
            <h2 class="card-title" id="lf-org-title">Organisation</h2>

            <div class="field">
                <label class="label" for="lf-name">Organisation name @if ($from('lead.name'))<span class="lf-from">from report</span>@endif</label>
                <input id="lf-name" type="text" class="input" wire:model.live.debounce.400ms="lead.name" maxlength="150" autocomplete="off"
                       @error('lead.name') aria-invalid="true" aria-describedby="lf-name-error" @enderror>
                @error('lead.name') <span class="field-error" id="lf-name-error">{{ $message }}</span> @enderror
            </div>

            @if ($matches->isNotEmpty())
                <div class="lf-matches" role="status" aria-live="polite">
                    <h3>{{ $matches->count() === 1 ? 'This may already be in HQ' : 'These may already be in HQ' }}</h3>
                    <p class="help" style="margin: 0">Open it to add to what is there, or carry on to add a new lead.</p>
                    <ul>
                        @foreach ($matches as $m)
                            <li wire:key="match-{{ $m['id'] }}">
                                <span class="lf-match-text">
                                    <span class="lf-match-name">{{ $m['name'] }}</span>
                                    <span class="help">
                                        {{ $m['status'] === 'Lead' ? 'Lead' : ($m['status'] === 'Active Client' ? 'Client' : $m['status']) }}@if ($m['owner']) · {{ $m['owner'] }}@endif
                                        · Same {{ collect($m['matched'])->implode(', ') }}
                                    </span>
                                </span>
                                @if (in_array($m['status'], \App\Support\Leads\LeadAccess::STATUSES, true))
                                    <a href="{{ route('hq.crm.lead', ['organization' => $m['id']]) }}" class="btn btn-secondary btn-sm" wire:navigate>Open it</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="lf-row">
                <div class="field">
                    <label class="label" for="lf-country">Country @if ($from('lead.country'))<span class="lf-from">from report</span>@endif</label>
                    <select id="lf-country" class="select" wire:model.live="lead.country" @error('lead.country') aria-invalid="true" @enderror>
                        @foreach ($countries as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                        <option value="{{ \App\Livewire\Hq\Leads\AddLead::OTHER_COUNTRY }}">Another country</option>
                    </select>
                    @error('lead.country') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                @if ($lead['country'] === \App\Livewire\Hq\Leads\AddLead::OTHER_COUNTRY)
                    <div class="field">
                        <label class="label" for="lf-other-country">Country name</label>
                        <input id="lf-other-country" type="text" class="input" wire:model.live.blur="otherCountry" maxlength="100"
                               @error('otherCountry') aria-invalid="true" aria-describedby="lf-other-country-error" @enderror>
                        @error('otherCountry') <span class="field-error" id="lf-other-country-error">{{ $message }}</span> @enderror
                    </div>
                @endif
                <div class="field">
                    <label class="label" for="lf-city">City <span class="lf-optional">(optional)</span> @if ($from('lead.city'))<span class="lf-from">from report</span>@endif</label>
                    <input id="lf-city" type="text" class="input" wire:model.live.blur="lead.city" maxlength="100" autocomplete="off">
                    @error('lead.city') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label" for="lf-industry">Industry @if ($from('lead.industry'))<span class="lf-from">from report</span>@endif</label>
                    <select id="lf-industry" class="select" wire:model.live.blur="lead.industry" @error('lead.industry') aria-invalid="true" aria-describedby="lf-industry-error" @enderror>
                        <option value="">Choose an industry</option>
                        @foreach ($industries as $ind)
                            <option value="{{ $ind }}">{{ $ind }}</option>
                        @endforeach
                    </select>
                    @error('lead.industry') <span class="field-error" id="lf-industry-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="lf-row">
                <div class="field">
                    <label class="label" for="lf-phone">Phone <span class="lf-optional">(optional)</span> @if ($from('lead.phone'))<span class="lf-from">from report</span>@endif</label>
                    <input id="lf-phone" type="tel" class="input" wire:model.live.debounce.600ms="lead.phone" maxlength="30" placeholder="e.g. +234 800 000 0000" autocomplete="off"
                           @error('lead.phone') aria-invalid="true" @enderror>
                    @error('lead.phone') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label" for="lf-email">Email <span class="lf-optional">(optional)</span> @if ($from('lead.email'))<span class="lf-from">from report</span>@endif</label>
                    <input id="lf-email" type="email" class="input" wire:model.live.debounce.600ms="lead.email" maxlength="150" placeholder="e.g. info@company.com" autocomplete="off"
                           @error('lead.email') aria-invalid="true" @enderror>
                    @error('lead.email') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="field">
                <label class="label" for="lf-address">Address <span class="lf-optional">(optional)</span></label>
                <textarea id="lf-address" class="textarea" rows="2" wire:model.live.blur="lead.address" maxlength="2000"></textarea>
                @error('lead.address') <span class="field-error">{{ $message }}</span> @enderror
            </div>
            <p class="help" style="margin: 0">Add at least one phone number or email address, here or for a contact.</p>
            @error('lead.reach') <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><span>{{ $message }}</span></div> @enderror
        </section>

        {{-- Contacts --}}
        <section class="card lf-card" aria-labelledby="lf-contacts-title">
            <div>
                <h2 class="card-title" id="lf-contacts-title">Contacts <span class="lf-optional">(optional)</span></h2>
                <p class="help" style="margin: 4px 0 0">The people you talk to there. The main contact is who you reach first.</p>
            </div>

            @foreach ($contacts as $i => $c)
                <div class="lf-contact" wire:key="contact-{{ $i }}" role="group" aria-labelledby="lf-contact-{{ $i }}-title">
                    <div class="lf-contact-head">
                        <h3 id="lf-contact-{{ $i }}-title">{{ count($contacts) > 1 ? 'Contact ' . ($i + 1) : 'Contact' }}</h3>
                        <div class="flex flex-wrap items-center gap-3">
                            @if (count($contacts) > 1)
                                <label class="lf-radio"><input type="radio" name="lf-main" @checked(! empty($c['main'])) wire:click="makeMain({{ $i }})"> Main contact</label>
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="removeContact({{ $i }})" aria-label="Remove contact {{ $i + 1 }}"><i data-lucide="trash-2"></i> Remove</button>
                            @endif
                        </div>
                    </div>
                    <div class="lf-row">
                        <div class="field">
                            <label class="label" for="lf-c{{ $i }}-name">Name @if ($from("contacts.$i.name"))<span class="lf-from">from report</span>@endif</label>
                            <input id="lf-c{{ $i }}-name" type="text" class="input" wire:model.live.blur="contacts.{{ $i }}.name" maxlength="100" autocomplete="off"
                                   @error("contacts.$i.name") aria-invalid="true" @enderror>
                            @error("contacts.$i.name") <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="lf-c{{ $i }}-title">Job title <span class="lf-optional">(optional)</span> @if ($from("contacts.$i.job_title"))<span class="lf-from">from report</span>@endif</label>
                            <input id="lf-c{{ $i }}-title" type="text" class="input" wire:model.live.blur="contacts.{{ $i }}.job_title" maxlength="100" autocomplete="off">
                            @error("contacts.$i.job_title") <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="lf-row">
                        <div class="field">
                            <label class="label" for="lf-c{{ $i }}-email">Email <span class="lf-optional">(optional)</span> @if ($from("contacts.$i.email"))<span class="lf-from">from report</span>@endif</label>
                            <input id="lf-c{{ $i }}-email" type="email" class="input" wire:model.live.debounce.600ms="contacts.{{ $i }}.email" maxlength="100" placeholder="e.g. name@company.com" autocomplete="off"
                                   @error("contacts.$i.email") aria-invalid="true" @enderror>
                            @error("contacts.$i.email") <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="lf-c{{ $i }}-phone">Phone <span class="lf-optional">(optional)</span> @if ($from("contacts.$i.phone"))<span class="lf-from">from report</span>@endif</label>
                            <input id="lf-c{{ $i }}-phone" type="tel" class="input" wire:model.live.debounce.600ms="contacts.{{ $i }}.phone" maxlength="30" placeholder="e.g. +234 800 000 0000" autocomplete="off"
                                   @error("contacts.$i.phone") aria-invalid="true" @enderror>
                            @error("contacts.$i.phone") <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label class="label" for="lf-c{{ $i }}-role">Role in the deal <span class="lf-optional">(optional)</span> @if ($from("contacts.$i.deal_role"))<span class="lf-from">from report</span>@endif</label>
                            <select id="lf-c{{ $i }}-role" class="select" wire:model="contacts.{{ $i }}.deal_role">
                                <option value="">Not known yet</option>
                                @foreach ($dealRoles as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("contacts.$i.deal_role") <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            @endforeach

            @if (count($contacts) < \App\Livewire\Hq\Leads\AddLead::MAX_CONTACTS)
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addContact"><i data-lucide="user-plus"></i> Add another contact</button>
                </div>
            @endif
        </section>

        {{-- Source and next step --}}
        <section class="card lf-card" aria-labelledby="lf-next-title">
            <h2 class="card-title" id="lf-next-title">Where it came from, and what happens next</h2>
            <div class="lf-row">
                <div class="field">
                    <label class="label" for="lf-source">Source</label>
                    <select id="lf-source" class="select" wire:model="lead.source">
                        @foreach ($sources as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('lead.source') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label" for="lf-source-detail">Details <span class="lf-optional">(optional)</span></label>
                    <input id="lf-source-detail" type="text" class="input" wire:model.live.blur="lead.source_detail" maxlength="255" placeholder="e.g. who referred them, or which event">
                    @error('lead.source_detail') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="lf-row">
                <div class="field">
                    <label class="label" for="lf-next">Next step <span class="lf-optional">(optional)</span></label>
                    <input id="lf-next" type="text" class="input" wire:model.live.blur="lead.next_step" maxlength="255" placeholder="e.g. Call to book a free audit"
                           @error('lead.next_step') aria-invalid="true" @enderror>
                    @error('lead.next_step') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="label" for="lf-next-due">By when</label>
                    <input id="lf-next-due" type="date" class="input" wire:model.live.blur="lead.next_step_due"
                           @error('lead.next_step_due') aria-invalid="true" aria-describedby="lf-next-due-error" @enderror>
                    @error('lead.next_step_due') <span class="field-error" id="lf-next-due-error">{{ $message }}</span> @enderror
                </div>
            </div>
            <p class="help" style="margin: 0">Every open lead has a next step. It shows on the lead and, soon, in your follow-ups.</p>
        </section>

        {{-- More details: closed at first, open when cost of poor quality was discussed, left out of a blank lead --}}
        @if ($path !== 'blank')
        <section class="card">
            <details class="lf-more" @if ($moreOpen) open @endif x-on:toggle="$wire.set('moreOpen', $el.open)">
                <summary><i data-lucide="chevron-right"></i> More details <span class="lf-optional">(optional)</span></summary>
                <div class="lf-more-body">
                    <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                        <legend class="label" style="margin-bottom: 6px">Language for emails</legend>
                        <div class="lf-radios">
                            <label class="lf-radio"><input type="radio" value="EN" wire:model.live="lead.preferred_language"> English</label>
                            <label class="lf-radio"><input type="radio" value="FR" wire:model.live="lead.preferred_language"> French</label>
                        </div>
                        <span class="help">Set from the country; change it if they prefer the other language.</span>
                    </fieldset>

                    <div>
                        <h3 class="card-title" style="font-size: 1rem; margin: 0">Cost of poor quality</h3>
                        <p class="help" style="margin: 4px 0 0">Fill in all the figures to calculate it now, or leave them empty and calculate it later from the lead’s page. Amounts are in the currency you choose.</p>
                    </div>
                    @if ($moreOpen)
                        <div class="lf-row">
                            <div class="field">
                                <label class="label" for="lf-tool">Systems in use</label>
                                <select id="lf-tool" class="select" wire:model="copq.tool">
                                    <option value="">Choose the main system</option>
                                    @foreach ($choices['systems'] as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('copq.tool') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lf-bottleneck">Main bottleneck</label>
                                <select id="lf-bottleneck" class="select" wire:model="copq.bottleneck">
                                    <option value="">Choose a bottleneck</option>
                                    @foreach ($choices['bottlenecks'] as $b)
                                        <option value="{{ $b }}">{{ $b }}</option>
                                    @endforeach
                                </select>
                                @error('copq.bottleneck') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="field">
                                <label class="label" for="lf-currency">Currency</label>
                                <select id="lf-currency" class="select" wire:model="copq.currency">
                                    @foreach ($choices['currencies'] as $code => $label)
                                        <option value="{{ $code }}">{{ $code }}: {{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('copq.currency') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="lf-row">
                            @foreach ($figures as $key => $label)
                                <div class="field" wire:key="fig-{{ $key }}">
                                    <label class="label" for="lf-fig-{{ $key }}">{{ $label }}</label>
                                    <input id="lf-fig-{{ $key }}" type="number" min="0" step="any" inputmode="decimal" class="input" wire:model.live.blur="copq.{{ $key }}"
                                           @error("copq.$key") aria-invalid="true" @enderror>
                                    @error("copq.$key") <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </details>
        </section>
        @endif

        <div class="lf-actions">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,intelFile">
                <i data-lucide="plus"></i>
                <span wire:loading.remove wire:target="save">Add lead</span>
                <span wire:loading wire:target="save">Adding…</span>
            </button>
            <a href="{{ route('hq.crm.leads') }}" class="btn btn-secondary" wire:navigate>Cancel</a>
        </div>
    </form>
    @endif
</div>
