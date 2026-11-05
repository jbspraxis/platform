@php
    use App\Support\CompanyProfile\Layout;

    $pov = $page['pov'] ?? [];
    $principles = array_values($page['principles'] ?? []);
    $count = max(count($principles), 1);
    $principleWidth = Layout::even($count);
    // The principles are placed from the foot of the page rather than after the argument, so the page
    // keeps its shape whichever language it is in and however long the argument runs.
    $principlesTop = 396.0;
@endphp
{{-- Point of view: the statement large on the left with nothing around it, the argument at reading size
     on the right. The principles close the page, because they are what the firm believes rather than
     what it does. --}}
<div class="pad">
    <div class="cols">
        <div class="col" style="width: {{ Layout::columnWidth(7) }}pt;">
            <p class="display-sm">{{ $pov['title'] ?? '' }}</p>
            @include('pdf.company-profile.rule', [
                'left' => 128,
                'right' => 86,
                'style' => 'margin-top: 28pt;',
            ])
        </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(5) }}pt;">
            @foreach ($pov['paragraphs'] ?? [] as $paragraph)
                <p class="body" style="margin: 0 0 13pt 0;">{{ $paragraph }}</p>
            @endforeach
        </div>
    </div>
</div>

@if ($principles !== [])
    <div style="position: absolute; left: {{ Layout::MARGIN_X }}pt; top: {{ $principlesTop }}pt; width: {{ Layout::CONTENT_WIDTH }}pt;">
        <div class="hair"></div>
        <div style="height: 20pt; font-size: 0; line-height: 0;"></div>

        <div class="cols">
            @foreach ($principles as $principle)
                <div class="col" style="width: {{ $principleWidth }}pt;">
                    <p class="h3">{{ $principle['title'] ?? '' }}</p>
                    <p class="small" style="margin-top: 9pt;">{{ $principle['body'] ?? '' }}</p>
                </div>@if (! $loop->last)<div class="gap"></div>@endif
            @endforeach
        </div>
    </div>
@endif
