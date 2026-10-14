<?php

namespace Tests\Feature;

use App\Models\ReportCard;
use App\Models\User;
use App\Services\Grading\ReportCardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Bulletins : génération PDF, archivage privé, versions immuables,
 * validation et verrouillage.
 */
class ReportCardTest extends TestCase
{
    use CreatesSchoolData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_generation_du_pdf_avec_moyenne_et_rang(): void
    {
        [$direction, $student, $class, $period, $classSubject] = $this->makeContext();

        $evaluation = $this->makeEvaluation($classSubject, $period, 'Devoir', 1);
        $this->setGrade($evaluation, $student, 15);

        $reportCard = app(ReportCardService::class)->generate($student, $period, $direction);

        $this->assertSame(ReportCard::STATUS_GENERATED, $reportCard->status);
        $this->assertEqualsWithDelta(15.0, (float) $reportCard->average, 0.01);
        $this->assertSame(1, $reportCard->rank);
        $this->assertNotNull($reportCard->file_path);
        $this->assertTrue(Storage::disk('local')->exists($reportCard->file_path), 'Le PDF doit être archivé sur le disque privé.');
        $this->assertStringNotContainsString('public', $reportCard->file_path);

        $this->assertDatabaseHas('audit_logs', ['action' => 'report_card.generated']);
    }

    public function test_telechargement_reserve_aux_personnes_autorisees(): void
    {
        [$direction, $student, $class, $period, $classSubject] = $this->makeContext();

        $evaluation = $this->makeEvaluation($classSubject, $period, 'Devoir', 1);
        $this->setGrade($evaluation, $student, 12);

        $reportCard = app(ReportCardService::class)->generate($student, $period, $direction);

        $this->actingAs($direction)->get(route('report-cards.download', $reportCard))->assertOk();

        // L'administrateur technique n'a pas accès aux bulletins.
        $admin = User::where('email', 'admin@epsv.cg')->firstOrFail();
        $this->actingAs($admin)->get(route('report-cards.download', $reportCard))->assertForbidden();
    }

    public function test_un_bulletin_valide_est_immuable_et_la_regeneration_cree_une_v2(): void
    {
        [$direction, $student, $class, $period, $classSubject] = $this->makeContext();

        $evaluation = $this->makeEvaluation($classSubject, $period, 'Devoir', 1);
        $this->setGrade($evaluation, $student, 10);

        $service = app(ReportCardService::class);
        $v1 = $service->generate($student, $period, $direction);

        // Validation puis verrouillage via les routes
        $this->actingAs($direction)->post(route('report-cards.validate', $v1))->assertRedirect();
        $this->assertSame(ReportCard::STATUS_VALIDATED, $v1->fresh()->status);

        $this->actingAs($direction)->post(route('report-cards.lock', $v1))->assertRedirect();
        $this->assertSame(ReportCard::STATUS_LOCKED, $v1->fresh()->status);

        // Nouvelle note puis régénération : une v2 est créée, la v1 reste intacte
        $this->setGrade($evaluation, $student, 18);
        $v2 = $service->generate($student, $period, $direction);

        $this->assertSame(2, $v2->version);
        $this->assertSame(ReportCard::STATUS_GENERATED, $v2->status);
        $this->assertEqualsWithDelta(18.0, (float) $v2->average, 0.01);

        $v1->refresh();
        $this->assertSame(ReportCard::STATUS_LOCKED, $v1->status);
        $this->assertEqualsWithDelta(10.0, (float) $v1->average, 0.01, 'La version verrouillée ne doit pas avoir changé.');
        $this->assertNotSame($v1->file_path, $v2->file_path);
    }

    /**
     * @return array{0: User, 1: \App\Models\Student, 2: \App\Models\SchoolClass, 3: \App\Models\SchoolPeriod, 4: \App\Models\ClassSubject}
     */
    private function makeContext(): array
    {
        $direction = User::where('email', 'direction@epsv.cg')->firstOrFail();
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class, $this->makeSubject(), 2);

        $student = $this->makeStudent();
        $this->enroll($student, $class);

        return [$direction, $student, $class, $period, $classSubject];
    }
}
