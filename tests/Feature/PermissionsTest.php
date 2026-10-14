<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matrice des rôles et permissions.
 * Règle clé : la direction valide et corrige mais ne saisit pas de notes ;
 * l'administrateur technique n'accède pas aux données scolaires.
 */
class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_direction_valide_mais_ne_saisit_pas_de_notes(): void
    {
        $direction = $this->user('direction@epsv.cg');

        $this->assertTrue($direction->can('grades.view'));
        $this->assertTrue($direction->can('grades.validate'));
        $this->assertTrue($direction->can('grades.lock'));
        $this->assertTrue($direction->can('grades.correct'));

        $this->assertFalse($direction->can('grades.enter'), 'La direction ne doit pas saisir de notes.');
        $this->assertFalse($direction->can('grades.submit'), 'La soumission appartient aux professeurs.');
    }

    public function test_professeur_saisit_mais_ne_valide_pas(): void
    {
        $teacher = $this->user('prof.mabiala@epsv.cg');

        $this->assertTrue($teacher->can('grades.enter'));
        $this->assertTrue($teacher->can('grades.submit'));
        $this->assertTrue($teacher->can('attendance.take'));
        $this->assertTrue($teacher->can('students.view'));

        $this->assertFalse($teacher->can('grades.validate'));
        $this->assertFalse($teacher->can('grades.correct'));
        $this->assertFalse($teacher->can('structure.manage'));
        $this->assertFalse($teacher->can('users.manage'));
        $this->assertFalse($teacher->can('audit.view'));
    }

    public function test_surveillant_gere_les_presences_pas_les_notes(): void
    {
        $surveillant = $this->user('surveillant@epsv.cg');

        $this->assertTrue($surveillant->can('attendance.take'));
        $this->assertTrue($surveillant->can('attendance.correct'));
        $this->assertTrue($surveillant->can('attendance.justify'));

        $this->assertFalse($surveillant->can('grades.view'));
        $this->assertFalse($surveillant->can('students.manage'));
    }

    public function test_secretariat_gere_les_inscriptions(): void
    {
        $secretariat = $this->user('secretariat@epsv.cg');

        $this->assertTrue($secretariat->can('students.manage'));
        $this->assertTrue($secretariat->can('enrollments.manage'));
        $this->assertTrue($secretariat->can('structure.view'));

        $this->assertFalse($secretariat->can('structure.manage'));
        $this->assertFalse($secretariat->can('grades.enter'));
        $this->assertFalse($secretariat->can('grades.validate'));
    }

    public function test_admin_technique_sans_acces_aux_donnees_scolaires(): void
    {
        $admin = $this->user('admin@epsv.cg');

        $this->assertTrue($admin->can('users.manage'));
        $this->assertTrue($admin->can('audit.view'));

        $this->assertFalse($admin->can('students.view'));
        $this->assertFalse($admin->can('grades.view'));
        $this->assertFalse($admin->can('report_cards.view'));
        $this->assertFalse($admin->can('attendance.view'));
    }

    public function test_les_roles_reference_existent(): void
    {
        foreach (array_keys(Roles::LABELS) as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }

        foreach (array_keys(Roles::PERMISSIONS) as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }
    }
}
