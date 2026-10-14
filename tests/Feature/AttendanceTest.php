<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Présences : appel rapide, unicité des séances, corrections traçables,
 * justifications.
 */
class AttendanceTest extends TestCase
{
    use CreatesSchoolData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_appel_enregistre_tous_les_eleves_avec_presence_par_defaut(): void
    {
        $teacher = $this->makeUserWithRole(\App\Support\Roles::PROFESSEUR);
        $year = $this->makeYear();
        $class = $this->makeClass($year);

        $a = $this->makeStudent();
        $b = $this->makeStudent();
        $this->enroll($a, $class);
        $this->enroll($b, $class);

        $service = app(AttendanceService::class);
        $session = $service->openSession($class, '2026-10-12', AttendanceSession::SLOT_MORNING, $teacher);

        // Seul l'élève A est explicitement absent ; B n'est pas fourni → présent.
        $service->saveCall($session, [
            ['student_id' => $a->id, 'status' => AttendanceRecord::STATUS_ABSENT],
        ], $teacher);

        $this->assertSame(2, $session->records()->count());
        $this->assertSame(
            AttendanceRecord::STATUS_ABSENT,
            $session->records()->where('student_id', $a->id)->first()->status,
        );
        $this->assertSame(
            AttendanceRecord::STATUS_PRESENT,
            $session->records()->where('student_id', $b->id)->first()->status,
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.updated']);
    }

    public function test_une_seule_seance_par_classe_date_et_creneau(): void
    {
        $teacher = $this->makeUserWithRole(\App\Support\Roles::PROFESSEUR);
        $year = $this->makeYear();
        $class = $this->makeClass($year);

        $service = app(AttendanceService::class);
        $first = $service->openSession($class, '2026-10-12', AttendanceSession::SLOT_MORNING, $teacher);
        $second = $service->openSession($class, '2026-10-12', AttendanceSession::SLOT_MORNING, $teacher);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('attendance_sessions', 1);

        // Un créneau différent crée bien une autre séance.
        $afternoon = $service->openSession($class, '2026-10-12', AttendanceSession::SLOT_AFTERNOON, $teacher);
        $this->assertNotSame($first->id, $afternoon->id);
    }

    public function test_correction_avec_motif_journalisee(): void
    {
        $surveillant = User::where('email', 'surveillant@epsv.cg')->firstOrFail();
        $year = $this->makeYear();
        $class = $this->makeClass($year);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $service = app(AttendanceService::class);
        $session = $service->openSession($class, '2026-10-12', AttendanceSession::SLOT_MORNING, $surveillant);
        $service->saveCall($session, [
            ['student_id' => $student->id, 'status' => AttendanceRecord::STATUS_ABSENT],
        ], $surveillant);

        $record = $session->records()->firstOrFail();

        // Correction sans motif : refusée
        $this->actingAs($surveillant)->post(route('attendance.records.correct', $record), [
            'status' => AttendanceRecord::STATUS_PRESENT,
        ])->assertSessionHasErrors('reason');

        // Avec motif : acceptée et tracée
        $this->actingAs($surveillant)->post(route('attendance.records.correct', $record), [
            'status' => AttendanceRecord::STATUS_PRESENT,
            'reason' => 'Élève retrouvé en salle 3 après vérification',
        ])->assertRedirect();

        $this->assertSame(AttendanceRecord::STATUS_PRESENT, $record->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.corrected']);
    }

    public function test_justification_absence_renseigne_auteur_et_date(): void
    {
        $surveillant = User::where('email', 'surveillant@epsv.cg')->firstOrFail();
        $year = $this->makeYear();
        $class = $this->makeClass($year);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $service = app(AttendanceService::class);
        $session = $service->openSession($class, '2026-10-12', AttendanceSession::SLOT_MORNING, $surveillant);
        $service->saveCall($session, [
            ['student_id' => $student->id, 'status' => AttendanceRecord::STATUS_ABSENT],
        ], $surveillant);

        $record = $session->records()->firstOrFail();

        $this->actingAs($surveillant)->post(route('attendance.records.justify', $record), [
            'justification' => 'Certificat médical présenté par le parent',
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame('Certificat médical présenté par le parent', $record->justification);
        $this->assertSame($surveillant->id, $record->justified_by);
        $this->assertNotNull($record->justified_at);
    }
}
