@php
    /** The blank letterhead: the header and the footer, and nothing else (docs/DOCUMENTS.md section 6). */
    $teal = \App\Support\Letters\Letterhead::TEAL;
    $ink = \App\Support\Letters\Letterhead::INK;
    $muted = \App\Support\Letters\Letterhead::MUTED;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $details['name'] }}</title>
    <style>
        @page { margin: 48pt 56pt 86pt 56pt; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: {{ $muted }}; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding: 0; }
        .head .office { text-align: right; font-size: 7.6pt; line-height: 1.5; }
        .head .office strong { display: block; font-family: "DejaVu Serif", serif; font-size: 9pt; color: {{ $ink }}; font-weight: normal; }
        .rule { height: 1.2pt; background: {{ $teal }}; margin: 10pt 0 0; font-size: 0; line-height: 0; }
        .rule-thin { height: 0.5pt; background: #D8DEE6; margin: 2pt 0 0; font-size: 0; line-height: 0; }
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
</body>
</html>
