@php
    /**
     * The letterhead and the document on it (docs/DOCUMENTS.md section 3). Rendered by dompdf, so the CSS is the
     * plain, old-fashioned kind it understands: tables for the two-column header, points for every size, and no
     * flexbox or grid. The footer and the page numbers are drawn on every page by LetterPdf, not here.
     *
     * DejaVu Serif and DejaVu Sans are the two faces dompdf carries. The brand's own faces (Newsreader and Plus
     * Jakarta Sans) exist only as woff2, which dompdf cannot read; see docs/DOCUMENTS.md section 3.
     */
    $teal = \App\Support\Letters\Letterhead::TEAL;
    $ink = \App\Support\Letters\Letterhead::INK;
    $muted = \App\Support\Letters\Letterhead::MUTED;
    $slate = \App\Support\Letters\Letterhead::SLATE;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document->reference }}</title>
    <style>
        @page { margin: 48pt 56pt 86pt 56pt; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.45; color: {{ $slate }}; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding: 0; }
        .head .office { text-align: right; font-size: 7.6pt; line-height: 1.5; color: {{ $muted }}; }
        .head .office strong { display: block; font-family: "DejaVu Serif", serif; font-size: 9pt; color: {{ $ink }}; font-weight: normal; }
        .rule { height: 1.2pt; background: {{ $teal }}; margin: 10pt 0 0; font-size: 0; line-height: 0; }
        .rule-thin { height: 0.5pt; background: #D8DEE6; margin: 2pt 0 0; font-size: 0; line-height: 0; }

        .meta { width: 100%; border-collapse: collapse; margin-top: 20pt; font-size: 8.4pt; color: {{ $muted }}; }
        .meta td { padding: 0; vertical-align: top; }
        .meta .date { text-align: right; }

        .recipient { margin-top: 18pt; font-size: 10pt; color: {{ $ink }}; line-height: 1.45; }
        .formal-head { margin-top: 20pt; text-align: center; }
        .formal-company { margin: 0; font-family: "DejaVu Serif", serif; font-size: 12pt; letter-spacing: 0.6pt; color: {{ $ink }}; }
        .formal-reg { margin: 2pt 0 0; font-size: 8pt; color: {{ $muted }}; }
        .formal-rule { width: 70pt; height: 1pt; background: {{ $teal }}; margin: 10pt auto; font-size: 0; line-height: 0; }
        h1.formal-title { margin: 0; font-family: "DejaVu Serif", serif; font-size: 14pt; font-weight: bold; line-height: 1.3; color: {{ $ink }}; }
        .formal-sub { margin: 4pt 0 0; font-size: 10.5pt; color: {{ $slate }}; }
        .subject { margin-top: 16pt; font-weight: bold; color: {{ $ink }}; }
        .salutation { margin-top: 14pt; }
        p.para { margin: 10pt 0 0; text-align: justify; }
        ol.clauses { margin: 10pt 0 0; padding-left: 18pt; }
        ol.clauses li { margin-bottom: 7pt; text-align: justify; }
        ol.clauses .people { margin: 6pt 0 0; padding-left: 0; list-style: none; }
        ol.clauses .people li { margin: 0 0 2pt; }
        ol.clauses .people strong { color: {{ $ink }}; }
        .rich { margin-top: 11pt; }
        .rich p { margin: 0 0 10pt; text-align: justify; }
        .rich ul, .rich ol { margin: 0 0 10pt; padding-left: 18pt; }
        .rich li { margin-bottom: 4pt; }
        .rich h2 { font-family: "DejaVu Serif", serif; font-size: 12pt; color: {{ $ink }}; margin: 14pt 0 6pt; }
        .rich h3 { font-size: 10.5pt; color: {{ $ink }}; margin: 12pt 0 4pt; }
        .rich a { color: #08707C; }
        .closing { margin-top: 18pt; }

        .signatures { width: 100%; border-collapse: collapse; margin-top: 24pt; page-break-inside: avoid; }
        .signatures td { vertical-align: bottom; padding: 0 18pt 0 0; width: 50%; }
        .sig-image { height: 42pt; }
        .sig-line { border-top: 0.8pt solid {{ $ink }}; margin-top: 4pt; padding-top: 4pt; }
        .sig-name { font-weight: bold; color: {{ $ink }}; font-size: 9.5pt; }
        .sig-title { font-size: 8.4pt; color: {{ $muted }}; }
        .sig-blank { height: 46pt; }

        .verify { width: 100%; border-collapse: collapse; margin-top: 24pt; page-break-inside: avoid; }
        .verify td { vertical-align: top; padding: 0; }
        .verify .qr-cell { width: 70pt; }
        .verify .words { padding-left: 12pt; font-size: 7.6pt; color: {{ $muted }}; line-height: 1.5; }
        .verify .words strong { display: block; color: {{ $ink }}; font-size: 8.4pt; font-weight: bold; }
        .draft { margin-top: 20pt; padding: 6pt 8pt; border: 0.8pt solid #D8DEE6; background: #F2F5F7; font-size: 8pt; color: {{ $muted }}; }
    </style>
</head>
<body>

<table class="head">
    <tr>
        <td style="width: 46%;"><img src="{{ $logo }}" style="width: 186pt;" alt="{{ $details['name'] }}"></td>
        <td class="office">
            <strong>{{ $details['name'] }}</strong>
            @foreach ($details['office_lines'] as $line)
                {{ $line }}<br>
            @endforeach
            @if ($details['phone']){{ $details['phone'] }}<br>@endif
            {{ $details['email'] }}
        </td>
    </tr>
</table>
<div class="rule"></div>
<div class="rule-thin"></div>

<table class="meta">
    <tr>
        <td>Our reference: {{ $document->reference }}</td>
        <td class="date">{{ $issuedDate }}</td>
    </tr>
</table>

@if (! $final)
    <div class="draft">Draft, not yet issued. The reference, the date and every word can still be changed.</div>
@endif

@foreach ($blocks as $block)
    @switch($block['kind'])
        @case('recipient')
            @if ($block['lines'])
                <div class="recipient">
                    @foreach ($block['lines'] as $i => $line)
                        @if ($i === 0)<strong>{{ $line }}</strong><br>@else{{ $line }}<br>@endif
                    @endforeach
                </div>
            @endif
            @break

        @case('heading')
            <div class="formal-head">
                <p class="formal-company">{{ $block['company'] }}</p>
                @if ($block['registration'])<p class="formal-reg">{{ $block['registration'] }}</p>@endif
                <div class="formal-rule"></div>
                <h1 class="formal-title">{{ $block['title'] }}</h1>
                <p class="formal-sub">{{ $block['subtitle'] }}</p>
            </div>
            @break

        @case('subject')
            <p class="subject">{{ $block['text'] }}</p>
            @break

        @case('salutation')
            <p class="salutation">{{ $block['text'] }}</p>
            @break

        @case('para')
            <p class="para">{{ $block['text'] }}</p>
            @break

        @case('clauses')
            <ol class="clauses">
                @foreach ($block['items'] as $item)
                    <li>
                        {{ is_array($item) ? $item['text'] : $item }}
                        @if (is_array($item) && ! empty($item['people']))
                            <ul class="people">
                                @foreach ($item['people'] as $person)
                                    <li><strong>{{ $person['name'] }}</strong>@if ($person['title']) — {{ $person['title'] }}@endif</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ol>
            @break

        @case('html')
            <div class="rich">{!! $block['html'] !!}</div>
            @break

        @case('closing')
            <p class="closing">{{ $block['text'] }}</p>
            @break
    @endswitch
@endforeach

@if ($document->type === 'board_resolution' && ! $signatures->count())
    <p class="para">Signed by the sole director of {{ $details['name'] }}:</p>
@endif

<table class="signatures">
    <tr>
        @forelse ($signatures as $signature)
            <td>
                <img class="sig-image" src="{{ $signature->dataUri() }}" alt="">
                <div class="sig-line">
                    <span class="sig-name">{{ $signature->signatory_name }}</span><br>
                    <span class="sig-title">{{ $signature->signatory_title }}</span>
                </div>
            </td>
            @if ($loop->iteration % 2 === 0 && ! $loop->last)</tr><tr>@endif
        @empty
            <td>
                <div class="sig-blank"></div>
                <div class="sig-line">
                    <span class="sig-name">{{ $blankSignatory['name'] }}</span><br>
                    <span class="sig-title">{{ $blankSignatory['title'] }}</span>
                </div>
            </td>
            <td></td>
        @endforelse
        @if ($signatures->count() % 2 === 1)<td></td>@endif
    </tr>
</table>

@if ($qr)
    <table class="verify">
        <tr>
            <td class="qr-cell"><img src="{{ $qr }}" style="width: 62pt; height: 62pt;" alt=""></td>
            <td class="words">
                <strong>Check that this document is genuine</strong>
                Scan the code, or go to {{ $verifyUrl }}.<br>
                The page names the reference, the date it was issued and who signed it, and says whether it is still
                valid. You can drop your copy of this PDF on it to check that nothing in it has been changed.
            </td>
        </tr>
    </table>
@endif

</body>
</html>
