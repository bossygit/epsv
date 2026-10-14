<?php

namespace Tests\Feature;

use App\Models\SchoolPeriod;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Workflow de validation des périodes : brouillon → soumis → validé → verrouillé.
 */
class PeriodWorkflowTest extends TestCase
{
    use CreatesSchoolData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_workflow_complet_avec_traçabilité(): void
    {
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();
        $teacher = $this->makeUserWithRole(\App\Support\Roles::PROFESSEUR);
        $year = $this->makeYear();
        $period = $this->makePeriod($year);

        // Soumission par un professeur (la direction ne soumet pas)
        $this->actingAs($direction)->post(route('periods.submit', $period))->assertForbidden();
        $this->actingAs($teacher)->post(route('periods.submit', $period))->assertRedirect();
        $this->assertSame(SchoolPeriod::STATUS_SUBMITTED, $period->fresh()->status);

        // Double soumission refusée
        $this->actingAs($teacher)->post(route('periods.submit', $period))->assertSessionHasErrors('status');

        // Validation
        $this->actingAs($direction)->post(route('periods.validate', $period))->assertRedirect();
        $period->refresh();
        $this->assertSame(SchoolPeriod::STATUS_VALIDATED, $period->status);
        $this->assertSame($direction->id, $period->validated_by);
        $this->assertNotNull($period->validated_at);

        // Verrouillage
        $this->actingAs($direction)->post(route('periods.lock', $period))->assertRedirect();
        $this->assertSame(SchoolPeriod::STATUS_LOCKED, $period->fresh()->status);
        $this->assertTrue($period->fresh()->isLocked());

        // Réouverture sans motif refusée, avec motif acceptée
        $this->actingAs($direction)->post(route('periods.reopen', $period))->assertSessionHasErrors('reason');
        $this->actingAs($direction)->post(route('periods.reopen', $period), [
            'reason' => 'Erreur de saisie signalée par le professeur de mathématiques',
        ])->assertRedirect();
        $this->assertSame(SchoolPeriod::STATUS_OPEN, $period->fresh()->status);

        // Chaque transition est journalisée
        foreach (['period.submitted', 'period.validated', 'period.locked', 'period.reopened'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_validation_impossible_sans_soumission_prealable(): void
    {
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();
        $year = $this->makeYear();
        $period = $this->makePeriod($year); // statut open

        $this->actingAs($direction)->post(route('periods.validate', $period))->assertSessionHasErrors('status');
        $this->assertSame(SchoolPeriod::STATUS_OPEN, $period->fresh()->status);
    }

    public function test_professeur_ne_peut_pas_valider_une_periode(): void
    {
        $teacher = $this->makeUserWithRole(\App\Support\Roles::PROFESSEUR);
        $year = $this->makeYear();
        $period = $this->makePeriod($year, ['status' => SchoolPeriod::STATUS_SUBMITTED]);

        $this->actingAs($teacher)->post(route('periods.validate', $period))->assertForbidden();
        $this->assertSame(SchoolPeriod::STATUS_SUBMITTED, $period->fresh()->status);
    }
}
