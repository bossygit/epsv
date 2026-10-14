<x-app-layout>
    <x-page-header
        title="Classes"
        :subtitle="($year ? 'Année scolaire '.$year->name : 'Aucune année scolaire courante').' — '.($seesAll ? 'toutes les classes' : 'vos classes assignées')"
    >
        <x-slot name="actions">
            @can('structure.manage')
                @if ($academicYears->isNotEmpty())
                    <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-class')">
                        + Nouvelle classe
                    </x-primary-button>
                @endif
            @endcan
        </x-slot>
    </x-page-header>

    @php
        // $teachers est attendu du contrôleur ; repli défensif s'il n'est pas transmis.
        $teacherOptions = ($teachers ?? collect())->pluck('name', 'id')->all();
        $levelOptions = $levels->mapWithKeys(fn ($level) => [
            $level->id => ($level->cycle ? $level->cycle->name.' — ' : '').$level->name,
        ])->all();
        $yearOptions = $academicYears->pluck('name', 'id')->all();
    @endphp

    @if (! $year)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Aucune année scolaire courante : définissez l'année courante dans « Années scolaires » pour filtrer les classes.
        </div>
    @endif

    <x-card :padding="false">
        @if ($classes->isEmpty())
            <div class="px-6 py-4">
                <x-empty-state message="Aucune classe pour le moment. Créez la première classe de l'année." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Classe</th>
                            <th class="px-4 py-2.5">Niveau</th>
                            <th class="px-4 py-2.5">Cycle</th>
                            <th class="px-4 py-2.5">Titulaire</th>
                            <th class="px-4 py-2.5 text-right">Effectif</th>
                            <th class="px-4 py-2.5">Salle</th>
                            <th class="px-4 py-2.5">Statut</th>
                            @can('structure.manage')
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($classes as $class)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('classes.show', $class) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ $class->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $class->gradeLevel?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $class->gradeLevel?->cycle?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $class->mainTeacher?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600">
                                    {{ $class->active_enrollments_count }}@if ($class->capacity) / {{ $class->capacity }}@endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $class->room ?: '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <x-status-badge :status="$class->is_active ? 'active' : 'inactive'" />
                                </td>
                                @can('structure.manage')
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                    x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-class-{{ $class->id }}')">
                                                Modifier
                                            </button>
                                            <form method="POST" action="{{ route('classes.destroy', $class) }}"
                                                  onsubmit="return confirm('Supprimer la classe « {{ $class->name }} » ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs font-semibold text-rose-600 hover:text-rose-800">Supprimer</button>
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

    @can('structure.manage')
        <!-- Modale : nouvelle classe -->
        <x-modal name="create-class" focusable>
            <form method="POST" action="{{ route('classes.store') }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Nouvelle classe</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="create-class-year" value="Année scolaire" />
                        <x-select id="create-class-year" name="academic_year_id" class="mt-1"
                                  :options="$yearOptions" :selected="$year?->id"
                                  placeholder="— Choisir une année —" required />
                    </div>
                    <div>
                        <x-input-label for="create-class-level" value="Niveau" />
                        <x-select id="create-class-level" name="grade_level_id" class="mt-1"
                                  :options="$levelOptions"
                                  placeholder="— Choisir un niveau —" required />
                    </div>
                    <div>
                        <x-input-label for="create-class-name" value="Nom de la classe (ex. 6ème A)" />
                        <x-text-input id="create-class-name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label for="create-class-capacity" value="Capacité (facultative)" />
                        <x-text-input id="create-class-capacity" name="capacity" type="number" min="1" max="200" class="mt-1 block w-full" :value="old('capacity')" />
                    </div>
                    <div>
                        <x-input-label for="create-class-teacher" value="Professeur titulaire (facultatif)" />
                        <x-select id="create-class-teacher" name="main_teacher_id" class="mt-1"
                                  :options="$teacherOptions"
                                  placeholder="— Aucun —" />
                    </div>
                    <div>
                        <x-input-label for="create-class-room" value="Salle (facultative)" />
                        <x-text-input id="create-class-room" name="room" class="mt-1 block w-full" :value="old('room')" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Créer la classe</x-primary-button>
                </div>
            </form>
        </x-modal>

        <!-- Modales : modifier une classe -->
        @foreach ($classes as $class)
            <x-modal :name="'edit-class-'.$class->id" focusable>
                <form method="POST" action="{{ route('classes.update', $class) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    <h2 class="text-lg font-semibold text-gray-900">Modifier la classe {{ $class->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Année scolaire : {{ $class->academicYear?->name ?? '—' }}</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="edit-class-level-{{ $class->id }}" value="Niveau" />
                            <x-select id="edit-class-level-{{ $class->id }}" name="grade_level_id" class="mt-1"
                                      :options="$levelOptions" :selected="old('grade_level_id', $class->grade_level_id)"
                                      placeholder="— Choisir un niveau —" required />
                        </div>
                        <div>
                            <x-input-label for="edit-class-name-{{ $class->id }}" value="Nom de la classe" />
                            <x-text-input id="edit-class-name-{{ $class->id }}" name="name" class="mt-1 block w-full" :value="old('name', $class->name)" required />
                        </div>
                        <div>
                            <x-input-label for="edit-class-capacity-{{ $class->id }}" value="Capacité" />
                            <x-text-input id="edit-class-capacity-{{ $class->id }}" name="capacity" type="number" min="1" max="200" class="mt-1 block w-full" :value="old('capacity', $class->capacity)" />
                        </div>
                        <div>
                            <x-input-label for="edit-class-teacher-{{ $class->id }}" value="Professeur titulaire" />
                            <x-select id="edit-class-teacher-{{ $class->id }}" name="main_teacher_id" class="mt-1"
                                      :options="$teacherOptions" :selected="old('main_teacher_id', $class->main_teacher_id)"
                                      placeholder="— Aucun —" />
                        </div>
                        <div>
                            <x-input-label for="edit-class-room-{{ $class->id }}" value="Salle" />
                            <x-text-input id="edit-class-room-{{ $class->id }}" name="room" class="mt-1 block w-full" :value="old('room', $class->room)" />
                        </div>
                        <div>
                            <x-input-label value="Statut" />
                            <label for="edit-class-active-{{ $class->id }}" class="mt-2 flex items-center gap-2">
                                <input type="hidden" name="is_active" value="0">
                                <input id="edit-class-active-{{ $class->id }}" type="checkbox" name="is_active" value="1"
                                       @checked(old('is_active', $class->is_active))
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Classe active</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                        <x-primary-button>Enregistrer</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    @endcan
</x-app-layout>
