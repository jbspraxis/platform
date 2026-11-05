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
    // Every template but the introduction letter writes English; that one follows the author's choice
    $locale = $locale ?? 'en';
    $fr = $locale === 'fr';
    // The introduction letter is written to hold one page in both languages; it is set a little tighter
    $compact = $compact ?? false;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->reference }}</title>
    <style>
        @page { margin: {{ $compact ? '40pt' : '48pt' }} 56pt {{ $compact ? '78pt' : '86pt' }} 56pt; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: {{ $compact ? '9.3pt' : '10pt' }}; line-height: {{ $compact ? '1.33' : '1.4' }}; color: {{ $slate }}; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding: 0; }
        .head .office { text-align: right; font-size: 7.6pt; line-height: 1.5; color: {{ $muted }}; }
        .head .office strong { display: block; font-family: "DejaVu Serif", serif; font-size: 9pt; color: {{ $ink }}; font-weight: normal; }
        .rule { height: 1.2pt; background: {{ $teal }}; margin: 10pt 0 0; font-size: 0; line-height: 0; }
        .rule-thin { height: 0.5pt; background: #D8DEE6; margin: 2pt 0 0; font-size: 0; line-height: 0; }

        .meta { width: 100%; border-collapse: collapse; margin-top: 20pt; font-size: 8.4pt; color: {{ $muted }}; }
        .meta td { padding: 0; vertical-align: top; }
        .meta .date { text-align: right; }

        .recipient { margin-top: {{ $compact ? '12pt' : '18pt' }}; font-size: {{ $compact ? '9.3pt' : '10pt' }}; color: {{ $ink }}; line-height: {{ $compact ? '1.35' : '1.45' }}; }
        .formal-head { margin-top: 14pt; text-align: center; }
        .formal-company { margin: 0; font-family: "DejaVu Serif", serif; font-size: 12pt; letter-spacing: 0.6pt; color: {{ $ink }}; }
        .formal-reg { margin: 2pt 0 0; font-size: 8pt; color: {{ $muted }}; }
        .formal-rule { width: 70pt; height: 1pt; background: {{ $teal }}; margin: 8pt auto; font-size: 0; line-height: 0; }
        h1.formal-title { margin: 0; font-family: "DejaVu Serif", serif; font-size: 14pt; font-weight: bold; line-height: 1.3; color: {{ $ink }}; }
        .formal-sub { margin: 4pt 0 0; font-size: 10.5pt; color: {{ $slate }}; }
        .subject { margin-top: {{ $compact ? '12pt' : '16pt' }}; font-weight: bold; color: {{ $ink }}; }
        .salutation { margin-top: {{ $compact ? '10pt' : '14pt' }}; }
        p.para { margin: {{ $compact ? '6pt' : '8pt' }} 0 0; text-align: justify; }
        ol.clauses { margin: 8pt 0 0; padding-left: 18pt; }
        ol.clauses li { margin-bottom: 5pt; text-align: justify; }
        ol.clauses .people { margin: 6pt 0 0; padding-left: 0; list-style: none; }
        ol.clauses .people li { margin: 0 0 2pt; }
        ol.clauses .people strong { color: {{ $ink }}; }
        ul.points { margin: {{ $compact ? '6pt' : '8pt' }} 0 0; padding-left: 14pt; }
        ul.points li { margin-bottom: {{ $compact ? '3pt' : '4pt' }}; text-align: left; }
        .rich { margin-top: 11pt; }
        .rich p { margin: 0 0 10pt; text-align: justify; }
        .rich ul, .rich ol { margin: 0 0 10pt; padding-left: 18pt; }
        .rich li { margin-bottom: 4pt; }
        .rich h2 { font-family: "DejaVu Serif", serif; font-size: 12pt; color: {{ $ink }}; margin: 14pt 0 6pt; }
        .rich h3 { font-size: 10.5pt; color: {{ $ink }}; margin: 12pt 0 4pt; }
        .rich a { color: #08707C; }
        .closing { margin-top: {{ $compact ? '12pt' : '18pt' }}; }

        .sign-off { page-break-inside: avoid; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: {{ $compact ? '10pt' : '18pt' }}; page-break-inside: avoid; }
        .signatures td { vertical-align: bottom; padding: 0 18pt 0 0; width: 50%; }
        .sig-image { height: 38pt; }
        .sig-line { border-top: 0.8pt solid {{ $ink }}; margin-top: 4pt; padding-top: 4pt; }
        .sig-name { font-weight: bold; color: {{ $ink }}; font-size: 9.5pt; }
        .sig-title { font-size: 8.4pt; color: {{ $muted }}; }
        .sig-blank { height: {{ $compact ? '24pt' : '34pt' }}; }

        .verify { width: 100%; border-collapse: collapse; margin-top: {{ $compact ? '14pt' : '24pt' }}; page-break-inside: avoid; }
        .verify td { vertical-align: top; padding: 0; }
        .verify .qr-cell { width: 70pt; }
        .verify .words { padding-left: 12pt; font-size: 7.6pt; color: {{ $muted }}; line-height: 1.5; }
        .verify .words strong { display: block; color: {{ $ink }}; font-size: 8.4pt; font-weight: bold; }
        .draft { margin-top: 20pt; padding: 6pt 8pt; border: 0.8pt solid #D8DEE6; background: #F2F5F7; font-size: 8pt; color: {{ $muted }}; }

        /* An agreement (docs/DOCUMENTS.md section 12): a title block, schedules as tables, and two signature boxes */
        .agr-head { margin-top: 18pt; text-align: center; }
        h1.agr-title { margin: 0; font-family: "DejaVu Serif", serif; font-size: 16pt; font-weight: bold; line-height: 1.3; color: {{ $ink }}; }
        .agr-rule { width: 70pt; height: 1pt; background: {{ $teal }}; margin: 8pt auto; font-size: 0; line-height: 0; }
        .agr-sub { margin: 0; font-size: 8.4pt; color: {{ $muted }}; }
        /* dompdf has no page-break-after: avoid, so LetterBody wraps a heading and its text in one of these */
        .keep { page-break-inside: avoid; }
        .rich table { width: 100%; border-collapse: collapse; margin: 10pt 0; font-size: 9pt; }
        .rich table p { margin: 0; text-align: left; }
        .rich th, .rich td { border: 0.5pt solid #D8DEE6; padding: 4pt 6pt; text-align: left; vertical-align: top; }
        .rich th { background: #F2F5F7; color: {{ $ink }}; font-weight: bold; }
        .rich tr { page-break-inside: avoid; }
        .agr-parties { margin-top: 20pt; page-break-inside: avoid; }
        /* Both boxes start at the same height, so the two signature lines sit level whoever has signed */
        .agr-parties .signatures td { vertical-align: top; }
        .agr-parties .sig-image, .agr-parties .sig-blank { height: 38pt; }
        .agr-parties .sig-line { border-top: 0.8pt solid {{ $ink }}; margin-top: 4pt; padding-top: 4pt; }
        .sig-party { display: block; font-size: 8pt; color: {{ $muted }}; margin-bottom: 2pt; }
        .sig-extra { display: block; font-size: 8pt; color: {{ $muted }}; }
        .sig-date { display: block; margin-top: 3pt; font-size: 8.4pt; color: {{ $muted }}; }

        .record { page-break-before: always; }
        h2.record-title { margin: 14pt 0 0; font-family: "DejaVu Serif", serif; font-size: 13pt; color: {{ $ink }}; }
        .record-intro { margin: 6pt 0 0; font-size: 9pt; color: {{ $slate }}; }
        table.record-table { width: 100%; border-collapse: collapse; margin-top: 12pt; font-size: 8.8pt; }
        table.record-table th { width: 32%; padding: 4pt 6pt; border-bottom: 0.5pt solid #D8DEE6; text-align: left; vertical-align: top; color: {{ $muted }}; font-weight: normal; }
        table.record-table td { padding: 4pt 6pt; border-bottom: 0.5pt solid #D8DEE6; text-align: left; vertical-align: top; color: {{ $ink }}; }
        .record-group { margin-top: 14pt; font-weight: bold; color: {{ $ink }}; font-size: 9.4pt; }
        .record-hash { margin-top: 14pt; padding: 6pt 8pt; border: 0.5pt solid #D8DEE6; background: #F7F9FB; font-size: 7.6pt; color: {{ $muted }}; word-wrap: break-word; }
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
        <td>{{ $fr ? 'Notre référence :' : 'Our reference:' }} {{ $document->reference }}</td>
        <td class="date">{{ $issuedDate }}</td>
    </tr>
</table>

@if (! $final)
    <div class="draft">{{ $fr
        ? 'Brouillon, pas encore émis. La référence, la date et chaque mot peuvent encore changer.'
        : 'Draft, not yet issued. The reference, the date and every word can still be changed.' }}</div>
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

        @case('agreement_head')
            <div class="agr-head">
                <h1 class="agr-title">{{ $block['title'] }}</h1>
                <div class="agr-rule"></div>
                <p class="agr-sub">{{ $block['reference'] }} · {{ $block['date'] }}</p>
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

        @case('points')
            <ul class="points">
                @foreach ($block['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
            @break

        @case('closing')
            <p class="closing">{{ $block['text'] }}</p>
            @break
    @endswitch
@endforeach

@if ($isAgreement)
{{--
    An agreement is signed by both sides: JBS Praxis in HQ, and the other party online (docs/DOCUMENTS.md
    section 12). Each box prints the name and the role whether or not it has been signed, so an unsigned copy can
    be signed with a pen and still says who signs what.
--}}
@php
    $jbs = $signatures->first();
    $signedDay = fn ($at) => $at ? \Carbon\Carbon::parse($at)->timezone('Africa/Lagos')->format('j F Y') : null;
@endphp
<div class="sign-off agr-parties">
    <p class="para">Signed by the parties on the dates shown.</p>
    <table class="signatures">
        <tr>
            <td>
                @if ($jbs)
                    <img class="sig-image" src="{{ $jbs->dataUri() }}" alt="">
                @else
                    <div class="sig-blank"></div>
                @endif
                <div class="sig-line">
                    <span class="sig-party">For and on behalf of {{ $details['name'] }}</span>
                    <span class="sig-name">{{ $jbs->signatory_name ?? $blankSignatory['name'] }}</span><br>
                    <span class="sig-title">{{ $jbs->signatory_title ?? $blankSignatory['title'] }}</span>
                    <span class="sig-date">Date: {{ $signedDay($jbs?->signed_at) ?: '........................' }}</span>
                </div>
            </td>
            @foreach ($counterparties as $party)
                <td>
                    @if ($countersigned && $party->dataUri())
                        <img class="sig-image" src="{{ $party->dataUri() }}" alt="">
                    @else
                        <div class="sig-blank"></div>
                    @endif
                    <div class="sig-line">
                        @if ($party->company)<span class="sig-party">For and on behalf of {{ $party->company }}</span>@endif
                        <span class="sig-name">{{ $countersigned && $party->signed_name ? $party->signed_name : $party->name }}</span><br>
                        <span class="sig-title">{{ $party->role_title }}</span>
                        @if ($party->company && $party->registration)
                            <span class="sig-extra">{{ $party->registration }}</span>
                        @endif
                        <span class="sig-date">Date: {{ ($countersigned ? $signedDay($party->signed_at) : null) ?: '........................' }}</span>
                    </div>
                </td>
                @if ($loop->iteration % 1 === 0 && ! $loop->last)</tr><tr><td></td>@endif
            @endforeach
        </tr>
    </table>
</div>
@else
<div class="sign-off">
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
</div>
@endif

@if ($qr)
    <table class="verify">
        <tr>
            <td class="qr-cell"><img src="{{ $qr }}" style="width: 62pt; height: 62pt;" alt=""></td>
            <td class="words">
                @if ($fr)
                    <strong>Vérifiez que ce document est authentique</strong>
                    Scannez le code, ou allez sur {{ $verifyUrl }}.<br>
                    La page indique la référence, la date d’émission et les signataires, et dit si le document est
                    toujours valable. Vous pouvez y déposer votre copie de ce PDF pour vérifier que rien n’y a été changé.
                @else
                    <strong>Check that this document is genuine</strong>
                    Scan the code, or go to {{ $verifyUrl }}.<br>
                    The page names the reference, the date it was issued and who signed it, and says whether it is still
                    valid. You can drop your copy of this PDF on it to check that nothing in it has been changed.
                @endif
            </td>
        </tr>
    </table>
@endif

@if ($countersigned)
{{--
    The last page of the signed version (docs/DOCUMENTS.md section 12): who signed, when, from where, and when
    the code emailed to them was accepted. It also prints the fingerprint of the document as it was issued, which
    is how this version points back at the one that went out: that file never changes.
--}}
<div class="record">
    <h2 class="record-title">Signature record</h2>
    <p class="record-intro">
        This page records how {{ $document->reference }} was signed. It is part of the signed version only; the
        document issued on {{ $issuedDate }} is unchanged and keeps its own fingerprint, printed below.
    </p>

    <p class="record-group">{{ $details['name'] }}</p>
    @foreach ($signatures as $signature)
        <table class="record-table">
            <tr><th>Signed by</th><td>{{ $signature->signatory_name }}{{ $signature->signatory_title ? ', ' . $signature->signatory_title : '' }}</td></tr>
            <tr><th>Signed on</th><td>{{ \Carbon\Carbon::parse($signature->signed_at)->timezone('Africa/Lagos')->format('j F Y, H:i') }} (Lagos time)</td></tr>
            <tr><th>Signed in</th><td>JBS Praxis HQ, signed in and with the password confirmed{{ $signature->ip_address ? ', from ' . $signature->ip_address : '' }}</td></tr>
        </table>
    @endforeach

    @foreach ($counterparties as $party)
        <p class="record-group">{{ $party->company ?: $party->name }}</p>
        <table class="record-table">
            <tr><th>Signed by</th><td>{{ $party->signed_name ?: $party->name }}{{ $party->role_title ? ', ' . $party->role_title : '' }}</td></tr>
            <tr><th>Named on the agreement as</th><td>{{ $party->name }}</td></tr>
            <tr><th>Email address</th><td>{{ $party->email }}</td></tr>
            <tr><th>Signed on</th><td>{{ $party->signed_at ? \Carbon\Carbon::parse($party->signed_at)->timezone('Africa/Lagos')->format('j F Y, H:i') . ' (Lagos time)' : '—' }}</td></tr>
            <tr><th>Code verified</th><td>{{ $party->code_verified_at ? \Carbon\Carbon::parse($party->code_verified_at)->timezone('Africa/Lagos')->format('j F Y, H:i') . ' (Lagos time)' : '—' }}</td></tr>
            <tr><th>From</th><td>{{ $party->ip_address ?: '—' }}</td></tr>
            <tr><th>How</th><td>A private link sent to the address above, then a one-time code emailed to it and entered on the signing page.</td></tr>
        </table>
    @endforeach

    <p class="record-hash">
        Fingerprint of the document as issued (SHA-256): {{ $document->file_sha256 ?: 'not recorded' }}
    </p>
</div>
@endif

</body>
</html>
