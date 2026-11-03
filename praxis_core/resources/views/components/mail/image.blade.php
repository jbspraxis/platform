{{-- A picture in a broadcast: full card width (520px on computers, the screen on phones), never the only place a message is
     written (docs/BRAND.md section 13). $alt is required; $link is optional. --}}
@props(['src', 'alt' => '', 'link' => null])
@php($img = '<img src="' . e($src) . '" width="520" alt="' . e($alt) . '" style="display: block; width: 100%; max-width: 520px; height: auto; border: 0; border-radius: 4px; font-family: Arial, sans-serif; font-size: 14px; line-height: 20px; color: #5E6679;">')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 20px;"><tr><td>
@if ($link)<a href="{{ $link }}" style="text-decoration: none;">{!! $img !!}</a>@else{!! $img !!}@endif
</td></tr></table>
