@php
    $classNames = $classes->pluck('name', 'id');
    $defaultClassId = old('class_id', $selectedClassId) ?: $classes->first()?->id;
    $defaultPeriodId = old('school_period_id', $selectedPeriodId)
        ?: ($periods->firstWhere('status', \App\Models\SchoolPeriod::STATUS_OPEN)?->id ?? $periods->first()?->id);
@endphp

<x-app-layout>
    <x-page-header
        title="Nouvelle évaluation"
        subtitle="Choisissez la matière et la période, puis définissez le barème. Vous saisirez les notes juste après."
        :back="route('evaluations.index')"
    />

    <form method="POST" action="{{ route('evaluations.store') }}"
          x-data="{
              classId: '{{ $defaultClassId }}',
              filterSubjects() {
                  const select = this.$refs.classSubject;
                  if (! select) return;
                  let firstVisible = null;
                  Array.from(select.options).forEach((option) => {
                      if (option.value === '') return;
                      const visible = ! this.classId || option.dataset.classId === String(this.classId);
                      option.hidden = ! visible;
                      option.disabled = ! visible;
                      if (visible && firstVisible === null) firstVisible = option;
                  });
                  const current = select.selectedOptions[0];
                  if (! current || current.disabled) {
                      select.value = firstVisible ? firstVisible.value : '';
                  }
              },
          }"
          x-init="filterSubjects()">
        @csrf

        <x-card title="Caractéristiques de l'évaluation" subtitle="Matière, période, intitulé, barème et coefficient.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="class_id" value="Classe" />
                    <x-select id="class_id" name="class_id" class="mt-1"
                              :options="$classNames"
                              :selected="$defaultClassId"
                              :placeholder="null"
                              x-model="classId"
                              x-on:change="filterSubjects()"
                              required />
                    <x-input-error :messages="$errors->get('class_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="school_period_id" value="Période" />
                    <x-select id="school_period_id" name="school_period_id" class="mt-1"
                              :options="$periods->pluck('name', 'id')"
                              :selected="$defaultPeriodId"
                              :placeholder="null"
                              required />
                    <x-input-error :messages="$errors->get('school_period_id')" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="class_subject_id" value="Matière" />
                    <select id="class_subject_id" name="class_subject_id" x-ref="classSubject" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">— Choisir —</option>
                        @foreach ($classSubjects as $classSubject)
                            <option value="{{ $classSubject->id }}"
                                    data-class-id="{{ $classSubject->class_id }}"
                                    @selected((string) old('class_subject_id') === (string) $classSubject->id)>
                                {{ $classSubject->subject->name }} — {{ $classNames[$classSubject->class_id] ?? 'Classe inconnue' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        Les matières proposées dépendent de la classe sélectionnée.
                    </p>
                    <x-input-error :messages="$errors->get('class_subject_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="name" value="Intitulé de l'évaluation" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full"
                                  :value="old('name')" placeholder="Ex. Devoir n°2" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="type" value="Type d'évaluation" />
                    <x-select id="type" name="type" class="mt-1"
                              :options="['devoir' => 'Devoir', 'composition' => 'Composition', 'interrogation' => 'Interrogation', 'autre' => 'Autre']"
                              selected="devoir"
                              :placeholder="null"
                              required />
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="evaluation_date" value="Date de l'évaluation" />
                    <x-text-input id="evaluation_date" name="evaluation_date" type="date" class="mt-1 block w-full"
                                  :value="old('evaluation_date', now()->toDateString())" required />
                    <x-input-error :messages="$errors->get('evaluation_date')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="max_score" value="Barème (note maximale)" />
                    <x-text-input id="max_score" name="max_score" type="number" step="0.5" min="1" max="100"
                                  class="mt-1 block w-full" :value="old('max_score', 20)" required />
                    <p class="mt-1 text-xs text-gray-500">Entre 1 et 100. Les notes saisies ne pourront pas dépasser ce barème.</p>
                    <x-input-error :messages="$errors->get('max_score')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="coefficient" value="Coefficient" />
                    <x-text-input id="coefficient" name="coefficient" type="number" step="0.5" min="0.5" max="10"
                                  class="mt-1 block w-full" :value="old('coefficient', 1)" required />
                    <p class="mt-1 text-xs text-gray-500">Entre 0,5 et 10. Poids de l'évaluation dans la moyenne.</p>
                    <x-input-error :messages="$errors->get('coefficient')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('evaluations.index') }}"
                   class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Annuler
                </a>
                <x-primary-button>Créer l'évaluation</x-primary-button>
            </div>
        </x-card>
    </form>
</x-app-layout>
