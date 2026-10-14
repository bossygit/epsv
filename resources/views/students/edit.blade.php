<x-app-layout>
    <x-page-header
        title="Modifier {{ $student->full_name }}"
        subtitle="Corrigez les informations administratives de l'élève."
        :back="route('students.show', $student)"
    />

    <form method="POST" action="{{ route('students.update', $student) }}">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            <x-card title="Identité de l'élève">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="matricule" value="Matricule" />
                        <x-text-input id="matricule" type="text" class="mt-1 block w-full bg-gray-50 text-sm text-gray-500"
                                      :value="$student->matricule" disabled />
                        <p class="mt-1 text-xs text-gray-400">Le matricule est attribué automatiquement et ne peut pas être modifié.</p>
                    </div>
                    <div>
                        <x-input-label for="gender" value="Sexe *" />
                        <x-select name="gender" id="gender" class="mt-1"
                                  :options="['M' => 'Masculin', 'F' => 'Féminin']"
                                  :selected="$student->gender" placeholder="— Choisir —" required />
                        <x-input-error :messages="$errors->get('gender')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="first_name" value="Prénom *" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('first_name', $student->first_name)" maxlength="100" required />
                        <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="Nom *" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('last_name', $student->last_name)" maxlength="100" required />
                        <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="birth_date" value="Date de naissance" />
                        <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full text-sm"
                                      :value="old('birth_date', $student->birth_date?->toDateString())" />
                        <x-input-error :messages="$errors->get('birth_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="birth_place" value="Lieu de naissance" />
                        <x-text-input id="birth_place" name="birth_place" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('birth_place', $student->birth_place)" maxlength="100" />
                        <x-input-error :messages="$errors->get('birth_place')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="nationality" value="Nationalité" />
                        <x-text-input id="nationality" name="nationality" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('nationality', $student->nationality)" maxlength="50" />
                        <x-input-error :messages="$errors->get('nationality')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Statut *" />
                        <x-select name="status" id="status" class="mt-1"
                                  :options="[
                                      'active' => 'Actif',
                                      'inactive' => 'Inactif',
                                      'transferred' => 'Transféré',
                                      'graduated' => 'Diplômé',
                                  ]"
                                  :selected="$student->status" :placeholder="null" required />
                        <x-input-error :messages="$errors->get('status')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Adresse" />
                        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('address', $student->address)" maxlength="255" />
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes', $student->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                </div>
            </x-card>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('students.show', $student) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Annuler
                </a>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </div>
    </form>
</x-app-layout>
