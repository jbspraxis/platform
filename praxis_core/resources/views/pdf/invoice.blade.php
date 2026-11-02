<!doctype html>
{{-- An invoice as a PDF (App\Support\Finance\InvoicePdf, dompdf): A4, the brand's colours, DejaVu fonts, no images and nothing remote, as the SOW. dompdf reads tables and blocks, not flex or grid. --}}
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 56pt 56pt 64pt 56pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.5; color: #4A5468; }
        h1, h2 { font-family: 'DejaVu Serif', serif; font-weight: normal; color: #0C142B; }
        h1 { font-size: 22pt; line-height: 1.2; margin: 0 0 4pt; }
        h2 { font-size: 13pt; line-height: 1.3; margin: 22pt 0 8pt; padding-top: 8pt; border-top: 0.75pt solid #D8DEE6; page-break-after: avoid; }
        p { margin: 0 0 8pt; }
        .brand { width: 100%; border-bottom: 2pt solid #08707C; padding-bottom: 8pt; margin-bottom: 28pt; }
        .brand td { vertical-align: bottom; }
        .wordmark { font-family: 'DejaVu Serif', serif; font-size: 15pt; color: #044B6A; }
        .kicker { font-size: 8pt; letter-spacing: 0.6pt; text-transform: uppercase; color: #5E6679; text-align: right; }
        .number { font-size: 11pt; color: #0C142B; margin: 0 0 16pt; }
        .paid { display: inline-block; margin-left: 8pt; padding: 1pt 6pt; border: 0.75pt solid #1F7A4D; color: #1F7A4D; font-size: 8pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .parties { width: 100%; border-collapse: collapse; margin: 0 0 6pt; }
        .parties td { width: 50%; vertical-align: top; padding: 0 16pt 0 0; }
        .party { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #5E6679; margin-bottom: 4pt; }
        .name { font-family: 'DejaVu Serif', serif; font-size: 11pt; color: #0C142B; }
        .meta { width: 100%; margin: 14pt 0 0; border-collapse: collapse; }
        .meta td { padding: 3pt 12pt 3pt 0; font-size: 9pt; vertical-align: top; }
        .meta .label { color: #5E6679; width: 30%; }
        .meta .value { color: #0C142B; }
        .lines { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; }
        .lines th { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #5E6679; text-align: left; font-weight: normal; padding: 6pt 6pt 6pt 0; border-bottom: 0.75pt solid #0C142B; }
        .lines td { padding: 6pt 6pt 6pt 0; border-bottom: 0.5pt solid #D8DEE6; vertical-align: top; color: #0C142B; }
        .lines .num { text-align: right; white-space: nowrap; padding-right: 0; }
        .lines tfoot td { border-bottom: 0; padding-top: 4pt; padding-bottom: 2pt; }
        .lines tfoot .total td { border-top: 0.75pt solid #0C142B; font-weight: bold; padding-top: 6pt; }
        .account { margin: 0 0 8pt; padding: 8pt 10pt; background: #F2F5F7; page-break-inside: avoid; }
        .account table { width: 100%; border-collapse: collapse; }
        .account td { padding: 1pt 12pt 1pt 0; vertical-align: top; }
        .account .label { color: #5E6679; width: 30%; }
        .account .value { color: #0C142B; }
        .small { font-size: 8.5pt; }
    </style>
</head>
<body>
    <table class="brand" role="presentation">
        <tr>
            <td class="wordmark">JBS Praxis</td>
            <td class="kicker">{{ $title }}</td>
        </tr>
    </table>

    <h1>{{ $title }}</h1>
    <p class="number">{{ $invoice->invoice_number }}@if ($paid !== null)<span class="paid">{{ $paid !== '' ? __('invoices.pdf.paid_on', ['date' => $paid]) : __('invoices.pdf.paid') }}</span>@endif</p>

    <table class="parties" role="presentation">
        <tr>
            <td>
                <div class="party">{{ __('invoices.pdf.from') }}</div>
                <div class="name">{{ $issuer?->legal_name ?? 'JBS Praxis' }}</div>
                @if (filled($issuer?->registered_address))
                    <div>{!! nl2br(e($issuer->registered_address)) !!}</div>
                @endif
                @if (filled($issuer?->tax_identification_number))
                    <div>{{ __('invoices.pdf.tax_number', ['number' => $issuer->tax_identification_number]) }}</div>
                @endif
                @if (filled($vat['vat_number']))
                    <div>{{ __('invoices.pdf.vat_number', ['number' => $vat['vat_number']]) }}</div>
                @endif
                @if (filled($issuer?->registration_number))
                    <div>{{ __('invoices.pdf.registration', ['number' => $issuer->registration_number]) }}</div>
                @endif
            </td>
            <td>
                <div class="party">{{ __('invoices.pdf.to') }}</div>
                <div class="name">{{ $client?->name }}</div>
                @if ($clientAddress)
                    <div>{!! nl2br(e($clientAddress)) !!}</div>
                @endif
                @if ($clientTaxId)
                    <div>{{ __('invoices.pdf.tax_number', ['number' => $clientTaxId]) }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="meta" role="presentation">
        <tr><td class="label">{{ __('invoices.pdf.issued') }}</td><td class="value">{{ $issued }}</td></tr>
        <tr><td class="label">{{ __('invoices.pdf.due') }}</td><td class="value">{{ $due }}</td></tr>
        @if ($project)
            <tr><td class="label">{{ __('invoices.pdf.project') }}</td><td class="value">{{ $project->title }}@if ($phase) · {{ $phase }}@endif</td></tr>
        @endif
        @if (filled($invoice->po_sow_reference))
            <tr><td class="label">{{ __('invoices.pdf.reference') }}</td><td class="value">{{ $invoice->po_sow_reference }}</td></tr>
        @endif
    </table>

    <h2>{{ __('invoices.pdf.details') }}</h2>
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
                    <td>{{ $line->description }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) ($line->quantity ?? 1), 2, '.', ''), '0'), '.') }}</td>
                    <td class="num">{{ $money($line->unit_price ?? $line->total_price) }}</td>
                    <td class="num">{{ $money($line->total_price) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if ($vat['applies'])
                <tr><td colspan="3">{{ __('proposals.lines.subtotal') }}</td><td class="num">{{ $money($invoice->amount_due) }}</td></tr>
                <tr><td colspan="3">{{ __('proposals.lines.vat', ['rate' => \App\Support\Finance\Vat::percent((float) $vat['rate'], $language)]) }}</td><td class="num">{{ $money($invoice->vat_amount) }}</td></tr>
                <tr class="total"><td colspan="3">{{ __('proposals.lines.total') }}</td><td class="num">{{ $money($invoice->total_amount) }}</td></tr>
            @else
                {{-- No VAT: no VAT line, and the total is the amount before VAT --}}
                <tr class="total"><td colspan="3">{{ __('vat.total') }}</td><td class="num">{{ $money($invoice->total_amount) }}</td></tr>
            @endif
            @if ($vatNote)
                <tr><td colspan="4" class="small" style="padding-top: 6pt">{{ $vatNote }}</td></tr>
            @endif
        </tfoot>
    </table>

    <h2>{{ __('invoices.pdf.how_to_pay') }}</h2>
    @forelse ($accounts as $account)
        <div class="account">
            <table role="presentation">
                <tr><td class="label">{{ __('invoices.pdf.bank') }}</td><td class="value">{{ $account->provider_name }}</td></tr>
                <tr><td class="label">{{ __('invoices.pdf.account_name') }}</td><td class="value">{{ $account->account_name }}</td></tr>
                <tr><td class="label">{{ __('invoices.pdf.account_number') }}</td><td class="value">{{ $account->account_reference }}</td></tr>
                @if (filled($account->routing_code))
                    <tr><td class="label">{{ __('invoices.pdf.routing') }}</td><td class="value">{{ $account->routing_code }}</td></tr>
                @endif
                <tr><td class="label">{{ __('invoices.pdf.currency') }}</td><td class="value">{{ $account->currency }}</td></tr>
            </table>
        </div>
    @empty
        <p>{{ __('invoices.pdf.no_accounts') }}</p>
    @endforelse
    <p class="small">{{ __('invoices.pdf.pay_note', ['number' => $invoice->invoice_number]) }}</p>
</body>
</html>
