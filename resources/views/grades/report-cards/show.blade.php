<x-app-layout>
    <x-page-header
        :title="'Bulletin — '.$data['student']->full_name"
        :subtitle="$data['period']->name.' — '.$data['class']->name.' — version '.$reportCard->version"
        :back="route('report-cards.index')"
    >
        <x-slot name="actions">
            @if ($reportCard->file_path)
                <a href="{{ route('report-cards.download', $reportCard) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition ease-in-out duration-150 hover:bg-gray-50">
                    Télécharger le PDF
                </a>
            @endif

            @can('report_cards.validate')
                @if ($reportCard->status === \App\Models\ReportCard::STATUS_GENERATED)
                    <form method="POST" action="{{ route('report-cards.validate', $reportCard) }}">
                        @csrf
                        <x-primary-button>Valider</x-primary-button>
                    </form>
                @endif

                @if ($reportCard->status === \App\Models\ReportCard::STATUS_VALIDATED)
                    <form method="POST" action="{{ route('report-cards.lock', $reportCard) }}"
                          onsubmit="return confirm('Verrouiller ce bulletin ? Cette action est définitive : toute régénération créera une nouvelle version.')">
                        @csrf
                        <x-danger-button>Verrouiller</x-danger-button>
                    </form>
                @endif
            @endcan
        </x-slot>
    </x-page-header>

    @if ($reportCard->isImmutable())
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <p>Version archivée : toute régénération créera une nouvelle version.</p>
        </div>
    @endif

    @if (! $data['general_complete'])
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p>Résultats partiels — certaines notes ne sont pas encore saisies.</p>
        </div>
    @endif

    <!-- Aperçu du bulletin -->
    <x-card>
        <!-- En-tête de l'établissement -->
        <div class="flex flex-col gap-4 border-b-2 border-indigo-900 pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-lg font-bold text-indigo-900">{{ $profile->name }}</p>
                @if ($profile->motto)
                    <p class="text-sm text-gray-600">{{ $profile->motto }}</p>
                @endif
                <p class="mt-1 text-xs text-gray-500">
                    @if ($profile->address){{ $profile->address }}@endif
                    @if ($profile->city) — {{ $profile->city }}@endif
                    @if ($profile->phone) — Tél. : {{ $profile->phone }}@endif
                </p>
            </div>
            <div class="text-xs text-gray-500 sm:text-right">
                <p class="font-semibold text-gray-700">Année scolaire {{ $data['period']->academicYear->name }}</p>
                <p>Version {{ $reportCard->version }} — {{ $reportCard->status ? \App\Models\ReportCard::statusLabel($reportCard->status) : '—' }}</p>
                @if ($reportCard->generated_at)
                    <p>Généré le {{ $reportCard->generated_at->format('d/m/Y') }}</p>
                @endif
            </div>
        </div>

        <div class="mt-4 text-center">
            <h2 class="text-xl font-bold tracking-wide text-gray-900">BULLETIN DE NOTES</h2>
            <p class="mt-0.5 text-sm text-gray-600">
                {{ $data['period']->name }} — {{ $data['class']->name }}@if ($data['class']->gradeLevel) ({{ $data['class']->gradeLevel->name }})@endif
            </p>
        </div>

        <!-- Informations de l'élève -->
        <dl class="mt-4 grid grid-cols-1 gap-4 rounded-lg bg-gray-50 px-4 py-3 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Élève</dt>
                <dd class="mt-0.5 font-medium text-gray-900">{{ $data['student']->full_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matricule</dt>
                <dd class="mt-0.5 font-mono text-gray-700">{{ $data['student']->matricule }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Classe</dt>
                <dd class="mt-0.5 text-gray-700">{{ $data['class']->name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Effectif</dt>
                <dd class="mt-0.5 text-gray-700">{{ $data['class_size'] }} élève(s)</dd>
            </div>
        </dl>

        <!-- Matières -->
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2.5">Matière</th>
                        <th class="px-4 py-2.5 text-right">Coef.</th>
                        <th class="px-4 py-2.5 text-right">Moyenne / {{ $config->format($config->targetScale) }}</th>
                        <th class="px-4 py-2.5 text-right">Points</th>
                        <th class="px-4 py-2.5 text-right">Rang</th>
                        <th class="px-4 py-2.5">Appréciation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @php $totalPoints = 0; $totalCoef = 0; @endphp
                    @foreach ($data['rows'] as $row)
                        @php
                            $counted = $row['average'] !== null;
                            if ($counted) {
                                $totalPoints += $row['weighted'];
                                $totalCoef += $row['coefficient'];
                            }
                        @endphp
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-gray-900">
                                {{ $row['subject'] }}
                                @unless ($row['graded'])
                                    <span class="ml-1 text-xs font-normal text-gray-400">(non notée)</span>
                                @endunless
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ rtrim(rtrim(number_format($row['coefficient'], 2, ',', ' '), '0'), ',') }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-900">{{ $config->format($row['average']) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $config->format($row['weighted']) }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-600">
                                @if ($row['rank'])
                                    {{ $row['rank'] }}{{ $row['rank'] === 1 ? 'er' : 'e' }}@if ($row['ranked_count']) <span class="text-xs text-gray-400">/{{ $row['ranked_count'] }}</span>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $row['appreciation'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-indigo-50 font-semibold text-gray-900">
                        <td class="px-4 py-2.5">Totaux</td>
                        <td class="px-4 py-2.5 text-right">{{ $config->format($totalCoef) }}</td>
                        <td class="px-4 py-2.5 text-right">—</td>
                        <td class="px-4 py-2.5 text-right">{{ $config->format($totalPoints) }}</td>
                        <td class="px-4 py-2.5" colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Récapitulatif -->
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-indigo-50 px-4 py-4 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Moyenne générale</p>
                <p class="mt-1 text-3xl font-bold text-indigo-900">{{ $config->format($data['general_average']) }}</p>
                <p class="text-xs text-indigo-700">sur {{ $config->format($config->targetScale) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-4 py-4 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rang</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">
                    @if ($data['rank'])
                        {{ $data['rank'] }}{{ $data['rank'] === 1 ? 'er' : 'e' }}
                    @else
                        —
                    @endif
                </p>
                <p class="text-xs text-gray-500">sur {{ $data['ranked_count'] }} élève(s) classé(s)</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-4 py-4 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Moyenne de la classe</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $config->format($data['class_average']) }}</p>
                <p class="text-xs text-gray-500">
                    Plus forte : {{ $config->format($data['class_max']) }} — plus faible : {{ $config->format($data['class_min']) }}
                </p>
            </div>
            <div class="rounded-lg bg-gray-50 px-4 py-4 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Taux de réussite</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">
                    {{ $data['class_success_rate'] !== null ? number_format($data['class_success_rate'], 1, ',', ' ').' %' : '—' }}
                </p>
                <p class="text-xs text-gray-500">Seuil : {{ $config->format($config->passThreshold) }} / {{ $config->format($config->targetScale) }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3 text-sm">
            <div class="flex items-center gap-3">
                <span class="text-gray-500">Décision :</span>
                @if ($data['decision'])
                    <x-status-badge :status="$data['decision']" />
                @else
                    <span class="text-gray-500">En attente</span>
                @endif
            </div>
            <div class="text-gray-600">
                Appréciation générale : <span class="font-medium text-gray-900">{{ $data['appreciation'] }}</span>
            </div>
        </div>
    </x-card>
</x-app-layout>
