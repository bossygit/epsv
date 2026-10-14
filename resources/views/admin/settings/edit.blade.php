<x-app-layout>
    <x-page-header
        title="Paramètres de l'école"
        subtitle="Identité de l'établissement, règles de calcul des moyennes et alertes."
    />

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Identité de l'établissement -->
        <x-card
            title="Identité de l'établissement"
            subtitle="Ces informations apparaissent sur les bulletins, procès-verbaux et documents officiels."
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Nom de l'établissement" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $profile->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="short_name" value="Nom court (sigle)" />
                    <x-text-input id="short_name" name="short_name" class="mt-1 block w-full" :value="old('short_name', $profile->short_name)" placeholder="ex. EPSV" />
                    <x-input-error :messages="$errors->get('short_name')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="motto" value="Devise" />
                    <x-text-input id="motto" name="motto" class="mt-1 block w-full" :value="old('motto', $profile->motto)" placeholder="ex. Travail — Discipline — Excellence" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="address" value="Adresse" />
                    <x-text-input id="address" name="address" class="mt-1 block w-full" :value="old('address', $profile->address)" />
                </div>
                <div>
                    <x-input-label for="city" value="Ville" />
                    <x-text-input id="city" name="city" class="mt-1 block w-full" :value="old('city', $profile->city)" />
                </div>
                <div>
                    <x-input-label for="country" value="Pays" />
                    <x-text-input id="country" name="country" class="mt-1 block w-full" :value="old('country', $profile->country)" />
                </div>
                <div>
                    <x-input-label for="phone" value="Téléphone" />
                    <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $profile->phone)" />
                </div>
                <div>
                    <x-input-label for="email" value="Adresse e-mail" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $profile->email)" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="website" value="Site web" />
                    <x-text-input id="website" name="website" class="mt-1 block w-full" :value="old('website', $profile->website)" />
                </div>
                <div>
                    <x-input-label for="director_name" value="Nom du directeur / de la directrice" />
                    <x-text-input id="director_name" name="director_name" class="mt-1 block w-full" :value="old('director_name', $profile->director_name)" />
                </div>
                <div>
                    <x-input-label for="director_title" value="Titre du responsable" />
                    <x-text-input id="director_title" name="director_title" class="mt-1 block w-full" :value="old('director_title', $profile->director_title)" placeholder="ex. Directeur" />
                </div>
            </div>
        </x-card>

        <!-- Règles de calcul -->
        <x-card
            title="Règles de calcul"
            subtitle="Ces règles s'appliquent à tous les calculs de moyennes, de rangs et de décisions."
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="target_scale" value="Barème cible" />
                    <x-text-input id="target_scale" name="target_scale" type="number" step="0.01" min="1" max="100" class="mt-1 block w-full" :value="old('target_scale', $profile->gradingSetting('target_scale'))" required />
                    <p class="mt-1 text-xs text-gray-500">Notes ramenées sur ce barème (ex. 20).</p>
                    <x-input-error :messages="$errors->get('target_scale')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="decimals" value="Nombre de décimales (0 à 4)" />
                    <x-text-input id="decimals" name="decimals" type="number" min="0" max="4" class="mt-1 block w-full" :value="old('decimals', $profile->gradingSetting('decimals'))" required />
                    <x-input-error :messages="$errors->get('decimals')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="rounding" value="Règle d'arrondi" />
                    <x-select name="rounding" class="mt-1" :options="[
                        'half_up' => 'Arrondi au plus proche',
                        'half_even' => 'Arrondi bancaire',
                        'floor' => 'Troncature',
                    ]" :selected="$profile->gradingSetting('rounding')" :placeholder="null" required />
                    <x-input-error :messages="$errors->get('rounding')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="pass_threshold" value="Seuil de réussite" />
                    <x-text-input id="pass_threshold" name="pass_threshold" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('pass_threshold', $profile->gradingSetting('pass_threshold'))" required />
                    <p class="mt-1 text-xs text-gray-500">Moyenne à partir de laquelle l'élève est admis.</p>
                    <x-input-error :messages="$errors->get('pass_threshold')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="ranking_enabled" value="0">
                        <input type="checkbox" id="ranking_enabled" name="ranking_enabled" value="1"
                               @checked(old('ranking_enabled', $profile->gradingSetting('ranking_enabled')))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <x-input-label for="ranking_enabled" value="Activer le classement des élèves" />
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Lorsque le classement est désactivé, les rangs ne sont pas calculés (les moyennes restent affichées).
                    </p>
                </div>
                <div>
                    <x-input-label for="missing_policy" value="Notes manquantes" />
                    <x-select name="missing_policy" class="mt-1" :options="[
                        'strict' => 'Exclure les matières incomplètes',
                        'ignore' => 'Calculer sur les données disponibles',
                    ]" :selected="$profile->gradingSetting('missing_policy')" :placeholder="null" required />
                    <x-input-error :messages="$errors->get('missing_policy')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="absent_policy" value="Absence à une évaluation" />
                    <x-select name="absent_policy" class="mt-1" :options="[
                        'exclude' => 'Ne pas pénaliser une absence à une évaluation',
                        'zero' => 'Compter zéro',
                    ]" :selected="$profile->gradingSetting('absent_policy')" :placeholder="null" required />
                    <x-input-error :messages="$errors->get('absent_policy')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="annual_weighting" value="Pondération annuelle" />
                    <x-select name="annual_weighting" class="mt-1" :options="[
                        'period_weight' => 'Pondération par période',
                    ]" :selected="$profile->gradingSetting('annual_weighting')" :placeholder="null" required />
                    <p class="mt-1 text-xs text-gray-500">La moyenne annuelle pondère chaque période par son poids.</p>
                    <x-input-error :messages="$errors->get('annual_weighting')" class="mt-1" />
                </div>
            </div>
        </x-card>

        <!-- Alertes -->
        <x-card
            title="Alertes"
            subtitle="Seuils déclenchant les alertes du tableau de bord."
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="alert_absences_count" value="Nombre d'absences déclenchant une alerte" />
                    <x-text-input id="alert_absences_count" name="alert_absences_count" type="number" min="1" max="50" class="mt-1 block w-full" :value="old('alert_absences_count', $profile->attendanceSetting('alert_absences_count'))" required />
                    <x-input-error :messages="$errors->get('alert_absences_count')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="alert_window_days" value="Fenêtre d'observation (jours)" />
                    <x-text-input id="alert_window_days" name="alert_window_days" type="number" min="7" max="180" class="mt-1 block w-full" :value="old('alert_window_days', $profile->attendanceSetting('alert_window_days'))" required />
                    <x-input-error :messages="$errors->get('alert_window_days')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="grade_entry_delay_days" value="Délai de saisie des notes (jours)" />
                    <x-text-input id="grade_entry_delay_days" name="grade_entry_delay_days" type="number" min="1" max="60" class="mt-1 block w-full" :value="old('grade_entry_delay_days', $profile->gradingAlertSetting('grade_entry_delay_days'))" required />
                    <x-input-error :messages="$errors->get('grade_entry_delay_days')" class="mt-1" />
                </div>
            </div>
        </x-card>

        <div class="flex justify-end">
            <x-primary-button>Enregistrer les paramètres</x-primary-button>
        </div>
    </form>
</x-app-layout>
