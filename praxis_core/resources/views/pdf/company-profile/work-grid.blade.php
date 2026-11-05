@php
    use App\Support\CompanyProfile\Layout;

    $items = array_values($page['items'] ?? []);
    $perRow = 3;
    $width = Layout::even($perRow);
@endphp
{{--
    Selected work, on one page: each case's own line, the client, the sector and what the system
    changed. Three to a row, which is how a reader takes in breadth. The proof of what was built is the
    strip of platforms on the clients page.
--}}
<div class="pad">
    @if ($page['first'] ?? true)
        <div class="cols">
            <div class="col" style="width: {{ Layout::columnWidth(5) }}pt;">
                <p class="h1">{{ $page['title'] ?? '' }}</p>
            </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(7) }}pt;">
                <p class="small">{{ $page['lead'] ?? '' }}</p>
            </div>
        </div>
        <div style="height: 22pt; font-size: 0; line-height: 0;"></div>
    @else
        <p class="meta">{{ $page['title'] ?? '' }} &nbsp;·&nbsp; {{ $words['continued'] }}</p>
        <div style="height: 20pt; font-size: 0; line-height: 0;"></div>
    @endif

    @foreach (array_chunk($items, $perRow) as $row)
        <div class="cols" style="margin-top: {{ $loop->first ? 0 : 28 }}pt;">
            @foreach ($row as $item)
                <div class="col" style="width: {{ $width }}pt;">
                    <div class="hair"></div>
                    <p class="serif" style="margin-top: 12pt;">{{ $item['title'] ?? '' }}</p>
                    <p class="meta" style="margin-top: 8pt;">
                        <span class="label" style="font-size: 7.4pt;">{{ $item['client'] ?? '' }}</span>
                        &nbsp;·&nbsp; {{ $item['sector'] ?? '' }}
                    </p>
                    @if (($item['outcome'] ?? '') !== '')
                        <p class="small" style="margin-top: 8pt;">{{ $item['outcome'] }}</p>
                    @endif
                </div>@if (! $loop->last)<div class="gap"></div>@endif
            @endforeach
        </div>
    @endforeach

</div>
