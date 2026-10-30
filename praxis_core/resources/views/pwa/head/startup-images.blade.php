{{-- iPhone and iPad startup images (docs/BRAND.md section 14): the first frame of the launch animation, the letters
     and HQ on Ink, so the installed app opens on exactly what the animation starts from. One image per device size
     and orientation; device-width and device-height are always the portrait values. scripts/pwa/startup_images.py
     reads $devices below and renders the files into resources/pwa/startup. --}}
@php
    // CSS width and height in portrait, and pixel ratio, of every iPhone and iPad that can add HQ to its home screen
    $devices = [
        [320, 568, 2], [375, 667, 2], [414, 736, 3], [375, 812, 3], [414, 896, 2], [414, 896, 3], [390, 844, 3],
        [428, 926, 3], [393, 852, 3], [430, 932, 3], [402, 874, 3], [440, 956, 3], [420, 912, 3],
        [768, 1024, 2], [810, 1080, 2], [834, 1112, 2], [820, 1180, 2], [834, 1194, 2], [1024, 1366, 2],
        [744, 1133, 2], [1032, 1376, 2], [834, 1210, 2],
    ];
@endphp
@foreach ($devices as [$w, $h, $r])
@foreach (['portrait' => [$w * $r, $h * $r], 'landscape' => [$h * $r, $w * $r]] as $orientation => [$pw, $ph])
<link rel="apple-touch-startup-image" media="screen and (device-width: {{ $w }}px) and (device-height: {{ $h }}px) and (-webkit-device-pixel-ratio: {{ $r }}) and (orientation: {{ $orientation }})" href="{{ \App\Support\Pwa\Pwa::asset("startup/hq-{$pw}x{$ph}.png") }}">
@endforeach
@endforeach
