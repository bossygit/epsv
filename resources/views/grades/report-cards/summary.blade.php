@php
    $profile = \App\Models\SchoolProfile::current();
@endphp

<x-app-layout>
    <x-page-header
        title="Procès-verbal de la classe"
        :subtitle="$schoolClass->name.' — '.$period->name.' — année scolaire '.$period->academicYear?->name"
        :back="route('report-cards.index', ['class_id' => $schoolClass->id, 'period_id' => $period->id])"
    >
        <x-slot name="actions">
            <x-secondary-button onclick="window.print()">Imprimer</x-secondary-button>
        </x-slot>
    </x-page-header>

    <div class="space-y-6">
        <!-- Statistiques de la classe -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card
                label="Moyenne de la classe"
                :value="$stats['average'] !== null ? number_format($stats['average'], 2, ',', ' ') : '—'"
                :hint="$stats['ranked'].' élève(s) classé(s) sur '.$stats['total']"
                tone="indigo"
            />
            <x-stat-card
                label="Plus forte moyenne"
                :value="$stats['max'] !== null ? number_format($stats['max'], 2, ',', ' ') : '—'"
                hint="Meilleur résultat de la classe"
                tone="emerald"
            />
            <x-stat-card
                label="Plus faible moyenne"
                :value="$stats['min'] !== null ? number_format($stats['min'], 2, ',', ' ') : '—'"
                hint="Résultat le plus bas de la classe"
                tone="amber"
            />
            <x-stat-card
                label="Taux de réussite"
                :value="$stats['success_rate'] !== null ? number_format($stats['success_rate'], 1, ',', ' ').' %' : '—'"
                hint="Élèves au-dessus du seuil de réussite"
                tone="rose"
            />
        </div>

        <!-- Classement -->
        <x-card :padding="false">
            <x-slot name="title">Classement de la classe</x-slot>
            <x-slot name="subtitle">
                {{ $schoolClass->name }}@if ($schoolClass->gradeLevel) ({{ $schoolClass->gradeLevel->name }})@endif — {{ $period->name }}
            </x-slot>

            @if ($rows->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun résultat à classer pour cette classe et cette période." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5 text-right">Rang</th>
                                <th class="px-4 py-2.5">Élève</th>
                                <th class="px-4 py-2.5">Matricule</th>
                                <th class="px-4 py-2.5 text-right">Moyenne</th>
                                <th class="px-4 py-2.5">Observation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($rows as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-right font-semibold text-gray-900">
                                        @if ($row['rank'])
                                            {{ $row['rank'] }}{{ $row['rank'] === 1 ? 'er' : 'e' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('students.show', $row['student']) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $row['student']->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-600">{{ $row['student']->matricule }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-gray-900">
                                        {{ $row['average'] !== null ? number_format($row['average'], 2, ',', ' ') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @unless ($row['complete'])
                                            <span class="text-xs font-medium text-amber-600">Résultats incomplets</span>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Pied de page officiel -->
            <div class="mt-6 flex flex-col gap-8 border-t border-gray-100 px-4 pt-6 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <p class="text-sm text-gray-600">
                    Fait à {{ $profile->city ?: 'Brazzaville' }}, le {{ now()->format('d/m/Y') }}
                </p>
                <div class="text-center text-sm">
                    <p class="font-semibold text-gray-700">{{ $profile->director_title ?: 'Le Directeur / La Directrice' }}</p>
                    <p class="mt-12 border-t border-gray-300 pt-1 text-gray-600">
                        {{ $profile->director_name ?: 'Signature et cachet' }}
                    </p>
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
