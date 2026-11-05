@php
    use App\Support\CompanyProfile\Layout;

    $teams = array_values($page['items'] ?? []);
    $nameWidth = Layout::columnWidth(Layout::PROBLEM_COLUMNS);
    $detailWidth = Layout::answerWidth();
@endphp
{{--
    The teams, by discipline. People are never named here and never will be: the contract and the brand
    both say a team is described by what it does and what it holds. A credential whose switch is off has
    already been dropped by Layout::teams(), so nothing on this page can leak one.
--}}
<div class="pad">
    @if ($page['first'] ?? true)
        <div class="cols">
            <div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
                <p class="h1">{{ $page['title'] ?? '' }}</p>
            </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
                <p class="lead">{{ $page['lead'] ?? '' }}</p>
            </div>
        </div>

        @include('pdf.company-profile.rule', [
            'left' => $nameWidth + Layout::GUTTER,
            'right' => $detailWidth,
            'style' => 'margin-top: 22pt;',
        ])
    @else
        <p class="meta">{{ $page['title'] ?? '' }} &nbsp;·&nbsp; {{ $words['continued'] }}</p>
    @endif

    @foreach ($teams as $team)
        @unless ($loop->first)
            <div style="height: 10pt; font-size: 0; line-height: 0;"></div>
            <div class="hair"></div>
            <div style="height: 11pt; font-size: 0; line-height: 0;"></div>
        @else
            <div style="height: 18pt; font-size: 0; line-height: 0;"></div>
        @endunless
        <div class="cols">
            <div class="col" style="width: {{ $nameWidth }}pt;">
                <p class="h3">{{ $team['name'] ?? '' }}</p>
                @if (($team['experience'] ?? '') !== '')
                    <p class="meta" style="margin-top: 7pt;">{{ $words['experience'] }}: {{ $team['experience'] }}</p>
                @endif
            </div><div class="gap"></div><div class="col" style="width: {{ $detailWidth }}pt;">
                <p class="small">{{ $team['summary'] ?? '' }}</p>

                @if (($team['disciplines'] ?? []) !== [])
                    <p class="meta" style="margin-top: 7pt;">{{ implode('  ·  ', $team['disciplines']) }}</p>
                @endif

                @if (($team['credentials'] ?? []) !== [])
                    <p class="meta" style="margin-top: 5pt;">
                        <span class="label" style="font-size: 7.4pt;">{{ $words['credentials'] }}</span>
                        &nbsp;{{ implode('  ·  ', array_map(
                            fn ($credential) => trim(($credential['name'] ?? '') . (($credential['issuer'] ?? '') !== '' ? ' (' . $credential['issuer'] . ')' : '')),
                            $team['credentials']
                        )) }}
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>
