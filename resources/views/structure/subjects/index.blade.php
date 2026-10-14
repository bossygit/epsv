<x-app-layout>
    <x-page-header
        title="Matières"
        subtitle="Catalogue des matières enseignées et de leurs paramètres par défaut."
    >
        <x-slot name="actions">
            @can('structure.manage')
                <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-subject')">
                    + Nouvelle matière
                </x-primary-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card :padding="false">
        @if ($subjects->isEmpty())
            <div class="px-6 py-4">
                <x-empty-state message="Aucune matière au catalogue. Créez la première matière (ex. Mathématiques)." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Matière</th>
                            <th class="px-4 py-2.5">Code</th>
                            <th class="px-4 py-2.5">Description</th>
                            <th class="px-4 py-2.5 text-right">Classes</th>
                            <th class="px-4 py-2.5">Statut</th>
                            @can('structure.manage')
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($subjects as $subject)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5 font-medium text-gray-900">{{ $subject->name }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $subject->code }}</td>
                                <td class="px-4 py-2.5 text-gray-500">
                                    {{ $subject->description ? \Illuminate\Support\Str::limit($subject->description, 90) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600">{{ $subject->class_subjects_count }}</td>
                                <td class="px-4 py-2.5">
                                    <x-status-badge :status="$subject->is_active ? 'active' : 'inactive'" />
                                </td>
                                @can('structure.manage')
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-wrap items-center justify-end gap-3">
                                            <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                    x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-subject-{{ $subject->id }}')">
                                                Modifier
                                            </button>
                                            <form method="POST" action="{{ route('subjects.destroy', $subject) }}"
                                                  onsubmit="return confirm('Supprimer la matière « {{ $subject->name }} » ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs font-semibold text-rose-600 hover:text-rose-800"
                                                        title="Une matière utilisée par des classes ne peut pas être supprimée : désactivez-la plutôt.">
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

    @can('structure.manage')
        <!-- Modale : nouvelle matière -->
        <x-modal name="create-subject" focusable>
            <form method="POST" action="{{ route('subjects.store') }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Nouvelle matière</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="create-subject-name" value="Nom (ex. Mathématiques)" />
                        <x-text-input id="create-subject-name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label for="create-subject-code" value="Code (ex. MATH)" />
                        <x-text-input id="create-subject-code" name="code" class="mt-1 block w-full" :value="old('code')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="create-subject-description" value="Description (facultative)" />
                        <textarea id="create-subject-description" name="description" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description') }}</textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Créer la matière</x-primary-button>
                </div>
            </form>
        </x-modal>

        <!-- Modales : modifier une matière -->
        @foreach ($subjects as $subject)
            <x-modal :name="'edit-subject-'.$subject->id" focusable>
                <form method="POST" action="{{ route('subjects.update', $subject) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    <h2 class="text-lg font-semibold text-gray-900">Modifier {{ $subject->name }}</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="edit-subject-name-{{ $subject->id }}" value="Nom" />
                            <x-text-input id="edit-subject-name-{{ $subject->id }}" name="name" class="mt-1 block w-full" :value="old('name', $subject->name)" required />
                        </div>
                        <div>
                            <x-input-label for="edit-subject-code-{{ $subject->id }}" value="Code" />
                            <x-text-input id="edit-subject-code-{{ $subject->id }}" name="code" class="mt-1 block w-full" :value="old('code', $subject->code)" required />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="edit-subject-description-{{ $subject->id }}" value="Description (facultative)" />
                            <textarea id="edit-subject-description-{{ $subject->id }}" name="description" rows="2"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $subject->description) }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="edit-subject-active-{{ $subject->id }}" class="flex items-center gap-2">
                                <input type="hidden" name="is_active" value="0">
                                <input id="edit-subject-active-{{ $subject->id }}" type="checkbox" name="is_active" value="1"
                                       @checked(old('is_active', $subject->is_active))
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Matière active (proposée dans les classes)</span>
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
