@php
    use App\Support\CompanyProfile\Layout;

    $pillars = array_values($page['pillars'] ?? []);
    // Two to a row rather than four. Four columns of a landscape page give a 171pt measure, which is
    // 38 characters: too narrow to read and too ragged to look designed. Two give 62, which is right.
    $perRow = count($pillars) > 2 ? 2 : max(count($pillars), 1);
    $width = Layout::even($perRow);
@endphp
{{-- What we build: the pillars of the system, each under its own hairline. No cards, no icons. --}}
<div class="pad">
    <div class="cols">
        <div class="col" style="width: {{ Layout::columnWidth(7) }}pt;">
            <p class="h1">{{ $page['title'] ?? '' }}</p>
        </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(5) }}pt;">
            <p class="lead">{{ $page['lead'] ?? '' }}</p>
        </div>
    </div>

    @include('pdf.company-profile.rule', [
        'left' => Layout::columnWidth(4) + Layout::GUTTER,
        'right' => Layout::CONTENT_WIDTH - Layout::columnWidth(4) - Layout::GUTTER,
        'style' => 'margin-top: 34pt;',
    ])

    <div style="height: 46pt; font-size: 0; line-height: 0;"></div>

    @foreach (array_chunk($pillars, $perRow) as $row)
        <div class="cols" style="margin-top: {{ $loop->first ? 0 : 44 }}pt;">
            @foreach ($row as $pillar)
                <div class="col" style="width: {{ $width }}pt;">
                    <div class="hair"></div>
                    <p class="h3" style="margin-top: 14pt;">{{ $pillar['title'] ?? '' }}</p>
                    <p class="body" style="margin-top: 10pt;">{{ $pillar['body'] ?? '' }}</p>
                </div>@if (! $loop->last)<div class="gap"></div>@endif
            @endforeach
        </div>
    @endforeach
</div>
