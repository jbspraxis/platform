{{-- The one email layout (docs/BRAND.md section 13). Literal hex only: CSS variables do not work in email.
     Slots: the body; optional $reason (why the person got this email). Props below. --}}
@props([
    'preheader' => '',        // the grey line inboxes show after the subject
    'headline' => null,       // Newsreader headline at the top of the card
    'unsubscribeUrl' => null, // broadcasts only
    'internal' => false,      // team alerts: no contact line, English only
])
@php
    $logo = config('mail.brand.logo');
    $site = config('mail.brand.site_url');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $headline ?? 'JBS Praxis' }}</title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  body { margin: 0; padding: 0; width: 100% !important; background: #F2F5F7; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
  table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
  img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
  a { color: #08707C; }
  @media (max-width: 620px) {
    .mail-outer { padding: 0 !important; }
    .mail-card { width: 100% !important; border-radius: 0 !important; }
    .mail-pad { padding-left: 24px !important; padding-right: 24px !important; }
    .mail-h1 { font-size: 24px !important; line-height: 30px !important; }
  }
</style>
<!--[if mso]><style>h1, h2, .serif { font-family: Georgia, serif !important; } td, p, a, li, span { font-family: Arial, sans-serif !important; }</style><![endif]-->
</head>
<body style="margin: 0; padding: 0; background: #F2F5F7;">
<!--text:skip--><div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">{{ $preheader }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div><!--/text:skip-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #F2F5F7;">
<tr><td align="center" class="mail-outer" style="padding: 32px 16px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" class="mail-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 600px; max-width: 600px; background: #FFFFFF; border-radius: 8px;">
    <tr>
        <td class="mail-pad" style="padding: 20px 40px 12px; background: #FFFFFF; border-radius: 8px 8px 0 0;">
            <a href="{{ $site }}" style="text-decoration: none;"><img src="{{ $logo }}" width="176" height="48" alt="JBS Praxis" style="display: block; width: 176px; height: 48px; border: 0;"></a>
        </td>
    </tr>
    <tr>
        <td style="padding: 0; font-size: 0; line-height: 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
                <td width="50%" height="2" bgcolor="#0C142B" style="height: 2px; font-size: 0; line-height: 0; background: #0C142B;">&nbsp;</td>
                <td width="50%" height="2" bgcolor="#0C8B9B" style="height: 2px; font-size: 0; line-height: 0; background: #0C8B9B;">&nbsp;</td>
            </tr></table>
        </td>
    </tr>
    <tr>
        <td class="mail-pad" style="padding: 32px 40px 16px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 16px; line-height: 26px; color: #4A5468;">
            @if ($headline)
                <h1 class="mail-h1 serif" style="margin: 0 0 20px; font-family: 'Newsreader', Georgia, serif; font-weight: 400; font-size: 28px; line-height: 34px; color: #0C142B;">{{ $headline }}</h1>
            @endif
            {{ $slot }}
        </td>
    </tr>
    <tr>
        <td class="mail-pad" style="padding: 20px 40px 28px; border-top: 1px solid #D8DEE6; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 13px; line-height: 20px; color: #5E6679;">
            <p style="margin: 0;">{{ __('emails.common.footer_place') }}</p>
            @isset($reason)
                <p style="margin: 0;">{{ $reason }}</p>
            @endisset
            @unless ($internal)
                <p style="margin: 8px 0 0;">{{ __('emails.common.contact_line', ['email' => config('mail.brand.reply_to.address')]) }}</p>
            @endunless
            @if ($unsubscribeUrl)
                <p style="margin: 8px 0 0;"><a href="{{ $unsubscribeUrl }}" style="color: #5E6679; text-decoration: underline;">{{ __('emails.common.unsubscribe') }}</a></p>
            @endif
        </td>
    </tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
