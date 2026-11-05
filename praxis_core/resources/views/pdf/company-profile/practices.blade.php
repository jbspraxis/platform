@php
    use App\Support\CompanyProfile\Layout;

    $practices = array_values($page['practices']['items'] ?? []);
    $method = $page['method'] ?? [];
    $stages = array_values($method['stages'] ?? []);
    // The two halves share a page unless the copy is too long for one, in which case Layout gives each
    // of them a page and this template draws whichever half it was handed.
    $showPractices = $practices !== [];
    $showMethod = $stages !== [];
    $practiceWidth = Layout::even(count($practices));
    $stageWidth = Layout::even(count($stages));
    // The method is a real sequence, so the rule's split falls between designing and building: the point
    // where the thinking becomes the thing. That is what praxis means, and it is the one place in the
    // book where numbered steps are correct.
    $split = (int) ceil(count($stages) / 2);
@endphp
<div class="pad">
    @if ($showPractices)
    <div class="cols">
        <div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
            <p class="h1">{{ $page['practices']['title'] ?? '' }}</p>
        </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
            <p class="lead">{{ $page['practices']['lead'] ?? '' }}</p>
        </div>
    </div>

    <div style="height: 14pt; font-size: 0; line-height: 0;"></div>

    <div class="cols">
        @foreach ($practices as $practice)
            <div class="col" style="width: {{ $practiceWidth }}pt;">
                <div class="hair"></div>
                <p class="h3" style="margin-top: 13pt;">{{ $practice['name'] ?? '' }}</p>
                <p class="small" style="margin-top: 9pt;">{{ $practice['summary'] ?? '' }}</p>
                @if (($practice['capabilities'] ?? []) !== [])
                    <p class="meta" style="margin-top: 9pt; line-height: 10.5pt;">
                        @foreach ($practice['capabilities'] as $capability)
                            {{ $capability }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </p>
                @endif
            </div>@if (! $loop->last)<div class="gap"></div>@endif
        @endforeach
    </div>
    @endif

    @if ($showPractices && $showMethod)
        <div style="height: 18pt; font-size: 0; line-height: 0;"></div>
        <div class="hair"></div>
        <div style="height: 12pt; font-size: 0; line-height: 0;"></div>
    @endif

    @if ($showMethod)
    <div class="cols">
        <div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
            <p class="{{ $showPractices ? 'statement' : 'h1' }}">{{ $method['title'] ?? '' }}</p>
        </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(6) }}pt; padding-top: 6pt;">
            <p class="small">{{ $method['lead'] ?? '' }}</p>
        </div>
    </div>

    @include('pdf.company-profile.rule', [
        'left' => ($split * ($stageWidth + Layout::GUTTER)) - Layout::GUTTER,
        'right' => Layout::CONTENT_WIDTH - ($split * ($stageWidth + Layout::GUTTER)) + Layout::GUTTER,
        'style' => 'margin-top: 12pt;',
    ])

    <div class="cols" style="margin-top: 8pt;">
        @foreach ($stages as $stage)
            <div class="col" style="width: {{ $stageWidth }}pt;">
                <p class="meta">{{ $loop->iteration }}</p>
                <p class="h3" style="margin-top: 4pt;">{{ $stage['name'] ?? '' }}</p>
                <p class="small" style="margin-top: 8pt;">{{ $stage['body'] ?? '' }}</p>
            </div>@if (! $loop->last)<div class="gap"></div>@endif
        @endforeach
    </div>
    @endif
</div>
