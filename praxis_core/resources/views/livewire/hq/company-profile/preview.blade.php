<div class="cpp">
    <style>
        /* HQ › Company profile › Preview (docs/COMPANY_PROFILE.md section 6). Plain on purpose: this proves the
           content, not the design. PR 2's renderer is what the reader sees. */
        .cpp-doc { max-width: 54rem; }
        .cpp-sec { border-top: 1px solid var(--color-line); padding-top: 18px; margin-top: 18px; }
        .cpp-sec:first-of-type { border-top: 0; padding-top: 0; margin-top: 0; }
        .cpp-kicker { font-size: 0.75rem; line-height: 1.25rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--color-muted); margin: 0 0 4px; }
        .cpp-h { font-size: 1.25rem; line-height: 1.75rem; font-weight: 600; color: var(--color-ink); margin: 0 0 6px; }
        .cpp-lead { font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-slate); margin: 0 0 10px; }
        .cpp-p { font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-ink); margin: 0 0 8px; }
        .cpp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px 20px; }
        .cpp-box { border: 1px solid var(--color-line); border-radius: 10px; padding: 12px; }
        .cpp-box h4 { font-size: 0.9375rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); margin: 0 0 4px; }
        .cpp-box p { font-size: 0.875rem; line-height: 1.375rem; color: var(--color-slate); margin: 0; }
        .cpp-tags { display: flex; flex-wrap: wrap; gap: 6px; margin: 6px 0 0; padding: 0; list-style: none; }
        .cpp-tags li { font-size: 0.75rem; line-height: 1.25rem; background: var(--color-mist); border-radius: 999px; padding: 1px 9px; color: var(--color-slate); }
        .cpp-dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px 20px; margin: 0; }
        .cpp-dl dt { font-size: 0.75rem; line-height: 1.25rem; color: var(--color-muted); }
        .cpp-dl dd { font-size: 0.9375rem; line-height: 1.5rem; color: var(--color-ink); margin: 0 0 6px; }
        .cpp-note { font-size: 0.8125rem; line-height: 1.25rem; color: var(--color-muted); }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $version->label() }} preview</h1>
            <p class="page-subtitle">{{ $version->reference }} · {{ $version->statusLabel() }}. Exactly what the renderers read: credentials that are not held and facts that are switched off are already gone.</p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:flex-end">
            <label class="field">
                <span class="field-label">Language</span>
                <select class="input" wire:model.live="locale">
                    <option value="en">English</option>
                    <option value="fr">French</option>
                </select>
            </label>
            <a class="btn btn-quiet" href="{{ route('company-profile.download', ['locale' => $locale, 'version' => $version->number]) }}"><i data-lucide="download"></i> Download</a>
            <a class="btn btn-quiet" href="{{ route('company-profile.deck', ['locale' => $locale, 'version' => $version->number]) }}"><i data-lucide="presentation"></i> Download deck</a>
            <a class="btn btn-quiet" href="{{ route('hq.company-profile.versions') }}" wire:navigate>Versions</a>
        </div>
    </div>

    @if ($changes)
        <div class="alert alert-info mb-6" role="status">
            {{ count($changes) }} {{ \Illuminate\Support\Str::plural('change', count($changes)) }} since the published version.
            <a href="{{ route('hq.company-profile.versions') }}" wire:navigate>See what changed</a>.
        </div>
    @endif

    <div class="card cpp-doc">
        <div class="cpp-sec">
            <p class="cpp-kicker">Cover</p>
            <h2 class="cpp-h">{{ $data['cover']['headline'] }}</h2>
            <p class="cpp-lead">{{ $data['cover']['descriptor'] }}</p>
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">Point of view</p>
            <h3 class="cpp-h">{{ $data['pov']['title'] }}</h3>
            @foreach ($data['pov']['paragraphs'] as $paragraph)<p class="cpp-p">{{ $paragraph }}</p>@endforeach
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['system']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['system']['title'] }}</h3>
            <p class="cpp-lead">{{ $data['system']['lead'] }}</p>
            <div class="cpp-grid">
                @foreach ($data['system']['pillars'] as $pillar)
                    <div class="cpp-box"><h4>{{ $pillar['title'] }}</h4><p>{{ $pillar['body'] }}</p></div>
                @endforeach
            </div>
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['problems']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['problems']['title'] }}</h3>
            <p class="cpp-lead">{{ $data['problems']['lead'] }}</p>
            @foreach ($data['problems']['groups'] as $group)
                <p class="cpp-kicker" style="margin-top:10px">{{ $group['domain'] }}</p>
                @foreach ($group['items'] as $item)
                    <p class="cpp-p"><strong>{{ $item['problem'] }}</strong><br>{{ $item['answer'] }}</p>
                @endforeach
            @endforeach
            <p class="cpp-lead">{{ $data['problems']['closing'] }}</p>
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['practices']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['practices']['title'] }}</h3>
            <p class="cpp-lead">{{ $data['practices']['lead'] }}</p>
            <div class="cpp-grid">
                @foreach ($data['practices']['items'] as $practice)
                    <div class="cpp-box">
                        <h4>{{ $practice['name'] }}</h4>
                        <p>{{ $practice['summary'] }}</p>
                        <ul class="cpp-tags">@foreach ($practice['capabilities'] as $capability)<li>{{ $capability }}</li>@endforeach</ul>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['method']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['method']['title'] }}</h3>
            <p class="cpp-lead">{{ $data['method']['lead'] }}</p>
            <div class="cpp-grid">
                @foreach ($data['method']['stages'] as $stage)
                    <div class="cpp-box"><h4>{{ $stage['name'] }}</h4><p>{{ $stage['body'] }}</p></div>
                @endforeach
            </div>
            @foreach ($data['method']['principles'] as $principle)
                <p class="cpp-p"><strong>{{ $principle['title'] }}</strong> — {{ $principle['body'] }}</p>
            @endforeach
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['teams']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['teams']['title'] }}</h3>
            <p class="cpp-lead">{{ $data['teams']['lead'] }}</p>
            <div class="cpp-grid">
                @foreach ($data['teams']['items'] as $team)
                    <div class="cpp-box">
                        <h4>{{ $team['name'] }}</h4>
                        <p>{{ $team['summary'] }}</p>
                        <ul class="cpp-tags">@foreach ($team['disciplines'] as $discipline)<li>{{ $discipline }}</li>@endforeach</ul>
                        <p style="margin-top:6px">{{ $team['experience'] }}</p>
                        @if ($team['credentials'])
                            <ul class="cpp-tags">
                                @foreach ($team['credentials'] as $credential)
                                    <li>{{ $credential['name'] }}@if ($credential['issuer']) · {{ $credential['issuer'] }}@endif</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="cpp-note" style="margin-top:6px">No credential is switched on for this team.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['work']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['work']['title'] }}</h3>
            <p class="cpp-lead">{{ $data['work']['lead'] }}</p>
            @foreach ($data['work']['items'] as $story)
                <div class="cpp-box" style="margin-bottom:10px">
                    <h4>{{ $story['title'] }}</h4>
                    <p class="cpp-note">{{ $story['client'] }} · {{ $story['sector'] }}</p>
                    <p>{{ $story['problem'] }}</p>
                    <p>{{ $story['built'] }}</p>
                    <p>{{ $story['outcome'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['clients']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['clients']['title'] }}</h3>
            @if ($data['clients']['logos'])
                <ul class="cpp-tags">@foreach ($data['clients']['logos'] as $logo)<li>{{ $logo['name'] }}</li>@endforeach</ul>
            @else
                <p class="cpp-note">The logo library is empty, so the profile carries no logo wall yet.</p>
            @endif
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['security']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['security']['title'] }}</h3>
            <div class="cpp-grid">
                @foreach ($data['security']['items'] as $point)
                    <div class="cpp-box"><h4>{{ $point['title'] }}</h4><p>{{ $point['body'] }}</p></div>
                @endforeach
            </div>
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['facts']['label'] }}</p>
            <h3 class="cpp-h">{{ $data['facts']['title'] }}</h3>
            <dl class="cpp-dl">
                @foreach ($data['facts']['items'] as $fact)
                    <div><dt>{{ $fact['label'] }}</dt><dd>{{ $fact['value'] }}</dd></div>
                @endforeach
            </dl>
        </div>

        <div class="cpp-sec">
            <p class="cpp-kicker">{{ $sections['contact']['label'] }}</p>
            <p class="cpp-lead">{{ $data['contact']['cta'] }}</p>
            <dl class="cpp-dl">
                <div><dt>Email</dt><dd>{{ $data['contact']['email'] }}</dd></div>
                <div><dt>Sales</dt><dd>{{ $data['contact']['sales_email'] }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $data['contact']['phone'] }}</dd></div>
                <div><dt>Website</dt><dd>{{ $data['contact']['website'] }}</dd></div>
                <div><dt>Offices</dt><dd>{{ implode(' · ', $data['contact']['offices']) }}</dd></div>
            </dl>
        </div>
    </div>
</div>
