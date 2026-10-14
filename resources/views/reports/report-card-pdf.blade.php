<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin — {{ $data['student']->full_name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1f2937; margin: 0; padding: 0; }
        .header { border-bottom: 3px solid #312e81; padding-bottom: 8px; margin-bottom: 10px; }
        .school-name { font-size: 16px; font-weight: bold; color: #312e81; }
        .school-info { font-size: 9px; color: #4b5563; }
        .title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin: 10px 0 2px 0; color: #111827; }
        .subtitle { text-align: center; font-size: 10px; color: #4b5563; margin-bottom: 10px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { padding: 3px 6px; border: 1px solid #d1d5db; font-size: 10px; }
        .info-label { background-color: #f3f4f6; font-weight: bold; width: 110px; color: #374151; }
        .grades { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .grades th { background-color: #312e81; color: #ffffff; font-size: 9px; padding: 4px 4px; border: 1px solid #312e81; text-align: center; }
        .grades td { border: 1px solid #d1d5db; padding: 3px 5px; font-size: 10px; }
        .grades tr.alt td { background-color: #f9fafb; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #9ca3af; font-size: 9px; }
        .totals td { background-color: #eef2ff; font-weight: bold; }
        .summary { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .summary td { border: 1px solid #d1d5db; padding: 4px 6px; font-size: 10px; }
        .summary .label { background-color: #f3f4f6; color: #374151; }
        .big-average { font-size: 14px; font-weight: bold; color: #312e81; }
        .decision { padding: 5px 8px; font-weight: bold; font-size: 11px; }
        .admis { background-color: #d1fae5; color: #065f46; }
        .redouble { background-color: #fee2e2; color: #991b1b; }
        .pending { background-color: #fef3c7; color: #92400e; }
        .footer { margin-top: 14px; font-size: 9px; color: #4b5563; }
        .signature { width: 100%; margin-top: 18px; }
        .signature td { width: 50%; text-align: center; padding-top: 30px; font-size: 10px; }
        .incomplete-note { color: #b45309; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="school-name">{{ $profile->name }}</div>
                    <div class="school-info">
                        {{ $profile->motto }}
                        @if ($profile->address) — {{ $profile->address }}@endif
                        @if ($profile->city), {{ $profile->city }}@endif
                    </div>
                    <div class="school-info">
                        @if ($profile->phone) Tél. : {{ $profile->phone }}@endif
                        @if ($profile->email) — {{ $profile->email }}@endif
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div class="school-info bold">République du Congo</div>
                    <div class="school-info">Année scolaire {{ $data['period']->academicYear->name }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">BULLETIN DE NOTES</div>
    <div class="subtitle">{{ $data['period']->name }} — {{ $data['class']->name }} ({{ $data['class']->gradeLevel->name }})</div>

    <table class="info-table">
        <tr>
            <td class="info-label">Élève</td>
            <td class="bold">{{ $data['student']->full_name }}</td>
            <td class="info-label">Matricule</td>
            <td>{{ $data['student']->matricule }}</td>
        </tr>
        <tr>
            <td class="info-label">Né(e) le</td>
            <td>{{ $data['student']->birth_date ? $data['student']->birth_date->format('d/m/Y') : '—' }}
                @if ($data['student']->birth_place) à {{ $data['student']->birth_place }}@endif</td>
            <td class="info-label">Sexe</td>
            <td>{{ $data['student']->gender === 'F' ? 'Féminin' : 'Masculin' }}</td>
        </tr>
        <tr>
            <td class="info-label">Classe</td>
            <td>{{ $data['class']->name }}</td>
            <td class="info-label">Effectif</td>
            <td>{{ $data['class_size'] }} élève(s)</td>
        </tr>
    </table>

    <table class="grades">
        <thead>
            <tr>
                <th style="width: 32%; text-align: left;">Matière</th>
                <th style="width: 8%;">Coef.</th>
                <th style="width: 12%;">Moyenne / {{ $config->format($config->targetScale) }}</th>
                <th style="width: 14%;">Points (Moy × Coef)</th>
                <th style="width: 9%;">Rang</th>
                <th style="width: 25%;">Appréciation</th>
            </tr>
        </thead>
        <tbody>
            @php $totalPoints = 0; $totalCoef = 0; @endphp
            @foreach ($data['rows'] as $index => $row)
                @php
                    $isCounted = $row['average'] !== null;
                    if ($isCounted) { $totalPoints += $row['weighted']; $totalCoef += $row['coefficient']; }
                @endphp
                <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $row['subject'] }}@if (! $row['graded']) <span class="muted">(non notée)</span>@endif</td>
                    <td class="center">{{ rtrim(rtrim(number_format($row['coefficient'], 2, ',', ' '), '0'), ',') }}</td>
                    <td class="center bold">
                        @if ($isCounted)
                            {{ $config->format($row['average']) }}
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="center">{{ $isCounted ? $config->format($row['weighted']) : '—' }}</td>
                    <td class="center">
                        @if ($row['rank'])
                            {{ $row['rank'] }}{{ $row['rank'] === 1 ? 'er' : 'e' }}
                            @if ($row['ranked_count']) <span class="muted">/{{ $row['ranked_count'] }}</span> @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        {{ $row['appreciation'] }}
                        @if (($row['missing_count'] ?? 0) > 0)
                            <span class="incomplete-note">({{ $row['missing_count'] }} note(s) manquante(s))</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Totaux</td>
                <td class="center">{{ $config->format($totalCoef) }}</td>
                <td class="center">—</td>
                <td class="center">{{ $config->format($totalPoints) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="label" style="width: 22%;">Moyenne générale</td>
            <td style="width: 28%;" class="big-average">
                {{ $config->format($data['general_average']) }} / {{ $config->format($config->targetScale) }}
            </td>
            <td class="label" style="width: 22%;">Rang</td>
            <td>
                @if ($data['rank'])
                    {{ $data['rank'] }}{{ $data['rank'] === 1 ? 'er' : 'e' }} sur {{ $data['ranked_count'] }} classé(s)
                @else
                    Non classé
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Moyenne de la classe</td>
            <td>{{ $config->format($data['class_average']) }}</td>
            <td class="label">Plus forte / plus faible</td>
            <td>{{ $config->format($data['class_max']) }} / {{ $config->format($data['class_min']) }}</td>
        </tr>
        <tr>
            <td class="label">Taux de réussite</td>
            <td>{{ $data['class_success_rate'] !== null ? number_format($data['class_success_rate'], 1, ',', ' ') . ' %' : '—' }}</td>
            <td class="label">Seuil de réussite</td>
            <td>{{ $config->format($config->passThreshold) }} / {{ $config->format($config->targetScale) }}</td>
        </tr>
        <tr>
            <td class="label">Appréciation générale</td>
            <td>{{ $data['appreciation'] }}</td>
            <td class="label">Décision</td>
            <td class="decision {{ $data['decision'] === 'admis' ? 'admis' : ($data['decision'] === 'redouble' ? 'redouble' : 'pending') }}">
                {{ $data['decision'] === 'admis' ? 'ADMIS(E)' : ($data['decision'] === 'redouble' ? 'REDOUBLE' : 'EN ATTENTE') }}
            </td>
        </tr>
    </table>

    @if (! $data['general_complete'])
        <p class="incomplete-note">
            ⚠ Résultats partiels : certaines évaluations publiées ne sont pas encore saisies ou certains élèves
            n'ont pas été évalués. La moyenne affichée ne prend en compte que les matières complètes.
        </p>
    @endif

    <table class="signature">
        <tr>
            <td>
                Fait à {{ $profile->city ?: 'Brazzaville' }}, le {{ $data['generated_at']->format('d/m/Y') }}
            </td>
            <td></td>
        </tr>
        <tr>
            <td>Le Directeur / La Directrice<br><span class="muted">{{ $profile->director_name }}</span></td>
            <td>Signature des parents</td>
        </tr>
    </table>

    <div class="footer">
        Document généré le {{ $data['generated_at']->format('d/m/Y à H:i') }}.
        Ce bulletin est archivé : toute régénération crée une nouvelle version sans modifier celle-ci.
    </div>
</body>
</html>
