<?php

namespace App\Services\Grading;

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\GradeRevision;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisie des notes en grille.
 *
 * Règles :
 *  - Avant validation de la période : le professeur affecté peut saisir
 *    et modifier librement (dans la limite du barème).
 *  - Après validation / verrouillage : toute modification exige la permission
 *    « grades.correct », un motif, et crée une révision horodatée.
 *  - Une évaluation annulée n'accepte plus de saisie.
 */
class GradeEntryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Enregistre les notes d'une évaluation.
     *
     * @param  array<int, array{student_id: int, value: float|string|null, status?: string, comment?: ?string}>  $rows
     */
    public function save(Evaluation $evaluation, array $rows, User $by, ?string $reason = null): void
    {
        $evaluation->loadMissing(['classSubject', 'period']);

        if ($evaluation->isCancelled()) {
            throw ValidationException::withMessages([
                'evaluation' => 'Cette évaluation est annulée : la saisie est impossible.',
            ]);
        }

        $period = $evaluation->period;
        $maxScore = (float) $evaluation->max_score;
        $isLocked = $period->isValidated(); // validated ou locked
        $canCorrect = $by->can('grades.correct');

        if ($isLocked && (! $canCorrect || blank($reason))) {
            throw ValidationException::withMessages([
                'reason' => 'Période validée : une correction exige la permission de correction et un motif.',
            ]);
        }

        $studentIds = $evaluation->classSubject->schoolClass->enrollments()
            ->where('status', \App\Models\Enrollment::STATUS_ACTIVE)
            ->pluck('student_id')
            ->all();

        DB::transaction(function () use ($evaluation, $rows, $by, $studentIds, $maxScore, $isLocked, $reason) {
            foreach ($rows as $row) {
                $studentId = (int) $row['student_id'];

                if (! in_array($studentId, $studentIds, true)) {
                    continue;
                }

                $status = $row['status'] ?? Grade::STATUS_ENTERED;
                $value = $row['value'] ?? null;

                if ($status === Grade::STATUS_ENTERED) {
                    if ($value === null || $value === '') {
                        $status = Grade::STATUS_MISSING;
                        $value = null;
                    } else {
                        $value = (float) $value;

                        if ($value < 0 || $value > $maxScore) {
                            throw ValidationException::withMessages([
                                'grades' => "Note hors barème pour l'élève #{$studentId} : la valeur doit être comprise entre 0 et {$maxScore}.",
                            ]);
                        }
                    }
                } else {
                    $value = null;
                }

                $grade = Grade::firstOrNew([
                    'evaluation_id' => $evaluation->id,
                    'student_id' => $studentId,
                ]);

                $oldValue = $grade->exists ? $grade->value : null;
                $oldStatus = $grade->exists ? $grade->status : null;

                $grade->value = $value;
                $grade->status = $status;
                $grade->comment = $row['comment'] ?? $grade->comment;

                if ($status === Grade::STATUS_ENTERED || $status === Grade::STATUS_ABSENT) {
                    $grade->entered_by = $by->id;
                    $grade->entered_at = now();
                }

                $changed = ! $grade->exists
                    || $grade->isDirty(['value', 'status']);

                $grade->save();

                if (! $changed) {
                    continue;
                }

                if ($isLocked) {
                    // Correction après validation : révision horodatée obligatoire.
                    GradeRevision::create([
                        'grade_id' => $grade->id,
                        'old_value' => $oldValue,
                        'new_value' => $grade->value,
                        'old_status' => $oldStatus,
                        'new_status' => $grade->status,
                        'reason' => $reason,
                        'revised_by' => $by->id,
                        'created_at' => now(),
                    ]);
                }

                $this->audit->log(
                    $isLocked ? 'grade.corrected' : 'grade.updated',
                    $grade,
                    ['value' => $oldValue, 'status' => $oldStatus],
                    ['value' => $grade->value, 'status' => $grade->status],
                    $reason,
                    $by,
                );
            }
        });
    }

    /** Publie une évaluation (elle entre dans les calculs). */
    public function publish(Evaluation $evaluation, User $by): void
    {
        if ($evaluation->period->isValidated()) {
            throw ValidationException::withMessages([
                'status' => 'La période est validée : impossible de publier une évaluation.',
            ]);
        }

        $before = $evaluation->status;
        $evaluation->update([
            'status' => Evaluation::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->audit->log('evaluation.published', $evaluation, ['status' => $before], ['status' => $evaluation->status], null, $by);
    }

    /** Annule une évaluation (exclue des calculs). */
    public function cancel(Evaluation $evaluation, User $by, string $reason): void
    {
        if ($evaluation->period->isValidated()) {
            throw ValidationException::withMessages([
                'status' => 'La période est validée : impossible d\'annuler une évaluation.',
            ]);
        }

        $before = $evaluation->status;
        $evaluation->update(['status' => Evaluation::STATUS_CANCELLED]);

        $this->audit->log('evaluation.cancelled', $evaluation, ['status' => $before], ['status' => $evaluation->status], $reason, $by);
    }
}
