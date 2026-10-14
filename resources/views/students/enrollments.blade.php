<x-app-layout>
    <x-page-header
        title="Inscriptions"
        :subtitle="$year
            ? 'Année scolaire '.$year->name.' — '.$enrollments->total().' inscription(s)'
            : 'Aucune année scolaire enregistrée'"
    >
        <x-slot name="actions">
            @can('enrollments.manage')
                @if ($classes->isNotEmpty())
                    <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-enrollment')">
                        + Nouvelle inscription
                    </x-primary-button>
                @endif
            @endcan
        </x-slot>
    </x-page-header>

    @php
        $yearOptions = $years
            ->mapWithKeys(fn ($item) => [$item->id => $item->name.($item->is_current ? ' (année courante)' : '')])
            ->all();

        $statusOptions = [
            'active' => 'Actif',
            'left' => 'Sorti(e)',
            'transferred' => 'Transféré',
        ];

        $baseClassOptions = $classes
            ->mapWithKeys(fn ($class) => [$class->id => $class->name.($class->gradeLevel ? ' — '.$class->gradeLevel->name : '')]);
    @endphp

    <div class="space-y-6">
        <!-- Filtre par année -->
        <x-card :padding="false">
            <div class="px-4 py-4 sm:px-6">
                <form method="GET" action="{{ route('enrollments.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-72">
                        <x-input-label for="year_id" value="Année scolaire" />
                        <x-select name="year_id" id="year_id" class="mt-1" :options="$yearOptions"
                                  :selected="$year?->id" :placeholder="null" />
                    </div>
                    <x-primary-button>Filtrer</x-primary-button>
                    <a href="{{ route('enrollments.index') }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Réinitialiser
                    </a>
                </form>
            </div>
        </x-card>

        <!-- Liste des inscriptions -->
        <x-card :padding="false">
            @if ($enrollments->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucune inscription pour cette année scolaire." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Élève</th>
                                <th class="px-4 py-2.5">Matricule</th>
                                <th class="px-4 py-2.5">Classe</th>
                                <th class="px-4 py-2.5">Année</th>
                                <th class="px-4 py-2.5">Date d'inscription</th>
                                <th class="px-4 py-2.5">Statut</th>
                                @can('enrollments.manage')
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($enrollments as $enrollment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        @if ($enrollment->student)
                                            <a href="{{ route('students.show', $enrollment->student) }}"
                                               class="font-medium text-indigo-600 hover:text-indigo-800">
                                                {{ $enrollment->student->full_name }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">Élève supprimé</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-600">
                                        {{ $enrollment->student?->matricule ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $enrollment->schoolClass?->name ?? '—' }}
                                        @if ($enrollment->schoolClass?->gradeLevel)
                                            <span class="ml-1 text-xs text-gray-400">{{ $enrollment->schoolClass->gradeLevel->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $enrollment->academicYear?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $enrollment->enrolled_on ? $enrollment->enrolled_on->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-status-badge :status="$enrollment->status"
                                                        :label="$enrollment->status === 'left' ? 'Sorti(e)' : null" />
                                    </td>
                                    @can('enrollments.manage')
                                        <td class="px-4 py-2.5">
                                            <div class="flex flex-wrap items-center justify-end gap-3">
                                                <button type="button" class="text-xs font-semibold text-gray-500 hover:text-gray-700"
                                                        x-data=""
                                                        x-on:click.prevent="$dispatch('open-modal', 'edit-enrollment-{{ $enrollment->id }}')">
                                                    Modifier
                                                </button>
                                                <form method="POST" action="{{ route('enrollments.destroy', $enrollment) }}"
                                                      onsubmit="return confirm('Supprimer définitivement cette inscription ? L\'opération est tracée dans le journal d\'audit.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                                                        Supprimer
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <div>
            {{ $enrollments->links() }}
        </div>
    </div>

    @can('enrollments.manage')
        <!-- Modales : modification des inscriptions -->
        @foreach ($enrollments as $enrollment)
            @php
                $editClassOptions = $baseClassOptions;
                if ($enrollment->schoolClass && ! $editClassOptions->has($enrollment->schoolClass->id)) {
                    $editClassOptions->put(
                        $enrollment->schoolClass->id,
                        $enrollment->schoolClass->name.($enrollment->schoolClass->gradeLevel ? ' — '.$enrollment->schoolClass->gradeLevel->name : '')
                    );
                }
                $editClassOptions = $editClassOptions->sortKeys()->all();
            @endphp

            <x-modal :name="'edit-enrollment-'.$enrollment->id" focusable>
                <form method="POST" action="{{ route('enrollments.update', $enrollment) }}" class="p-6">
                    @csrf
                    @method('PUT')

                    <h2 class="text-lg font-semibold text-gray-900">Modifier l'inscription</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $enrollment->student?->full_name ?? 'Élève supprimé' }}
                        — {{ $enrollment->academicYear?->name ?? 'année inconnue' }}
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="edit-class-{{ $enrollment->id }}" value="Classe *" />
                            <x-select name="class_id" :id="'edit-class-'.$enrollment->id" class="mt-1"
                                      :options="$editClassOptions" :selected="$enrollment->class_id" :placeholder="null" required />
                            <x-input-error :messages="$errors->get('class_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="edit-status-{{ $enrollment->id }}" value="Statut *" />
                            <x-select name="status" :id="'edit-status-'.$enrollment->id" class="mt-1"
                                      :options="$statusOptions" :selected="$enrollment->status" :placeholder="null" required />
                            <x-input-error :messages="$errors->get('status')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="edit-left-on-{{ $enrollment->id }}" value="Date de sortie" />
                            <x-text-input :id="'edit-left-on-'.$enrollment->id" name="left_on" type="date"
                                          class="mt-1 block w-full text-sm"
                                          :value="old('left_on', $enrollment->left_on?->toDateString())" />
                            <x-input-error :messages="$errors->get('left_on')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="edit-left-reason-{{ $enrollment->id }}" value="Motif de sortie" />
                            <x-text-input :id="'edit-left-reason-'.$enrollment->id" name="left_reason" type="text"
                                          class="mt-1 block w-full text-sm"
                                          :value="old('left_reason', $enrollment->left_reason)" maxlength="255" />
                            <x-input-error :messages="$errors->get('left_reason')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                        <x-primary-button>Enregistrer</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endforeach

        <!-- Modale : nouvelle inscription -->
        @if ($classes->isNotEmpty())
            <x-modal name="create-enrollment" focusable>
                <form method="POST" action="{{ route('enrollments.store') }}" class="p-6">
                    @csrf

                    <h2 class="text-lg font-semibold text-gray-900">Nouvelle inscription</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Année scolaire {{ $year ? $year->name : 'courante' }} — une seule inscription par élève et par année.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="create-student-id" value="Identifiant de l'élève *" />
                            <x-text-input id="create-student-id" name="student_id" type="number" min="1"
                                          class="mt-1 block w-full text-sm" :value="old('student_id')" required />
                            <p class="mt-1 text-xs text-gray-400">Identifiant numérique de l'élève, affiché dans l'URL de sa fiche.</p>
                            <x-input-error :messages="$errors->get('student_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="create-class-id" value="Classe *" />
                            <x-select name="class_id" id="create-class-id" class="mt-1"
                                      :options="$baseClassOptions->all()" placeholder="— Choisir une classe —" required />
                            <x-input-error :messages="$errors->get('class_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="create-enrolled-on" value="Date d'inscription *" />
                            <x-text-input id="create-enrolled-on" name="enrolled_on" type="date"
                                          class="mt-1 block w-full text-sm"
                                          :value="old('enrolled_on', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('enrolled_on')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="create-notes" value="Notes" />
                            <textarea id="create-notes" name="notes" rows="2" maxlength="1000"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                        <x-primary-button>Inscrire</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endcan
</x-app-layout>
