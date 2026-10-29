{{-- The one primary action: a bulletproof button (VML for Outlook), full width on phones, then the link written out for clients that drop buttons. --}}
@props(['url', 'label'])
@php($width = max(200, mb_strlen($label) * 9 + 48))
<table role="presentation" class="mail-btn" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 20px;"><tr><td bgcolor="#08707C" style="border-radius: 4px; background: #08707C;">
<!--[if mso]><v:roundrect href="{{ $url }}" style="height: 46px; v-text-anchor: middle; width: {{ $width }}px;" arcsize="9%" stroke="f" fillcolor="#08707C"><w:anchorlock/><center style="color: #FFFFFF; font-family: Arial, sans-serif; font-size: 15px; font-weight: 600;">{{ $label }}</center></v:roundrect><![endif]-->
<!--[if !mso]><!--><a href="{{ $url }}" data-text="button" style="display: inline-block; padding: 13px 22px; border-radius: 4px; background: #08707C; color: #FFFFFF; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 15px; font-weight: 600; line-height: 20px; text-align: center; text-decoration: none;">{{ $label }}</a><!--<![endif]-->
</td></tr></table>
<!--text:skip--><p style="margin: 0 0 20px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 13px; line-height: 20px; color: #5E6679;">{{ __('emails.common.button_fallback') }}<br><a href="{{ $url }}" style="color: #08707C; word-break: break-all;">{{ $url }}</a></p><!--/text:skip-->
