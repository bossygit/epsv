<x-app-layout>
    <x-page-header
        title="Bulletins"
        subtitle="Générez les bulletins de notes par classe et par période, puis suivez leurs versions."
    >
        <x-slot name="actions">
            @if ($selectedClass && $selectedPeriod)
                <a href="{{ route('report-cards.summary', ['schoolClass' => $selectedClass, 'school_period_id' => $selectedPeriod->id]) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50">
                    Procès-verbal de la classe
                </a>
                @can('report_cards.generate')
                    <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'bulk-generate')">
                        Générer tous les bulletins
                    </x-primary-button>
                @endcan
            @endif
        </x-slot>
    </x-page-header>

    <div class="space-y-6">
        <!-- Filtres -->
        <x-card>
            <form method="GET" action="{{ route('report-cards.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div>
                    <x-input-label for="class_id" value="Classe" />
                    <x-select name="class_id" class="mt-1" :options="$classes->pluck('name', 'id')" :selected="$selectedClass?->id" placeholder="— Toutes les classes —" />
                </div>
                <div>
                    <x-input-label for="period_id" value="Période" />
                    <x-select name="period_id" class="mt-1" :options="$periods->pluck('name', 'id')" :selected="$selectedPeriod?->id" placeholder="— Toutes les périodes —" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('report-cards.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Réinitialiser</a>
                </div>
                @unless ($seesAll)
                    <p class="text-xs text-gray-500 lg:text-right">Vous ne voyez que les classes qui vous sont affectées.</p>
                @endunless
            </form>
        </x-card>

        @if (! $selectedClass || ! $selectedPeriod)
            <x-card>
                <x-empty-state message="Sélectionnez une classe et une période pour afficher les élèves et leurs bulletins." />
            </x-card>
        @else
            <!-- Statistiques de la classe -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-card
                    label="Moyenne de la classe"
                    :value="$stats && $stats['average'] !== null ? number_format($stats['average'], 2, ',', ' ') : '—'"
                    :hint="$stats ? $stats['ranked'].' élève(s) classé(s) sur '.$stats['total'] : null"
                    tone="indigo"
                />
                <x-stat-card
                    label="Plus forte moyenne"
                    :value="$stats && $stats['max'] !== null ? number_format($stats['max'], 2, ',', ' ') : '—'"
                    hint="Meilleur résultat de la classe"
                    tone="emerald"
                />
                <x-stat-card
                    label="Plus faible moyenne"
                    :value="$stats && $stats['min'] !== null ? number_format($stats['min'], 2, ',', ' ') : '—'"
                    hint="Résultat le plus bas de la classe"
                    tone="amber"
                />
                <x-stat-card
                    label="Taux de réussite"
                    :value="$stats && $stats['success_rate'] !== null ? number_format($stats['success_rate'], 1, ',', ' ').' %' : '—'"
                    hint="Élèves au-dessus du seuil de réussite"
                    tone="rose"
                />
            </div>

            <!-- Élèves et bulletins -->
            <x-card :padding="false">
                <x-slot name="title">{{ $selectedClass->name }} — {{ $selectedPeriod->name }}</x-slot>
                <x-slot name="subtitle">
                    {{ $students->count() }} élève(s) actif(s) — année scolaire {{ $selectedPeriod->academicYear?->name }}
                </x-slot>

                @if ($students->isEmpty())
                    <div class="px-6 py-4">
                        <x-empty-state message="Aucun élève actif dans cette classe." />
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2.5">Élève</th>
                                    <th class="px-4 py-2.5">Matricule</th>
                                    <th class="px-4 py-2.5">Bulletin</th>
                                    <th class="px-4 py-2.5 text-right">Moyenne</th>
                                    <th class="px-4 py-2.5 text-right">Rang</th>
                                    <th class="px-4 py-2.5 text-center">Version</th>
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($students as $enrollment)
                                    @php
                                        $student = $enrollment->student;
                                        $card = $reportCards->get($student->id);
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5">
                                            <a href="{{ route('students.show', $student) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                                {{ $student->full_name }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-gray-600">{{ $student->matricule }}</td>
                                        <td class="px-4 py-2.5">
                                            @if ($card)
                                                <x-status-badge :status="$card->status" />
                                            @else
                                                <span class="text-xs text-gray-400">Non généré</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-semibold text-gray-900">
                                            {{ $card && $card->average !== null ? number_format($card->average, 2, ',', ' ') : '—' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $card?->rank ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-center text-gray-600">{{ $card ? 'v'.$card->version : '—' }}</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @can('report_cards.generate')
                                                    <form method="POST" action="{{ route('report-cards.generate', $student) }}">
                                                        @csrf
                                                        <input type="hidden" name="school_period_id" value="{{ $selectedPeriod->id }}">
                                                        <button class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Générer</button>
                                                    </form>
                                                @endcan
                                                @if ($card)
                                                    <a href="{{ route('report-cards.show', $card) }}" class="text-xs font-semibold text-gray-700 hover:text-gray-900">Voir</a>
                                                    <a href="{{ route('report-cards.download', $card) }}" class="text-xs font-semibold text-gray-700 hover:text-gray-900">PDF</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endif
    </div>

    @if ($selectedClass && $selectedPeriod)
        @can('report_cards.generate')
            <!-- Modale : génération de tous les bulletins -->
            <x-modal name="bulk-generate" focusable>
                <form method="POST" action="{{ route('report-cards.bulk', $selectedClass) }}" class="p-6">
                    @csrf
                    <input type="hidden" name="school_period_id" value="{{ $selectedPeriod->id }}">

                    <h2 class="text-lg font-semibold text-gray-900">Générer tous les bulletins</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $students->count() }} élève(s) actif(s) — {{ $selectedClass->name }} / {{ $selectedPeriod->name }}.
                    </p>
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        Les bulletins déjà validés ou verrouillés sont conservés : une nouvelle version sera créée
                        pour ces élèves. L'opération peut prendre quelques instants.
                    </p>

                    <div class="mt-6 flex justify-end gap-2">
                        <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                        <x-primary-button>Générer les bulletins</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endif
</x-app-layout>
