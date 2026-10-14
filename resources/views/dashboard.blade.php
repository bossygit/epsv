<x-app-layout>
    <x-page-header
        title="Tableau de bord"
        :subtitle="$year ? 'Année scolaire '.$year->name.($period ? ' — '.$period->name : '') : 'Aucune année scolaire active'"
    >
        <x-slot name="actions">
            @if ($period && $period->status === \App\Models\SchoolPeriod::STATUS_OPEN && auth()->user()->can('grades.submit'))
                <form method="POST" action="{{ route('periods.submit', $period) }}">
                    @csrf
                    <x-primary-button onclick="return confirm('Soumettre les résultats de {{ $period->name }} à la direction ?')">
                        Soumettre {{ $period->name }}
                    </x-primary-button>
                </form>
            @endif
        </x-slot>
    </x-page-header>

    <!-- Indicateurs -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            label="Élèves inscrits"
            :value="$totalStudents"
            :hint="$girls.' fille(s) — '.($totalStudents - $girls).' garçon(s)'"
            tone="indigo"
            icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
        />
        <x-stat-card
            label="Taux de présence"
            :value="$attendanceRate !== null ? $attendanceRate.' %' : '—'"
            :hint="'Sur '.$windowDays.' derniers jours ('.$attendanceTotal.' pointages)'"
            tone="emerald"
            icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"
        />
        <x-stat-card
            label="Absences non justifiées"
            :value="$unjustifiedAbsences"
            hint="À traiter par la surveillance"
            tone="amber"
            icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
        />
        <x-stat-card
            label="Notes manquantes"
            :value="$lateEvaluations->sum('missing_count')"
            hint="Évaluations publiées non complétées"
            tone="rose"
            icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Résultats par classe -->
        <x-card title="Résultats par classe" :subtitle="$period ? $period->name : 'Aucune période en cours'" class="lg:col-span-2" :padding="false">
            @if (count($classResults))
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Classe</th>
                            <th class="px-4 py-2.5 text-right">Moyenne</th>
                            <th class="px-4 py-2.5 text-right">Élèves évalués</th>
                            <th class="px-4 py-2.5 text-right">Taux de réussite</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($classResults as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('classes.show', $row['class']) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ $row['class']->name }}
                                    </a>
                                    <span class="ml-1 text-xs text-gray-400">{{ $row['class']->gradeLevel?->name }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-900">
                                    {{ $row['average'] !== null ? number_format($row['average'], 2, ',', ' ') : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600">{{ $row['ranked'] }}/{{ $row['total'] }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($row['success_rate'] !== null)
                                        <span class="{{ $row['success_rate'] >= 50 ? 'text-emerald-600' : 'text-amber-600' }} font-medium">
                                            {{ number_format($row['success_rate'], 1, ',', ' ') }} %
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-6 py-4">
                    <x-empty-state message="Aucune donnée de résultats pour le moment." />
                </div>
            @endif
        </x-card>

        <!-- Alertes -->
        <x-card title="Alertes" subtitle="Points à traiter en priorité" :padding="false">
            <div class="divide-y divide-gray-100">
                @if ($periodsToValidate->isNotEmpty())
                    @foreach ($periodsToValidate as $toValidate)
                        <div class="flex items-start gap-3 px-4 py-3">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-gray-900">Période à valider</p>
                                <p class="text-gray-500">{{ $toValidate->label() }}</p>
                                @can('grades.validate')
                                    <form method="POST" action="{{ route('periods.validate', $toValidate) }}" class="mt-1">
                                        @csrf
                                        <button class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Valider maintenant →</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                @endif

                @foreach ($lateEvaluations as $evaluation)
                    <div class="flex items-start gap-3 px-4 py-3">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-rose-500"></span>
                        <div class="min-w-0 text-sm">
                            <p class="font-medium text-gray-900">Notes en retard</p>
                            <p class="text-gray-500">
                                {{ $evaluation->classSubject->subject->name }} — {{ $evaluation->classSubject->schoolClass->name }} :
                                {{ $evaluation->missing_count }} note(s) non saisie(s)
                            </p>
                        </div>
                    </div>
                @endforeach

                @foreach ($repeatAbsentees as $absentee)
                    <div class="flex items-start gap-3 px-4 py-3">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                        <div class="min-w-0 text-sm">
                            <p class="font-medium text-gray-900">Absences répétées</p>
                            <p class="text-gray-500">
                                <a href="{{ route('students.show', $absentee->student) }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $absentee->student->full_name }}
                                </a>
                                — {{ $absentee->absence_count }} absence(s) sur {{ $windowDays }} jours
                            </p>
                        </div>
                    </div>
                @endforeach

                @if ($periodsToValidate->isEmpty() && $lateEvaluations->isEmpty() && $repeatAbsentees->isEmpty())
                    <div class="px-4 py-2">
                        <x-empty-state message="Aucune alerte. Tout est à jour." />
                    </div>
                @endif
            </div>
        </x-card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Effectifs par niveau -->
        <x-card title="Effectifs par niveau">
            @if ($byLevel->isNotEmpty())
                <ul class="space-y-2">
                    @foreach ($byLevel as $level => $total)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">{{ $level }}</span>
                            <span class="font-semibold text-gray-900">{{ $total }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state message="Aucun élève inscrit." />
            @endif
        </x-card>

        <!-- Derniers bulletins -->
        <x-card title="Derniers bulletins générés" class="lg:col-span-2" :padding="false">
            @if ($recentReportCards->isNotEmpty())
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5">Élève</th>
                            <th class="px-4 py-2.5">Période</th>
                            <th class="px-4 py-2.5 text-right">Moyenne</th>
                            <th class="px-4 py-2.5 text-right">Rang</th>
                            <th class="px-4 py-2.5 text-right">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($recentReportCards as $card)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('report-cards.show', $card) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ $card->student->full_name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $card->period->name }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold">
                                    {{ $card->average !== null ? number_format($card->average, 2, ',', ' ') : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right">{{ $card->rank ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right"><x-status-badge :status="$card->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="px-6 py-4">
                    <x-empty-state message="Aucun bulletin généré pour l'instant." />
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
