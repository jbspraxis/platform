@php
    use App\Support\CompanyProfile\Layout;

    $item = $page['item'] ?? [];
    $panelWidth = 362.0;
    $panelPadding = 30.0;
@endphp
{{--
    The feature case: a place photograph across the whole page, with the story on a Paper panel, which is
    the only thing the brand allows on top of a photograph. The image is cut to the page's exact shape
    when it is built, so nothing here squashes or crops it.
--}}
@if (($page['image'] ?? null) !== null)
    <img class="bleed" src="{{ $page['image'] }}" style="width: {{ Layout::PAGE_WIDTH }}pt; height: {{ Layout::PAGE_HEIGHT }}pt;">
@endif

<div class="panel" style="left: {{ Layout::MARGIN_X }}pt; top: {{ Layout::MARGIN_TOP }}pt; width: {{ $panelWidth }}pt; padding: {{ $panelPadding }}pt;">
    <p class="meta">{{ $page['title'] ?? '' }}</p>

    <p class="statement" style="margin-top: 14pt;">{{ $item['title'] ?? '' }}</p>

    @include('pdf.company-profile.rule', [
        'left' => 96,
        'right' => 64,
        'style' => 'margin-top: 18pt;',
    ])

    <p class="small" style="margin-top: 18pt;">
        <span class="label">{{ $item['client'] ?? '' }}</span><br>
        <span class="meta">{{ $item['sector'] ?? '' }}</span>
    </p>

    @foreach ([['problem', 'problem'], ['built', 'built'], ['outcome', 'outcome']] as [$key, $word])
        @if (($item[$key] ?? '') !== '')
            <p class="meta" style="margin-top: 15pt;">{{ $words[$word] }}</p>
            <p class="small" style="margin-top: 4pt;">{{ $item[$key] }}</p>
        @endif
    @endforeach
</div>
