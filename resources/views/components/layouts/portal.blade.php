<!DOCTYPE html>
<html lang="es" class="h-full bg-[#f8f8f6] text-zinc-800 antialiased selection:bg-amber-200">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>{{ $title ?? 'Seguimiento de Pedidos · Kudos Print Media' }}</title>

    <!-- Web App & Styling -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#ffffff">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=3">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f8f6;
            -webkit-tap-highlight-color: transparent;
        }
        /* Custom scrollbar for mobile drawers & timelines */
        .portal-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .portal-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(212, 212, 216, 0.6);
            border-radius: 9999px;
        }
    </style>
</head>
<body class="min-h-full flex flex-col justify-between overflow-x-hidden">
    {{ $slot }}

    @livewireScripts
</body>
</html>
