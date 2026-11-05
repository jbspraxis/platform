@php
    use App\Support\CompanyProfile\Assets;
    use App\Support\CompanyProfile\Layout;

    $rows = $page['rows'] ?? [];
    $platforms = array_values($page['platforms'] ?? []);
    $perRow = 5;
    $cell = Layout::even($perRow);
    $rowHeight = 72.0;

    // The strip of platforms across the foot. The screens are 2:1 as they were captured, so nothing
    // here crops or squashes one; each is a little under a full column, and what the strip does not
    // use goes into the gutters, so it still spans the measure.
    $count = max(count($platforms), 1);
    $stripWidth = floor(Layout::even($count) * 0.82 * 100) / 100;
    $stripHeight = round($stripWidth / 2, 2);
    $stripGutter = $count > 1
        ? round((Layout::CONTENT_WIDTH - ($count * $stripWidth)) / ($count - 1), 2)
        : 0.0;
@endphp
{{--
    The logo wall, from the library HQ manages: the artwork as the owner approved it, which is the
    library's to decide and not this page's to recolour. Each mark is sized by its area rather than its
    height, so a wide wordmark does not shout over a square mark. Rows are separated by hairlines, never
    by a box, and each mark is centred in its cell by the margin its own proportions ask for, because an
    inline block cannot centre its contents vertically by itself.

    The platforms close the page, and the book's proof: each client's own system, as that client's team
    signs in to it, every one in the client's own brand. A logo wall says who hired us; this says what
    they got. The screens are the website's own, already public, and they are sign-in screens, so none
    of them carries a client's records.
--}}
<div class="pad">
    @if ($page['first'] ?? true)
        <div class="cols">
            <div class="col" style="width: {{ Layout::columnWidth(7) }}pt;">
                <p class="h1">{{ $page['title'] ?? '' }}</p>
            </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(5) }}pt;">
                @if (($page['note'] ?? '') !== '')
                    <p class="lead">{{ $page['note'] }}</p>
                @endif
            </div>
        </div>
        @include('pdf.company-profile.rule', [
            'left' => Layout::columnWidth(3) + Layout::GUTTER,
            'right' => Layout::CONTENT_WIDTH - Layout::columnWidth(3) - Layout::GUTTER,
            'style' => 'margin-top: 28pt;',
        ])
    @endif

    <div style="height: 26pt; font-size: 0; line-height: 0;"></div>

    @foreach ($rows as $row)
        @unless ($loop->first)
            <div class="hair"></div>
        @endunless
        <div class="cols">
            @for ($index = 0; $index < $perRow; $index++)
                @php
                    $logo = $row[$index] ?? null;
                    $box = $logo ? Assets::logoBox($logo['path']) : null;
                @endphp
                <div class="col" style="width: {{ $cell }}pt; height: {{ $rowHeight }}pt; text-align: center;">
                    @if ($logo && $box)
                        <img src="{{ $logo['path'] }}"
                             style="width: {{ $box['width'] }}pt; height: {{ $box['height'] }}pt; margin-top: {{ round(($rowHeight - $box['height']) / 2, 2) }}pt;">
                    @endif
                </div>@if ($index < $perRow - 1)<div class="gap"></div>@endif
            @endfor
        </div>
    @endforeach

    @if ($platforms !== [])
        <div style="height: 22pt; font-size: 0; line-height: 0;"></div>
        <div class="hair"></div>
        <p class="label" style="margin-top: 11pt;">{{ $words['platforms'] }}</p>

        <div class="cols" style="margin-top: 10pt;">
            @foreach ($platforms as $platform)
                <div class="col" style="width: {{ $stripWidth }}pt;">
                    <img src="{{ $platform['screen'] }}" style="width: {{ $stripWidth }}pt; height: {{ $stripHeight }}pt;">
                    <p class="meta" style="margin-top: 7pt;">{{ $platform['client'] ?? '' }}</p>
                </div>@if (! $loop->last)<div class="gap" style="width: {{ $stripGutter }}pt;"></div>@endif
            @endforeach
        </div>
    @endif
</div>
