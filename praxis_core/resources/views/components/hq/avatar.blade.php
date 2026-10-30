{{-- A person's avatar: their photo (uploaded on My profile) or their initials. Decorative: the name is always next to it.
     $user: a User, or a database row / array with id, first_name, last_name and avatar_path (or name). $size: px, optional. --}}
@props(['user', 'size' => null])
@php
    $get = fn (string $key) => is_array($user) ? ($user[$key] ?? null) : ($user->{$key} ?? null);
    $path = $user ? $get('avatar_path') : null;
    $id = $user ? $get('id') : null;
    $url = $path && $id ? route('hq.avatar', ['user' => $id, 'v' => substr(sha1($path), 0, 10)], false) : null;
    $first = (string) ($user ? $get('first_name') : '');
    $last = (string) ($user ? $get('last_name') : '');
    if ($first === '' && $last === '' && $user) {
        $words = preg_split('/\s+/', trim((string) $get('name')), -1, PREG_SPLIT_NO_EMPTY);
        $first = $words[0] ?? '';
        $last = count($words) > 1 ? end($words) : '';
    }
    $initials = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: '?';
    $sizeStyle = $size ? "width: {$size}px; height: {$size}px; font-size: " . round($size * 0.38) . 'px;' : '';
@endphp
@if ($url)
    <img src="{{ $url }}" alt="" aria-hidden="true" {{ $attributes->merge(['class' => 'avatar']) }} style="object-fit: cover; {{ $sizeStyle }}" loading="lazy" decoding="async">
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'avatar']) }} @if ($sizeStyle) style="{{ $sizeStyle }}" @endif>{{ $initials }}</span>
@endif
