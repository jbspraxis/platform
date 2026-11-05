<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.app-head', ['app' => 'portal', 'title' => $title ?? null])
</head>
{{-- Client Portal and the client's token pages (proposal, sign-off, contract check): light workspace (docs/BRAND.md section 14) --}}
<body class="bg-mist text-slate">
    <x-pwa.launch />
    {{ $slot }}
    {{-- Every confirmation and error, in the corner over the page (resources/views/components/flash.blade.php) --}}
    <x-flash />
    @livewireScripts
</body>
</html>
