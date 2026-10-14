<x-app-layout>
    <x-page-header
        title="Nouvel élève"
        :subtitle="$year
            ? 'Inscription d\'un nouvel élève pour l\'année scolaire '.$year->name
            : 'Aucune année scolaire active'"
        :back="route('students.index')"
    />

    @php
        $classOptions = $classes
            ->mapWithKeys(fn ($class) => [$class->id => $class->name.($class->gradeLevel ? ' — '.$class->gradeLevel->name : '')])
            ->all();
    @endphp

    <form method="POST" action="{{ route('students.store') }}">
        @csrf

        <div class="space-y-6">
            <!-- Identité -->
            <x-card title="Identité de l'élève" subtitle="Le matricule est généré automatiquement à l'enregistrement.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="first_name" value="Prénom *" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('first_name')" maxlength="100" required />
                        <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="Nom *" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('last_name')" maxlength="100" required />
                        <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="gender" value="Sexe *" />
                        <x-select name="gender" id="gender" class="mt-1"
                                  :options="['M' => 'Masculin', 'F' => 'Féminin']"
                                  placeholder="— Choisir —" required />
                        <x-input-error :messages="$errors->get('gender')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="birth_date" value="Date de naissance" />
                        <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full text-sm"
                                      :value="old('birth_date')" />
                        <x-input-error :messages="$errors->get('birth_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="birth_place" value="Lieu de naissance" />
                        <x-text-input id="birth_place" name="birth_place" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('birth_place')" maxlength="100" />
                        <x-input-error :messages="$errors->get('birth_place')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="nationality" value="Nationalité" />
                        <x-text-input id="nationality" name="nationality" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('nationality', 'Congolaise')" maxlength="50" />
                        <x-input-error :messages="$errors->get('nationality')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Adresse" />
                        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('address')" maxlength="255" />
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                </div>
            </x-card>

            <!-- Responsable légal -->
            <x-card title="Responsable légal (optionnel)"
                    subtitle="Renseignez un premier responsable : il sera enregistré comme contact principal et contact d'urgence. Un nom et un téléphone sont nécessaires.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="guardian_full_name" value="Nom complet du responsable" />
                        <x-text-input id="guardian_full_name" name="guardian_full_name" type="text"
                                      class="mt-1 block w-full text-sm" :value="old('guardian_full_name')" maxlength="150" />
                        <x-input-error :messages="$errors->get('guardian_full_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian_relationship" value="Lien avec l'élève" />
                        <x-select name="guardian_relationship" id="guardian_relationship" class="mt-1"
                                  :options="['pere' => 'Père', 'mere' => 'Mère', 'tuteur' => 'Tuteur', 'autre' => 'Autre']"
                                  placeholder="— Choisir —" />
                        <x-input-error :messages="$errors->get('guardian_relationship')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian_phone" value="Téléphone" />
                        <x-text-input id="guardian_phone" name="guardian_phone" type="text" class="mt-1 block w-full text-sm"
                                      :value="old('guardian_phone')" maxlength="30" placeholder="+242 06 000 00 00" />
                        <x-input-error :messages="$errors->get('guardian_phone')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="guardian_phone_alt" value="Téléphone secondaire" />
                        <x-text-input id="guardian_phone_alt" name="guardian_phone_alt" type="text"
                                      class="mt-1 block w-full text-sm" :value="old('guardian_phone_alt')" maxlength="30" />
                        <x-input-error :messages="$errors->get('guardian_phone_alt')" class="mt-1" />
                    </div>
                </div>
            </x-card>

            <!-- Inscription immédiate -->
            <x-card title="Inscription immédiate (optionnel)"
                    subtitle="Inscrivez directement l'élève dans une classe de l'année courante. Vous pourrez aussi le faire plus tard depuis sa fiche.">
                @if ($classes->isEmpty())
                    <p class="text-sm text-gray-500">Aucune classe n'est disponible pour l'année courante.</p>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="class_id" value="Classe" />
                            <x-select name="class_id" id="class_id" class="mt-1" :options="$classOptions"
                                      placeholder="— Ne pas inscrire maintenant —" />
                            <x-input-error :messages="$errors->get('class_id')" class="mt-1" />
                        </div>
                    </div>
                @endif
            </x-card>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('students.index') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Annuler
                </a>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
        </div>
    </form>
</x-app-layout>
