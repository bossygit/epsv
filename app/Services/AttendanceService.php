<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion des présences : appel rapide et corrections traçables.
 */
class AttendanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Crée (ou récupère) la séance d'appel d'une classe pour une date et un créneau.
     * whereDate garantit la comparaison correcte quel que soit le SGBD.
     */
    public function openSession(SchoolClass $class, string $date, string $slot, User $by): AttendanceSession
    {
        $existing = AttendanceSession::query()
            ->where('class_id', $class->id)
            ->whereDate('session_date', $date)
            ->where('slot', $slot)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return AttendanceSession::create([
                'class_id' => $class->id,
                'session_date' => $date,
                'slot' => $slot,
                'taken_by' => $by->id,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Séance créée entre-temps (double clic) : on la récupère.
            return AttendanceSession::query()
                ->where('class_id', $class->id)
                ->whereDate('session_date', $date)
                ->where('slot', $slot)
                ->firstOrFail();
        }
    }

    /**
     * Enregistre l'appel complet d'une séance.
     * Les élèves actifs de la classe absents du tableau reçoivent le statut « présent ».
     *
     * @param  array<int, array{student_id: int, status: string, minutes_late?: ?int, justification?: ?string}>  $rows
     */
    public function saveCall(AttendanceSession $session, array $rows, User $by): void
    {
        $studentIds = $this->activeStudentIds($session->schoolClass);

        DB::transaction(function () use ($session, $rows, $by, $studentIds) {
            $provided = [];

            foreach ($rows as $row) {
                $studentId = (int) $row['student_id'];

                if (! in_array($studentId, $studentIds, true)) {
                    continue; // sécurité : seuls les élèves inscrits dans la classe
                }

                $provided[$studentId] = $row;
            }

            foreach ($studentIds as $studentId) {
                $row = $provided[$studentId] ?? [
                    'student_id' => $studentId,
                    'status' => AttendanceRecord::STATUS_PRESENT,
                ];

                $record = AttendanceRecord::firstOrNew([
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentId,
                ]);

                $isNew = ! $record->exists;

                $before = $record->exists ? [
                    'status' => $record->status,
                    'minutes_late' => $record->minutes_late,
                    'justification' => $record->justification,
                ] : [];

                $record->status = $row['status'];
                $record->minutes_late = $row['status'] === AttendanceRecord::STATUS_LATE
                    ? ($row['minutes_late'] ?? null)
                    : null;
                $record->justification = $row['justification'] ?? $record->justification;

                $dirty = $isNew || $record->isDirty();

                $record->save();

                if ($dirty) {
                    $this->audit->log(
                        'attendance.updated',
                        $record,
                        $before,
                        ['status' => $record->status, 'minutes_late' => $record->minutes_late],
                        null,
                        $by,
                    );
                }
            }
        });
    }

    /**
     * Correction d'un enregistrement de présence avec motif obligatoire.
     */
    public function correctRecord(AttendanceRecord $record, array $data, User $by, string $reason): void
    {
        $before = [
            'status' => $record->status,
            'minutes_late' => $record->minutes_late,
            'justification' => $record->justification,
        ];

        $record->fill([
            'status' => $data['status'] ?? $record->status,
            'minutes_late' => $data['minutes_late'] ?? null,
            'justification' => $data['justification'] ?? $record->justification,
        ])->save();

        $this->audit->log(
            'attendance.corrected',
            $record,
            $before,
            ['status' => $record->status, 'minutes_late' => $record->minutes_late, 'justification' => $record->justification],
            $reason,
            $by,
        );
    }

    /** Justifie une absence/un retard sans changer le statut. */
    public function justify(AttendanceRecord $record, string $justification, User $by): void
    {
        $before = ['justification' => $record->justification];

        $record->update([
            'justification' => $justification,
            'justified_by' => $by->id,
            'justified_at' => now(),
        ]);

        $this->audit->log(
            'attendance.justified',
            $record,
            $before,
            ['justification' => $justification],
            null,
            $by,
        );
    }

    /** @return array<int> */
    public function activeStudentIds(SchoolClass $class): array
    {
        return $class->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->orderBy(
                Student::select('last_name')
                    ->whereColumn('students.id', 'enrollments.student_id')
            )
            ->pluck('student_id')
            ->all();
    }
}
