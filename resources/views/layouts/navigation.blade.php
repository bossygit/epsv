@php
    $profile = \App\Models\SchoolProfile::current();
    $user = auth()->user();
    $chatUnread = \App\Models\Conversation::unreadTotalFor($user);
@endphp

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 flex w-64 transform flex-col bg-slate-900 transition-transform duration-200 ease-in-out lg:translate-x-0"
       x-cloak>
    <!-- En-tête école -->
    <div class="flex h-16 shrink-0 items-center gap-x-3 border-b border-white/10 px-4">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">
            {{ $profile->short_name ?: 'E' }}
        </div>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">{{ $profile->short_name ?: $profile->name }}</p>
            <p class="truncate text-xs text-slate-400">{{ $profile->name }}</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        <div class="space-y-1">
            <x-sidebar-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">
                Tableau de bord
            </x-sidebar-link>
        </div>

        <div>
            <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Communication</p>
            <div class="space-y-1">
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
        </div>

        @canany(['students.view', 'attendance.view', 'grades.view', 'report_cards.view'])
            <div>
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Scolarité</p>
                <div class="space-y-1">
                    @can('students.view')
                        <x-sidebar-link :href="route('students.index')" icon="users" :active="request()->routeIs('students.*')">
                            Élèves
                        </x-sidebar-link>
                    @endcan
                    @can('students.view')
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
                            Notes & évaluations
                        </x-sidebar-link>
                    @endcan
                    @can('report_cards.view')
                        <x-sidebar-link :href="route('report-cards.index')" icon="document" :active="request()->routeIs('report-cards.*')">
                            Bulletins
                        </x-sidebar-link>
                    @endcan
                </div>
            </div>
        @endcanany

        @can('structure.view')
            <div>
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Structure</p>
                <div class="space-y-1">
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
                        Cycles & niveaux
                    </x-sidebar-link>
                </div>
            </div>
        @endcan

        @canany(['users.manage', 'settings.manage', 'audit.view'])
            <div>
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Administration</p>
                <div class="space-y-1">
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
            </div>
        @endcanany
    </nav>

    <!-- Pied : utilisateur -->
    <div class="border-t border-white/10 p-3">
        <div class="flex items-center justify-between gap-x-2">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ $user->getRoleNames()->map(fn ($r) => \App\Support\Roles::LABELS[$r] ?? $r)->join(', ') }}</p>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('profile.edit') }}" title="Mon profil"
                   class="rounded-md p-1.5 text-slate-400 hover:bg-white/5 hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Se déconnecter"
                            class="rounded-md p-1.5 text-slate-400 hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
