<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicons -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/deker-icon-32.png') }}">
        <link rel="icon" type="image/png" sizes="180x180" href="{{ asset('images/deker-icon-180.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Helper global para formulários JS que postam via fetch --}}
        <script>window.CSRF_TOKEN = '{{ csrf_token() }}';</script>

        {{-- Espaço opcional para páginas incluírem estilos extras --}}
        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 dark:text-gray-100">
            {{-- Navegação principal fixa --}}
            @include('layouts.navigation')

            {{-- Cabeçalho da página (funciona com componente OU com @section('header')) --}}
            @if (isset($header))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @elseif (View::hasSection('header'))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        @yield('header')
                    </div>
                </header>
            @endif

            {{-- Conteúdo da página (componente: $slot | layout clássico: @section('content')) --}}
            <main class="pb-20">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
        </div>

        {{-- Rodapé fixo com a marca IntareTech --}}
        <footer class="fixed bottom-0 inset-x-0 z-40 bg-white/90 dark:bg-gray-800/90 backdrop-blur border-t border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto h-12 px-4 flex items-center justify-end">
                <span class="mr-2 text-sm text-gray-700 dark:text-gray-300">Desenvolvido por</span>
                <img src="{{ asset('images/IntareTech_Horizontal.png') }}"
                     alt="IntareTech Sistemas e Soluções"
                     class="h-6 w-auto opacity-90">
            </div>
        </footer>

        {{-- Espaço opcional para páginas incluírem scripts extras --}}
        @stack('scripts')
        @stack('modals')
    </body>
</html>
