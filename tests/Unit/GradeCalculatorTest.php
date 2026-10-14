<?php

namespace Tests\Unit;

use App\Models\Grade;
use App\Models\SchoolPeriod;
use App\Services\Grading\GradeCalculator;
use App\Services\Grading\GradingConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

/**
 * Tests du moteur de calcul scolaire : barèmes, coefficients, notes
 * manquantes, dispenses, absences, annulations, arrondis, classement.
 */
class GradeCalculatorTest extends TestCase
{
    use CreatesSchoolData, RefreshDatabase;

    private function calculator(array $config = []): GradeCalculator
    {
        return GradeCalculator::make(new GradingConfig(...$config));
    }

    public function test_normalisation_de_notes_sur_des_barèmes_différents(): void
    {
        $calc = $this->calculator();

        $this->assertEqualsWithDelta(12.0, $calc->normalize(12, 20), 0.0001);
        $this->assertEqualsWithDelta(16.0, $calc->normalize(8, 10), 0.0001);
        $this->assertEqualsWithDelta(12.0, $calc->normalize(3, 5), 0.0001);
        $this->assertEqualsWithDelta(30.0, $calc->normalize(15, 20, 40), 0.0001);
    }

    public function test_moyenne_de_matiere_pondérée_par_les_coefficients(): void
    {
        // Exemple du cahier des charges : 12/20 (coef 1) et 16/20 (coef 2) → 14,67
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $e1 = $this->makeEvaluation($classSubject, $period, 'Devoir n°1', 1);
        $e2 = $this->makeEvaluation($classSubject, $period, 'Composition n°1', 2);

        $this->setGrade($e1, $student, 12);
        $this->setGrade($e2, $student, 16);

        $average = $this->calculator()->subjectAverage($student, $classSubject, $period);

        $this->assertEqualsWithDelta(44 / 3, $average->value, 0.0001);
        $this->assertTrue($average->complete);
        $this->assertEqualsWithDelta(3.0, $average->totalWeight, 0.0001);
        $this->assertSame(2, $average->gradedCount);
    }

    public function test_barèmes_différents_normalisés_avant_pondération(): void
    {
        // 12/20 (coef 1) et 8/10 (coef 1) → (12 + 16) / 2 = 14
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $e1 = $this->makeEvaluation($classSubject, $period, 'Devoir', 1, 20);
        $e2 = $this->makeEvaluation($classSubject, $period, 'Test', 1, 10);

        $this->setGrade($e1, $student, 12);
        $this->setGrade($e2, $student, 8);

        $average = $this->calculator()->subjectAverage($student, $classSubject, $period);

        $this->assertEqualsWithDelta(14.0, $average->value, 0.0001);
    }

    public function test_note_manquante_exclue_et_resultat_marqué_incomplet(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $e1 = $this->makeEvaluation($classSubject, $period, 'Devoir 1', 1);
        $e2 = $this->makeEvaluation($classSubject, $period, 'Devoir 2', 1);

        $this->setGrade($e1, $student, 14);
        $this->setGrade($e2, $student, null, Grade::STATUS_MISSING);

        $average = $this->calculator()->subjectAverage($student, $classSubject, $period);

        // La moyenne ne compte que la note saisie, mais le résultat est incomplet.
        $this->assertEqualsWithDelta(14.0, $average->value, 0.0001);
        $this->assertFalse($average->complete);
        $this->assertSame(1, $average->missingCount);
    }

    public function test_absence_a_une_evaluation_exclue_par_defaut(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $e1 = $this->makeEvaluation($classSubject, $period, 'Devoir 1', 1);
        $e2 = $this->makeEvaluation($classSubject, $period, 'Devoir 2', 1);

        $this->setGrade($e1, $student, 12);
        $this->setGrade($e2, $student, null, Grade::STATUS_ABSENT);

        $average = $this->calculator()->subjectAverage($student, $classSubject, $period);

        $this->assertEqualsWithDelta(12.0, $average->value, 0.0001);
        $this->assertTrue($average->complete, 'Une absence définitive ne rend pas le résultat incomplet.');
        $this->assertSame(1, $average->absentCount);
    }

    public function test_absence_comptée_zero_si_configurée(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $e1 = $this->makeEvaluation($classSubject, $period, 'Devoir 1', 1);
        $e2 = $this->makeEvaluation($classSubject, $period, 'Devoir 2', 1);

        $this->setGrade($e1, $student, 12);
        $this->setGrade($e2, $student, null, Grade::STATUS_ABSENT);

        $average = $this->calculator(['absentPolicy' => 'zero'])->subjectAverage($student, $classSubject, $period);

        $this->assertEqualsWithDelta(6.0, $average->value, 0.0001);
    }

    public function test_dispense_toujours_exclue(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $e1 = $this->makeEvaluation($classSubject, $period, 'Devoir 1', 1);
        $e2 = $this->makeEvaluation($classSubject, $period, 'Devoir 2', 1);

        $this->setGrade($e1, $student, 15);
        $this->setGrade($e2, $student, null, Grade::STATUS_EXEMPT);

        $average = $this->calculator()->subjectAverage($student, $classSubject, $period);

        $this->assertEqualsWithDelta(15.0, $average->value, 0.0001);
        $this->assertSame(1, $average->exemptCount);
    }

    public function test_evaluation_annulee_ou_brouillon_exclue_du_calcul(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $e1 = $this->makeEvaluation($classSubject, $period, 'Devoir 1', 1);
        $cancelled = $this->makeEvaluation($classSubject, $period, 'Devoir annulé', 1, 20, \App\Models\Evaluation::STATUS_CANCELLED);
        $draft = $this->makeEvaluation($classSubject, $period, 'Brouillon', 1, 20, \App\Models\Evaluation::STATUS_DRAFT);

        $this->setGrade($e1, $student, 13);
        $this->setGrade($cancelled, $student, 2);
        $this->setGrade($draft, $student, 1);

        $average = $this->calculator()->subjectAverage($student, $classSubject, $period);

        $this->assertEqualsWithDelta(13.0, $average->value, 0.0001);
        $this->assertSame(1, $average->gradedCount);
    }

    public function test_moyenne_generale_pondérée_par_les_coefficients_de_matières(): void
    {
        // FR coef 4 → 12 ; MATH coef 4 → 16 ; ANG coef 2 → 8 → (48 + 64 + 16) / 10 = 12,8
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        foreach ([[4, 12], [4, 16], [2, 8]] as $index => [$coefficient, $value]) {
            $classSubject = $this->attachSubject($class, $this->makeSubject(['name' => 'Matière'.$index]), $coefficient);
            $evaluation = $this->makeEvaluation($classSubject, $period);
            $this->setGrade($evaluation, $student, $value);
        }

        $general = $this->calculator()->generalAverage($student, $class, $period);

        $this->assertEqualsWithDelta(12.8, $general->value, 0.0001);
        $this->assertEqualsWithDelta(10.0, $general->totalCoefficient, 0.0001);
        $this->assertTrue($general->isComplete());
    }

    public function test_matiere_incomplete_exclue_de_la_moyenne_generale_en_mode_strict(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        // Matière complète : 12, coef 1
        $complete = $this->attachSubject($class, $this->makeSubject(['name' => 'Complète']), 1);
        $this->setGrade($this->makeEvaluation($complete, $period), $student, 12);

        // Matière incomplète (une note manquante) : moyenne partielle 18, coef 1
        $incomplete = $this->attachSubject($class, $this->makeSubject(['name' => 'Incomplète']), 1);
        $this->setGrade($this->makeEvaluation($incomplete, $period, 'Devoir 1'), $student, 18);
        $this->setGrade($this->makeEvaluation($incomplete, $period, 'Devoir 2'), $student, null, Grade::STATUS_MISSING);

        // Mode strict (défaut) : la matière incomplète est exclue → moyenne = 12
        $strict = $this->calculator(['missingPolicy' => 'strict'])->generalAverage($student, $class, $period);
        $this->assertEqualsWithDelta(12.0, $strict->value, 0.0001);
        $this->assertFalse($strict->isComplete());

        // Mode ignore : la matière est intégrée avec sa moyenne partielle → moyenne = 15
        $ignore = $this->calculator(['missingPolicy' => 'ignore'])->generalAverage($student, $class, $period);
        $this->assertEqualsWithDelta(15.0, $ignore->value, 0.0001);
        $this->assertFalse($ignore->isComplete(), 'Le résultat reste signalé comme incomplet.');
    }

    public function test_matiere_non_notée_ignorée(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $graded = $this->attachSubject($class, $this->makeSubject(['name' => 'Notée']), 2);
        $this->setGrade($this->makeEvaluation($graded, $period), $student, 14);

        $this->attachSubject($class, $this->makeSubject(['name' => 'Non notée']), 5, 20, false);

        $general = $this->calculator()->generalAverage($student, $class, $period);

        $this->assertEqualsWithDelta(14.0, $general->value, 0.0001);
        $this->assertEqualsWithDelta(2.0, $general->totalCoefficient, 0.0001);
    }

    public function test_arrondis_selon_la_configuration(): void
    {
        $halfUp = new GradingConfig(rounding: 'half_up', decimals: 2);
        $floor = new GradingConfig(rounding: 'floor', decimals: 2);
        $halfEven = new GradingConfig(rounding: 'half_even', decimals: 2);

        $this->assertEqualsWithDelta(14.67, $halfUp->round(44 / 3), 0.0001);
        $this->assertEqualsWithDelta(14.66, $floor->round(44 / 3), 0.0001);
        $this->assertEqualsWithDelta(14.67, $halfEven->round(44 / 3), 0.0001);
        $this->assertEqualsWithDelta(0.13, $halfUp->round(0.125), 0.0001);
        $this->assertEqualsWithDelta(0.12, $halfEven->round(0.125), 0.0001);
        $this->assertEqualsWithDelta(0.12, $floor->round(0.125), 0.0001);
    }

    public function test_classement_avec_ex_aequo_et_non_classes(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);

        // Trois élèves : 15, 15 (ex æquo), 12
        $a = $this->makeStudent(['first_name' => 'A']);
        $b = $this->makeStudent(['first_name' => 'B']);
        $c = $this->makeStudent(['first_name' => 'C']);

        $this->enroll($a, $class);
        $this->enroll($b, $class);
        $this->enroll($c, $class);

        $evaluation = $this->makeEvaluation($classSubject, $period);
        $this->setGrade($evaluation, $a, 15);
        $this->setGrade($evaluation, $b, 15);
        $this->setGrade($evaluation, $c, 12);

        $ranking = $this->calculator()->classRanking($class, $period);

        $this->assertSame(1, $ranking[$a->id]['rank']);
        $this->assertSame(1, $ranking[$b->id]['rank'], 'Les ex æquo partagent le même rang.');
        $this->assertSame(3, $ranking[$c->id]['rank']);

        // Classement désactivé : aucun rang attribué.
        $disabled = $this->calculator(['rankingEnabled' => false])->classRanking($class, $period);
        $this->assertNull($disabled[$a->id]['rank']);
    }

    public function test_statistiques_de_classe(): void
    {
        $year = $this->makeYear();
        $period = $this->makePeriod($year);
        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);

        $a = $this->makeStudent();
        $b = $this->makeStudent();
        $this->enroll($a, $class);
        $this->enroll($b, $class);

        $evaluation = $this->makeEvaluation($classSubject, $period);
        $this->setGrade($evaluation, $a, 16);
        $this->setGrade($evaluation, $b, 8);

        $stats = $this->calculator()->classStats($class, $period);

        $this->assertEqualsWithDelta(12.0, $stats['average'], 0.0001);
        $this->assertEqualsWithDelta(16.0, $stats['max'], 0.0001);
        $this->assertEqualsWithDelta(8.0, $stats['min'], 0.0001);
        $this->assertEqualsWithDelta(50.0, $stats['success_rate'], 0.0001);
    }

    public function test_moyenne_annuelle_pondérée_par_les_périodes_validees(): void
    {
        $year = $this->makeYear();
        $period1 = $this->makePeriod($year, ['status' => SchoolPeriod::STATUS_VALIDATED, 'weight' => 2]);
        $period2 = $this->makePeriod($year, ['status' => SchoolPeriod::STATUS_LOCKED, 'weight' => 1]);
        $period3 = $this->makePeriod($year, ['status' => SchoolPeriod::STATUS_OPEN, 'weight' => 1]);

        $class = $this->makeClass($year);
        $classSubject = $this->attachSubject($class);
        $student = $this->makeStudent();
        $this->enroll($student, $class);

        $this->setGrade($this->makeEvaluation($classSubject, $period1), $student, 10);
        $this->setGrade($this->makeEvaluation($classSubject, $period2), $student, 16);
        // Période 3 (ouverte) ne doit pas compter.
        $this->setGrade($this->makeEvaluation($classSubject, $period3), $student, 2);

        $annual = $this->calculator()->annualAverage($student, $class);

        // (10 × 2 + 16 × 1) / 3 = 12
        $this->assertEqualsWithDelta(12.0, $annual, 0.0001);
    }
}
