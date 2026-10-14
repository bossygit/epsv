<x-app-layout>
    <x-page-header
        title="Années scolaires"
        subtitle="Créez les années, leurs périodes (trimestres) et définissez l'année courante."
    >
        <x-slot name="actions">
            @can('structure.manage')
                <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-year')">
                    + Nouvelle année
                </x-primary-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="space-y-6">
        @forelse ($years as $year)
            <x-card :padding="false">
                <x-slot name="title">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-base font-semibold text-gray-900">{{ $year->name }}</span>
                        <x-status-badge :status="$year->status" />
                        @if ($year->is_current)
                            <span class="inline-flex items-center rounded-full bg-indigo-600 px-2.5 py-0.5 text-xs font-semibold text-white">Année courante</span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Du {{ $year->start_date->format('d/m/Y') }} au {{ $year->end_date->format('d/m/Y') }}
                        — {{ $year->classes_count }} classe(s), {{ $year->enrollments_count }} inscription(s)
                    </p>
                </x-slot>

                <x-slot name="actions">
                    @can('structure.manage')
                        @if (! $year->is_current && ! $year->isClosed())
                            <form method="POST" action="{{ route('academic-years.set-current', $year) }}">
                                @csrf
                                <x-secondary-button>Définir comme courante</x-secondary-button>
                            </form>
                        @endif
                        @if ($year->status === \App\Models\AcademicYear::STATUS_ACTIVE && ! $year->isClosed())
                            <form method="POST" action="{{ route('academic-years.close', $year) }}"
                                  onsubmit="return confirm('Clôturer l\'année {{ $year->name }} ? Cette action est définitive.')">
                                @csrf
                                <x-secondary-button>Clôturer</x-secondary-button>
                            </form>
                        @endif
                        <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-year-{{ $year->id }}')">
                            Modifier
                        </x-secondary-button>
                    @endcan
                </x-slot>

                <!-- Périodes -->
                <div class="px-4 py-4 sm:px-6">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-700">Périodes</h4>
                        @can('structure.manage')
                            <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                    x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-period-{{ $year->id }}')">
                                + Ajouter une période
                            </button>
                        @endcan
                    </div>

                    @if ($year->periods->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">Aucune période définie.</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="py-2 pr-4">Période</th>
                                        <th class="py-2 pr-4">Dates</th>
                                        <th class="py-2 pr-4">Poids</th>
                                        <th class="py-2 pr-4">Statut</th>
                                        <th class="py-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($year->periods as $period)
                                        <tr>
                                            <td class="py-2 pr-4 font-medium text-gray-900">{{ $period->name }}</td>
                                            <td class="py-2 pr-4 text-gray-600">
                                                {{ $period->start_date->format('d/m/Y') }} → {{ $period->end_date->format('d/m/Y') }}
                                            </td>
                                            <td class="py-2 pr-4 text-gray-600">{{ rtrim(rtrim($period->weight, '0'), '.') }}</td>
                                            <td class="py-2 pr-4"><x-status-badge :status="$period->status" /></td>
                                            <td class="py-2">
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    @can('grades.submit')
                                                        @if ($period->status === \App\Models\SchoolPeriod::STATUS_OPEN)
                                                            <form method="POST" action="{{ route('periods.submit', $period) }}">
                                                                @csrf
                                                                <button class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Soumettre</button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                    @can('grades.validate')
                                                        @if ($period->status === \App\Models\SchoolPeriod::STATUS_SUBMITTED)
                                                            <form method="POST" action="{{ route('periods.validate', $period) }}">
                                                                @csrf
                                                                <button class="text-xs font-semibold text-blue-600 hover:text-blue-800">Valider</button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                    @can('grades.lock')
                                                        @if ($period->status === \App\Models\SchoolPeriod::STATUS_VALIDATED)
                                                            <form method="POST" action="{{ route('periods.lock', $period) }}">
                                                                @csrf
                                                                <button class="text-xs font-semibold text-slate-600 hover:text-slate-800">Verrouiller</button>
                                                            </form>
                                                        @endif
                                                        @if (in_array($period->status, [\App\Models\SchoolPeriod::STATUS_VALIDATED, \App\Models\SchoolPeriod::STATUS_LOCKED, \App\Models\SchoolPeriod::STATUS_SUBMITTED], true))
                                                            <button class="text-xs font-semibold text-amber-600 hover:text-amber-800"
                                                                    x-data="" x-on:click.prevent="$dispatch('open-modal', 'reopen-period-{{ $period->id }}')">
                                                                Réouvrir
                                                            </button>
                                                        @endif
                                                    @endcan
                                                    @can('structure.manage')
                                                        <button class="text-xs font-semibold text-gray-500 hover:text-gray-700"
                                                                x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-period-{{ $period->id }}')">
                                                            Modifier
                                                        </button>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </x-card>

            @can('structure.manage')
                <!-- Modale : modifier l'année -->
                <x-modal :name="'edit-year-'.$year->id" focusable>
                    <form method="POST" action="{{ route('academic-years.update', $year) }}" class="p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-semibold text-gray-900">Modifier l'année {{ $year->name }}</h2>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="name-{{ $year->id }}" value="Nom (ex. 2026-2027)" />
                                <x-text-input id="name-{{ $year->id }}" name="name" class="mt-1 block w-full" :value="old('name', $year->name)" required />
                            </div>
                            <div>
                                <x-input-label for="start-{{ $year->id }}" value="Début" />
                                <x-text-input id="start-{{ $year->id }}" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', $year->start_date->toDateString())" required />
                            </div>
                            <div>
                                <x-input-label for="end-{{ $year->id }}" value="Fin" />
                                <x-text-input id="end-{{ $year->id }}" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date', $year->end_date->toDateString())" required />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="notes-{{ $year->id }}" value="Notes" />
                                <textarea id="notes-{{ $year->id }}" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes', $year->notes) }}</textarea>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <x-primary-button>Enregistrer</x-primary-button>
                        </div>
                    </form>
                </x-modal>

                <!-- Modale : nouvelle période -->
                <x-modal :name="'create-period-'.$year->id" focusable>
                    <form method="POST" action="{{ route('periods.store', $year) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-semibold text-gray-900">Nouvelle période — {{ $year->name }}</h2>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Nom (ex. 1er Trimestre)" />
                                <x-text-input name="name" class="mt-1 block w-full" :value="old('name')" required />
                            </div>
                            <div>
                                <x-input-label value="Ordre (1, 2, 3…)" />
                                <x-text-input name="sequence" type="number" min="1" max="10" class="mt-1 block w-full" :value="old('sequence', $year->periods->max('sequence') + 1)" required />
                            </div>
                            <div>
                                <x-input-label value="Type" />
                                <x-select name="type" class="mt-1" :options="['trimestre' => 'Trimestre', 'semestre' => 'Semestre']" selected="trimestre" :placeholder="null" required />
                            </div>
                            <div>
                                <x-input-label value="Poids annuel" />
                                <x-text-input name="weight" type="number" step="0.1" min="0.1" max="10" class="mt-1 block w-full" :value="old('weight', 1)" required />
                            </div>
                            <div>
                                <x-input-label value="Début" />
                                <x-text-input name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" required />
                            </div>
                            <div>
                                <x-input-label value="Fin" />
                                <x-text-input name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date')" required />
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <x-primary-button>Créer la période</x-primary-button>
                        </div>
                    </form>
                </x-modal>

                <!-- Modales : période -->
                @foreach ($year->periods as $period)
                    <x-modal :name="'edit-period-'.$period->id" focusable>
                        <form method="POST" action="{{ route('periods.update', $period) }}" class="p-6">
                            @csrf
                            @method('PUT')
                            <h2 class="text-lg font-semibold text-gray-900">Modifier {{ $period->name }}</h2>
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Nom" />
                                    <x-text-input name="name" class="mt-1 block w-full" :value="$period->name" required />
                                </div>
                                <div>
                                    <x-input-label value="Ordre" />
                                    <x-text-input name="sequence" type="number" min="1" max="10" class="mt-1 block w-full" :value="$period->sequence" required />
                                </div>
                                <div>
                                    <x-input-label value="Type" />
                                    <x-select name="type" class="mt-1" :options="['trimestre' => 'Trimestre', 'semestre' => 'Semestre']" :selected="$period->type" :placeholder="null" required />
                                </div>
                                <div>
                                    <x-input-label value="Poids" />
                                    <x-text-input name="weight" type="number" step="0.1" min="0.1" max="10" class="mt-1 block w-full" :value="$period->weight" required />
                                </div>
                                <div>
                                    <x-input-label value="Début" />
                                    <x-text-input name="start_date" type="date" class="mt-1 block w-full" :value="$period->start_date->toDateString()" required />
                                </div>
                                <div>
                                    <x-input-label value="Fin" />
                                    <x-text-input name="end_date" type="date" class="mt-1 block w-full" :value="$period->end_date->toDateString()" required />
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-2">
                                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                <x-primary-button>Enregistrer</x-primary-button>
                            </div>
                        </form>
                    </x-modal>

                    <x-modal :name="'reopen-period-'.$period->id" focusable>
                        <form method="POST" action="{{ route('periods.reopen', $period) }}" class="p-6">
                            @csrf
                            <h2 class="text-lg font-semibold text-gray-900">Réouvrir {{ $period->name }}</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                La réouverture permet de modifier les notes. Chaque modification ultérieure sera tracée dans le journal d'audit.
                            </p>
                            <div class="mt-4">
                                <x-input-label value="Motif (obligatoire)" />
                                <textarea name="reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required></textarea>
                            </div>
                            <div class="mt-6 flex justify-end gap-2">
                                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                                <x-danger-button>Réouvrir la période</x-danger-button>
                            </div>
                        </form>
                    </x-modal>
                @endforeach
            @endcan
        @empty
            <x-card>
                <x-empty-state message="Aucune année scolaire. Créez la première année pour commencer." />
            </x-card>
        @endforelse
    </div>

    @can('structure.manage')
        <x-modal name="create-year" focusable>
            <form method="POST" action="{{ route('academic-years.store') }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Nouvelle année scolaire</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Nom (ex. 2027-2028)" />
                        <x-text-input name="name" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label value="Notes" />
                        <x-text-input name="notes" class="mt-1 block w-full" :value="old('notes')" />
                    </div>
                    <div>
                        <x-input-label value="Début" />
                        <x-text-input name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" required />
                    </div>
                    <div>
                        <x-input-label value="Fin" />
                        <x-text-input name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date')" required />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Créer</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
