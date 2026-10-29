<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.app-head', ['app' => 'portal', 'title' => $title ?? null])
</head>
{{-- Client Portal and the client's token pages (proposal, sign-off, contract check): light workspace (docs/BRAND.md section 14) --}}
<body class="bg-mist text-slate">
    {{ $slot }}
    @livewireScripts
</body>
</html>
