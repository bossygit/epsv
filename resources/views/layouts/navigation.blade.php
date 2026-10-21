@php
    $profile = \App\Models\SchoolProfile::current();
    $user = auth()->user();
    $chatUnread = \App\Models\Conversation::unreadTotalFor($user);
    $userInitials = collect(preg_split('/\s+/u', trim((string) $user?->name)))
        ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-[212px] transform flex-col bg-navy transition-transform duration-200 ease-in-out lg:translate-x-0"
       x-cloak>
    <!-- En-tête école -->
    <div class="flex h-[52px] shrink-0 items-center gap-2.5 border-b border-white/10 px-4">
        <div class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-sm border border-gold/60 font-serif text-[13px] text-gold">
            {{ mb_strtoupper(mb_substr($profile->short_name ?: 'E', 0, 1)) }}
        </div>
        <div class="min-w-0">
            <p class="truncate font-serif text-[12.5px] font-semibold leading-tight text-white">{{ $profile->short_name ?: 'EPSV' }}</p>
            <p class="truncate text-[9px] uppercase tracking-[0.14em] text-slate-400">{{ $profile->name }}</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-2 pb-4">
        <p class="px-2 pb-1 pt-4 text-[8.5px] font-semibold uppercase tracking-[0.16em] text-slate-500">Pilotage</p>
        <div class="space-y-px">
            <x-sidebar-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">
                Tableau de bord
            </x-sidebar-link>

            <x-sidebar-link
                :href="route('chat.index')"
                icon="chat"
                :active="request()->routeIs('chat.*')"
                :badge="$chatUnread"
                badge-id="chat-unread-badge"
            >
                Messagerie
            </x-sidebar-link>
        </div>

        @canany(['students.view', 'attendance.view', 'grades.view', 'report_cards.view'])
            <p class="px-2 pb-1 pt-5 text-[8.5px] font-semibold uppercase tracking-[0.16em] text-slate-500">Scolarité</p>
            <div class="space-y-px">
                @can('students.view')
                    <x-sidebar-link :href="route('students.index')" icon="users" :active="request()->routeIs('students.*')">
                        Élèves
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('enrollments.index')" icon="clipboard" :active="request()->routeIs('enrollments.*')">
                        Inscriptions
                    </x-sidebar-link>
                @endcan
                @can('attendance.view')
                    <x-sidebar-link :href="route('attendance.index')" icon="calendar" :active="request()->routeIs('attendance.*')">
                        Présences
                    </x-sidebar-link>
                @endcan
                @can('grades.view')
                    <x-sidebar-link :href="route('evaluations.index')" icon="chart" :active="request()->routeIs('evaluations.*') || request()->routeIs('grades.*')">
                        Notes &amp; évaluations
                    </x-sidebar-link>
                @endcan
                @can('report_cards.view')
                    <x-sidebar-link :href="route('report-cards.index')" icon="document" :active="request()->routeIs('report-cards.*')">
                        Bulletins
                    </x-sidebar-link>
                @endcan
            </div>
        @endcanany

        @can('structure.view')
            <p class="px-2 pb-1 pt-5 text-[8.5px] font-semibold uppercase tracking-[0.16em] text-slate-500">Structure</p>
            <div class="space-y-px">
                <x-sidebar-link :href="route('academic-years.index')" icon="calendar" :active="request()->routeIs('academic-years.*')">
                    Années scolaires
                </x-sidebar-link>
                <x-sidebar-link :href="route('classes.index')" icon="building" :active="request()->routeIs('classes.*')">
                    Classes
                </x-sidebar-link>
                <x-sidebar-link :href="route('subjects.index')" icon="book" :active="request()->routeIs('subjects.*')">
                    Matières
                </x-sidebar-link>
                <x-sidebar-link :href="route('structure.index')" icon="cog" :active="request()->routeIs('structure.*')">
                    Cycles &amp; niveaux
                </x-sidebar-link>
            </div>
        @endcan

        @canany(['users.manage', 'settings.manage', 'audit.view'])
            <p class="px-2 pb-1 pt-5 text-[8.5px] font-semibold uppercase tracking-[0.16em] text-slate-500">Administration</p>
            <div class="space-y-px">
                @can('users.manage')
                    <x-sidebar-link :href="route('users.index')" icon="users" :active="request()->routeIs('users.*')">
                        Utilisateurs
                    </x-sidebar-link>
                @endcan
                @can('settings.manage')
                    <x-sidebar-link :href="route('settings.edit')" icon="cog" :active="request()->routeIs('settings.*')">
                        Paramètres
                    </x-sidebar-link>
                @endcan
                @can('audit.view')
                    <x-sidebar-link :href="route('audit.index')" icon="shield" :active="request()->routeIs('audit.*')">
                        Journal d'audit
                    </x-sidebar-link>
                @endcan
            </div>
        @endcanany
    </nav>

    <!-- Pied : utilisateur -->
    <div class="border-t border-white/10 px-3 py-2.5">
        <div class="flex items-center gap-2">
            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-sm border border-white/10 bg-navy-800 text-[9.5px] text-slate-200">
                {{ $userInitials ?: '?' }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[11.5px] font-medium leading-tight text-white">{{ $user->name }}</p>
                <p class="truncate text-[9.5px] text-slate-400">{{ $user->getRoleNames()->map(fn ($r) => \App\Support\Roles::LABELS[$r] ?? $r)->join(', ') }}</p>
            </div>
            <div class="flex items-center">
                <a href="{{ route('profile.edit') }}" title="Mon profil"
                   class="rounded-sm p-1 text-slate-400 hover:bg-white/5 hover:text-white">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Se déconnecter"
                            class="rounded-sm p-1 text-slate-400 hover:bg-white/5 hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
