@php
    $slotOptions = [
        \App\Models\AttendanceSession::SLOT_MORNING => 'Matin',
        \App\Models\AttendanceSession::SLOT_AFTERNOON => 'Après-midi',
        \App\Models\AttendanceSession::SLOT_FULLDAY => 'Journée',
    ];
    $today = now()->toDateString();
@endphp

<x-app-layout>
    <x-page-header
        title="Présences"
        subtitle="{{ $year ? 'Année scolaire '.$year->name.' — '.$classes->count().' classe(s) accessible(s)' : 'Aucune année scolaire active' }}"
    />

    <div class="space-y-6">
        @can('attendance.take')
            <x-card
                title="Nouvel appel"
                subtitle="Ouvrez une séance d'appel pour une classe, puis saisissez les présences."
                :padding="false"
            >
                @if ($classes->isEmpty())
                    <div class="px-4 py-4 sm:px-6">
                        <x-empty-state message="Aucune classe ne vous est affectée pour l'appel." />
                    </div>
                @else
                    <div class="hidden border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:grid sm:grid-cols-12 sm:gap-3 sm:px-6 sm:py-2.5">
                        <div class="sm:col-span-5">Classe</div>
                        <div class="sm:col-span-3">Date</div>
                        <div class="sm:col-span-2">Créneau</div>
                        <div class="sm:col-span-2 text-right">Action</div>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @foreach ($classes as $class)
                            <form method="POST" action="{{ route('attendance.take', ['schoolClass' => $class->id]) }}"
                                  class="grid grid-cols-1 gap-3 px-4 py-3 hover:bg-gray-50 sm:grid-cols-12 sm:items-center sm:px-6">
                                @csrf

                                <div class="min-w-0 sm:col-span-5">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $class->name }}</p>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ $class->gradeLevel?->name }}
                                        — {{ $class->active_enrollments_count }} élève(s) inscrit(s)
                                    </p>
                                </div>

                                <div class="sm:col-span-3">
                                    <x-input-label :for="'take-date-'.$class->id" value="Date" class="sm:hidden" />
                                    <x-text-input
                                        :id="'take-date-'.$class->id"
                                        name="date"
                                        type="date"
                                        class="block w-full"
                                        :value="old('date', $today)"
                                        max="{{ $today }}"
                                        required
                                    />
                                </div>

                                <div class="sm:col-span-2">
                                    <x-input-label :for="'take-slot-'.$class->id" value="Créneau" class="sm:hidden" />
                                    <x-select
                                        :id="'take-slot-'.$class->id"
                                        name="slot"
                                        class="mt-1 sm:mt-0"
                                        :options="$slotOptions"
                                        :selected="\App\Models\AttendanceSession::SLOT_MORNING"
                                        :placeholder="null"
                                        required
                                    />
                                </div>

                                <div class="text-right sm:col-span-2">
                                    <x-primary-button>Appel</x-primary-button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endcan

        <x-card
            title="Séances récentes"
            :subtitle="$sessions->total().' séance(s) enregistrée(s)'.($year ? ' — '.$year->name : '')"
            :padding="false"
        >
            @if ($sessions->isEmpty())
                <div class="px-4 py-4 sm:px-6">
                    <x-empty-state message="Aucune séance d'appel enregistrée pour le moment." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Date</th>
                                <th class="px-4 py-2.5">Classe</th>
                                <th class="px-4 py-2.5">Créneau</th>
                                <th class="px-4 py-2.5">Pris par</th>
                                <th class="px-4 py-2.5 text-right">Absents</th>
                                <th class="px-4 py-2.5 text-right">Retards</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($sessions as $session)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">
                                        {{ $session->session_date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-medium text-gray-900">{{ $session->schoolClass?->name ?? '—' }}</span>
                                        <span class="ml-1 text-xs text-gray-400">{{ $session->schoolClass?->gradeLevel?->name }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ \App\Models\AttendanceSession::slotLabel($session->slot) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $session->takenBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="font-semibold {{ $session->absences_count > 0 ? 'text-rose-600' : 'text-gray-400' }}">
                                            {{ $session->absences_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="font-semibold {{ $session->lates_count > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                                            {{ $session->lates_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <a href="{{ route('attendance.session', $session) }}"
                                               class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                                Ouvrir
                                            </a>
                                            @if ($session->schoolClass)
                                                <a href="{{ route('attendance.report', $session->schoolClass) }}"
                                                   class="text-xs font-semibold text-gray-500 hover:text-gray-700">
                                                    Rapport
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
                    {{ $sessions->links() }}
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
