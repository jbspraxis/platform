@php
    use App\Support\CompanyProfile\Layout;

    $items = array_values($page['items'] ?? []);
    $perRow = 3;
    $width = Layout::even($perRow);
    $bandHeight = Layout::WORK_BAND;
@endphp
{{--
    The rest of the selected work, as an index: the client, its sector, the case's own one line and what
    the system changed. Three to a row, which is how a reader takes in breadth. A band of place imagery
    bleeds off the foot of the page and closes the section.
--}}
<div class="pad" style="padding-bottom: {{ $bandHeight + 24 }}pt;">
    <p class="meta">{{ $page['title'] ?? '' }} &nbsp;·&nbsp; {{ $words['continued'] }}</p>

    <div style="height: 18pt; font-size: 0; line-height: 0;"></div>

    @foreach (array_chunk($items, $perRow) as $row)
        <div class="cols" style="margin-top: {{ $loop->first ? 0 : 24 }}pt;">
            @foreach ($row as $item)
                <div class="col" style="width: {{ $width }}pt;">
                    <div class="hair"></div>
                    <p class="serif" style="margin-top: 13pt;">{{ $item['title'] ?? '' }}</p>
                    <p class="meta" style="margin-top: 10pt;">
                        <span class="label" style="font-size: 7.4pt;">{{ $item['client'] ?? '' }}</span>
                        &nbsp;·&nbsp; {{ $item['sector'] ?? '' }}
                    </p>
                    @if (($item['outcome'] ?? '') !== '')
                        <p class="small" style="margin-top: 9pt;">{{ $item['outcome'] }}</p>
                    @endif
                </div>@if (! $loop->last)<div class="gap"></div>@endif
            @endforeach
        </div>
    @endforeach
</div>

@if (($page['band'] ?? null) !== null)
    <img src="{{ $page['band'] }}" style="position: absolute; left: 0; top: {{ Layout::PAGE_HEIGHT - $bandHeight }}pt; width: {{ Layout::PAGE_WIDTH }}pt; height: {{ $bandHeight }}pt;">
@endif
