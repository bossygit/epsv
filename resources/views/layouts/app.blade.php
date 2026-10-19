<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name', 'EPSV') }}</title>

        <link rel="icon" id="app-favicon" href="/favicon.ico" type="image/x-icon">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-gray-100 font-sans antialiased"
          x-data="{ sidebarOpen: false }"
          data-authenticated="{{ auth()->check() ? '1' : '0' }}"
          data-user-id="{{ auth()->id() }}"
          data-unread-url="{{ auth()->check() ? route('chat.unread') : '' }}"
          data-push-subscribe-url="{{ auth()->check() ? route('push.subscriptions.store') : '' }}"
          data-push-disable-url="{{ auth()->check() ? route('push.subscriptions.disable') : '' }}">
        <!-- Voile mobile -->
        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
             class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" x-cloak></div>

        <!-- Barre latérale -->
        @include('layouts.navigation')

        <div class="lg:pl-64">
            <!-- Barre supérieure mobile -->
            <div class="sticky top-0 z-20 flex items-center gap-x-4 border-b border-gray-200 bg-white px-4 py-3 lg:hidden">
                <button type="button" @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-gray-700">
                    <span class="sr-only">Ouvrir le menu</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <div class="text-sm font-semibold text-gray-900">{{ config('app.name') }}</div>
            </div>

            <main class="py-6">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <x-flash-messages />
                    {{ $slot }}
                </div>
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
