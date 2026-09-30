<!DOCTYPE html>
<html lang="es" class="h-full bg-[#fbfbfa] text-zinc-800">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? (__('Iniciar Sesión - ') . config('app.name')) }}</title>
    
    <!-- PWA & Favicon -->
    <link rel="manifest" href="{{ asset('site.webmanifest') }}?v=3">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <meta name="app-name" content="{{ config('app.name') }}">
    <meta name="theme-color" content="#fbfbfa">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=3">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance

    <style>
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f7f7f5;
        }
    </style>
</head>
<body class="h-full antialiased selection:bg-stone-200">
    {{ $slot }}

    @livewireScripts
</body>
</html>
