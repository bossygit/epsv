<x-app-layout>
    <x-page-header
        title="Élèves"
        :subtitle="$year
            ? 'Année scolaire '.$year->name.' — '.$query->total().' élève(s)'
            : 'Aucune année scolaire active'"
    >
        <x-slot name="actions">
            @can('students.manage')
                <a href="{{ route('students.create') }}"
                   class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition ease-in-out duration-150 hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    + Nouvel élève
                </a>
            @endcan
        </x-slot>
    </x-page-header>

    @php
        $statusOptions = [
            'active' => 'Actif',
            'inactive' => 'Inactif',
            'transferred' => 'Transféré',
            'graduated' => 'Diplômé',
        ];

        $classOptions = $classes
            ->mapWithKeys(fn ($class) => [$class->id => $class->name.($class->gradeLevel ? ' — '.$class->gradeLevel->name : '')])
            ->all();
    @endphp

    <div class="space-y-6">
        <!-- Filtres -->
        <x-card :padding="false">
            <div class="px-4 py-4 sm:px-6">
                <form method="GET" action="{{ route('students.index') }}"
                      class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <x-input-label for="search" value="Recherche" />
                        <x-text-input id="search" name="search" type="text" class="mt-1 block w-full text-sm"
                                      :value="request('search')" placeholder="Nom, prénom ou matricule" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Statut" />
                        <x-select name="status" id="status" class="mt-1" :options="$statusOptions"
                                  :selected="request('status')" placeholder="Tous les statuts" />
                    </div>
                    <div>
                        <x-input-label for="class_id" value="Classe" />
                        <x-select name="class_id" id="class_id" class="mt-1" :options="$classOptions"
                                  :selected="request('class_id')" placeholder="Toutes les classes" />
                    </div>
                    <div class="flex items-center gap-2">
                        <x-primary-button>Filtrer</x-primary-button>
                        <a href="{{ route('students.index') }}"
                           class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </x-card>

        <!-- Liste -->
        <x-card :padding="false">
            @if ($query->isEmpty())
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun élève ne correspond à ces critères." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Matricule</th>
                                <th class="px-4 py-2.5">Nom complet</th>
                                <th class="px-4 py-2.5">Sexe</th>
                                <th class="px-4 py-2.5">Classe actuelle</th>
                                <th class="px-4 py-2.5">Statut</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($query as $student)
                                @php $enrollment = $student->enrollments->first(); @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-600">{{ $student->matricule }}</td>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('students.show', $student) }}"
                                           class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $student->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ $student->gender === 'F' ? 'Féminin' : 'Masculin' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        @if ($enrollment?->schoolClass)
                                            {{ $enrollment->schoolClass->name }}
                                            @if ($enrollment->schoolClass->gradeLevel)
                                                <span class="ml-1 text-xs text-gray-400">{{ $enrollment->schoolClass->gradeLevel->name }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">Non inscrit</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <x-status-badge :status="$student->status" />
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <a href="{{ route('students.show', $student) }}"
                                               class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                                Voir la fiche
                                            </a>
                                            @can('students.manage')
                                                <a href="{{ route('students.edit', $student) }}"
                                                   class="text-xs font-semibold text-gray-500 hover:text-gray-700">
                                                    Modifier
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <div>
            {{ $query->links() }}
        </div>
    </div>
</x-app-layout>
