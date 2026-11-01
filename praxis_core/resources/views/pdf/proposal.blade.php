<!doctype html>
{{-- A version of the proposal as a PDF (App\Support\Opportunities\ProposalPdf, dompdf): A4, the brand's colours, DejaVu fonts (bundled with dompdf), no images and nothing remote. dompdf reads tables and blocks, not flex or grid. --}}
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->title }}</title>
    <style>
        @page { margin: 56pt 56pt 64pt 56pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.5; color: #4A5468; }
        h1, h2, h3 { font-family: 'DejaVu Serif', serif; font-weight: normal; color: #0C142B; }
        h1 { font-size: 22pt; line-height: 1.2; margin: 0 0 10pt; }
        h2 { font-size: 13pt; line-height: 1.3; margin: 22pt 0 8pt; padding-top: 8pt; border-top: 0.75pt solid #D8DEE6; page-break-after: avoid; }
        h3 { font-size: 10.5pt; margin: 12pt 0 4pt; font-family: 'DejaVu Sans', sans-serif; font-weight: bold; }
        p { margin: 0 0 8pt; }
        ul { margin: 0 0 8pt; padding-left: 14pt; }
        li { margin: 0 0 3pt; }
        .brand { width: 100%; border-bottom: 2pt solid #08707C; padding-bottom: 8pt; margin-bottom: 28pt; }
        .brand td { vertical-align: bottom; }
        .wordmark { font-family: 'DejaVu Serif', serif; font-size: 15pt; color: #044B6A; }
        .kicker { font-size: 8pt; letter-spacing: 0.6pt; text-transform: uppercase; color: #5E6679; text-align: right; }
        .meta { width: 100%; margin: 4pt 0 0; border-collapse: collapse; }
        .meta td { padding: 3pt 12pt 3pt 0; font-size: 9pt; vertical-align: top; }
        .meta .label { color: #5E6679; width: 30%; }
        .meta .value { color: #0C142B; }
        .changes { margin: 18pt 0 0; padding: 10pt 12pt; background: #F2F5F7; border-left: 2pt solid #08707C; }
        .changes h3 { margin-top: 0; }
        .lines { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; }
        .lines th { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #5E6679; text-align: left; font-weight: normal; padding: 6pt 6pt 6pt 0; border-bottom: 0.75pt solid #0C142B; }
        .lines td { padding: 6pt 6pt 6pt 0; border-bottom: 0.5pt solid #D8DEE6; vertical-align: top; color: #0C142B; }
        .lines .num { text-align: right; white-space: nowrap; padding-right: 0; }
        .lines .code { color: #5E6679; font-size: 8pt; }
        .lines tfoot td { border-bottom: 0; padding-top: 4pt; padding-bottom: 2pt; }
        .lines tfoot .total td { border-top: 0.75pt solid #0C142B; font-weight: bold; padding-top: 6pt; }
        .terms { width: 100%; border-collapse: collapse; margin-top: 6pt; }
        .terms td { padding: 3pt 12pt 3pt 0; vertical-align: top; }
        .terms .label { color: #5E6679; width: 30%; }
        .closing { page-break-inside: avoid; margin-top: 22pt; padding-top: 10pt; border-top: 0.75pt solid #D8DEE6; font-size: 8.5pt; color: #5E6679; }
    </style>
</head>
<body>
    <table class="brand" role="presentation">
        <tr>
            <td class="wordmark">JBS Praxis</td>
            <td class="kicker">{{ __('proposals.pdf.kicker') }}</td>
        </tr>
    </table>

    <h1>{{ $proposal->title }}</h1>
    <table class="meta" role="presentation">
        <tr><td class="label">{{ __('proposals.pdf.prepared_for') }}</td><td class="value">{{ $organisation }}</td></tr>
        <tr><td class="label">{{ __('proposals.pdf.reference') }}</td><td class="value">{{ $proposal->proposal_ref }} · {{ __('proposals.version', ['n' => $proposal->version]) }}</td></tr>
        <tr><td class="label">{{ __('proposals.pdf.date') }}</td><td class="value">{{ $issued }}</td></tr>
        <tr><td class="label">{{ __('proposals.pdf.valid_until') }}</td><td class="value">{{ $validUntil }}</td></tr>
    </table>

    @if ($changes)
        <div class="changes">
            <h3>{{ __('proposals.pdf.changes', ['n' => $proposal->version - 1]) }}</h3>
            @include('pdf.partials.blocks', ['blocks' => $changes])
        </div>
    @endif

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @include('pdf.partials.blocks', ['blocks' => $section['blocks']])
    @endforeach

    <h2>{{ __('proposals.pdf.investment') }}</h2>
    @if ($lines->isNotEmpty())
        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('proposals.lines.item') }}</th>
                    <th>{{ __('proposals.lines.quantity') }}</th>
                    <th class="num">{{ __('proposals.lines.unit_price') }}</th>
                    <th class="num">{{ __('proposals.lines.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line->description }}@if ($line->code !== 'CUSTOM')<br><span class="code">{{ $line->code }}</span>@endif</td>
                        <td>{{ $line->quantity }}</td>
                        <td class="num">{{ $money($line->unit_price) }}</td>
                        <td class="num">{{ $money($line->total_price) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="3">{{ __('proposals.lines.subtotal') }}</td><td class="num">{{ $money($totals['subtotal']) }}</td></tr>
                <tr><td colspan="3">{{ __('proposals.lines.vat', ['rate' => rtrim(rtrim(number_format((float) $proposal->vat_rate, 2, $language === 'fr' ? ',' : '.', ''), '0'), '.,')]) }}</td><td class="num">{{ $money($totals['vat']) }}</td></tr>
                <tr class="total"><td colspan="3">{{ __('proposals.lines.total') }}</td><td class="num">{{ $money($totals['total']) }}</td></tr>
            </tfoot>
        </table>
    @else
        <p>{{ __('proposals.pdf.no_lines') }}</p>
    @endif
    <table class="terms" role="presentation">
        @if ($schedule)
            <tr><td class="label">{{ __('proposals.terms.schedule') }}</td><td>{{ $schedule }}</td></tr>
        @endif
        <tr><td class="label">{{ __('proposals.terms.validity') }}</td><td>{{ __('proposals.pdf.validity', ['days' => $proposal->validity_days, 'date' => $validUntil]) }}</td></tr>
    </table>

    <div class="closing">
        <p style="margin: 0">{{ __('proposals.pdf.next') }}</p>
    </div>
</body>
</html>
