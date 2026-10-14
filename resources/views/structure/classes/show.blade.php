<x-app-layout>
    <x-page-header
        :title="$schoolClass->name"
        :subtitle="trim(($schoolClass->gradeLevel?->name ?? '').' — '.($schoolClass->gradeLevel?->cycle?->name ?? '').' • '.($schoolClass->academicYear?->name ?? ''), ' —•')"
        :back="route('classes.index')"
    >
        <x-slot name="actions">
            @can('structure.manage')
                <a href="{{ route('classes.index') }}"
                   title="Modifier les informations de la classe depuis la liste des classes"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Modifier la classe
                </a>
            @endcan
        </x-slot>
    </x-page-header>

    <!-- (a) Informations de la classe -->
    <x-card title="Informations" subtitle="Fiche de la classe">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Année scolaire</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $schoolClass->academicYear?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Niveau</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $schoolClass->gradeLevel?->name ?? '—' }}
                    @if ($schoolClass->gradeLevel?->cycle)
                        <span class="text-gray-500">({{ $schoolClass->gradeLevel->cycle->name }})</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Professeur titulaire</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $schoolClass->mainTeacher?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Salle</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $schoolClass->room ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Effectif</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $students->count() }}@if ($schoolClass->capacity) / {{ $schoolClass->capacity }}@endif
                    <span class="text-gray-500">élève(s)</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Statut</dt>
                <dd class="mt-1"><x-status-badge :status="$schoolClass->is_active ? 'active' : 'inactive'" /></dd>
            </div>
        </dl>
    </x-card>

    <div class="mt-6 space-y-6">
        <!-- (b) Élèves inscrits -->
        <x-card :padding="false">
            <x-slot name="title">
                <span class="text-base font-semibold text-gray-900">Élèves inscrits</span>
                <p class="mt-0.5 text-sm text-gray-500">{{ $students->count() }} élève(s) actif(s) dans cette classe.</p>
            </x-slot>

            <x-slot name="actions">
                @can('enrollments.manage')
                    <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'enroll-student')">
                        + Inscrire un élève
                    </x-primary-button>
                @endcan
            </x-slot>

            @if ($students->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun élève inscrit dans cette classe pour le moment." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Élève</th>
                                <th class="px-4 py-2.5">Matricule</th>
                                <th class="px-4 py-2.5">Sexe</th>
                                <th class="px-4 py-2.5">Date de naissance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($students as $enrollment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('students.show', $enrollment->student) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $enrollment->student->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $enrollment->student->matricule }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $enrollment->student->gender === \App\Models\Student::GENDER_M ? 'Masculin' : 'Féminin' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $enrollment->student->birth_date?->format('d/m/Y') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <!-- (d) Matières de la classe -->
        <x-card :padding="false">
            <x-slot name="title">
                <span class="text-base font-semibold text-gray-900">Matières de la classe</span>
                <p class="mt-0.5 text-sm text-gray-500">Coefficient, barème et enseignants affectés à chaque matière.</p>
            </x-slot>

            <x-slot name="actions">
                @can('structure.manage')
                    <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'add-class-subject')">
                        + Ajouter une matière
                    </x-primary-button>
                @endcan
            </x-slot>

            @if ($schoolClass->classSubjects->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucune matière rattachée à cette classe." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Matière</th>
                                <th class="px-4 py-2.5 text-right">Coefficient</th>
                                <th class="px-4 py-2.5 text-right">Barème</th>
                                <th class="px-4 py-2.5">Notée</th>
                                <th class="px-4 py-2.5">Enseignants affectés</th>
                                @can('structure.manage')
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($schoolClass->classSubjects as $classSubject)
                                @php
                                    // Enseignants affectés à cette matière dans cette classe.
                                    $subjectTeachers = $schoolClass->teacherAssignments
                                        ->where('subject_id', $classSubject->subject_id)
                                        ->map(fn ($assignment) => $assignment->teacher?->name)
                                        ->filter()
                                        ->unique()
                                        ->values();
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">
                                        {{ $classSubject->subject?->name ?? '—' }}
                                        @if ($classSubject->subject?->code)
                                            <span class="ml-1 text-xs text-gray-400">{{ $classSubject->subject->code }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600">
                                        {{ number_format((float) $classSubject->coefficient, 2, ',', ' ') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600">
                                        {{ number_format((float) $classSubject->max_score, 2, ',', ' ') }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-status-badge
                                            :status="$classSubject->is_graded ? 'active' : 'inactive'"
                                            :label="$classSubject->is_graded ? 'Notée' : 'Non notée'"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $subjectTeachers->isEmpty() ? '—' : $subjectTeachers->implode(', ') }}
                                    </td>
                                    @can('structure.manage')
                                        <td class="px-4 py-2.5">
                                            <div class="flex flex-wrap items-center justify-end gap-3">
                                                <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                        x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-class-subject-{{ $classSubject->id }}')">
                                                    Modifier
                                                </button>
                                                <form method="POST" action="{{ route('classes.subjects.destroy', [$schoolClass, $classSubject]) }}"
                                                      onsubmit="return confirm('Retirer la matière « {{ $classSubject->subject?->name }} » de cette classe ?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-800">Retirer</button>
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

        <!-- (e) Affectations des enseignants -->
        <x-card :padding="false">
            <x-slot name="title">
                <span class="text-base font-semibold text-gray-900">Affectations des enseignants</span>
                <p class="mt-0.5 text-sm text-gray-500">Enseignant responsable de chaque matière dans cette classe.</p>
            </x-slot>

            <x-slot name="actions">
                @can('structure.manage')
                    <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'assign-teacher')">
                        + Affecter un enseignant
                    </x-primary-button>
                @endcan
            </x-slot>

            @if ($schoolClass->teacherAssignments->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun enseignant affecté à cette classe." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Enseignant</th>
                                <th class="px-4 py-2.5">Matière</th>
                                @can('structure.manage')
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($schoolClass->teacherAssignments as $assignment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ $assignment->teacher?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $assignment->subject?->name ?? '—' }}</td>
                                    @can('structure.manage')
                                        <td class="px-4 py-2.5">
                                            <div class="flex justify-end">
                                                <form method="POST" action="{{ route('classes.assignments.destroy', [$schoolClass, $assignment]) }}"
                                                      onsubmit="return confirm('Retirer l\'affectation de {{ $assignment->teacher?->name }} en {{ $assignment->subject?->name }} ?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-800">Retirer</button>
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

        <!-- (f) Liens rapides -->
        <x-card title="Liens rapides" subtitle="Accès direct aux modules liés à cette classe.">
            <div class="flex flex-wrap items-center gap-3">
                @can('attendance.view')
                    <a href="{{ route('attendance.report', $schoolClass) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Feuille de présences
                    </a>
                @endcan
                @can('report_cards.view')
                    <a href="{{ route('report-cards.index', ['class_id' => $schoolClass->id]) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Bulletins de la classe
                    </a>
                @endcan
                @can('report_cards.generate')
                    <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'bulk-report-cards')"
                            class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition ease-in-out duration-150 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Générer les bulletins
                    </button>
                @endcan
            </div>
        </x-card>
    </div>

    <!-- (c) Modale : inscrire un élève -->
    @can('enrollments.manage')
        <x-modal name="enroll-student" focusable>
            <form method="POST" action="{{ route('enrollments.store') }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Inscrire un élève</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Inscription dans {{ $schoolClass->name }} pour l'année {{ $schoolClass->academicYear?->name ?? '—' }}.
                </p>
                <input type="hidden" name="class_id" value="{{ $schoolClass->id }}">

                @if ($availableStudents->isEmpty())
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Aucun élève disponible : tous les élèves actifs ont déjà une inscription pour cette année scolaire.
                    </p>
                @else
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-input-label for="enroll-student-id" value="Élève" />
                            <x-select id="enroll-student-id" name="student_id" class="mt-1"
                                      :options="$availableStudents->mapWithKeys(fn ($student) => [$student->id => $student->full_name.' ('.$student->matricule.')'])->all()"
                                      placeholder="— Choisir un élève —" required />
                        </div>
                        <div>
                            <x-input-label for="enroll-enrolled-on" value="Date d'inscription" />
                            <x-text-input id="enroll-enrolled-on" name="enrolled_on" type="date" class="mt-1 block w-full"
                                          :value="old('enrolled_on', now()->toDateString())" required />
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Inscrire l'élève</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan

    @can('structure.manage')
        <!-- (d) Modale : ajouter une matière -->
        <x-modal name="add-class-subject" focusable>
            <form method="POST" action="{{ route('classes.subjects.store', $schoolClass) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Ajouter une matière</h2>
                <p class="mt-1 text-sm text-gray-500">Le coefficient pondère la matière dans la moyenne ; le barème est la note maximale.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="add-class-subject-id" value="Matière" />
                        <x-select id="add-class-subject-id" name="subject_id" class="mt-1"
                                  :options="$subjects->pluck('name', 'id')->all()"
                                  placeholder="— Choisir une matière —" required />
                    </div>
                    <div>
                        <x-input-label for="add-class-subject-coefficient" value="Coefficient" />
                        <x-text-input id="add-class-subject-coefficient" name="coefficient" type="number" step="0.5" min="0.5" max="20"
                                      class="mt-1 block w-full" :value="old('coefficient', 1)" required />
                    </div>
                    <div>
                        <x-input-label for="add-class-subject-max-score" value="Barème (note maximale)" />
                        <x-text-input id="add-class-subject-max-score" name="max_score" type="number" step="0.5" min="1" max="100"
                                      class="mt-1 block w-full" :value="old('max_score', 20)" required />
                    </div>
                    <div class="sm:col-span-2">
                        <label for="add-class-subject-graded" class="flex items-center gap-2">
                            <input type="hidden" name="is_graded" value="0">
                            <input id="add-class-subject-graded" type="checkbox" name="is_graded" value="1"
                                   @checked(old('is_graded', true))
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">Matière notée (entre dans la moyenne)</span>
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Ajouter la matière</x-primary-button>
                </div>
            </form>
        </x-modal>

        <!-- (d) Modales : modifier une matière de la classe -->
        @foreach ($schoolClass->classSubjects as $classSubject)
            <x-modal :name="'edit-class-subject-'.$classSubject->id" focusable>
                <form method="POST" action="{{ route('classes.subjects.update', [$schoolClass, $classSubject]) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    <h2 class="text-lg font-semibold text-gray-900">
                        Modifier {{ $classSubject->subject?->name ?? 'la matière' }}
                    </h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="edit-class-subject-coefficient-{{ $classSubject->id }}" value="Coefficient" />
                            <x-text-input id="edit-class-subject-coefficient-{{ $classSubject->id }}" name="coefficient"
                                          type="number" step="0.5" min="0.5" max="20" class="mt-1 block w-full"
                                          :value="old('coefficient', $classSubject->coefficient)" required />
                        </div>
                        <div>
                            <x-input-label for="edit-class-subject-max-score-{{ $classSubject->id }}" value="Barème (note maximale)" />
                            <x-text-input id="edit-class-subject-max-score-{{ $classSubject->id }}" name="max_score"
                                          type="number" step="0.5" min="1" max="100" class="mt-1 block w-full"
                                          :value="old('max_score', $classSubject->max_score)" required />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="edit-class-subject-graded-{{ $classSubject->id }}" class="flex items-center gap-2">
                                <input type="hidden" name="is_graded" value="0">
                                <input id="edit-class-subject-graded-{{ $classSubject->id }}" type="checkbox" name="is_graded" value="1"
                                       @checked(old('is_graded', $classSubject->is_graded))
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Matière notée (entre dans la moyenne)</span>
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

        <!-- (e) Modale : affecter un enseignant -->
        <x-modal name="assign-teacher" focusable>
            <form method="POST" action="{{ route('classes.assignments.store', $schoolClass) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Affecter un enseignant</h2>
                <p class="mt-1 text-sm text-gray-500">L'enseignant pourra saisir les notes de cette matière dans cette classe.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="assign-teacher-user" value="Enseignant" />
                        <x-select id="assign-teacher-user" name="user_id" class="mt-1"
                                  :options="$teachers->pluck('name', 'id')->all()"
                                  placeholder="— Choisir un enseignant —" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="assign-teacher-subject" value="Matière" />
                        <x-select id="assign-teacher-subject" name="subject_id" class="mt-1"
                                  :options="$subjects->pluck('name', 'id')->all()"
                                  placeholder="— Choisir une matière —" required />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Affecter</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan

    <!-- (f) Modale : génération groupée des bulletins -->
    @can('report_cards.generate')
        <x-modal name="bulk-report-cards" focusable>
            <form method="POST" action="{{ route('report-cards.bulk', $schoolClass) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Générer les bulletins de la classe</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Les bulletins de tous les élèves inscrits dans {{ $schoolClass->name }} seront générés pour la période choisie.
                </p>
                <div class="mt-4">
                    <x-input-label for="bulk-report-card-period" value="Période" />
                    <x-select id="bulk-report-card-period" name="school_period_id" class="mt-1"
                              :options="$periods->pluck('name', 'id')->all()"
                              placeholder="— Choisir une période —" required />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Générer les bulletins</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
