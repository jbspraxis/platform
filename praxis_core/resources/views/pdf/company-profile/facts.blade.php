@php
    use App\Support\CompanyProfile\Layout;

    $security = array_values($page['security']['items'] ?? []);
    $facts = array_values($page['facts']['items'] ?? []);
    $securityWidth = Layout::even(count($security));
    $factColumns = array_chunk($facts, (int) ceil(count($facts) / 2) ?: 1);
    $labelWidth = Layout::columnWidth(2);
    $valueWidth = Layout::columnWidth(6) - $labelWidth - Layout::GUTTER;
@endphp
{{--
    The one Ink page in the book: security first, then the company's own facts. A fact whose switch is off
    has already been dropped by Layout::facts(), so the Cameroon entity cannot appear here until the owner
    turns it on. On Ink the secondary text is a solid colour, never opacity, and the accent is Bright Teal.
--}}
<div class="pad">
    <p class="h1" style="width: {{ Layout::columnWidth(7) }}pt;">{{ $page['security']['title'] ?? '' }}</p>

    @include('pdf.company-profile.rule', [
        'left' => Layout::columnWidth(3) + Layout::GUTTER,
        'right' => Layout::columnWidth(4),
        'style' => 'margin-top: 26pt;',
    ])

    <div style="height: 30pt; font-size: 0; line-height: 0;"></div>

    <div class="cols">
        @foreach ($security as $item)
            <div class="col" style="width: {{ $securityWidth }}pt;">
                <p class="h3" style="font-size: 11.5pt; line-height: 15pt;">{{ $item['title'] ?? '' }}</p>
                <p class="small" style="margin-top: 9pt;">{{ $item['body'] ?? '' }}</p>
            </div>@if (! $loop->last)<div class="gap"></div>@endif
        @endforeach
    </div>

    <div style="height: 40pt; font-size: 0; line-height: 0;"></div>
    <div class="hair"></div>
    <div style="height: 26pt; font-size: 0; line-height: 0;"></div>

    <p class="statement">{{ $page['facts']['title'] ?? '' }}</p>

    <div class="cols" style="margin-top: 20pt;">
        @foreach ($factColumns as $column)
            <div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
                @foreach ($column as $fact)
                    <div class="cols" style="width: {{ Layout::columnWidth(6) }}pt; margin-top: {{ $loop->first ? 0 : 9 }}pt;">
                        <div class="col" style="width: {{ $labelWidth }}pt;">
                            <p class="meta">{{ $fact['label'] ?? '' }}</p>
                        </div><div class="gap"></div><div class="col" style="width: {{ $valueWidth }}pt;">
                            <p class="small" style="color: #FFFFFF;">{{ $fact['value'] ?? '' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>@if (! $loop->last)<div class="gap"></div>@endif
        @endforeach
    </div>
</div>
