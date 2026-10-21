<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name', 'EPSV') }}</title>

        <link rel="icon" id="app-favicon" href="/favicon.ico" type="image/x-icon">

        <!-- Fonts : Spectral (titres) + Inter (interface) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|spectral:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans antialiased"
          x-data="{ sidebarOpen: false }"
          data-authenticated="{{ auth()->check() ? '1' : '0' }}"
          data-user-id="{{ auth()->id() }}"
          data-unread-url="{{ auth()->check() ? route('chat.unread') : '' }}"
          data-push-subscribe-url="{{ auth()->check() ? route('push.subscriptions.store') : '' }}"
          data-push-disable-url="{{ auth()->check() ? route('push.subscriptions.disable') : '' }}">
        @php
            $navYear = auth()->check() ? \App\Models\AcademicYear::currentYear() : null;
            $navPeriod = $navYear?->currentPeriod();

            $section = match (true) {
                request()->routeIs('dashboard') => 'Tableau de bord',
                request()->routeIs('chat.*') => 'Messagerie',
                request()->routeIs('students.*') => 'Élèves',
                request()->routeIs('enrollments.*') => 'Inscriptions',
                request()->routeIs('attendance.*') => 'Présences',
                request()->routeIs('evaluations.*', 'grades.*') => 'Notes & évaluations',
                request()->routeIs('report-cards.*') => 'Bulletins',
                request()->routeIs('academic-years.*') => 'Années scolaires',
                request()->routeIs('classes.*') => 'Classes',
                request()->routeIs('subjects.*') => 'Matières',
                request()->routeIs('structure.*') => 'Cycles & niveaux',
                request()->routeIs('users.*') => 'Utilisateurs',
                request()->routeIs('settings.*') => 'Paramètres',
                request()->routeIs('audit.*') => "Journal d'audit",
                request()->routeIs('profile.*') => 'Mon profil',
                default => 'Gestion scolaire',
            };

            $initials = collect(preg_split('/\s+/u', trim((string) auth()->user()?->name)))
                ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
        @endphp

        <!-- Voile mobile -->
        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
             class="fixed inset-0 z-30 bg-navy-950/60 lg:hidden" x-cloak></div>

        <!-- Barre latérale -->
        @include('layouts.navigation')

        <div class="lg:pl-[212px]">
            <!-- Barre supérieure -->
            <header class="sticky top-0 z-20 flex h-[52px] items-center gap-3 border-b border-line bg-paper px-4 sm:px-5">
                <button type="button" @click="sidebarOpen = true" class="-ml-1 p-1.5 text-ink lg:hidden">
                    <span class="sr-only">Ouvrir le menu</span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="hidden min-w-0 items-center gap-2 text-[11px] text-muted sm:flex">
                    <span class="truncate">{{ \App\Models\SchoolProfile::current()->short_name ?: 'EPSV' }}</span>
                    <span class="text-line">/</span>
                    <span class="truncate font-semibold text-ink">{{ $section }}</span>
                </div>

                @can('students.view')
                    <form method="GET" action="{{ route('students.index') }}" class="relative ml-1 hidden w-full max-w-[320px] md:block">
                        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                        </svg>
                        <input type="search" name="search" value="{{ request()->routeIs('students.*') ? request('search') : '' }}"
                               placeholder="Rechercher un élève, un matricule…"
                               class="h-8 w-full rounded-sm border-line bg-ivory pl-8 pr-3 text-xs text-ink placeholder:text-gray-500 focus:border-navy focus:bg-paper focus:ring-0">
                    </form>
                @endcan

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    <div class="hidden items-center gap-1.5 rounded-sm border border-line px-2.5 py-1 text-[11px] text-ink sm:flex">
                        <span class="num font-semibold">{{ $navYear?->name ?? '—' }}</span>
                        <span class="text-line">|</span>
                        <span class="text-muted">{{ $navPeriod?->name ?? 'Hors période' }}</span>
                    </div>

                    <a href="{{ route('profile.edit') }}" title="{{ auth()->user()?->name }}"
                       class="flex h-7 w-7 items-center justify-center rounded-full bg-navy text-[10px] font-semibold text-white hover:bg-navy-800">
                        {{ $initials ?: '?' }}
                    </a>
                </div>
            </header>

            <main class="px-4 py-5 pb-12 sm:px-5 lg:px-6">
                <div class="mx-auto w-full max-w-[1400px]">
                    <x-flash-messages />
                    {{ $slot }}
                </div>
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
