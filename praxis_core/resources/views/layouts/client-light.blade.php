<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.app-head', ['app' => 'portal', 'title' => $title ?? 'Project tracker'])
</head>
{{-- The mobile project tracker (clients and the delivery team) --}}
<body class="bg-mist text-slate">
    {{ $slot }}
    @livewireScripts
</body>
</html>
