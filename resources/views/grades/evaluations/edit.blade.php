@php
    $typeLabels = [
        'devoir' => 'Devoir',
        'composition' => 'Composition',
        'interrogation' => 'Interrogation',
        'autre' => 'Autre',
    ];
@endphp

<x-app-layout>
    <x-page-header
        title="Modifier l'évaluation"
        :subtitle="$evaluation->name.' — '.$evaluation->classSubject->subject->name.' ('.$evaluation->classSubject->schoolClass->name.')'"
        :back="route('evaluations.index')"
    >
        <x-slot name="actions">
            <x-status-badge :status="$evaluation->status" />
            <a href="{{ route('grades.entry', $evaluation) }}"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Saisir les notes
            </a>
        </x-slot>
    </x-page-header>

    <div class="space-y-6">
        <!-- Rappel (lecture seule) -->
        <x-card title="Rattachement" subtitle="Ces informations ne sont pas modifiables après la création.">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matière</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $evaluation->classSubject->subject->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Classe</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $evaluation->classSubject->schoolClass->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Période</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2 text-sm font-medium text-gray-900">
                        {{ $evaluation->period->name }}
                        <x-status-badge :status="$evaluation->period->status" />
                    </dd>
                </div>
            </dl>

            @if ($evaluation->period->isValidated())
                <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    Période validée : cette évaluation n'est plus modifiable.
                </div>
            @endif
        </x-card>

        <!-- Formulaire -->
        <form method="POST" action="{{ route('evaluations.update', $evaluation) }}">
            @csrf
            @method('PUT')

            <x-card title="Caractéristiques de l'évaluation" subtitle="Intitulé, type, date, barème et coefficient.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Intitulé de l'évaluation" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full"
                                      :value="old('name', $evaluation->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="type" value="Type d'évaluation" />
                        <x-select id="type" name="type" class="mt-1"
                                  :options="$typeLabels"
                                  :selected="old('type', $evaluation->type)"
                                  :placeholder="null"
                                  required />
                        <x-input-error :messages="$errors->get('type')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="evaluation_date" value="Date de l'évaluation" />
                        <x-text-input id="evaluation_date" name="evaluation_date" type="date" class="mt-1 block w-full"
                                      :value="old('evaluation_date', $evaluation->evaluation_date->toDateString())" required />
                        <x-input-error :messages="$errors->get('evaluation_date')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="max_score" value="Barème (note maximale)" />
                        <x-text-input id="max_score" name="max_score" type="number" step="0.5" min="1" max="100"
                                      class="mt-1 block w-full" :value="old('max_score', $evaluation->max_score)" required />
                        <p class="mt-1 text-xs text-gray-500">Entre 1 et 100. Les notes saisies ne pourront pas dépasser ce barème.</p>
                        <x-input-error :messages="$errors->get('max_score')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="coefficient" value="Coefficient" />
                        <x-text-input id="coefficient" name="coefficient" type="number" step="0.5" min="0.5" max="10"
                                      class="mt-1 block w-full" :value="old('coefficient', $evaluation->coefficient)" required />
                        <p class="mt-1 text-xs text-gray-500">Entre 0,5 et 10. Poids de l'évaluation dans la moyenne.</p>
                        <x-input-error :messages="$errors->get('coefficient')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('grades.entry', $evaluation) }}"
                       class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Annuler
                    </a>
                    <x-primary-button>Enregistrer</x-primary-button>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
