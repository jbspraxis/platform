<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JBS PRAXIS | Secure Terminal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Roboto+Mono:wght@400;500;700&family=Montserrat:wght@700;900&display=swap" rel="stylesheet">
    @livewireStyles
    <style>
        body { 
            background-color: #03050A; 
            color: #FFFFFF; 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh;
            background-image: radial-gradient(circle at center, rgba(0, 229, 255, 0.05) 0%, transparent 60%);
        }
        .scanline { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, transparent 50%, rgba(0, 229, 255, 0.02) 51%); background-size: 100% 4px; pointer-events: none; z-index: 10; }
    </style>
</head>
<body>
    <div class="scanline"></div>
    {{ $slot }}
    @livewireScripts
</body>
</html>