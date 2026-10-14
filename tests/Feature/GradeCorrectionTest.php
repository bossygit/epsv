<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\SchoolPeriod;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Saisie et correction des notes : avant validation (libre) et après
 * validation (permission de correction + motif + révision horodatée).
 */
class GradeCorrectionTest extends TestCase
{
    use CreatesSchoolData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_professeur_saisit_et_modifie_ses_notes_avant_validation(): void
    {
        [$teacher, $classSubject, $period, $student, $evaluation] = $this->makeContext();

        $this->actingAs($teacher)->post(route('grades.entry.store', $evaluation), [
            'records' => [
                ['student_id' => $student->id, 'value' => 14, 'status' => 'entered'],
            ],
        ])->assertRedirect();

        $grade = Grade::where('evaluation_id', $evaluation->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('14.00', $grade->value);
        $this->assertSame(Grade::STATUS_ENTERED, $grade->status);

        // Modification libre avant validation : pas de révision
        $this->actingAs($teacher)->post(route('grades.entry.store', $evaluation), [
            'records' => [
                ['student_id' => $student->id, 'value' => 16, 'status' => 'entered'],
            ],
        ])->assertRedirect();

        $this->assertSame('16.00', $grade->fresh()->value);
        $this->assertDatabaseCount('grade_revisions', 0);
    }

    public function test_note_hors_barème_refusée(): void
    {
        [$teacher, $classSubject, $period, $student, $evaluation] = $this->makeContext();

        $this->actingAs($teacher)->post(route('grades.entry.store', $evaluation), [
            'records' => [
                ['student_id' => $student->id, 'value' => 25, 'status' => 'entered'],
            ],
        ])->assertSessionHasErrors('grades');
    }

    public function test_apres_validation_le_professeur_ne_peut_plus_modifier(): void
    {
        [$teacher, $classSubject, $period, $student, $evaluation] = $this->makeContext();

        $this->actingAs($teacher)->post(route('grades.entry.store', $evaluation), [
            'records' => [['student_id' => $student->id, 'value' => 10, 'status' => 'entered']],
        ]);

        $period->update(['status' => SchoolPeriod::STATUS_VALIDATED]);

        // Sans permission de correction ni motif : refusé
        $this->actingAs($teacher)->post(route('grades.entry.store', $evaluation), [
            'records' => [['student_id' => $student->id, 'value' => 18, 'status' => 'entered']],
        ])->assertSessionHasErrors('reason');

        $grade = Grade::where('evaluation_id', $evaluation->id)->where('student_id', $student->id)->first();
        $this->assertSame('10.00', $grade->value, 'La note ne doit pas avoir été modifiée.');
    }

    public function test_la_direction_corrige_avec_motif_et_revision_enregistree(): void
    {
        [$teacher, $classSubject, $period, $student, $evaluation] = $this->makeContext();

        $this->actingAs($teacher)->post(route('grades.entry.store', $evaluation), [
            'records' => [['student_id' => $student->id, 'value' => 10, 'status' => 'entered']],
        ]);

        $period->update(['status' => SchoolPeriod::STATUS_VALIDATED]);
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();

        $this->actingAs($direction)->post(route('grades.entry.store', $evaluation), [
            'records' => [['student_id' => $student->id, 'value' => 18, 'status' => 'entered']],
            'reason' => 'Erreur de report signalée par le professeur',
        ])->assertRedirect();

        $grade = Grade::where('evaluation_id', $evaluation->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('18.00', $grade->value);

        // La révision conserve l'ancienne valeur, la nouvelle et le motif
        $this->assertDatabaseHas('grade_revisions', [
            'grade_id' => $grade->id,
            'old_value' => '10.00',
            'new_value' => '18.00',
            'reason' => 'Erreur de report signalée par le professeur',
            'revised_by' => $direction->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'grade.corrected']);
    }

    public function test_la_direction_ne_peut_pas_saisir_de_notes_sans_permission_den_saisie(): void
    {
        // La direction possède grades.correct mais pas grades.enter :
        // elle ne peut pas saisir de notes dans une période ouverte.
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();
        $this->assertFalse($direction->can('grades.enter'));
        $this->assertTrue($direction->can('grades.correct'));
        $this->assertTrue($direction->can('grades.validate'));
    }

    /**
     * @return array{0: User, 1: \App\Models\ClassSubject, 2: SchoolPeriod, 3: \App\Models\Student, 4: \App\Models\Evaluation}
     */
    private function makeContext(): array
    {
        $teacher = $this->makeUserWithRole(\App\Support\Roles::PROFESSEUR);
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $subject = $this->makeSubject();
        $classSubject = $this->attachSubject($class, $subject, 4);
        $this->assignTeacher($teacher, $class, $subject);

        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $evaluation = $this->makeEvaluation($classSubject, $period);

        return [$teacher, $classSubject, $period, $student, $evaluation];
    }
}
