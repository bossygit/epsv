<?php

namespace Tests\Concerns;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\SchoolClass;
use App\Models\SchoolPeriod;
use App\Models\Student;
use App\Models\Subject;

/**
 * Fabrique des données scolaires minimales et lisibles pour les tests.
 */
trait CreatesSchoolData
{
    private static int $seq = 0;

    protected function makeYear(array $attributes = []): AcademicYear
    {
        self::$seq++;

        return AcademicYear::create(array_merge([
            'name' => '2026-2027-'.self::$seq,
            'start_date' => '2026-09-15',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_ACTIVE,
            'is_current' => true,
        ], $attributes));
    }

    protected function makePeriod(AcademicYear $year, array $attributes = []): SchoolPeriod
    {
        $sequence = ($year->periods()->max('sequence') ?? 0) + 1;

        return SchoolPeriod::create(array_merge([
            'academic_year_id' => $year->id,
            'name' => "Période {$sequence}",
            'sequence' => $sequence,
            'type' => 'trimestre',
            'start_date' => '2026-09-15',
            'end_date' => '2026-12-19',
            'weight' => 1,
            'status' => SchoolPeriod::STATUS_OPEN,
        ], $attributes));
    }

    protected function makeLevel(?Cycle $cycle = null): GradeLevel
    {
        self::$seq++;

        $cycle ??= Cycle::create([
            'name' => 'Cycle '.self::$seq,
            'code' => 'CYC'.self::$seq,
            'sequence' => 1,
        ]);

        return GradeLevel::create([
            'cycle_id' => $cycle->id,
            'name' => 'Niveau '.self::$seq,
            'code' => 'NIV'.self::$seq,
            'sequence' => 1,
        ]);
    }

    protected function makeClass(AcademicYear $year, ?GradeLevel $level = null, array $attributes = []): SchoolClass
    {
        self::$seq++;
        $level ??= $this->makeLevel();

        return SchoolClass::create(array_merge([
            'academic_year_id' => $year->id,
            'grade_level_id' => $level->id,
            'name' => 'Classe '.self::$seq,
            'capacity' => 50,
            'is_active' => true,
        ], $attributes));
    }

    protected function makeSubject(array $attributes = []): Subject
    {
        self::$seq++;

        return Subject::create(array_merge([
            'name' => 'Matière '.self::$seq,
            'code' => 'MAT'.self::$seq,
            'is_active' => true,
        ], $attributes));
    }

    protected function attachSubject(
        SchoolClass $class,
        ?Subject $subject = null,
        float $coefficient = 1,
        float $maxScore = 20,
        bool $isGraded = true,
    ): ClassSubject {
        $subject ??= $this->makeSubject();

        return ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'coefficient' => $coefficient,
            'max_score' => $maxScore,
            'is_graded' => $isGraded,
        ]);
    }

    protected function makeUserWithRole(string $role, array $attributes = []): \App\Models\User
    {
        self::$seq++;

        $user = \App\Models\User::create(array_merge([
            'name' => 'Utilisateur '.self::$seq,
            'email' => 'user'.self::$seq.'@test.cg',
            'password' => bcrypt('password'),
            'is_active' => true,
        ], $attributes));

        $user->assignRole($role);

        return $user;
    }

    protected function assignTeacher(\App\Models\User $teacher, SchoolClass $class, Subject $subject): \App\Models\TeacherAssignment
    {
        return \App\Models\TeacherAssignment::create([
            'user_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $class->academic_year_id,
        ]);
    }

    protected function makeStudent(array $attributes = []): Student
    {
        self::$seq++;

        return Student::create(array_merge([
            'matricule' => 'TEST-'.self::$seq,
            'first_name' => 'Élève'.self::$seq,
            'last_name' => 'Test'.self::$seq,
            'gender' => 'M',
            'birth_date' => '2014-05-10',
            'status' => Student::STATUS_ACTIVE,
        ], $attributes));
    }

    protected function enroll(Student $student, SchoolClass $class): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $class->academic_year_id,
            'class_id' => $class->id,
            'enrolled_on' => '2026-09-15',
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
    }

    protected function makeEvaluation(
        ClassSubject $classSubject,
        SchoolPeriod $period,
        string $name = 'Devoir',
        float $coefficient = 1,
        float $maxScore = 20,
        string $status = Evaluation::STATUS_PUBLISHED,
    ): Evaluation {
        return Evaluation::create([
            'class_subject_id' => $classSubject->id,
            'school_period_id' => $period->id,
            'name' => $name,
            'type' => 'devoir',
            'evaluation_date' => '2026-10-01',
            'max_score' => $maxScore,
            'coefficient' => $coefficient,
            'status' => $status,
            'published_at' => $status === Evaluation::STATUS_PUBLISHED ? now() : null,
        ]);
    }

    protected function setGrade(
        Evaluation $evaluation,
        Student $student,
        ?float $value,
        string $status = Grade::STATUS_ENTERED,
    ): Grade {
        return Grade::updateOrCreate(
            ['evaluation_id' => $evaluation->id, 'student_id' => $student->id],
            [
                'value' => $status === Grade::STATUS_ENTERED ? $value : null,
                'status' => $status,
                'entered_at' => $status === Grade::STATUS_ENTERED ? now() : null,
            ],
        );
    }
}
