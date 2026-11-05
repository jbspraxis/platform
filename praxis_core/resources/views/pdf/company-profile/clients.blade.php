@php
    use App\Support\CompanyProfile\Assets;
    use App\Support\CompanyProfile\Layout;

    $rows = $page['rows'] ?? [];
    $perRow = 5;
    $cell = Layout::even($perRow);
    $rowHeight = 104.0;
@endphp
{{--
    The logo wall. Every mark is greyscale on Paper, and each one is sized by its area rather than its
    height, so a wide wordmark does not shout over a square mark: the wall reads as one wall. Rows are
    separated by hairlines, never by a box, and each mark is centred in its cell by the margin its own
    proportions ask for, because an inline block cannot centre its contents vertically by itself.
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
            'style' => 'margin-top: 32pt;',
        ])
    @endif

    <div style="height: 30pt; font-size: 0; line-height: 0;"></div>

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
</div>
