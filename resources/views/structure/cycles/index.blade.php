<x-app-layout>
    <x-page-header
        title="Structure pédagogique"
        subtitle="Définissez les cycles (primaire, secondaire…) et les niveaux qu'ils contiennent."
    >
        <x-slot name="actions">
            @can('structure.manage')
                <x-primary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-cycle')">
                    + Nouveau cycle
                </x-primary-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="space-y-6">
        @forelse ($cycles as $cycle)
            <x-card :padding="false">
                <x-slot name="title">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-base font-semibold text-gray-900">{{ $cycle->name }}</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $cycle->code }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Ordre {{ $cycle->sequence }} — {{ $cycle->gradeLevels->count() }} niveau(x)
                    </p>
                </x-slot>

                <x-slot name="actions">
                    @can('structure.manage')
                        <button type="button"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-level-{{ $cycle->id }}')">
                            + Ajouter un niveau
                        </button>
                        <x-secondary-button x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-cycle-{{ $cycle->id }}')">
                            Modifier
                        </x-secondary-button>
                        <form method="POST" action="{{ route('cycles.destroy', $cycle) }}"
                              onsubmit="return confirm('Supprimer le cycle « {{ $cycle->name }} » ? Cette action est définitive.');">
                            @csrf
                            @method('DELETE')
                            <x-secondary-button>Supprimer</x-secondary-button>
                        </form>
                    @endcan
                </x-slot>

                <!-- Niveaux du cycle -->
                <div class="px-4 py-4 sm:px-6">
                    @if ($cycle->gradeLevels->isEmpty())
                        <p class="text-sm text-gray-500">Aucun niveau dans ce cycle.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Niveau</th>
                                        <th class="px-4 py-2.5">Code</th>
                                        <th class="px-4 py-2.5 text-right">Ordre</th>
                                        <th class="px-4 py-2.5 text-right">Classes</th>
                                        @can('structure.manage')
                                            <th class="px-4 py-2.5 text-right">Actions</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($cycle->gradeLevels as $level)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $level->name }}</td>
                                            <td class="px-4 py-2.5 text-gray-600">{{ $level->code }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $level->sequence }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $level->classes_count }}</td>
                                            @can('structure.manage')
                                                <td class="px-4 py-2.5">
                                                    <div class="flex flex-wrap items-center justify-end gap-3">
                                                        <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                                x-data="" x-on:click.prevent="$dispatch('open-modal', 'edit-level-{{ $level->id }}')">
                                                            Modifier
                                                        </button>
                                                        <form method="POST" action="{{ route('levels.destroy', $level) }}"
                                                              onsubmit="return confirm('Supprimer le niveau « {{ $level->name }} » ?');">
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
                </div>
            </x-card>

            @can('structure.manage')
                <!-- Modale : modifier le cycle -->
                <x-modal :name="'edit-cycle-'.$cycle->id" focusable>
                    <form method="POST" action="{{ route('cycles.update', $cycle) }}" class="p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-semibold text-gray-900">Modifier le cycle {{ $cycle->name }}</h2>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="cycle-name-{{ $cycle->id }}" value="Nom (ex. Primaire)" />
                                <x-text-input id="cycle-name-{{ $cycle->id }}" name="name" class="mt-1 block w-full" :value="old('name', $cycle->name)" required />
                            </div>
                            <div>
                                <x-input-label for="cycle-code-{{ $cycle->id }}" value="Code (ex. PRIM)" />
                                <x-text-input id="cycle-code-{{ $cycle->id }}" name="code" class="mt-1 block w-full" :value="old('code', $cycle->code)" required />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="cycle-sequence-{{ $cycle->id }}" value="Ordre d'affichage" />
                                <x-text-input id="cycle-sequence-{{ $cycle->id }}" name="sequence" type="number" min="1" max="20" class="mt-1 block w-full" :value="old('sequence', $cycle->sequence)" required />
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <x-primary-button>Enregistrer</x-primary-button>
                        </div>
                    </form>
                </x-modal>

                <!-- Modale : nouveau niveau -->
                <x-modal :name="'create-level-'.$cycle->id" focusable>
                    <form method="POST" action="{{ route('levels.store') }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-semibold text-gray-900">Nouveau niveau — {{ $cycle->name }}</h2>
                        <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="level-name-{{ $cycle->id }}" value="Nom (ex. Sixième)" />
                                <x-text-input id="level-name-{{ $cycle->id }}" name="name" class="mt-1 block w-full" :value="old('name')" required />
                            </div>
                            <div>
                                <x-input-label for="level-code-{{ $cycle->id }}" value="Code (ex. 6EME)" />
                                <x-text-input id="level-code-{{ $cycle->id }}" name="code" class="mt-1 block w-full" :value="old('code')" required />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="level-sequence-{{ $cycle->id }}" value="Ordre d'affichage" />
                                <x-text-input id="level-sequence-{{ $cycle->id }}" name="sequence" type="number" min="1" max="20" class="mt-1 block w-full" :value="old('sequence', $cycle->gradeLevels->max('sequence') + 1)" required />
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <x-primary-button>Créer le niveau</x-primary-button>
                        </div>
                    </form>
                </x-modal>

                <!-- Modales : modifier un niveau -->
                @foreach ($cycle->gradeLevels as $level)
                    <x-modal :name="'edit-level-'.$level->id" focusable>
                        <form method="POST" action="{{ route('levels.update', $level) }}" class="p-6">
                            @csrf
                            @method('PUT')
                            <h2 class="text-lg font-semibold text-gray-900">Modifier le niveau {{ $level->name }}</h2>
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="edit-level-name-{{ $level->id }}" value="Nom" />
                                    <x-text-input id="edit-level-name-{{ $level->id }}" name="name" class="mt-1 block w-full" :value="old('name', $level->name)" required />
                                </div>
                                <div>
                                    <x-input-label for="edit-level-code-{{ $level->id }}" value="Code" />
                                    <x-text-input id="edit-level-code-{{ $level->id }}" name="code" class="mt-1 block w-full" :value="old('code', $level->code)" required />
                                </div>
                                <div>
                                    <x-input-label for="edit-level-cycle-{{ $level->id }}" value="Cycle" />
                                    <x-select id="edit-level-cycle-{{ $level->id }}" name="cycle_id" class="mt-1"
                                              :options="$cycles->pluck('name', 'id')->all()"
                                              :selected="old('cycle_id', $level->cycle_id)" :placeholder="null" required />
                                </div>
                                <div>
                                    <x-input-label for="edit-level-sequence-{{ $level->id }}" value="Ordre d'affichage" />
                                    <x-text-input id="edit-level-sequence-{{ $level->id }}" name="sequence" type="number" min="1" max="20" class="mt-1 block w-full" :value="old('sequence', $level->sequence)" required />
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
        @empty
            <x-card>
                <x-empty-state message="Aucun cycle défini. Créez le premier cycle (ex. Primaire) pour structurer l'école." />
            </x-card>
        @endforelse
    </div>

    @can('structure.manage')
        <!-- Modale : nouveau cycle -->
        <x-modal name="create-cycle" focusable>
            <form method="POST" action="{{ route('cycles.store') }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Nouveau cycle</h2>
                <p class="mt-1 text-sm text-gray-500">Un cycle regroupe plusieurs niveaux (ex. le primaire contient CP1, CP2, CE1…).</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="create-cycle-name" value="Nom (ex. Primaire)" />
                        <x-text-input id="create-cycle-name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label for="create-cycle-code" value="Code (ex. PRIM)" />
                        <x-text-input id="create-cycle-code" name="code" class="mt-1 block w-full" :value="old('code')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="create-cycle-sequence" value="Ordre d'affichage (1, 2, 3…)" />
                        <x-text-input id="create-cycle-sequence" name="sequence" type="number" min="1" max="20" class="mt-1 block w-full" :value="old('sequence', $cycles->max('sequence') + 1)" required />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                    <x-primary-button>Créer le cycle</x-primary-button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
