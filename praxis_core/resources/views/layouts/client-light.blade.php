<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.app-head', ['app' => 'portal', 'title' => $title ?? __('gateway.tracker.page_title')])
</head>
{{-- The mobile project tracker (clients and the delivery team) --}}
<body class="bg-mist text-slate">
    {{ $slot }}
    @livewireScripts
</body>
</html>
