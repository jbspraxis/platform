{{-- Label and value rows (booking details, team alerts). Empty values are left out. --}}
@props(['rows' => []])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 20px; border-top: 1px solid #D8DEE6;">
@foreach ($rows as $label => $value)
    @continue(blank($value))
    <tr>
        <td valign="top" style="width: 36%; padding: 8px 12px 8px 0; border-bottom: 1px solid #D8DEE6; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 14px; line-height: 22px; color: #5E6679;">{{ $label }}<!--text:sep--></td>
        <td valign="top" style="padding: 8px 0; border-bottom: 1px solid #D8DEE6; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 14px; line-height: 22px; font-weight: 500; color: #0C142B; font-variant-numeric: tabular-nums;">{{ $value }}</td>
    </tr>
@endforeach
</table>
