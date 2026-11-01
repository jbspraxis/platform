<!doctype html>
{{-- A version of the statement of work as a PDF (App\Support\Opportunities\SowPdf, dompdf): A4, the brand's colours, DejaVu fonts, no images and nothing remote. Signatures are text: dompdf needs gd for PNGs. dompdf reads tables and blocks, not flex or grid. --}}
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sow->title }}</title>
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
        .lines .sub { color: #5E6679; font-size: 8.5pt; }
        .lines tfoot td { border-bottom: 0; padding-top: 4pt; padding-bottom: 2pt; }
        .lines tfoot .total td { border-top: 0.75pt solid #0C142B; font-weight: bold; padding-top: 6pt; }
        .terms { width: 100%; border-collapse: collapse; margin-top: 6pt; }
        .terms td { padding: 3pt 12pt 3pt 0; vertical-align: top; }
        .terms .label { color: #5E6679; width: 30%; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 8pt; page-break-inside: avoid; }
        .signatures td { width: 50%; vertical-align: top; padding: 10pt 12pt 10pt 0; }
        .signatures .party { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #5E6679; margin-bottom: 4pt; }
        .signatures .name { font-family: 'DejaVu Serif', serif; font-size: 11pt; color: #0C142B; }
        .signatures .hash { font-size: 7pt; color: #5E6679; word-wrap: break-word; }
        .signatures .pending { color: #5E6679; font-style: italic; }
    </style>
</head>
<body>
    <table class="brand" role="presentation">
        <tr>
            <td class="wordmark">JBS Praxis</td>
            <td class="kicker">{{ __('sow.pdf.kicker') }}</td>
        </tr>
    </table>

    <h1>{{ $sow->title }}</h1>
    <table class="meta" role="presentation">
        <tr><td class="label">{{ __('sow.pdf.between') }}</td><td class="value">{{ $party }}</td></tr>
        <tr><td class="label">{{ __('sow.pdf.and') }}</td><td class="value">{{ $organisation }}</td></tr>
        <tr><td class="label">{{ __('sow.pdf.reference') }}</td><td class="value">{{ $sow->reference }} · {{ __('sow.version', ['n' => $sow->version]) }}</td></tr>
        @if ($proposalRef)
            <tr><td class="label">{{ __('sow.pdf.proposal') }}</td><td class="value">{{ $proposalRef }}</td></tr>
        @endif
        <tr><td class="label">{{ __('sow.pdf.date') }}</td><td class="value">{{ $issued }}</td></tr>
        @if (! $sow->signed_at)
            <tr><td class="label">{{ __('sow.pdf.valid_until') }}</td><td class="value">{{ $validUntil }}</td></tr>
        @endif
    </table>

    @if ($changes)
        <div class="changes">
            <h3>{{ __('sow.pdf.changes', ['n' => $sow->version - 1]) }}</h3>
            @include('pdf.partials.blocks', ['blocks' => $changes])
        </div>
    @endif

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @include('pdf.partials.blocks', ['blocks' => $section['blocks']])
    @endforeach

    <h2>{{ __('sow.pdf.milestones') }}</h2>
    @if ($fees['milestones'])
        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('sow.milestones.milestone') }}</th>
                    <th>{{ __('sow.milestones.weeks') }}</th>
                    <th class="num">{{ __('sow.milestones.weight') }}</th>
                    <th class="num">{{ __('sow.milestones.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fees['milestones'] as $i => $m)
                    <tr>
                        <td>{{ __('sow.milestones.code', ['n' => $i + 1]) }} · {{ $m['title'] }}@if (filled($m['description']))<br><span class="sub">{!! nl2br(e($m['description'])) !!}</span>@endif</td>
                        <td>{{ trans_choice('sow.milestones.weeks_count', $m['weeks'], ['count' => $m['weeks']]) }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $m['weight'], 2, $language === 'fr' ? ',' : '.', ''), '0'), '.,') }}&nbsp;%</td>
                        <td class="num">{{ $money($fees['amounts'][$i]) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ __('sow.pdf.fees') }}</h2>
    @if ($fees['lines']->isNotEmpty())
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
                @foreach ($fees['lines'] as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->quantity }}</td>
                        <td class="num">{{ $money($line->unit_price) }}</td>
                        <td class="num">{{ $money($line->total_price) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if ($fees['vat']['applies'])
                    <tr><td colspan="3">{{ __('proposals.lines.subtotal') }}</td><td class="num">{{ $money($fees['totals']['subtotal']) }}</td></tr>
                    <tr><td colspan="3">{{ __('proposals.lines.vat', ['rate' => \App\Support\Finance\Vat::percent($fees['vat_rate'], $language)]) }}</td><td class="num">{{ $money($fees['totals']['vat']) }}</td></tr>
                    <tr class="total"><td colspan="3">{{ __('proposals.lines.total') }}</td><td class="num">{{ $money($fees['totals']['total']) }}</td></tr>
                @else
                    {{-- No VAT (App\Support\Finance\Vat): no VAT line, and the total is the subtotal --}}
                    <tr class="total"><td colspan="3">{{ __('vat.total') }}</td><td class="num">{{ $money($fees['totals']['total']) }}</td></tr>
                @endif
            </tfoot>
        </table>
    @endif
    <table class="terms" role="presentation">
        @if ($fees['schedule'])
            <tr><td class="label">{{ __('proposals.terms.schedule') }}</td><td>{{ $fees['schedule'] }}</td></tr>
        @endif
        <tr><td class="label">{{ __('sow.fees.deposit') }}</td><td>{{ __('sow.fees.deposit_text', ['percent' => rtrim(rtrim(number_format($fees['deposit_percent'], 2, '.', ''), '0'), '.'), 'amount' => $money($fees['deposit'])]) }}</td></tr>
    </table>

    <h2>{{ __('sow.pdf.signatures') }}</h2>
    <table class="signatures" role="presentation">
        <tr>
            <td>
                <div class="party">{{ __('sow.pdf.for', ['party' => $party]) }}</div>
                @if ($sow->hq_signed_at)
                    <div class="name">{{ $sow->hq_signer_name }}</div>
                    <div>{{ __('sow.pdf.signed_on', ['date' => $hqSigned]) }}</div>
                    <div class="hash">{{ $sow->hq_signature_hash }}</div>
                @else
                    <div class="pending">{{ __('sow.pdf.not_signed') }}</div>
                @endif
            </td>
            <td>
                <div class="party">{{ __('sow.pdf.for', ['party' => $organisation]) }}</div>
                @if ($sow->signed_at)
                    <div class="name">{{ $sow->signed_by_name }}</div>
                    <div>{{ $sow->signed_by_title }}</div>
                    <div>{{ __('sow.pdf.signed_on', ['date' => $clientSigned]) }}</div>
                    <div class="hash">{{ $sow->client_signature_hash }}</div>
                @else
                    <div class="pending">{{ __('sow.pdf.sign_online') }}</div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
