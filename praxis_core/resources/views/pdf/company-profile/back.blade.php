@php
    use App\Support\CompanyProfile\Assets;
    use App\Support\CompanyProfile\Layout;

    $contact = $page['contact'] ?? [];
    $meta = $page['meta'] ?? [];
    $lines = array_values(array_filter([
        $contact['website'] ?? '',
        $contact['email'] ?? '',
        $contact['sales_email'] ?? '',
        $contact['phone'] ?? '',
        implode('  ·  ', array_values($contact['offices'] ?? [])),
    ], fn ($line) => trim((string) $line) !== ''));
@endphp
{{--
    The back cover: what to do next, where to find us, and the brand's closing line. "Order, made visible"
    is the internal compass and is used in public only in places like this one, never as a slogan.
--}}
<div class="pad">
    <div class="cols">
        <div class="col" style="width: {{ Layout::columnWidth(7) }}pt;">
            <p class="display-sm" style="width: {{ Layout::columnWidth(6) }}pt;">{{ $contact['cta'] ?? '' }}</p>

            @include('pdf.company-profile.rule', [
                'left' => 128,
                'right' => 86,
                'style' => 'margin-top: 26pt;',
            ])

            <p class="body" style="margin-top: 26pt; width: {{ Layout::columnWidth(6) }}pt;">{{ $words['speak'] }}</p>

            <div style="margin-top: 24pt;">
                @foreach ($lines as $line)
                    <p class="small ink-text" style="margin-bottom: 5pt;">{{ $line }}</p>
                @endforeach
            </div>
        </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(5) }}pt; text-align: right;">
            <img src="{{ Assets::brand('stacked-colour.jpg') }}" style="width: 136pt;">
        </div>
    </div>

</div>

<div style="position: absolute; left: {{ Layout::MARGIN_X }}pt; top: 404pt; width: {{ Layout::CONTENT_WIDTH }}pt;">
    <div class="hair"></div>

    <div class="cols" style="margin-top: 22pt;">
        <div class="col" style="width: {{ Layout::columnWidth(8) }}pt;">
            <p class="display-md">{{ $contact['closing'] ?? '' }}</p>
        </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(4) }}pt; text-align: right; padding-top: 14pt;">
            <p class="meta">
                {{ $words['published'] }} {{ $meta['published_at'] ?? '' }}<br>
                {{ $meta['reference'] ?? '' }}
            </p>
        </div>
    </div>
</div>
