<x-app-layout>
    @php
        $alertCount = $periodsToValidate->count() + $lateEvaluations->count() + $repeatAbsentees->count();
        $maxLevel = $byLevel->max() ?: 1;
    @endphp

    <x-page-header
        title="Tableau de bord"
        :subtitle="$year ? 'Année scolaire '.$year->name.($period ? ' · '.$period->name : '') : 'Aucune année scolaire active'"
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

    <!-- Bandeau d'indicateurs -->
    <div class="grid grid-cols-2 gap-px border border-line bg-line sm:grid-cols-4">
        <x-stat-card
            variant="strip"
            label="Élèves inscrits"
            :value="$totalStudents"
            :hint="$girls.' fille(s) · '.($totalStudents - $girls).' garçon(s)'"
            tone="indigo"
        />
        <x-stat-card
            variant="strip"
            label="Présence · {{ $windowDays }} j"
            :value="$attendanceRate !== null ? number_format($attendanceRate, 1, ',', ' ').' %' : '—'"
            :hint="$attendanceTotal.' pointages'"
            tone="emerald"
        />
        <x-stat-card
            variant="strip"
            label="Absences injustifiées"
            :value="$unjustifiedAbsences"
            hint="À traiter par la surveillance"
            tone="amber"
        />
        <x-stat-card
            variant="strip"
            label="Notes manquantes"
            :value="$lateEvaluations->sum('missing_count')"
            hint="Évaluations publiées non complétées"
            tone="rose"
        />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_300px]">
        <!-- Résultats par classe -->
        <x-card title="Résultats par classe" :subtitle="$period ? $period->name.' · moyennes pondérées' : 'Aucune période en cours'" :padding="false" class="self-start">
            @if (count($classResults))
                <table class="min-w-full text-[12px]">
                    <thead>
                        <tr class="bg-ivory text-left">
                            <th class="border-b border-line px-4 py-2 text-[9px] font-semibold uppercase tracking-[0.11em] text-muted sm:px-5">Classe</th>
                            <th class="border-b border-line px-3 py-2 text-right text-[9px] font-semibold uppercase tracking-[0.11em] text-muted">Moyenne</th>
                            <th class="border-b border-line px-3 py-2 text-right text-[9px] font-semibold uppercase tracking-[0.11em] text-muted">Évalués</th>
                            <th class="border-b border-line px-4 py-2 text-right text-[9px] font-semibold uppercase tracking-[0.11em] text-muted sm:px-5">Réussite</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classResults as $row)
                            <tr class="border-b border-line-soft last:border-b-0 hover:bg-ivory/60">
                                <td class="px-4 py-2.5 sm:px-5">
                                    <a href="{{ route('classes.show', $row['class']) }}" class="font-serif text-[14px] font-medium text-ink hover:text-gold">
                                        {{ $row['class']->name }}
                                    </a>
                                    <span class="ml-1.5 text-[10px] text-muted">{{ $row['class']->gradeLevel?->name }}</span>
                                </td>
                                <td class="num px-3 py-2.5 text-right font-semibold text-ink">
                                    {{ $row['average'] !== null ? number_format($row['average'], 2, ',', ' ') : '—' }}
                                </td>
                                <td class="num px-3 py-2.5 text-right text-muted">{{ $row['ranked'] }}/{{ $row['total'] }}</td>
                                <td class="px-4 py-2.5 text-right sm:px-5">
                                    @if ($row['success_rate'] !== null)
                                        <span class="inline-flex items-center justify-end gap-2 {{ $row['success_rate'] >= 50 ? 'text-ink' : 'text-amber-700' }}">
                                            <span class="hidden h-[3px] w-14 bg-line-soft sm:inline-block">
                                                <span class="block h-[3px] {{ $row['success_rate'] >= 50 ? 'bg-emerald-700' : 'bg-amber-600' }}" style="width: {{ min(100, (float) $row['success_rate']) }}%"></span>
                                            </span>
                                            <span class="num text-[11.5px]">{{ number_format($row['success_rate'], 1, ',', ' ') }} %</span>
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
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

        <div class="space-y-4">
            <!-- À traiter -->
            <x-card title="À traiter" :padding="false">
                <x-slot name="actions">
                    <span class="rounded-sm border border-line bg-ivory px-1.5 py-0.5 text-[9.5px] font-semibold text-ink">{{ $alertCount }}</span>
                </x-slot>

                @if ($alertCount)
                    <div>
                        @foreach ($periodsToValidate as $toValidate)
                            <div class="flex items-start gap-2.5 border-b border-line-soft px-4 py-2.5 last:border-b-0 hover:bg-ivory/60">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600"></span>
                                <div class="min-w-0">
                                    <p class="text-[12px] font-medium leading-snug text-ink">Période à valider</p>
                                    <p class="text-[10.5px] text-muted">{{ $toValidate->label() }}</p>
                                    @can('grades.validate')
                                        <form method="POST" action="{{ route('periods.validate', $toValidate) }}" class="mt-1">
                                            @csrf
                                            <button class="text-[10.5px] font-semibold text-navy underline decoration-gold decoration-1 underline-offset-2 hover:text-gold">
                                                Valider maintenant →
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach

                        @foreach ($lateEvaluations as $evaluation)
                            <div class="flex items-start gap-2.5 border-b border-line-soft px-4 py-2.5 last:border-b-0 hover:bg-ivory/60">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-600"></span>
                                <div class="min-w-0">
                                    <p class="text-[12px] font-medium leading-snug text-ink">
                                        {{ $evaluation->classSubject->subject->name }} — {{ $evaluation->classSubject->schoolClass->name }}
                                    </p>
                                    <p class="text-[10.5px] text-muted">{{ $evaluation->missing_count }} note(s) non saisie(s)</p>
                                </div>
                            </div>
                        @endforeach

                        @foreach ($repeatAbsentees as $absentee)
                            <div class="flex items-start gap-2.5 border-b border-line-soft px-4 py-2.5 last:border-b-0 hover:bg-ivory/60">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600"></span>
                                <div class="min-w-0">
                                    <a href="{{ route('students.show', $absentee->student) }}" class="text-[12px] font-medium leading-snug text-ink hover:text-gold">
                                        {{ $absentee->student->full_name }}
                                    </a>
                                    <p class="text-[10.5px] text-muted">{{ $absentee->absence_count }} absence(s) sur {{ $windowDays }} jours</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-4 py-2">
                        <x-empty-state message="Aucune alerte. Tout est à jour." />
                    </div>
                @endif
            </x-card>

            <!-- Raccourcis -->
            <x-card title="Raccourcis">
                <div class="flex flex-col gap-1.5">
                    <a href="{{ route('attendance.index') }}" class="flex items-center justify-between rounded-sm border border-line px-2.5 py-1.5 text-[11.5px] text-ink hover:border-navy hover:bg-ivory">
                        Faire l'appel <span class="text-[10px] text-muted">Présences</span>
                    </a>
                    <a href="{{ route('evaluations.index') }}" class="flex items-center justify-between rounded-sm border border-line px-2.5 py-1.5 text-[11.5px] text-ink hover:border-navy hover:bg-ivory">
                        Saisir des notes <span class="text-[10px] text-muted">Évaluations</span>
                    </a>
                    <a href="{{ route('students.create') }}" class="flex items-center justify-between rounded-sm border border-line px-2.5 py-1.5 text-[11.5px] text-ink hover:border-navy hover:bg-ivory">
                        Inscrire un élève <span class="text-[10px] text-muted">Effectifs</span>
                    </a>
                </div>
            </x-card>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <!-- Effectifs par niveau -->
        <x-card title="Effectifs par niveau" :subtitle="$totalStudents.' inscrits'">
            @if ($byLevel->isNotEmpty())
                <ul class="space-y-2.5">
                    @foreach ($byLevel as $level => $total)
                        <li class="flex items-center gap-3 text-[12px]">
                            <span class="w-24 shrink-0 text-muted">{{ $level }}</span>
                            <span class="h-[7px] flex-1 bg-ivory">
                                <span class="block h-[7px] bg-navy" style="width: {{ round($total / $maxLevel * 100) }}%"></span>
                            </span>
                            <span class="num w-7 shrink-0 text-right font-semibold text-ink">{{ $total }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state message="Aucun élève inscrit." />
            @endif
        </x-card>

        <!-- Derniers bulletins -->
        <x-card title="Derniers bulletins" subtitle="Générés cette semaine" :padding="false">
            @if ($recentReportCards->isNotEmpty())
                <table class="min-w-full text-[12px]">
                    <thead>
                        <tr class="bg-ivory text-left">
                            <th class="border-b border-line px-4 py-2 text-[9px] font-semibold uppercase tracking-[0.11em] text-muted sm:px-5">Élève</th>
                            <th class="border-b border-line px-3 py-2 text-[9px] font-semibold uppercase tracking-[0.11em] text-muted">Période</th>
                            <th class="border-b border-line px-3 py-2 text-right text-[9px] font-semibold uppercase tracking-[0.11em] text-muted">Moy.</th>
                            <th class="border-b border-line px-3 py-2 text-right text-[9px] font-semibold uppercase tracking-[0.11em] text-muted">Rang</th>
                            <th class="border-b border-line px-4 py-2 text-right text-[9px] font-semibold uppercase tracking-[0.11em] text-muted sm:px-5">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentReportCards as $card)
                            <tr class="border-b border-line-soft last:border-b-0 hover:bg-ivory/60">
                                <td class="px-4 py-2.5 sm:px-5">
                                    <a href="{{ route('report-cards.show', $card) }}" class="font-medium text-ink hover:text-gold">{{ $card->student->full_name }}</a>
                                </td>
                                <td class="px-3 py-2.5 text-muted">{{ $card->period->name }}</td>
                                <td class="num px-3 py-2.5 text-right font-semibold text-ink">
                                    {{ $card->average !== null ? number_format($card->average, 2, ',', ' ') : '—' }}
                                </td>
                                <td class="num px-3 py-2.5 text-right text-muted">{{ $card->rank ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right sm:px-5"><x-status-badge :status="$card->status" /></td>
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
