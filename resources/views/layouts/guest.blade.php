{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/deker-icon-32.png') }}">
    <link rel="icon" type="image/png" sizes="180x180" href="{{ asset('images/deker-icon-180.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased bg-gray-100 dark:bg-gray-900 dark:text-gray-100 relative">
    <div class="min-h-screen flex flex-col items-center justify-center pt-6 sm:pt-0">

        {{-- Logo principal DEKER --}}
        <div class="w-full flex justify-center mb-6">
            <img src="{{ asset('images/deker.png') }}"
                 alt="DEKER"
                 class="h-64 w-auto drop-shadow-lg">
        </div>

        {{-- Conteúdo principal (slot) --}}
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            {{ $slot }}
        </div>
    </div>

    {{-- Marca da empresa no canto inferior direito --}}
    <div class="absolute bottom-4 right-4 opacity-80 text-sm text-gray-600 dark:text-gray-400 flex items-center gap-2">
        <span>Desenvolvido por</span>
        <img src="{{ asset('images/IntareTech_Horizontal.png') }}"
             alt="IntareTech Sistemas e Soluções"
             class="h-6 w-auto">
    </div>
</body>
</html>
