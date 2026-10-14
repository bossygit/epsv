@php
    $initialStatuses = $students->map(
        fn ($enrollment, $index) => old(
            "records.{$index}.status",
            $grades->get($enrollment->student_id)?->status ?? \App\Models\Grade::STATUS_MISSING
        )
    )->values()->all();

    $periodValidated = $evaluation->period->isValidated();
@endphp

<x-app-layout>
    <x-page-header
        title="Saisie des notes"
        :subtitle="$evaluation->name.' — '.$evaluation->classSubject->subject->name.' ('.$evaluation->classSubject->schoolClass->name.')'"
        :back="route('evaluations.index')"
    >
        <x-slot name="actions">
            <x-status-badge :status="$evaluation->status" />
            @can('grades.enter')
                <a href="{{ route('evaluations.edit', $evaluation) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Modifier l'évaluation
                </a>
            @endcan
        </x-slot>
    </x-page-header>

    <!-- Fiche de l'évaluation -->
    <x-card class="mb-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matière</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ $evaluation->classSubject->subject->name }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Classe</p>
                <p class="mt-1 text-sm font-medium text-gray-900">
                    {{ $evaluation->classSubject->schoolClass->name }}
                    <span class="block text-xs font-normal text-gray-500">
                        {{ $evaluation->classSubject->schoolClass->gradeLevel?->name }}
                    </span>
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Période</p>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm font-medium text-gray-900">
                    {{ $evaluation->period->name }}
                    <x-status-badge :status="$evaluation->period->status" />
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Barème</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ number_format($evaluation->max_score, 2, ',', ' ') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Coefficient</p>
                <p class="mt-1 text-sm font-medium text-gray-900">{{ number_format($evaluation->coefficient, 2, ',', ' ') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Statut</p>
                <p class="mt-1"><x-status-badge :status="$evaluation->status" /></p>
            </div>
        </div>
    </x-card>

    @if ($evaluation->isCancelled())
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Cette évaluation est annulée : elle est exclue des calculs et n'accepte plus de saisie.
        </div>
    @endif

    @if (! $canEdit)
        <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            Lecture seule : la période est validée et vous n'avez pas la permission de corriger une note.
        </div>
    @endif

    <div x-data="{
            onlyMissing: false,
            statuses: @js($initialStatuses),
            rowClass(status) {
                if (status === 'absent') return 'bg-rose-50';
                if (status === 'exempt') return 'bg-gray-100 text-gray-500';
                if (status === 'missing') return 'bg-amber-50';
                return '';
            },
        }">
        <!-- Barre d'outils -->
        <x-card class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
                    <span>{{ $students->count() }} élève(s) inscrit(s)</span>
                    <span>
                        Notes manquantes :
                        <span class="font-semibold {{ $missingCount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $missingCount }}</span>
                    </span>
                </div>
                <button type="button" x-on:click="onlyMissing = ! onlyMissing"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <span x-show="! onlyMissing">Afficher uniquement les non saisies</span>
                    <span x-show="onlyMissing" x-cloak>Afficher tous les élèves</span>
                </button>
            </div>
        </x-card>

        @if ($students->isEmpty())
            <x-card>
                <x-empty-state message="Aucun élève inscrit dans cette classe : il n'y a rien à saisir." />
            </x-card>
        @else
            @if ($canEdit)
                <form method="POST" action="{{ route('grades.entry.store', $evaluation) }}">
                    @csrf
            @endif

            <x-card :padding="false">
                <x-slot name="title">Grille de saisie</x-slot>
                <x-slot name="subtitle">
                    Statuts possibles : saisie, absent(e), dispensé(e) ou non saisie. Une note absente ou dispensée est exclue de la moyenne.
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2.5">Élève</th>
                                <th class="px-4 py-2.5">Note / {{ number_format($evaluation->max_score, 2, ',', ' ') }}</th>
                                <th class="px-4 py-2.5">Statut</th>
                                <th class="px-4 py-2.5">Commentaire</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($students as $enrollment)
                                @php
                                    $student = $enrollment->student;
                                    $existing = $grades->get($student->id);
                                    $index = $loop->index;
                                    $rowStatus = old("records.{$index}.status", $existing?->status ?? \App\Models\Grade::STATUS_MISSING);
                                    $rowValue = old(
                                        "records.{$index}.value",
                                        $existing?->status === \App\Models\Grade::STATUS_ENTERED ? $existing?->value : ''
                                    );
                                @endphp
                                <tr :class="rowClass(statuses[{{ $index }}])"
                                    x-show="! onlyMissing || statuses[{{ $index }}] !== 'entered'"
                                    class="transition-colors">
                                    <td class="px-4 py-2.5">
                                        @if ($canEdit)
                                            <input type="hidden" name="records[{{ $index }}][student_id]" value="{{ $student->id }}">
                                        @endif
                                        <p class="font-medium text-gray-900">{{ $student->full_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $student->matricule }}</p>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($canEdit)
                                            <input type="number" name="records[{{ $index }}][value]"
                                                   step="0.25" min="0" max="{{ $evaluation->max_score }}"
                                                   value="{{ $rowValue }}" placeholder="—"
                                                   class="block w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                        @else
                                            <span class="font-medium text-gray-900">
                                                {{ $existing?->value !== null ? number_format($existing->value, 2, ',', ' ') : '—' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($canEdit)
                                            <select name="records[{{ $index }}][status]" x-model="statuses[{{ $index }}]"
                                                    class="block w-full min-w-[9rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="entered" @selected($rowStatus === \App\Models\Grade::STATUS_ENTERED)>Saisie</option>
                                                <option value="absent" @selected($rowStatus === \App\Models\Grade::STATUS_ABSENT)>Absent(e)</option>
                                                <option value="exempt" @selected($rowStatus === \App\Models\Grade::STATUS_EXEMPT)>Dispensé(e)</option>
                                                <option value="missing" @selected($rowStatus === \App\Models\Grade::STATUS_MISSING)>Non saisie</option>
                                            </select>
                                        @else
                                            <x-status-badge :status="$existing?->status ?? \App\Models\Grade::STATUS_MISSING" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($canEdit)
                                            <input type="text" name="records[{{ $index }}][comment]" maxlength="255"
                                                   value="{{ old("records.{$index}.comment", $existing?->comment) }}"
                                                   placeholder="Optionnel"
                                                   class="block w-full min-w-[12rem] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                        @else
                                            <span class="text-gray-600">{{ $existing?->comment ?: '—' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($canEdit)
                    @if ($periodValidated)
                        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <p class="font-semibold">
                                Période validée : toute modification exige la permission de correction et un motif.
                            </p>
                            <div class="mt-3">
                                <x-input-label for="reason" value="Motif de la correction (obligatoire)" class="text-rose-900" />
                                <textarea id="reason" name="reason" rows="3" required
                                          placeholder="Ex. Correction d'une erreur de report."
                                          class="mt-1 block w-full rounded-md border-rose-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm">{{ old('reason') }}</textarea>
                                <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <a href="{{ route('evaluations.index') }}"
                           class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Retour aux évaluations
                        </a>
                        <x-primary-button>Enregistrer les notes</x-primary-button>
                    </div>
                </form>
            @else
                <div class="mt-6 flex justify-end">
                    <a href="{{ route('evaluations.index') }}"
                       class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Retour aux évaluations
                    </a>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
