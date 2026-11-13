<!doctype html>
{{--
    An invoice as a PDF (App\Support\Finance\InvoicePdf, dompdf) on the company letterhead (owner, 13 Nov 2026: "use
    the company's letterhead" and "contain everything within one page"): the Documents letterhead's header (logo, the
    company that bills it, its office and tax numbers) and its footer, drawn on every page by LetterPdf (legal line,
    contact line, "Page 2 of 3" only when there is a second page). Laid out to fit one A4 page: the client and the
    invoice's dates side by side, compact lines, the totals, and the accounts two to a row. dompdf reads tables and
    blocks, not flex or grid; nothing remote (the logo is a data URI).
--}}
@php
    $teal = \App\Support\Letters\Letterhead::TEAL;
    $ink = \App\Support\Letters\Letterhead::INK;
    $muted = \App\Support\Letters\Letterhead::MUTED;
    $slate = \App\Support\Letters\Letterhead::SLATE;
    $red = '#B42318';
@endphp
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 40pt 50pt 78pt 50pt; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 8.6pt; line-height: 1.42; color: {{ $slate }}; }
        p { margin: 0 0 5pt; }

        /* The letterhead's header (resources/views/pdf/letter.blade.php) */
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding: 0; }
        .head .office { text-align: right; font-size: 7.4pt; line-height: 1.45; color: {{ $muted }}; }
        .head .office strong { display: block; font-family: 'DejaVu Serif', serif; font-size: 9pt; color: {{ $ink }}; font-weight: normal; }
        .rule { height: 1.2pt; background: {{ $teal }}; margin: 8pt 0 0; font-size: 0; line-height: 0; }
        .rule-thin { height: 0.5pt; background: #D8DEE6; margin: 2pt 0 0; font-size: 0; line-height: 0; }

        /* Title row */
        .titlebar { width: 100%; border-collapse: collapse; margin: 14pt 0 10pt; }
        .titlebar td { vertical-align: bottom; padding: 0; }
        h1 { font-family: 'DejaVu Serif', serif; font-weight: normal; color: {{ $ink }}; font-size: 19pt; line-height: 1.1; margin: 0; }
        .number { text-align: right; font-size: 10.5pt; color: {{ $ink }}; }
        .paid { display: inline-block; margin-left: 6pt; padding: 1pt 5pt; border: 0.75pt solid #1F7A4D; color: #1F7A4D; font-size: 7.5pt; letter-spacing: 0.4pt; text-transform: uppercase; }

        /* The client, and the invoice's dates, side by side */
        .parties { width: 100%; border-collapse: collapse; margin: 0 0 10pt; }
        .parties > tbody > tr > td, .parties > tr > td { vertical-align: top; padding: 0; }
        .party { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5pt; color: {{ $muted }}; margin-bottom: 3pt; }
        .name { font-family: 'DejaVu Serif', serif; font-size: 10pt; color: {{ $ink }}; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1.5pt 0; vertical-align: top; }
        .meta .label { color: {{ $muted }}; width: 34%; padding-right: 8pt; }
        .meta .value { color: {{ $ink }}; }

        /* The lines */
        .lines { width: 100%; border-collapse: collapse; margin: 0 0 6pt; }
        .lines th { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.4pt; color: {{ $muted }}; text-align: left; font-weight: normal; padding: 4pt 6pt 4pt 0; border-top: 0.75pt solid {{ $ink }}; border-bottom: 0.75pt solid {{ $ink }}; }
        .lines td { padding: 4pt 6pt 4pt 0; border-bottom: 0.5pt solid #E3E7EC; vertical-align: top; color: {{ $ink }}; }
        .lines .qty { width: 8%; text-align: center; padding-right: 6pt; }
        /* Room between Unit price and Amount: right-aligned figures otherwise run together */
        .lines .num { text-align: right; white-space: nowrap; padding-left: 14pt; padding-right: 0; width: 18%; }
        /* A discount, an advance taken off, a payment: shown in red */
        .lines td.neg, .totals td.neg { color: {{ $red }}; }

        .totals { width: 52%; border-collapse: collapse; margin: 0 0 4pt auto; }
        .totals td { padding: 2.5pt 0; color: {{ $ink }}; }
        .totals .num { text-align: right; white-space: nowrap; padding-left: 14pt; }
        .totals .grand td { border-top: 0.75pt solid {{ $ink }}; font-weight: bold; padding-top: 4pt; }
        .vat-note { font-size: 7.4pt; color: {{ $muted }}; text-align: right; margin: 0 0 8pt; }

        /* How to pay: the accounts two to a row */
        h2 { font-family: 'DejaVu Serif', serif; font-weight: normal; color: {{ $ink }}; font-size: 10.5pt; margin: 10pt 0 5pt; padding-top: 6pt; border-top: 0.5pt solid #D8DEE6; page-break-after: avoid; }
        .accounts { width: 100%; border-collapse: separate; border-spacing: 0 0; }
        .accounts > tbody > tr > td, .accounts > tr > td { width: 50%; vertical-align: top; padding: 0 6pt 6pt 0; }
        .account { padding: 6pt 8pt; background: #F2F5F7; page-break-inside: avoid; }
        .account table { width: 100%; border-collapse: collapse; }
        .account td { padding: 0.5pt 8pt 0.5pt 0; vertical-align: top; font-size: 8pt; }
        .account .label { color: {{ $muted }}; width: 40%; }
        .account .value { color: {{ $ink }}; }
        .small { font-size: 7.6pt; color: {{ $muted }}; }
    </style>
</head>
<body>
    {{-- The letterhead: the company that bills this invoice --}}
    <table class="head" role="presentation">
        <tr>
            <td style="width: 46%;"><img src="{{ $logo }}" style="width: 170pt;" alt="{{ $letterhead['name'] ?: 'JBS Praxis' }}"></td>
            <td class="office">
                <strong>{{ $letterhead['name'] ?: 'JBS Praxis' }}</strong>
                @foreach ($letterhead['office_lines'] as $line)
                    {{ $line }}<br>
                @endforeach
                @if (filled($issuer?->tax_identification_number))
                    {{ __('invoices.pdf.tax_number', ['number' => $issuer->tax_identification_number]) }}<br>
                @endif
                @if (filled($vat['vat_number']))
                    {{ __('invoices.pdf.vat_number', ['number' => $vat['vat_number']]) }}<br>
                @endif
                @if (filled($issuer?->registration_number))
                    {{ __('invoices.pdf.registration', ['number' => $issuer->registration_number]) }}<br>
                @endif
                {{ implode(' · ', array_filter([$letterhead['phone'], $letterhead['email']])) }}
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    <div class="rule-thin"></div>

    <table class="titlebar" role="presentation">
        <tr>
            <td><h1>{{ $title }}</h1></td>
            <td class="number">{{ $invoice->invoice_number }}@if ($paid !== null)<span class="paid">{{ $paid !== '' ? __('invoices.pdf.paid_on', ['date' => $paid]) : __('invoices.pdf.paid') }}</span>@endif</td>
        </tr>
    </table>

    <table class="parties" role="presentation">
        <tr>
            <td style="width: 54%; padding-right: 18pt;">
                <div class="party">{{ __('invoices.pdf.to') }}</div>
                <div class="name">{{ $client?->name }}</div>
                @if ($clientAddress)
                    <div>{!! nl2br(e($clientAddress)) !!}</div>
                @endif
                @if ($clientRegistration)
                    <div>{{ __('invoices.pdf.registration', ['number' => $clientRegistration]) }}</div>
                @endif
                @if ($clientTaxId)
                    <div>{{ __('invoices.pdf.tax_number', ['number' => $clientTaxId]) }}</div>
                @endif
                @if ($clientEmail || $clientPhone)
                    <div>{{ implode(' · ', array_filter([$clientEmail, $clientPhone])) }}</div>
                @endif
            </td>
            <td>
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
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('proposals.lines.item') }}</th>
                <th class="qty">{{ __('proposals.lines.quantity') }}</th>
                <th class="num">{{ __('proposals.lines.unit_price') }}</th>
                <th class="num">{{ __('proposals.lines.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                @php $unit = (float) ($line->unit_price ?? $line->total_price); @endphp
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) ($line->quantity ?? 1), 2, '.', ''), '0'), '.') }}</td>
                    <td class="num{{ $unit < 0 ? ' neg' : '' }}">{{ $money($unit) }}</td>
                    <td class="num{{ (float) $line->total_price < 0 ? ' neg' : '' }}">{{ $money($line->total_price) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" role="presentation">
        @if ($vat['applies'])
            <tr><td>{{ __('proposals.lines.subtotal') }}</td><td class="num">{{ $money($invoice->amount_due) }}</td></tr>
            <tr><td>{{ __('proposals.lines.vat', ['rate' => \App\Support\Finance\Vat::percent((float) $vat['rate'], $language)]) }}</td><td class="num">{{ $money($invoice->vat_amount) }}</td></tr>
            <tr class="grand"><td>{{ __('proposals.lines.total') }}</td><td class="num">{{ $money($invoice->total_amount) }}</td></tr>
        @else
            {{-- No VAT: no VAT line, and the total is the amount before VAT --}}
            <tr class="grand"><td>{{ __('vat.total') }}</td><td class="num">{{ $money($invoice->total_amount) }}</td></tr>
        @endif
        @if ($paidSoFar > 0 && $invoice->status !== 'Paid')
            <tr><td>{{ __('invoices.pdf.paid_to_date') }}</td><td class="num neg">{{ $money(-$paidSoFar) }}</td></tr>
            <tr class="grand"><td>{{ __('invoices.pdf.balance_due') }}</td><td class="num">{{ $money(max(0, (float) $invoice->total_amount - $paidSoFar)) }}</td></tr>
        @endif
    </table>
    @if ($vatNote)
        <p class="vat-note">{{ $vatNote }}</p>
    @endif

    <h2>{{ __('invoices.pdf.how_to_pay') }}</h2>
    @if (count($accounts))
        <table class="accounts" role="presentation">
            @foreach (collect($accounts)->chunk(2) as $pair)
                <tr>
                    @foreach ($pair as $account)
                        <td>
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
                        </td>
                    @endforeach
                    @if ($pair->count() === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @else
        <p>{{ __('invoices.pdf.no_accounts') }}</p>
    @endif
    <p class="small">{{ __('invoices.pdf.pay_note', ['number' => $invoice->invoice_number]) }}</p>
</body>
</html>
