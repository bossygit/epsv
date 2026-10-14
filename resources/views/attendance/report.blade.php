@php
    $fromLabel = \Illuminate\Support\Carbon::parse($from)->format('d/m/Y');
    $toLabel = \Illuminate\Support\Carbon::parse($to)->format('d/m/Y');

    $rated = $rows->filter(fn ($row) => $row['rate'] !== null);
    $averageRate = $rated->isNotEmpty() ? round($rated->avg('rate'), 1) : null;
@endphp

<x-app-layout>
    <x-page-header
        title="Rapport de présences — {{ $schoolClass->name }}"
        :subtitle="'Du '.$fromLabel.' au '.$toLabel.' — '.$schoolClass->gradeLevel?->name"
        :back="route('attendance.index')"
    />

    <div class="space-y-6">
        <!-- Filtre de période -->
        <x-card title="Période analysée" subtitle="Choisissez la plage de dates puis cliquez sur « Filtrer ».">
            <form method="GET" action="{{ route('attendance.report', $schoolClass) }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div>
                    <x-input-label for="from" value="Du" />
                    <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="$from" />
                </div>
                <div>
                    <x-input-label for="to" value="Au" />
                    <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="$to" />
                </div>
                <div>
                    <x-primary-button>Filtrer</x-primary-button>
                </div>
            </form>
        </x-card>

        <!-- Synthèse -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card
                label="Élèves suivis"
                :value="$rows->count()"
                hint="Élèves actifs de la classe"
                tone="indigo"
                icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
            />
            <x-stat-card
                label="Pointages sur la période"
                :value="$rows->sum('total')"
                hint="Présences, absences, retards et justifiés"
                tone="slate"
                icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"
            />
            <x-stat-card
                label="Absences non justifiées"
                :value="$rows->sum('absent')"
                hint="À traiter par la surveillance"
                tone="rose"
                icon="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
            <x-stat-card
                label="Taux de présence moyen"
                :value="$averageRate !== null ? number_format($averageRate, 1, ',', ' ').' %' : '—'"
                hint="Moyenne des élèves ayant au moins un pointage"
                tone="emerald"
                icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
        </div>

        <!-- Détail par élève -->
        <x-card title="Détail par élève" :subtitle="$rows->count().' élève(s) — du '.$fromLabel.' au '.$toLabel" :padding="false">
            @if ($rows->isEmpty())
                <div class="px-4 py-4 sm:px-6">
                    <x-empty-state message="Aucun élève actif dans cette classe." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Élève</th>
                                <th class="px-4 py-2.5 text-right">Séances</th>
                                <th class="px-4 py-2.5 text-right">Présences</th>
                                <th class="px-4 py-2.5 text-right">Absences</th>
                                <th class="px-4 py-2.5 text-right">Retards</th>
                                <th class="px-4 py-2.5 text-right">Justifiées</th>
                                <th class="px-4 py-2.5">Taux de présence</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($rows as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('students.show', $row['student']) }}"
                                           class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $row['student']->full_name }}
                                        </a>
                                        <p class="text-xs text-gray-400">{{ $row['student']->matricule }}</p>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600">{{ $row['total'] }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="font-medium {{ $row['present'] > 0 ? 'text-emerald-600' : 'text-gray-400' }}">{{ $row['present'] }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="font-medium {{ $row['absent'] > 0 ? 'text-rose-600' : 'text-gray-400' }}">{{ $row['absent'] }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="font-medium {{ $row['late'] > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ $row['late'] }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="font-medium {{ $row['excused'] > 0 ? 'text-blue-600' : 'text-gray-400' }}">{{ $row['excused'] }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($row['rate'] !== null)
                                            <div class="flex items-center gap-3">
                                                <span class="w-16 font-semibold {{ $row['rate'] >= 80 ? 'text-emerald-600' : 'text-amber-600' }}">
                                                    {{ number_format($row['rate'], 1, ',', ' ') }} %
                                                </span>
                                                <div class="h-1.5 w-28 overflow-hidden rounded-full bg-gray-100">
                                                    <div class="h-full rounded-full {{ $row['rate'] >= 80 ? 'bg-emerald-500' : 'bg-amber-500' }}"
                                                         style="width: {{ min(100, max(0, $row['rate'])) }}%"></div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
