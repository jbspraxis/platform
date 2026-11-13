<!doctype html>
{{--
    A receipt for one payment (App\Support\Finance\PaymentReceipts, dompdf; docs/RECEIPTS.md) on the letterhead of the
    company that billed the invoice, laid out like the invoice (resources/views/pdf/invoice.blade.php): who paid, when
    and how, the amount received, and where the invoice stands after it. dompdf reads tables and blocks; nothing remote.
--}}
@php
    $teal = \App\Support\Letters\Letterhead::TEAL;
    $ink = \App\Support\Letters\Letterhead::INK;
    $muted = \App\Support\Letters\Letterhead::MUTED;
    $slate = \App\Support\Letters\Letterhead::SLATE;
    $green = '#1F7A4D';
@endphp
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('invoices.receipt.title') }} {{ $receipt->receipt_number }}</title>
    <style>
        @page { margin: 40pt 50pt 78pt 50pt; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 8.6pt; line-height: 1.42; color: {{ $slate }}; }
        p { margin: 0 0 5pt; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding: 0; }
        .head .office { text-align: right; font-size: 7.4pt; line-height: 1.45; color: {{ $muted }}; }
        .head .office strong { display: block; font-family: 'DejaVu Serif', serif; font-size: 9pt; color: {{ $ink }}; font-weight: normal; }
        .rule { height: 1.2pt; background: {{ $teal }}; margin: 8pt 0 0; font-size: 0; line-height: 0; }
        .rule-thin { height: 0.5pt; background: #D8DEE6; margin: 2pt 0 0; font-size: 0; line-height: 0; }
        .titlebar { width: 100%; border-collapse: collapse; margin: 14pt 0 10pt; }
        .titlebar td { vertical-align: bottom; padding: 0; }
        h1 { font-family: 'DejaVu Serif', serif; font-weight: normal; color: {{ $ink }}; font-size: 19pt; line-height: 1.1; margin: 0; }
        .number { text-align: right; font-size: 10.5pt; color: {{ $ink }}; }
        .stamp { display: inline-block; margin-left: 6pt; padding: 1pt 5pt; border: 0.75pt solid {{ $green }}; color: {{ $green }}; font-size: 7.5pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .parties { width: 100%; border-collapse: collapse; margin: 0 0 12pt; }
        .parties > tbody > tr > td, .parties > tr > td { vertical-align: top; padding: 0; }
        .party { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5pt; color: {{ $muted }}; margin-bottom: 3pt; }
        .name { font-family: 'DejaVu Serif', serif; font-size: 10pt; color: {{ $ink }}; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1.5pt 0; vertical-align: top; }
        .meta .label { color: {{ $muted }}; width: 38%; padding-right: 8pt; }
        .meta .value { color: {{ $ink }}; }
        .lines { width: 100%; border-collapse: collapse; margin: 0 0 6pt; }
        .lines th { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.4pt; color: {{ $muted }}; text-align: left; font-weight: normal; padding: 4pt 6pt 4pt 0; border-top: 0.75pt solid {{ $ink }}; border-bottom: 0.75pt solid {{ $ink }}; }
        .lines td { padding: 5pt 6pt 5pt 0; border-bottom: 0.5pt solid #E3E7EC; vertical-align: top; color: {{ $ink }}; }
        .lines .num { text-align: right; white-space: nowrap; padding-left: 14pt; padding-right: 0; width: 24%; }
        .lines .sub { display: block; color: {{ $muted }}; font-size: 7.6pt; margin-top: 1pt; }
        .totals { width: 52%; border-collapse: collapse; margin: 0 0 4pt auto; }
        .totals td { padding: 2.5pt 0; color: {{ $ink }}; }
        .totals .num { text-align: right; white-space: nowrap; padding-left: 14pt; }
        .totals .grand td { border-top: 0.75pt solid {{ $ink }}; font-weight: bold; padding-top: 4pt; }
        h2 { font-family: 'DejaVu Serif', serif; font-weight: normal; color: {{ $ink }}; font-size: 10.5pt; margin: 14pt 0 5pt; padding-top: 6pt; border-top: 0.5pt solid #D8DEE6; }
        .settled { margin: 4pt 0 0; color: {{ $green }}; }
        .small { font-size: 7.6pt; color: {{ $muted }}; }
        .thanks { margin-top: 14pt; color: {{ $ink }}; }
    </style>
</head>
<body>
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
            <td><h1>{{ __('invoices.receipt.title') }}</h1></td>
            <td class="number">{{ $receipt->receipt_number }}<span class="stamp">{{ __('invoices.receipt.stamp') }}</span></td>
        </tr>
    </table>

    <table class="parties" role="presentation">
        <tr>
            <td style="width: 54%; padding-right: 18pt;">
                <div class="party">{{ __('invoices.receipt.from') }}</div>
                <div class="name">{{ $client?->name ?? $receipt->organisation_name }}</div>
                @if ($clientAddress)
                    <div>{!! nl2br(e($clientAddress)) !!}</div>
                @endif
                @if ($clientRegistration)
                    <div>{{ __('invoices.pdf.registration', ['number' => $clientRegistration]) }}</div>
                @endif
                @if ($clientTaxId)
                    <div>{{ __('invoices.pdf.tax_number', ['number' => $clientTaxId]) }}</div>
                @endif
            </td>
            <td>
                <table class="meta" role="presentation">
                    <tr><td class="label">{{ __('invoices.receipt.received_on') }}</td><td class="value">{{ $received }}</td></tr>
                    <tr><td class="label">{{ __('invoices.receipt.issued_on') }}</td><td class="value">{{ $issued }}</td></tr>
                    <tr><td class="label">{{ __('invoices.receipt.invoice') }}</td><td class="value">{{ $receipt->invoice_number }}</td></tr>
                    <tr><td class="label">{{ __('invoices.pdf.project') }}</td><td class="value">{{ $receipt->project_title }}@if ($phase) · {{ $phase }}@endif</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('invoices.pdf.details') }}</th>
                <th class="num">{{ __('proposals.lines.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ __('invoices.receipt.payment_for', ['number' => $receipt->invoice_number]) }}
                    <span class="sub">{{ $method }} · {{ __('invoices.receipt.reference', ['reference' => $receipt->tx_hash_or_ref]) }}</span>
                </td>
                <td class="num">{{ $money($amount) }}</td>
            </tr>
            @if ($wht > 0)
                <tr><td>{{ __('invoices.receipt.wht') }}</td><td class="num">{{ $money($wht) }}</td></tr>
            @endif
            @if ($writeoff > 0)
                <tr><td>{{ __('invoices.receipt.writeoff') }}</td><td class="num">{{ $money($writeoff) }}</td></tr>
            @endif
        </tbody>
    </table>
    <table class="totals" role="presentation">
        <tr class="grand"><td>{{ __('invoices.receipt.total') }}</td><td class="num">{{ $money($settledTotal) }}</td></tr>
    </table>

    <h2>{{ __('invoices.receipt.invoice_heading', ['number' => $receipt->invoice_number]) }}</h2>
    <table class="totals" role="presentation" style="margin-left: 0; width: 60%;">
        <tr><td>{{ __('invoices.receipt.invoice_total') }}</td><td class="num">{{ $money($receipt->total_amount) }}</td></tr>
        <tr><td>{{ __('invoices.pdf.paid_to_date') }}</td><td class="num">{{ $money($paidToDate) }}</td></tr>
        <tr class="grand"><td>{{ __('invoices.pdf.balance_due') }}</td><td class="num">{{ $money($left) }}</td></tr>
    </table>
    @if ($left <= 0)
        <p class="settled">{{ __('invoices.receipt.fully_paid', ['number' => $receipt->invoice_number]) }}</p>
    @endif

    <p class="thanks">{{ __('invoices.receipt.thanks') }}</p>
    <p class="small">{{ __('invoices.receipt.note') }}</p>
</body>
</html>
