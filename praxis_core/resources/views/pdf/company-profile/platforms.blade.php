@php
    use App\Support\CompanyProfile\Layout;

    $items = array_values($page['items'] ?? []);
    $perRow = count($items) > 4 ? 3 : 2;
    $rows = (int) ceil(count($items) / $perRow);

    // The screens are 2:1 as they were captured, so nothing here crops or squashes one; they are set as
    // large as the page can hold them and no larger. The head and the caption under each screen are
    // allowed for, and the French title runs to a second line, which is why the allowance is generous.
    $head = ($page['first'] ?? true) ? 118.0 : 48.0;
    $rowGap = 24.0;
    $caption = 21.0;
    $slack = 26.0;

    $cell = (Layout::contentHeight() - $head - $slack - (($rows - 1) * $rowGap)) / max($rows, 1);
    $height = floor(min($cell - $caption, Layout::even($perRow) / 2) * 100) / 100;
    $width = floor($height * 2 * 100) / 100;

    // The screens are as large as the page allows, so a row of them is usually narrower than the
    // measure. It is centred on the measure rather than left to stop short of the right margin.
    $gutter = $perRow > 1 ? 2 * Layout::GUTTER : 0.0;
    $inset = max(0.0, round((Layout::CONTENT_WIDTH - ($perRow * $width) - (($perRow - 1) * $gutter)) / 2, 2));
@endphp
{{--
    The platforms: each client's own system, as that client's team signs in to it, every one in the
    client's own brand. A logo wall says who hired us; this says what they got.

    The screens are the website's own (praxis_site/public/img/screens), already public, and they are
    sign-in screens, so none of them carries a client's records. They are framed the way the brand asks:
    the screen itself on the page, with its name beneath it, and no device mock-up around it.
--}}
<div class="pad">
    @if ($page['first'] ?? true)
        <div class="cols">
            <div class="col" style="width: {{ Layout::columnWidth(7) }}pt;">
                <p class="h1">{{ $words['platforms'] }}</p>
            </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(5) }}pt;">
                <p class="lead">{{ $words['platforms_lead'] }}</p>
            </div>
        </div>

        @include('pdf.company-profile.rule', [
            'left' => Layout::columnWidth(4) + Layout::GUTTER,
            'right' => Layout::CONTENT_WIDTH - Layout::columnWidth(4) - Layout::GUTTER,
            'style' => 'margin-top: 28pt;',
        ])
    @else
        <p class="meta">{{ $words['platforms'] }} &nbsp;·&nbsp; {{ $words['continued'] }}</p>
    @endif

    <div style="height: 26pt; font-size: 0; line-height: 0;"></div>

    @foreach (array_chunk($items, $perRow) as $row)
        <div class="cols" style="margin-top: {{ $loop->first ? 0 : $rowGap }}pt; padding-left: {{ $inset }}pt;">
            @foreach ($row as $item)
                <div class="col" style="width: {{ $width }}pt;">
                    <img src="{{ $item['screen'] }}" style="width: {{ $width }}pt; height: {{ $height }}pt;">
                    <p class="meta" style="margin-top: 9pt;">
                        <span class="label" style="font-size: 7.4pt;">{{ $item['client'] ?? '' }}</span>
                        &nbsp;·&nbsp; {{ $item['sector'] ?? '' }}
                    </p>
                </div>@if (! $loop->last)<div class="gap" style="width: {{ $gutter }}pt;"></div>@endif
            @endforeach
        </div>
    @endforeach
</div>
