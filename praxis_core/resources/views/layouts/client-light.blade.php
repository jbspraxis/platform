<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Project Tracker | {{ config('app.name', 'JBS Praxis') }}</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    @livewireStyles
    @stack('styles')
</head>
<body style="margin: 0; padding: 0; background-color: #F8FAFC; color: #111827; -webkit-font-smoothing: antialiased;">
    {{ $slot }}
    @livewireScripts
</body>
</html>