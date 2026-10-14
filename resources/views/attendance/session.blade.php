@php
    $quickStatuses = [
        \App\Models\AttendanceRecord::STATUS_PRESENT => 'Présent',
        \App\Models\AttendanceRecord::STATUS_ABSENT => 'Absent',
        \App\Models\AttendanceRecord::STATUS_LATE => 'Retard',
        \App\Models\AttendanceRecord::STATUS_EXCUSED => 'Justifié',
    ];

    $statusOptions = collect(\App\Models\AttendanceRecord::STATUSES)
        ->mapWithKeys(fn ($status) => [$status => \App\Models\AttendanceRecord::statusLabel($status)])
        ->all();

    // Classes Tailwind du bouton de statut actif (grille d'appel).
    $statusActiveClasses = [
        \App\Models\AttendanceRecord::STATUS_PRESENT => 'border-emerald-500 bg-emerald-50 text-emerald-700',
        \App\Models\AttendanceRecord::STATUS_ABSENT => 'border-rose-500 bg-rose-50 text-rose-700',
        \App\Models\AttendanceRecord::STATUS_LATE => 'border-amber-500 bg-amber-50 text-amber-700',
        \App\Models\AttendanceRecord::STATUS_EXCUSED => 'border-blue-500 bg-blue-50 text-blue-700',
    ];

    $presentCount = $records->where('status', \App\Models\AttendanceRecord::STATUS_PRESENT)->count();
    $absentCount = $records->where('status', \App\Models\AttendanceRecord::STATUS_ABSENT)->count();
    $lateCount = $records->where('status', \App\Models\AttendanceRecord::STATUS_LATE)->count();
    $excusedCount = $records->where('status', \App\Models\AttendanceRecord::STATUS_EXCUSED)->count();

    $initialRows = [];
    foreach ($students as $enrollment) {
        $existing = $records->get($enrollment->student_id);
        $initialRows[$enrollment->student_id] = [
            'status' => $existing?->status ?? \App\Models\AttendanceRecord::STATUS_PRESENT,
            'minutes_late' => $existing?->minutes_late,
            'justification' => $existing?->justification,
        ];
    }
@endphp

<x-app-layout>
    <x-page-header
        title="Appel — {{ $session->schoolClass->name }}"
        :subtitle="'Le '.$session->session_date->format('d/m/Y').' — '.\App\Models\AttendanceSession::slotLabel($session->slot).' — '.$students->count().' élève(s)'.($session->takenBy ? ' — appel pris par '.$session->takenBy->name : '').($canEdit ? '' : ' — lecture seule')"
        :back="route('attendance.index')"
    >
        <x-slot name="actions">
            <a href="{{ route('attendance.report', $session->schoolClass) }}"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Rapport de la classe
            </a>
        </x-slot>
    </x-page-header>

    <div class="space-y-6">
        <!-- Résumé de la séance -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Présents" :value="$presentCount" :hint="'Sur '.$records->count().' pointage(s)'" tone="emerald"
                icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <x-stat-card label="Absents" :value="$absentCount" hint="Absences non justifiées" tone="rose"
                icon="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <x-stat-card label="Retards" :value="$lateCount" hint="Arrivées tardives" tone="amber"
                icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            <x-stat-card label="Justifiés" :value="$excusedCount" hint="Absences couvertes par un motif" tone="indigo"
                icon="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
        </div>

        @if ($students->isEmpty())
            <x-card>
                <x-empty-state message="Aucun élève actif dans cette classe." />
            </x-card>
        @elseif ($canEdit)
            <script>
                function call() {
                    return {
                        initial: @json($initialRows),
                        rows: @json($initialRows),

                        setAll(status) {
                            Object.keys(this.rows).forEach((id) => {
                                this.rows[id].status = status;
                            });
                        },

                        reset() {
                            this.rows = JSON.parse(JSON.stringify(this.initial));
                        },
                    };
                }
            </script>

            <form method="POST" action="{{ route('attendance.store', $session) }}" x-data="call()">
                @csrf

                <x-card :padding="false">
                    <x-slot name="title">Grille d'appel</x-slot>
                    <x-slot name="subtitle">Touchez un statut par élève, puis enregistrez l'appel.</x-slot>

                    <x-slot name="actions">
                        <x-secondary-button type="button" x-on:click="setAll('present')">Tous présents</x-secondary-button>
                        <x-secondary-button type="button" x-on:click="reset()">Réinitialiser</x-secondary-button>
                    </x-slot>

                    <div class="divide-y divide-gray-100">
                        @foreach ($students as $enrollment)
                            @php
                                $student = $enrollment->student;
                                $record = $records->get($enrollment->student_id);
                                $rowIndex = $loop->index;
                            @endphp

                            <div class="flex flex-col gap-3 px-4 py-3 hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="w-5 shrink-0 text-right text-xs text-gray-400">{{ $loop->iteration }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('students.show', $student) }}"
                                           class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $student->full_name }}
                                        </a>
                                        <p class="text-xs text-gray-400">{{ $student->matricule }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="records[{{ $rowIndex }}][student_id]" value="{{ $student->id }}">

                                    @foreach ($quickStatuses as $value => $label)
                                        <label class="cursor-pointer select-none rounded-md border px-3 py-1.5 text-xs font-semibold transition"
                                               x-bind:class="rows[{{ $student->id }}].status === '{{ $value }}'
                                                   ? '{{ $statusActiveClasses[$value] }}'
                                                   : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'">
                                            <input type="radio"
                                                   class="sr-only"
                                                   name="records[{{ $rowIndex }}][status]"
                                                   value="{{ $value }}"
                                                   x-model="rows[{{ $student->id }}].status"
                                                   x-on:change="if (rows[{{ $student->id }}].status !== 'late') { rows[{{ $student->id }}].minutes_late = null; }"
                                                   @checked(($record?->status ?? \App\Models\AttendanceRecord::STATUS_PRESENT) === $value)>
                                            {{ $label }}
                                        </label>
                                    @endforeach

                                    <div class="flex items-center gap-1" x-show="rows[{{ $student->id }}].status === 'late'" style="display: none;">
                                        <input type="number"
                                               name="records[{{ $rowIndex }}][minutes_late]"
                                               min="1" max="600"
                                               x-model="rows[{{ $student->id }}].minutes_late"
                                               class="w-20 rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               placeholder="Min.">
                                        <span class="text-xs text-gray-500">min</span>
                                    </div>

                                    <div class="min-w-[10rem] flex-1" x-show="['absent', 'excused'].includes(rows[{{ $student->id }}].status)" style="display: none;">
                                        <input type="text"
                                               name="records[{{ $rowIndex }}][justification]"
                                               maxlength="500"
                                               x-model="rows[{{ $student->id }}].justification"
                                               class="block w-full rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               placeholder="Motif (facultatif)">
                                    </div>

                                    @if ($record)
                                        <div class="flex items-center gap-2">
                                            @can('attendance.correct')
                                                <button type="button"
                                                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                        x-on:click.prevent="$dispatch('open-modal', 'correct-{{ $record->id }}')">
                                                    Corriger
                                                </button>
                                            @endcan
                                            @can('attendance.justify')
                                                <button type="button"
                                                        class="text-xs font-semibold text-gray-500 hover:text-gray-700"
                                                        x-on:click.prevent="$dispatch('open-modal', 'justify-{{ $record->id }}')">
                                                    Justifier
                                                </button>
                                            @endcan
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <p class="text-xs text-gray-500">
                            Les élèves non renseignés seront enregistrés comme présents.
                        </p>
                        <x-primary-button>Enregistrer l'appel</x-primary-button>
                    </div>
                </x-card>
            </form>
        @else
            <x-card :padding="false" x-data="">
                <x-slot name="title">Feuille d'appel</x-slot>
                <x-slot name="subtitle">Consultation seule — vous n'avez pas le droit de saisir l'appel pour cette séance.</x-slot>

                <div class="divide-y divide-gray-100">
                    @foreach ($students as $enrollment)
                        @php
                            $student = $enrollment->student;
                            $record = $records->get($enrollment->student_id);
                        @endphp

                        <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="w-5 shrink-0 text-right text-xs text-gray-400">{{ $loop->iteration }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('students.show', $student) }}"
                                       class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ $student->full_name }}
                                    </a>
                                    <p class="text-xs text-gray-400">{{ $student->matricule }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                @if ($record)
                                    <x-status-badge :status="$record->status" />
                                    @if ($record->minutes_late)
                                        <span class="text-xs text-gray-500">{{ $record->minutes_late }} min</span>
                                    @endif
                                    @if ($record->justification)
                                        <span class="text-xs italic text-gray-500">{{ $record->justification }}</span>
                                    @endif

                                    @can('attendance.correct')
                                        <button type="button"
                                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                                x-on:click.prevent="$dispatch('open-modal', 'correct-{{ $record->id }}')">
                                            Corriger
                                        </button>
                                    @endcan
                                    @can('attendance.justify')
                                        <button type="button"
                                                class="text-xs font-semibold text-gray-500 hover:text-gray-700"
                                                x-on:click.prevent="$dispatch('open-modal', 'justify-{{ $record->id }}')">
                                            Justifier
                                        </button>
                                    @endcan
                                @else
                                    <span class="text-xs text-gray-400">Non renseigné</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>

    <!-- Modales de correction / justification -->
    @foreach ($students as $enrollment)
        @php
            $student = $enrollment->student;
            $record = $records->get($enrollment->student_id);
        @endphp

        @if ($record)
            @can('attendance.correct')
                <x-modal :name="'correct-'.$record->id" focusable>
                    <form method="POST" action="{{ route('attendance.records.correct', $record) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-semibold text-gray-900">Corriger la présence</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $student->full_name }} — {{ $session->session_date->format('d/m/Y') }}
                            ({{ \App\Models\AttendanceSession::slotLabel($session->slot) }})
                        </p>

                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label :for="'correct-status-'.$record->id" value="Statut" />
                                <x-select
                                    :id="'correct-status-'.$record->id"
                                    name="status"
                                    class="mt-1"
                                    :options="$statusOptions"
                                    :selected="$record->status"
                                    :placeholder="null"
                                    required
                                />
                            </div>
                            <div>
                                <x-input-label :for="'correct-minutes-'.$record->id" value="Minutes de retard" />
                                <x-text-input
                                    :id="'correct-minutes-'.$record->id"
                                    name="minutes_late"
                                    type="number"
                                    min="1"
                                    max="600"
                                    class="mt-1 block w-full"
                                    :value="$record->minutes_late"
                                />
                            </div>
                            <div>
                                <x-input-label :for="'correct-justification-'.$record->id" value="Justification" />
                                <textarea id="correct-justification-{{ $record->id }}" name="justification" rows="2" maxlength="500"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ $record->justification }}</textarea>
                            </div>
                            <div>
                                <x-input-label :for="'correct-reason-'.$record->id" value="Motif de la correction (obligatoire)" />
                                <textarea id="correct-reason-{{ $record->id }}" name="reason" rows="3" required minlength="3" maxlength="1000"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                          placeholder="Ex. erreur de saisie, pièce justificative présentée…"></textarea>
                                <p class="mt-1 text-xs text-gray-500">La correction est conservée dans le journal d'audit.</p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <x-primary-button>Enregistrer la correction</x-primary-button>
                        </div>
                    </form>
                </x-modal>
            @endcan

            @can('attendance.justify')
                <x-modal :name="'justify-'.$record->id" focusable>
                    <form method="POST" action="{{ route('attendance.records.justify', $record) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-semibold text-gray-900">Justifier une absence ou un retard</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $student->full_name }} — statut actuel :
                            {{ \App\Models\AttendanceRecord::statusLabel($record->status) }}
                            ({{ $session->session_date->format('d/m/Y') }})
                        </p>

                        <div class="mt-4">
                            <x-input-label :for="'justify-text-'.$record->id" value="Justification (obligatoire)" />
                            <textarea id="justify-text-{{ $record->id }}" name="justification" rows="3" required minlength="3" maxlength="500"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                      placeholder="Ex. certificat médical remis au secrétariat">{{ $record->justification }}</textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                            <x-primary-button>Enregistrer la justification</x-primary-button>
                        </div>
                    </form>
                </x-modal>
            @endcan
        @endif
    @endforeach
</x-app-layout>
