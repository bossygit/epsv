<?php

namespace App\Services\Grading;

use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\SchoolPeriod;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Génération et gestion des bulletins.
 *
 * Principes :
 *  - Les données du bulletin proviennent exclusivement du moteur de calcul.
 *  - Un bulletin validé ou verrouillé est immuable : une régénération crée
 *    une nouvelle version (v2, v3…) sans écraser la précédente.
 *  - Les PDF sont stockés dans le disque privé, jamais dans public/.
 */
class ReportCardService
{
    public function __construct(
        private readonly GradeCalculator $calculator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Construit les données complètes d'un bulletin (structure réutilisée
     * par l'aperçu HTML et le PDF).
     *
     * @return array<string, mixed>
     */
    public function buildData(Student $student, SchoolPeriod $period): array
    {
        $enrollment = $student->enrollments()
            ->where('academic_year_id', $period->academic_year_id)
            ->with('schoolClass.gradeLevel.cycle')
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'student' => "{$student->full_name} n'a pas d'inscription pour l'année {$period->academicYear->name}.",
            ]);
        }

        $class = $enrollment->schoolClass;
        $general = $this->calculator->generalAverage($student, $class, $period);
        $ranking = $this->calculator->classRanking($class, $period);
        $stats = $this->calculator->classStats($class, $period);

        $rank = $ranking[$student->id]['rank'] ?? null;
        $rankedCount = collect($ranking)->filter(fn ($r) => $r['rank'] !== null)->count();

        $rows = [];

        foreach ($class->classSubjects()->with('subject')->get() as $classSubject) {
            $average = $general->subjectAverages[$classSubject->id] ?? null;

            if (! $classSubject->is_graded) {
                $rows[] = [
                    'subject' => $classSubject->subject->name,
                    'coefficient' => (float) $classSubject->coefficient,
                    'average' => null,
                    'weighted' => null,
                    'rank' => null,
                    'ranked_count' => null,
                    'graded' => false,
                    'complete' => true,
                    'appreciation' => 'Matière non notée',
                ];

                continue;
            }

            $value = $average?->value;
            $usable = $average?->isUsable($this->calculator->config()->missingPolicy) ?? false;
            [$subjectRank, $subjectRanked] = $usable
                ? $this->calculator->subjectRank($student, $classSubject, $period)
                : [null, null];

            $rows[] = [
                'subject' => $classSubject->subject->name,
                'coefficient' => (float) $classSubject->coefficient,
                'average' => $usable ? $value : null,
                'weighted' => $usable ? $value * (float) $classSubject->coefficient : null,
                'rank' => $subjectRank,
                'ranked_count' => $subjectRanked,
                'graded' => true,
                'complete' => $average?->complete ?? false,
                'missing_count' => $average?->missingCount ?? 0,
                'appreciation' => $this->appreciation($usable ? $value : null, $average?->complete ?? false),
            ];
        }

        $generalValue = $general->isComplete() || $this->calculator->config()->missingPolicy === 'ignore'
            ? $general->value
            : $general->value;

        return [
            'student' => $student,
            'enrollment' => $enrollment,
            'class' => $class,
            'period' => $period,
            'rows' => $rows,
            'general_average' => $generalValue,
            'general_complete' => $general->isComplete(),
            'rank' => $rank,
            'ranked_count' => $rankedCount,
            'class_size' => count($ranking),
            'class_average' => $stats['average'],
            'class_max' => $stats['max'],
            'class_min' => $stats['min'],
            'class_success_rate' => $stats['success_rate'],
            'decision' => $this->decision($generalValue),
            'appreciation' => $this->appreciation($generalValue, $general->isComplete()),
            'generated_at' => now(),
        ];
    }

    /**
     * Génère le PDF d'un bulletin et enregistre la version.
     * Si un bulletin immuable existe déjà, une nouvelle version est créée.
     */
    public function generate(Student $student, SchoolPeriod $period, User $by): ReportCard
    {
        $data = $this->buildData($student, $period);

        $latest = ReportCard::query()
            ->where('student_id', $student->id)
            ->where('school_period_id', $period->id)
            ->orderByDesc('version')
            ->first();

        if ($latest?->isImmutable()) {
            $version = $latest->version + 1;
            $reportCard = new ReportCard([
                'student_id' => $student->id,
                'school_period_id' => $period->id,
                'version' => $version,
            ]);
        } else {
            $reportCard = $latest ?? new ReportCard([
                'student_id' => $student->id,
                'school_period_id' => $period->id,
                'version' => 1,
            ]);
        }

        $config = $this->calculator->config();
        $path = sprintf(
            'report-cards/%s/%s/%s-v%d.pdf',
            $period->academicYear->name,
            $period->sequence,
            $student->matricule,
            $reportCard->version,
        );

        $pdf = Pdf::loadView('reports.report-card-pdf', [
            'data' => $data,
            'profile' => \App\Models\SchoolProfile::current(),
            'config' => $config,
        ])->setPaper('a4');

        Storage::disk('local')->put($path, $pdf->output());

        $reportCard->fill([
            'status' => ReportCard::STATUS_GENERATED,
            'file_path' => $path,
            'average' => $config->round($data['general_average']),
            'rank' => $data['rank'],
            'class_size' => $data['class_size'],
            'decision' => $data['decision'],
            'metadata' => [
                'class_average' => $config->round($data['class_average']),
                'class_max' => $config->round($data['class_max']),
                'class_min' => $config->round($data['class_min']),
                'class_success_rate' => $data['class_success_rate'] !== null ? round($data['class_success_rate'], 1) : null,
                'complete' => $data['general_complete'],
                'appreciation' => $data['appreciation'],
                'rows' => array_map(fn ($r) => [
                    'subject' => $r['subject'],
                    'coefficient' => $r['coefficient'],
                    'average' => $config->round($r['average']),
                    'rank' => $r['rank'],
                    'appreciation' => $r['appreciation'],
                ], $data['rows']),
            ],
            'generated_by' => $by->id,
            'generated_at' => now(),
        ]);

        $reportCard->save();

        $this->audit->log('report_card.generated', $reportCard, [], [
            'version' => $reportCard->version,
            'average' => $reportCard->average,
        ], null, $by);

        return $reportCard;
    }

    public function validate(ReportCard $reportCard, User $by): void
    {
        if (! in_array($reportCard->status, [ReportCard::STATUS_GENERATED, ReportCard::STATUS_DRAFT], true)) {
            throw ValidationException::withMessages([
                'status' => 'Seul un bulletin généré peut être validé.',
            ]);
        }

        $before = $reportCard->status;

        $reportCard->update([
            'status' => ReportCard::STATUS_VALIDATED,
            'validated_by' => $by->id,
            'validated_at' => now(),
        ]);

        $this->audit->log('report_card.validated', $reportCard, ['status' => $before], ['status' => $reportCard->status], null, $by);
    }

    public function lock(ReportCard $reportCard, User $by): void
    {
        if ($reportCard->status !== ReportCard::STATUS_VALIDATED) {
            throw ValidationException::withMessages([
                'status' => 'Seul un bulletin validé peut être verrouillé.',
            ]);
        }

        $reportCard->update(['status' => ReportCard::STATUS_LOCKED]);

        $this->audit->log('report_card.locked', $reportCard, ['status' => ReportCard::STATUS_VALIDATED], ['status' => $reportCard->status], null, $by);
    }

    /** Appréciation automatique selon la moyenne (paramétrable plus tard). */
    public function appreciation(?float $value, bool $complete = true): string
    {
        if ($value === null) {
            return $complete ? '—' : 'Résultats incomplets';
        }

        if (! $complete) {
            return 'Résultats partiels';
        }

        return match (true) {
            $value >= 16 => 'Excellent',
            $value >= 14 => 'Très bien',
            $value >= 12 => 'Bien',
            $value >= 10 => 'Passable',
            default => 'Insuffisant',
        };
    }

    private function decision(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value >= $this->calculator->config()->passThreshold ? 'admis' : 'redouble';
    }
}
