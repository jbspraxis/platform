@php
    use App\Support\CompanyProfile\Layout;

    $items = array_values($page['items'] ?? []);
    $perRow = 3;
    $width = Layout::even($perRow);
@endphp
{{--
    The rest of the selected work, as an index: the client, its sector, the case's own one line and what
    the system changed. Three to a row, which is how a reader takes in breadth. The proof of what was
    built is the page after this one, where each client's own platform is shown.
--}}
<div class="pad">
    <p class="meta">{{ $page['title'] ?? '' }} &nbsp;·&nbsp; {{ $words['continued'] }}</p>

    <div style="height: 32pt; font-size: 0; line-height: 0;"></div>

    @foreach (array_chunk($items, $perRow) as $row)
        <div class="cols" style="margin-top: {{ $loop->first ? 0 : 52 }}pt;">
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
