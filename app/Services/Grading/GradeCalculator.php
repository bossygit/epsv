<?php

namespace App\Services\Grading;

use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolPeriod;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Moteur de calcul scolaire.
 *
 * Règles (configurables via GradingConfig) :
 *  - Chaque note est normalisée sur le barème cible de la matière :
 *      N* = N / B_i × B_c
 *  - Moyenne de matière : moyenne pondérée par le coefficient de chaque évaluation.
 *  - Moyenne générale : moyenne des moyennes de matières pondérée par les
 *    coefficients des matières, uniquement pour les matières admissibles.
 *  - Une évaluation annulée est exclue. Une note manquante n'est jamais
 *    convertie en zéro : elle marque le résultat « incomplet ».
 *  - Une absence à une évaluation est exclue du calcul par défaut
 *    (absent_policy = exclude), ou comptée zéro si configuré.
 *  - Une dispense est toujours exclue.
 *  - L'arrondi n'intervient qu'à l'affichage / à l'enregistrement final.
 */
class GradeCalculator
{
    public function __construct(private readonly GradingConfig $config) {}

    public static function make(?GradingConfig $config = null): self
    {
        return new self($config ?? GradingConfig::fromProfile());
    }

    public function config(): GradingConfig
    {
        return $this->config;
    }

    /** Normalise une note d'un barème source vers le barème cible. */
    public function normalize(float $value, float $sourceMax, ?float $target = null): float
    {
        $target ??= $this->config->targetScale;

        if ($sourceMax <= 0) {
            return 0.0;
        }

        return $value / $sourceMax * $target;
    }

    /**
     * Calcule la moyenne de matière d'un élève à partir d'évaluations déjà
     * chargées (calcul en mémoire, sans requête supplémentaire).
     *
     * @param  Collection<int, Evaluation>  $evaluations
     */
    public function subjectAverageFromEvaluations(
        Collection $evaluations,
        int $studentId,
        float $targetScale,
    ): SubjectAverage {
        $sum = 0.0;
        $weight = 0.0;
        $missing = 0;
        $absent = 0;
        $exempt = 0;
        $graded = 0;
        $entries = [];

        foreach ($evaluations as $evaluation) {
            /** @var Grade|null $grade */
            $grade = $evaluation->grades->firstWhere('student_id', $studentId);
            $status = $grade?->status ?? Grade::STATUS_MISSING;
            $coefficient = (float) $evaluation->coefficient;

            $row = [
                'evaluation_id' => $evaluation->id,
                'name' => $evaluation->name,
                'type' => $evaluation->type,
                'date' => $evaluation->evaluation_date,
                'max_score' => (float) $evaluation->max_score,
                'coefficient' => $coefficient,
                'status' => $status,
                'value' => $grade?->value !== null ? (float) $grade->value : null,
                'normalized' => null,
                'counted' => false,
            ];

            switch ($status) {
                case Grade::STATUS_ENTERED:
                    $normalized = $this->normalize((float) $grade->value, (float) $evaluation->max_score, $targetScale);
                    $sum += $normalized * $coefficient;
                    $weight += $coefficient;
                    $graded++;
                    $row['normalized'] = $normalized;
                    $row['counted'] = true;
                    break;

                case Grade::STATUS_ABSENT:
                    $absent++;
                    if ($this->config->absentPolicy === 'zero') {
                        $weight += $coefficient;
                        $graded++;
                        $row['normalized'] = 0.0;
                        $row['counted'] = true;
                    }
                    break;

                case Grade::STATUS_EXEMPT:
                    $exempt++;
                    break;

                default: // missing
                    $missing++;
                    break;
            }

            $entries[] = $row;
        }

        $value = $weight > 0 ? $sum / $weight : null;

        return new SubjectAverage(
            value: $value,
            complete: $missing === 0 && $evaluations->isNotEmpty(),
            totalWeight: $weight,
            missingCount: $missing,
            absentCount: $absent,
            exemptCount: $exempt,
            gradedCount: $graded,
            entries: $entries,
        );
    }

    /** Moyenne d'un élève dans une matière pour une période. */
    public function subjectAverage(Student $student, ClassSubject $classSubject, SchoolPeriod $period): SubjectAverage
    {
        $target = (float) $classSubject->max_score ?: $this->config->targetScale;

        $evaluations = $classSubject->evaluations()
            ->where('school_period_id', $period->id)
            ->where('status', Evaluation::STATUS_PUBLISHED)
            ->with('grades')
            ->orderBy('evaluation_date')
            ->get();

        return $this->subjectAverageFromEvaluations($evaluations, $student->id, $target);
    }

    /** Moyenne générale d'un élève pour une période (matières admissibles uniquement). */
    public function generalAverage(Student $student, SchoolClass $class, SchoolPeriod $period): GeneralAverage
    {
        $classSubjects = $class->classSubjects()->with('subject')->get();

        $sum = 0.0;
        $coefficientSum = 0.0;
        $complete = true;
        $missingSubjects = 0;
        $subjectAverages = [];
        $coefficients = [];

        foreach ($classSubjects as $classSubject) {
            $coefficients[$classSubject->id] = (float) $classSubject->coefficient;

            if (! $classSubject->is_graded) {
                continue;
            }

            $average = $this->subjectAverage($student, $classSubject, $period);
            $subjectAverages[$classSubject->id] = $average;

            if (! $average->hasValue()) {
                // Matière sans aucune évaluation exploitable : exclue du calcul.
                $missingSubjects++;
                $complete = false;

                continue;
            }

            if (! $average->complete) {
                $complete = false;

                if ($this->config->missingPolicy === 'strict') {
                    // Une matière incomplète n'est pas intégrée : pas de moyenne gonflée.
                    continue;
                }
            }

            $sum += $average->value * (float) $classSubject->coefficient;
            $coefficientSum += (float) $classSubject->coefficient;
        }

        $value = $coefficientSum > 0 ? $sum / $coefficientSum : null;

        return new GeneralAverage(
            value: $value,
            complete: $complete,
            totalCoefficient: $coefficientSum,
            subjectAverages: $subjectAverages,
            coefficients: $coefficients,
            missingSubjects: $missingSubjects,
        );
    }

    /**
     * Résultats de toute une classe pour une période, en un minimum de requêtes.
     *
     * @return array<int, GeneralAverage> indexé par student_id
     */
    public function classPeriodResults(SchoolClass $class, SchoolPeriod $period): array
    {
        $classSubjects = $class->classSubjects()
            ->where('is_graded', true)
            ->with([
                'subject',
                'evaluations' => fn ($q) => $q
                    ->where('school_period_id', $period->id)
                    ->where('status', Evaluation::STATUS_PUBLISHED)
                    ->with('grades')
                    ->orderBy('evaluation_date'),
            ])
            ->get();

        $studentIds = $class->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->pluck('student_id')
            ->all();

        $results = [];

        foreach ($studentIds as $studentId) {
            $sum = 0.0;
            $coefficientSum = 0.0;
            $complete = true;
            $missingSubjects = 0;
            $subjectAverages = [];
            $coefficients = [];

            foreach ($classSubjects as $classSubject) {
                $coefficients[$classSubject->id] = (float) $classSubject->coefficient;
                $target = (float) $classSubject->max_score ?: $this->config->targetScale;

                $average = $this->subjectAverageFromEvaluations($classSubject->evaluations, $studentId, $target);
                $subjectAverages[$classSubject->id] = $average;

                if (! $average->hasValue()) {
                    $missingSubjects++;
                    $complete = false;

                    continue;
                }

                if (! $average->complete) {
                    $complete = false;

                    if ($this->config->missingPolicy === 'strict') {
                        continue;
                    }
                }

                $sum += $average->value * (float) $classSubject->coefficient;
                $coefficientSum += (float) $classSubject->coefficient;
            }

            $results[$studentId] = new GeneralAverage(
                value: $coefficientSum > 0 ? $sum / $coefficientSum : null,
                complete: $complete,
                totalCoefficient: $coefficientSum,
                subjectAverages: $subjectAverages,
                coefficients: $coefficients,
                missingSubjects: $missingSubjects,
            );
        }

        return $results;
    }

    /**
     * Classement de la classe pour une période.
     * Les ex æquo partagent le même rang (1, 2, 2, 4…).
     * Les élèves sans moyenne exploitable sont non classés (rank = null).
     *
     * @return array<int, array{average: ?float, rank: ?int, complete: bool}> indexé par student_id
     */
    public function classRanking(SchoolClass $class, SchoolPeriod $period): array
    {
        $results = $this->classPeriodResults($class, $period);

        $ranking = [];

        foreach ($results as $studentId => $result) {
            $ranking[$studentId] = [
                'average' => $result->value,
                'rank' => null,
                'complete' => $result->isComplete(),
            ];
        }

        if (! $this->config->rankingEnabled) {
            return $ranking;
        }

        $rankable = collect($ranking)
            ->filter(fn ($row) => $row['average'] !== null)
            ->sortByDesc('average');

        $rank = 0;
        $position = 0;
        $previous = null;

        foreach ($rankable as $studentId => $row) {
            $position++;

            if ($previous === null || abs($row['average'] - $previous) > 1e-9) {
                $rank = $position;
                $previous = $row['average'];
            }

            $ranking[$studentId]['rank'] = $rank;
        }

        return $ranking;
    }

    /**
     * Rang d'un élève dans une matière pour une période.
     * Retourne [rang, effectif classé] ou [null, effectif].
     *
     * @return array{0: ?int, 1: int}
     */
    public function subjectRank(Student $student, ClassSubject $classSubject, SchoolPeriod $period): array
    {
        $target = (float) $classSubject->max_score ?: $this->config->targetScale;

        $evaluations = $classSubject->evaluations()
            ->where('school_period_id', $period->id)
            ->where('status', Evaluation::STATUS_PUBLISHED)
            ->with('grades')
            ->orderBy('evaluation_date')
            ->get();

        $studentIds = $classSubject->schoolClass->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->pluck('student_id');

        $values = [];

        foreach ($studentIds as $studentId) {
            $average = $this->subjectAverageFromEvaluations($evaluations, $studentId, $target);

            if ($average->hasValue()) {
                $values[$studentId] = $average->value;
            }
        }

        $count = count($values);

        if (! $this->config->rankingEnabled || ! isset($values[$student->id])) {
            return [null, $count];
        }

        $rank = 1;

        foreach ($values as $studentId => $value) {
            if ($value > $values[$student->id] + 1e-9) {
                $rank++;
            }
        }

        return [$rank, $count];
    }

    /**
     * Moyenne annuelle : moyenne des moyennes de périodes pondérée par le
     * poids de chaque période. Seules les périodes validées ou verrouillées
     * sont prises en compte (les périodes en cours sont provisoires).
     */
    public function annualAverage(Student $student, SchoolClass $class, ?int $academicYearId = null): ?float
    {
        $yearId = $academicYearId ?? $class->academic_year_id;

        $periods = SchoolPeriod::query()
            ->where('academic_year_id', $yearId)
            ->whereIn('status', [SchoolPeriod::STATUS_VALIDATED, SchoolPeriod::STATUS_LOCKED])
            ->orderBy('sequence')
            ->get();

        if ($periods->isEmpty()) {
            return null;
        }

        $sum = 0.0;
        $weightSum = 0.0;

        foreach ($periods as $period) {
            $general = $this->generalAverage($student, $class, $period);

            if ($general->value === null) {
                continue;
            }

            $sum += $general->value * (float) $period->weight;
            $weightSum += (float) $period->weight;
        }

        return $weightSum > 0 ? $sum / $weightSum : null;
    }

    /**
     * Statistiques de classe pour une période.
     *
     * @return array{average: ?float, max: ?float, min: ?float, ranked: int, total: int, success_rate: ?float}
     */
    public function classStats(SchoolClass $class, SchoolPeriod $period): array
    {
        $results = $this->classPeriodResults($class, $period);

        $values = collect($results)
            ->map(fn (GeneralAverage $r) => $r->value)
            ->filter(fn ($v) => $v !== null)
            ->values();

        $successCount = $values->filter(fn ($v) => $v >= $this->config->passThreshold)->count();

        return [
            'average' => $values->isNotEmpty() ? $values->average() : null,
            'max' => $values->isNotEmpty() ? $values->max() : null,
            'min' => $values->isNotEmpty() ? $values->min() : null,
            'ranked' => $values->count(),
            'total' => count($results),
            'success_rate' => $values->isNotEmpty() ? $successCount / $values->count() * 100 : null,
        ];
    }
}
