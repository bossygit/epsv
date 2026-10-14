<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Responsables légaux : ajout, unicité du responsable principal, retrait.
 */
class GuardianTest extends TestCase
{
    use CreatesSchoolData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_ajout_dun_responsable_sans_drapeau_principal(): void
    {
        $secretariat = User::where('email', 'secretariat@epsv.cg')->firstOrFail();
        $student = $this->makeStudent();

        $this->actingAs($secretariat)->post(route('guardians.store', $student), [
            'full_name' => 'Marie Samba',
            'relationship' => 'mère',
            'phone' => '+242 06 111 11 11',
            'is_primary' => 0,
            'is_emergency_contact' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('guardians', ['full_name' => 'Marie Samba']);
        $this->assertSame(1, $student->guardians()->count());
    }

    public function test_un_seul_responsable_principal_a_la_fois(): void
    {
        $secretariat = User::where('email', 'secretariat@epsv.cg')->firstOrFail();
        $student = $this->makeStudent();

        // Premier responsable, principal
        $this->actingAs($secretariat)->post(route('guardians.store', $student), [
            'full_name' => 'Premier Parent',
            'relationship' => 'père',
            'phone' => '+242 06 222 22 22',
            'is_primary' => 1,
            'is_emergency_contact' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $first = Guardian::where('full_name', 'Premier Parent')->firstOrFail();
        $this->assertDatabaseHas('student_guardian', [
            'student_id' => $student->id,
            'guardian_id' => $first->id,
            'is_primary' => true,
        ]);

        // Second responsable, également principal : le premier est rétrogradé
        $this->actingAs($secretariat)->post(route('guardians.store', $student), [
            'full_name' => 'Second Parent',
            'relationship' => 'mère',
            'phone' => '+242 06 333 33 33',
            'is_primary' => 1,
            'is_emergency_contact' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $second = Guardian::where('full_name', 'Second Parent')->firstOrFail();

        $this->assertDatabaseHas('student_guardian', [
            'student_id' => $student->id,
            'guardian_id' => $second->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('student_guardian', [
            'student_id' => $student->id,
            'guardian_id' => $first->id,
            'is_primary' => false,
        ]);

        $this->assertSame($second->id, $student->primaryGuardian()->id);
    }

    public function test_mise_a_jour_dun_responsable(): void
    {
        $secretariat = User::where('email', 'secretariat@epsv.cg')->firstOrFail();
        $student = $this->makeStudent();

        $this->actingAs($secretariat)->post(route('guardians.store', $student), [
            'full_name' => 'Parent À Modifier',
            'relationship' => 'tuteur',
            'phone' => '+242 06 444 44 44',
            'is_primary' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $guardian = Guardian::where('full_name', 'Parent À Modifier')->firstOrFail();

        $this->actingAs($secretariat)->put(route('guardians.update', [$student, $guardian]), [
            'full_name' => 'Parent Modifié',
            'relationship' => 'tuteur',
            'phone' => '+242 06 555 55 55',
            'is_primary' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'full_name' => 'Parent Modifié']);
        $this->assertDatabaseHas('student_guardian', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'is_primary' => true,
        ]);
    }

    public function test_retrait_dun_responsable(): void
    {
        $secretariat = User::where('email', 'secretariat@epsv.cg')->firstOrFail();
        $student = $this->makeStudent();

        $this->actingAs($secretariat)->post(route('guardians.store', $student), [
            'full_name' => 'Parent À Retirer',
            'relationship' => 'autre',
            'phone' => '+242 06 666 66 66',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $guardian = Guardian::where('full_name', 'Parent À Retirer')->firstOrFail();

        $this->actingAs($secretariat)->delete(route('guardians.destroy', [$student, $guardian]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(0, $student->guardians()->count());
        $this->assertNull(Guardian::find($guardian->id), 'Un responsable sans élève est supprimé.');
    }
}
