{{-- Key figures in a broadcast: up to three values side by side (stacked on phones), each with its label.
     Tabular figures in Newsreader, Deep Teal; the label 14/20 Muted. Empty values are left out. --}}
@props(['items' => []])
@php($items = array_values(array_filter($items, fn ($i) => trim((string) ($i['value'] ?? '')) !== '')))
@if ($items)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 24px; border-top: 1px solid #D8DEE6; border-bottom: 1px solid #D8DEE6;"><tr>
@foreach ($items as $item)
    <td class="mail-col" valign="top" width="{{ (int) floor(100 / count($items)) }}%" style="padding: 16px {{ $loop->last ? '0' : '16px' }} 16px 0;">
        <p class="serif" style="margin: 0; font-family: 'Newsreader', Georgia, serif; font-weight: 400; font-size: 32px; line-height: 38px; color: #08707C; font-variant-numeric: tabular-nums;">{{ $item['value'] }}</p>
        @if (trim((string) ($item['label'] ?? '')) !== '')
            <p style="margin: 4px 0 0; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 14px; line-height: 20px; color: #5E6679;">{{ $item['label'] }}</p>
        @endif
    </td>
@endforeach
</tr></table>
@endif
