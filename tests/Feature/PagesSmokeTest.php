<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Evaluation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Grading\ReportCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recette de fumée : vérifie que toutes les pages principales se rendent
 * et que les droits d'accès de base sont respectés.
 */
class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeding explicite : la propriété $seed est ignorée lorsque la base
        // a déjà été migrée par une classe de test précédente. On garantit
        // ainsi les données de démonstration quel que soit l'ordre des tests.
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_invité_redirigé_vers_connexion(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_direction_accede_a_toutes_les_pages_principales(): void
    {
        $direction = User::where('email', 'direction@epsv.cg')->first();

        if (! $direction) {
            $this->fail('Compte direction absent. Comptes présents : '.json_encode(User::pluck('email')->all())
                .' | années: '.AcademicYear::count().' | élèves: '.Student::count());
        }
        $class = SchoolClass::firstOrFail();
        $student = Student::firstOrFail();
        $evaluation = Evaluation::firstOrFail();
        $session = AttendanceSession::firstOrFail();

        $routes = [
            route('dashboard'),
            route('students.index'),
            route('students.show', $student),
            route('students.create'),
            route('students.edit', $student),
            route('enrollments.index'),
            route('attendance.index'),
            route('attendance.session', $session),
            route('attendance.report', $class),
            route('evaluations.index'),
            route('grades.entry', $evaluation),
            route('report-cards.index'),
            route('report-cards.summary', ['schoolClass' => $class, 'school_period_id' => $evaluation->school_period_id]),
            route('academic-years.index'),
            route('classes.index'),
            route('classes.show', $class),
            route('subjects.index'),
            route('structure.index'),
            route('users.index'),
            route('settings.edit'),
            route('audit.index'),
            route('profile.edit'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($direction)->get($url);
            $this->assertSame(200, $response->status(), "La page {$url} doit être accessible à la direction.");
        }
    }

    public function test_professeur_voit_ses_classes_mais_pas_celles_des_autres(): void
    {
        $teacher = User::where('email', 'prof.mabiala@epsv.cg')->firstOrFail();
        $ownClassIds = $teacher->assignedClassIds();

        $this->assertNotEmpty($ownClassIds);

        $ownClass = SchoolClass::find($ownClassIds[0]);

        // Une classe de la même année sans affectation pour ce professeur.
        $otherClass = SchoolClass::factory()->create([
            'academic_year_id' => $ownClass->academic_year_id,
            'grade_level_id' => $ownClass->grade_level_id,
        ]);

        $this->actingAs($teacher)->get(route('classes.show', $ownClass))->assertOk();
        $this->actingAs($teacher)->get(route('classes.show', $otherClass))->assertForbidden();

        // Le professeur consulte les notes et crée des évaluations ;
        // en revanche il ne saisit pas de résultat à la place de la direction.
        $this->actingAs($teacher)->get(route('evaluations.index'))->assertOk();
        $this->actingAs($teacher)->get(route('evaluations.create'))->assertOk();

        // Le professeur ne peut pas gérer la structure ni les utilisateurs.
        $this->actingAs($teacher)->get(route('users.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($teacher)->get(route('academic-years.index'))->assertForbidden();
    }

    public function test_admin_technique_na_acces_ni_aux_eleves_ni_aux_notes(): void
    {
        $admin = User::where('email', 'admin@epsv.cg')->firstOrFail();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->actingAs($admin)->get(route('audit.index'))->assertOk();

        $this->actingAs($admin)->get(route('students.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('evaluations.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('report-cards.index'))->assertForbidden();
    }

    public function test_bulletin_genere_puis_telecharge(): void
    {
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();
        $student = Student::firstOrFail();
        $evaluation = Evaluation::firstOrFail();

        $reportCard = app(ReportCardService::class)->generate($student, $evaluation->period, $direction);

        $this->assertNotNull($reportCard->file_path);
        $this->assertNotNull($reportCard->average);

        $this->actingAs($direction)->get(route('report-cards.show', $reportCard))->assertOk();
        $this->actingAs($direction)->get(route('report-cards.download', $reportCard))->assertOk();
    }

    public function test_compte_desactive_deconnecte_immediatement(): void
    {
        $teacher = User::where('email', 'prof.ngoma@epsv.cg')->firstOrFail();
        $teacher->update(['is_active' => false]);

        $this->actingAs($teacher)->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
