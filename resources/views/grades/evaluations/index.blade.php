@php
    $typeLabels = [
        'devoir' => 'Devoir',
        'composition' => 'Composition',
        'interrogation' => 'Interrogation',
        'autre' => 'Autre',
    ];
@endphp

<x-app-layout>
    <x-page-header
        title="Notes & évaluations"
        subtitle="Évaluations de la classe et de la période sélectionnées : saisie, publication et annulation.{{ $seesAll ? '' : ' Seules vos classes affectées sont affichées.' }}"
    >
        <x-slot name="actions">
            @can('grades.enter')
                <a href="{{ route('evaluations.create', array_filter(['class_id' => $selectedClass?->id, 'period_id' => $selectedPeriod?->id])) }}"
                   class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition ease-in-out duration-150 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    + Nouvelle évaluation
                </a>
            @endcan
        </x-slot>
    </x-page-header>

    <!-- Filtres -->
    <x-card class="mb-6">
        <form method="GET" action="{{ route('evaluations.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
            <div>
                <x-input-label for="filter-class" value="Classe" />
                <x-select id="filter-class" name="class_id" class="mt-1"
                          :options="$classes->pluck('name', 'id')"
                          :selected="$selectedClass?->id"
                          :placeholder="null" />
            </div>
            <div>
                <x-input-label for="filter-period" value="Période" />
                <x-select id="filter-period" name="period_id" class="mt-1"
                          :options="$periods->pluck('name', 'id')"
                          :selected="$selectedPeriod?->id"
                          :placeholder="null" />
            </div>
            <div class="flex items-center gap-2">
                <x-primary-button>Filtrer</x-primary-button>
                <a href="{{ route('evaluations.index') }}"
                   class="text-sm font-medium text-gray-500 hover:text-gray-700">Réinitialiser</a>
            </div>
        </form>
    </x-card>

    @if ($evaluations->isEmpty())
        <x-card>
            <x-empty-state message="Aucune évaluation pour cette classe et cette période. Créez la première évaluation." />
        </x-card>
    @else
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Matière</th>
                            <th class="px-4 py-2.5">Classe</th>
                            <th class="px-4 py-2.5">Évaluation</th>
                            <th class="px-4 py-2.5">Type</th>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5 text-right">Barème</th>
                            <th class="px-4 py-2.5 text-right">Coef.</th>
                            <th class="px-4 py-2.5">Progression</th>
                            <th class="px-4 py-2.5">Statut</th>
                            <th class="px-4 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($evaluations as $evaluation)
                            @php
                                $progress = $evaluation->grades_count > 0
                                    ? (int) round($evaluation->entered_count / $evaluation->grades_count * 100)
                                    : 0;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $evaluation->isCancelled() ? 'text-gray-400' : '' }}">
                                <td class="px-4 py-2.5 font-medium text-gray-900">
                                    {{ $evaluation->classSubject->subject->name }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    {{ $evaluation->classSubject->schoolClass->name }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('grades.entry', $evaluation) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ $evaluation->name }}
                                    </a>
                                    <p class="text-xs text-gray-400">
                                        Par {{ $evaluation->creator?->name ?? '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    {{ $typeLabels[$evaluation->type] ?? $evaluation->type }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    {{ $evaluation->evaluation_date->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-700">
                                    {{ number_format($evaluation->max_score, 2, ',', ' ') }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-700">
                                    {{ number_format($evaluation->coefficient, 2, ',', ' ') }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-baseline gap-2">
                                        <span class="font-medium text-gray-700">
                                            {{ $evaluation->entered_count }}/{{ $evaluation->grades_count }}
                                        </span>
                                        @if ($evaluation->missing_count > 0)
                                            <span class="text-xs font-medium text-amber-600">
                                                {{ $evaluation->missing_count }} manquante(s)
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ $progress }}%"></div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <x-status-badge :status="$evaluation->status" />
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a href="{{ route('grades.entry', $evaluation) }}"
                                           class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                            Saisir les notes
                                        </a>

                                        @can('grades.enter')
                                            <a href="{{ route('evaluations.edit', $evaluation) }}"
                                               class="text-xs font-semibold text-gray-600 hover:text-gray-800">
                                                Modifier
                                            </a>

                                            @if ($evaluation->status === \App\Models\Evaluation::STATUS_DRAFT)
                                                <form method="POST" action="{{ route('evaluations.publish', $evaluation) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800">
                                                        Publier
                                                    </button>
                                                </form>
                                            @endif

                                            @if (in_array($evaluation->status, [\App\Models\Evaluation::STATUS_DRAFT, \App\Models\Evaluation::STATUS_PUBLISHED], true))
                                                <button type="button" class="text-xs font-semibold text-amber-600 hover:text-amber-800"
                                                        x-data="" x-on:click.prevent="$dispatch('open-modal', 'cancel-evaluation-{{ $evaluation->id }}')">
                                                    Annuler
                                                </button>
                                            @endif

                                            @if ($evaluation->grades_count === 0 && ! $evaluation->period->isValidated())
                                                <form method="POST" action="{{ route('evaluations.destroy', $evaluation) }}"
                                                      onsubmit="return confirm('Supprimer l\'évaluation « {{ $evaluation->name }} » ? Cette action est définitive.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                                                        Supprimer
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @can('grades.enter')
        <!-- Modales : annulation d'une évaluation -->
        @foreach ($evaluations as $evaluation)
            @if (in_array($evaluation->status, [\App\Models\Evaluation::STATUS_DRAFT, \App\Models\Evaluation::STATUS_PUBLISHED], true))
                <x-modal :name="'cancel-evaluation-'.$evaluation->id" focusable>
                    <form method="POST" action="{{ route('evaluations.cancel', $evaluation) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-semibold text-gray-900">Annuler l'évaluation</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $evaluation->name }} — {{ $evaluation->classSubject->subject->name }}
                            ({{ $evaluation->classSubject->schoolClass->name }}), {{ $evaluation->period->name }}.
                            Une évaluation annulée est exclue des calculs ; les notes déjà saisies sont conservées.
                        </p>
                        <div class="mt-4">
                            <x-input-label for="cancel-reason-{{ $evaluation->id }}" value="Motif (obligatoire)" />
                            <textarea id="cancel-reason-{{ $evaluation->id }}" name="reason" rows="3" required
                                      placeholder="Ex. Évaluation annulée : séance reportée."
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                            <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <x-danger-button>Confirmer l'annulation</x-danger-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endforeach
    @endcan
</x-app-layout>
