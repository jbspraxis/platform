{{-- A one-time code: large, spaced, tabular figures on Mist, then how long it works. Never put the code in the subject. --}}
@props(['code', 'minutes' => 10])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 16px;"><tr>
<td style="padding: 16px 24px; background: #F2F5F7; border-radius: 4px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 30px; line-height: 36px; font-weight: 600; letter-spacing: 6px; color: #0C142B; font-variant-numeric: tabular-nums;">{{ $code }}</td>
</tr></table>
<p style="margin: 0 0 16px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 14px; line-height: 22px; color: #5E6679;">{{ __('emails.common.code_expiry', ['minutes' => $minutes]) }}</p>
