<x-app-layout>
    <x-page-header
        :title="$student->full_name"
        :subtitle="'Matricule '.$student->matricule.' — '.($student->gender === 'F' ? 'Fille' : 'Garçon')"
        :back="route('students.index')"
    >
        <x-slot name="actions">
            @can('students.manage')
                <a href="{{ route('students.edit', $student) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Modifier
                </a>
                <form method="POST" action="{{ route('students.archive', $student) }}"
                      onsubmit="return confirm('Archiver cette fiche élève ? L\'élève sera désactivé et les données resteront consultables dans l\'historique.')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>Archiver</x-danger-button>
                </form>
            @endcan
        </x-slot>
    </x-page-header>

    @php
        $relationshipLabels = [
            'pere' => 'Père',
            'mere' => 'Mère',
            'tuteur' => 'Tuteur',
            'autre' => 'Autre',
        ];

        $classOptions = $classes
            ->mapWithKeys(fn ($class) => [$class->id => $class->name.($class->gradeLevel ? ' — '.$class->gradeLevel->name : '')])
            ->all();
    @endphp

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- (a) Carte identité -->
            <x-card title="Identité" class="lg:col-span-2">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matricule</dt>
                        <dd class="mt-1 font-mono text-sm text-gray-900">{{ $student->matricule }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nom complet</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sexe</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $student->gender === 'F' ? 'Féminin' : 'Masculin' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Date de naissance</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $student->birth_date ? $student->birth_date->format('d/m/Y') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lieu de naissance</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $student->birth_place ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Âge</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $student->age() !== null ? $student->age().' ans' : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nationalité</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $student->nationality ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Statut</dt>
                        <dd class="mt-1"><x-status-badge :status="$student->status" /></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Adresse</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $student->address ?: '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <!-- (b) Inscription de l'année -->
            <x-card title="Inscription de l'année" :subtitle="$year ? $year->name : 'Aucune année courante'">
                @if ($currentEnrollment)
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Classe</dt>
                            <dd class="text-right font-medium text-gray-900">
                                {{ $currentEnrollment->schoolClass?->name ?? '—' }}
                                @if ($currentEnrollment->schoolClass?->gradeLevel)
                                    <span class="block text-xs font-normal text-gray-400">{{ $currentEnrollment->schoolClass->gradeLevel->name }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Date d'inscription</dt>
                            <dd class="text-right text-gray-900">
                                {{ $currentEnrollment->enrolled_on ? $currentEnrollment->enrolled_on->format('d/m/Y') : '—' }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Statut</dt>
                            <dd class="text-right">
                                <x-status-badge :status="$currentEnrollment->status"
                                                :label="$currentEnrollment->status === 'left' ? 'Sorti(e)' : null" />
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-500">
                        Aucune inscription pour {{ $year ? $year->name : "l'année courante" }}.
                    </p>
                    @can('enrollments.manage')
                        @if ($classes->isNotEmpty())
                            <div class="mt-4">
                                <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'enroll-student')">
                                    Inscrire dans une classe
                                </x-secondary-button>
                            </div>
                        @else
                            <p class="mt-2 text-xs text-gray-400">Aucune classe disponible pour cette année.</p>
                        @endif
                    @endcan
                @endif
            </x-card>
        </div>

        <!-- (c) Responsables légaux -->
        <x-card title="Responsables légaux" subtitle="Contacts à joindre en cas de besoin." :padding="false">
            <x-slot name="actions">
                @can('students.manage')
                    <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'add-guardian')">
                        + Ajouter un responsable
                    </x-secondary-button>
                @endcan
            </x-slot>

            @if ($student->guardians->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun responsable légal enregistré pour cet élève." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Responsable</th>
                                <th class="px-4 py-2.5">Lien</th>
                                <th class="px-4 py-2.5">Téléphone</th>
                                <th class="px-4 py-2.5">Rôle</th>
                                @can('students.manage')
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($student->guardians as $guardian)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <p class="font-medium text-gray-900">{{ $guardian->full_name }}</p>
                                        @if ($guardian->profession)
                                            <p class="text-xs text-gray-400">{{ $guardian->profession }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $relationshipLabels[$guardian->pivot->relationship] ?? ($guardian->pivot->relationship ? ucfirst($guardian->pivot->relationship) : '—') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $guardian->phone ?: '—' }}
                                        @if ($guardian->phone_alt)
                                            <span class="block text-xs text-gray-400">{{ $guardian->phone_alt }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @if ($guardian->pivot->is_primary)
                                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">Principal</span>
                                            @endif
                                            @if ($guardian->pivot->is_emergency_contact)
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Urgence</span>
                                            @endif
                                            @if (! $guardian->pivot->is_primary && ! $guardian->pivot->is_emergency_contact)
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    @can('students.manage')
                                        <td class="px-4 py-2.5 text-right">
                                            <form method="POST" action="{{ route('guardians.destroy', [$student, $guardian]) }}"
                                                  onsubmit="return confirm('Retirer ce responsable de la fiche de l\'élève ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                                                    Retirer
                                                </button>
                                            </form>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <!-- (d) Résumé des présences -->
        <div>
            <h2 class="mb-3 text-base font-semibold text-gray-900">Assiduité</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-card
                    label="Présences"
                    :value="$attendanceSummary->get('present', 0)"
                    tone="emerald"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
                <x-stat-card
                    label="Absences"
                    :value="$attendanceSummary->get('absent', 0)"
                    tone="rose"
                    icon="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
                <x-stat-card
                    label="Retards"
                    :value="$attendanceSummary->get('late', 0)"
                    tone="amber"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                />
                <x-stat-card
                    label="Absences justifiées"
                    :value="$attendanceSummary->get('excused', 0)"
                    tone="indigo"
                    icon="M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                />
            </div>
        </div>

        <x-card title="Dernières absences et retards" :padding="false">
            @if ($recentAbsences->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucune absence ni retard enregistré." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Date</th>
                                <th class="px-4 py-2.5">Classe</th>
                                <th class="px-4 py-2.5">Statut</th>
                                <th class="px-4 py-2.5">Justification</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($recentAbsences as $record)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-gray-900">
                                        {{ $record->session?->session_date ? $record->session->session_date->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $record->session?->schoolClass?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-status-badge :status="$record->status" />
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $record->justification ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <!-- (e) Historique des inscriptions -->
        <x-card title="Historique des inscriptions" subtitle="Parcours de l'élève année par année." :padding="false">
            @if ($student->enrollments->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucune inscription enregistrée." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Année scolaire</th>
                                <th class="px-4 py-2.5">Classe</th>
                                <th class="px-4 py-2.5">Date d'inscription</th>
                                <th class="px-4 py-2.5">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($student->enrollments->sortByDesc(fn ($enrollment) => $enrollment->academicYear?->start_date?->toDateString() ?? '') as $enrollment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">
                                        {{ $enrollment->academicYear?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $enrollment->schoolClass?->name ?? '—' }}
                                        @if ($enrollment->schoolClass?->gradeLevel)
                                            <span class="ml-1 text-xs text-gray-400">{{ $enrollment->schoolClass->gradeLevel->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $enrollment->enrolled_on ? $enrollment->enrolled_on->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-status-badge :status="$enrollment->status"
                                                        :label="$enrollment->status === 'left' ? 'Sorti(e)' : null" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <!-- (f) Bulletins -->
        @can('report_cards.view')
            <x-card title="Bulletins" subtitle="Derniers bulletins générés." :padding="false">
                @if ($reportCards->isEmpty())
                    <div class="px-6 py-4">
                        <x-empty-state message="Aucun bulletin généré pour cet élève." />
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2.5">Période</th>
                                    <th class="px-4 py-2.5 text-right">Moyenne</th>
                                    <th class="px-4 py-2.5 text-right">Rang</th>
                                    <th class="px-4 py-2.5">Statut</th>
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($reportCards as $card)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900">{{ $card->period?->name ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-semibold text-gray-900">
                                            {{ $card->average !== null ? number_format((float) $card->average, 2, ',', ' ') : '—' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $card->rank ?? '—' }}</td>
                                        <td class="px-4 py-2.5"><x-status-badge :status="$card->status" /></td>
                                        <td class="px-4 py-2.5 text-right">
                                            <a href="{{ route('report-cards.show', $card) }}"
                                               class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                                Consulter
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endcan
    </div>

    <!-- Modale : inscription dans une classe -->
    @can('enrollments.manage')
        @if (! $currentEnrollment && $classes->isNotEmpty())
            <x-modal name="enroll-student" focusable>
                <form method="POST" action="{{ route('enrollments.store') }}" class="p-6">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">

                    <h2 class="text-lg font-semibold text-gray-900">Inscrire {{ $student->full_name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Année scolaire {{ $year ? $year->name : 'courante' }} — une seule inscription par année est autorisée.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="enroll-class" value="Classe *" />
                            <x-select name="class_id" id="enroll-class" class="mt-1" :options="$classOptions"
                                      placeholder="— Choisir une classe —" required />
                            <x-input-error :messages="$errors->get('class_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="enroll-date" value="Date d'inscription *" />
                            <x-text-input id="enroll-date" name="enrolled_on" type="date" class="mt-1 block w-full text-sm"
                                          :value="old('enrolled_on', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('enrolled_on')" class="mt-1" />
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

    <!-- Modale : ajout d'un responsable légal -->
    @can('students.manage')
        <x-modal name="add-guardian" focusable>
            <form method="POST" action="{{ route('guardians.store', $student) }}" class="p-6">
                @csrf

                <h2 class="text-lg font-semibold text-gray-900">Ajouter un responsable légal</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $student->full_name }} — {{ $student->matricule }}</p>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="guardian-full-name" value="Nom complet *" />
                        <x-text-input id="guardian-full-name" name="full_name" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('full_name')" maxlength="150" required />
                        <x-input-error :messages="$errors->get('full_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian-relationship" value="Lien avec l'élève *" />
                        <x-select name="relationship" id="guardian-relationship" class="mt-1"
                                  :options="$relationshipLabels" placeholder="— Choisir —" required />
                        <x-input-error :messages="$errors->get('relationship')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian-phone" value="Téléphone *" />
                        <x-text-input id="guardian-phone" name="phone" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('phone')" maxlength="30" placeholder="+242 06 000 00 00" required />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian-phone-alt" value="Téléphone secondaire" />
                        <x-text-input id="guardian-phone-alt" name="phone_alt" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('phone_alt')" maxlength="30" />
                        <x-input-error :messages="$errors->get('phone_alt')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian-email" value="Courriel" />
                        <x-text-input id="guardian-email" name="email" type="email" class="mt-1 block w-full text-sm"
                                      :value="old('email')" maxlength="150" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian-profession" value="Profession" />
                        <x-text-input id="guardian-profession" name="profession" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('profession')" maxlength="100" />
                        <x-input-error :messages="$errors->get('profession')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="guardian-address" value="Adresse" />
                        <x-text-input id="guardian-address" name="address" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('address')" maxlength="255" />
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2 flex flex-col gap-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_primary" value="1"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                   @checked(old('is_primary'))>
                            <span>Responsable principal</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_emergency_contact" value="1"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                   @checked(old('is_emergency_contact'))>
                            <span>Contact d'urgence</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Ajouter</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
