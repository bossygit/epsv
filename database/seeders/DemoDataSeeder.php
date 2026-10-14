<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassSubject;
use App\Models\Cycle;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\SchoolPeriod;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\MatriculeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration réalistes pour l'École Panafricaine
 * Séguynaud de Vinci (EPSV) : années, classes, élèves, notes, présences.
 *
 * Reproductible : la graine aléatoire est fixée.
 */
class DemoDataSeeder extends Seeder
{
    private array $firstNamesM = ['Jean', 'Paul', 'Pierre', 'André', 'Emmanuel', 'Joseph', 'David', 'Moïse', 'Gédéon', 'Hervé', 'Franck', 'Christian', 'Yannick', 'Bertrand', 'Lionel', 'Rodrigue', 'Prince', 'Elie'];

    private array $firstNamesF = ['Marie', 'Grâce', 'Esther', 'Rachel', 'Rebecca', 'Clarisse', 'Sylvie', 'Chantal', 'Léontine', 'Merveille', 'Divine', 'Nadège', 'Gloria', 'Sarah', 'Bénédicte', 'Prisca', 'Naomi', 'Bénie'];

    private array $lastNames = ['Mabiala', 'Kimbembe', 'Ngoma', 'Loubaki', 'Samba', 'Bakala', 'Malonga', 'Tchibinda', 'Bikindou', 'Massamba', 'Okemba', 'Nkounkou', 'Ibara', 'Mouyabi', 'Ondongo', 'Kaya', 'Makosso', 'Tati', 'Bouiti', 'Ndinga'];

    public function run(): void
    {
        mt_srand(20261014);

        $previousYear = AcademicYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-09-15',
            'end_date' => '2026-06-30',
            'status' => AcademicYear::STATUS_CLOSED,
            'is_current' => false,
        ]);

        $currentYear = AcademicYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-09-15',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_ACTIVE,
            'is_current' => true,
        ]);

        $this->seedPeriods($previousYear, true);
        $periods = $this->seedPeriods($currentYear, false);
        $period1 = $periods[0];

        $cycles = $this->seedCyclesAndLevels();
        $subjects = $this->seedSubjects();

        $teachers = $this->seedTeachers();

        $classes = $this->seedClasses($currentYear, $cycles['levels'], $teachers);
        $previousClasses = $this->seedPreviousYearClasses($previousYear, $cycles['levels'], $teachers);

        $this->seedClassSubjects($classes, $subjects, $cycles['levels']);
        $this->seedClassSubjects($previousClasses, $subjects, $cycles['levels']);

        $this->seedAssignments($classes, $subjects, $teachers, $currentYear);
        $this->seedAssignments($previousClasses, $subjects, $teachers, $previousYear);

        $students = $this->seedStudents($classes, $currentYear, $previousClasses, $previousYear);

        $this->seedEvaluationsAndGrades($classes, $period1, $teachers);

        $this->seedAttendance($classes, $teachers);

        $this->command->info(sprintf(
            'Données de démonstration EPSV créées : %d élèves, %d classes, %d notes.',
            $students->count(),
            $classes->count(),
            Grade::count(),
        ));
    }

    /** @return array<int, SchoolPeriod> */
    private function seedPeriods(AcademicYear $year, bool $locked): array
    {
        $definitions = [
            ['1er Trimestre', 1, '2026-09-15', '2026-12-19'],
            ['2ème Trimestre', 2, '2027-01-05', '2027-03-27'],
            ['3ème Trimestre', 3, '2027-04-06', '2027-06-25'],
        ];

        if ($locked) {
            $definitions = [
                ['1er Trimestre', 1, '2025-09-15', '2025-12-19'],
                ['2ème Trimestre', 2, '2026-01-05', '2026-03-27'],
                ['3ème Trimestre', 3, '2026-04-06', '2026-06-25'],
            ];
        }

        $periods = [];

        foreach ($definitions as [$name, $sequence, $start, $end]) {
            $periods[] = SchoolPeriod::create([
                'academic_year_id' => $year->id,
                'name' => $name,
                'sequence' => $sequence,
                'type' => 'trimestre',
                'start_date' => $start,
                'end_date' => $end,
                'weight' => 1,
                'status' => $locked ? SchoolPeriod::STATUS_LOCKED : SchoolPeriod::STATUS_OPEN,
                'validated_at' => $locked ? now()->subMonths(6) : null,
                'locked_at' => $locked ? now()->subMonths(6) : null,
            ]);
        }

        return $periods;
    }

    /** @return array{cycles: array<string, Cycle>, levels: array<string, GradeLevel>} */
    private function seedCyclesAndLevels(): array
    {
        $primaire = Cycle::create(['name' => 'Primaire', 'code' => 'PRIM', 'sequence' => 1]);
        $college = Cycle::create(['name' => 'Secondaire — Collège', 'code' => 'COLL', 'sequence' => 2]);

        $levelDefs = [
            ['CP1', 'Cours Préparatoire 1', $primaire, 1],
            ['CP2', 'Cours Préparatoire 2', $primaire, 2],
            ['CE1', 'Cours Élémentaire 1', $primaire, 3],
            ['CE2', 'Cours Élémentaire 2', $primaire, 4],
            ['CM1', 'Cours Moyen 1', $primaire, 5],
            ['CM2', 'Cours Moyen 2', $primaire, 6],
            ['6EME', 'Sixième', $college, 7],
            ['5EME', 'Cinquième', $college, 8],
            ['4EME', 'Quatrième', $college, 9],
            ['3EME', 'Troisième', $college, 10],
        ];

        $levels = [];

        foreach ($levelDefs as [$code, $name, $cycle, $sequence]) {
            $levels[$code] = GradeLevel::create([
                'cycle_id' => $cycle->id,
                'name' => $name,
                'code' => $code,
                'sequence' => $sequence,
            ]);
        }

        return ['cycles' => ['primaire' => $primaire, 'college' => $college], 'levels' => $levels];
    }

    /** @return array<string, Subject> */
    private function seedSubjects(): array
    {
        $definitions = [
            'FR' => 'Français',
            'MATH' => 'Mathématiques',
            'SVT' => 'Sciences de la Vie et de la Terre',
            'HG' => 'Histoire-Géographie',
            'ANG' => 'Anglais',
            'ECM' => 'Éducation Civique et Morale',
            'EPS' => 'Éducation Physique et Sportive',
            'INFO' => 'Informatique',
        ];

        $subjects = [];

        foreach ($definitions as $code => $name) {
            $subjects[$code] = Subject::create(['name' => $name, 'code' => $code, 'is_active' => true]);
        }

        return $subjects;
    }

    /** @return array<string, User> */
    private function seedTeachers(): array
    {
        $password = config('epsv.demo_password');

        $definitions = [
            'bakala' => ['Sylvie Bakala', 'prof.bakala@epsv.cg', 'Professeure de SVT'],
            'malonga' => ['André Malonga', 'prof.malonga@epsv.cg', 'Professeur d\'anglais et EPS'],
        ];

        $teachers = [];

        foreach ($definitions as $key => [$name, $email, $jobTitle]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'job_title' => $jobTitle,
                    'password' => Hash::make($password),
                    'is_active' => true,
                ],
            );

            $user->assignRole(\App\Support\Roles::PROFESSEUR);

            $teachers[$key] = $user;
        }

        foreach (['prof.mabiala@epsv.cg' => 'mabiala', 'prof.kimbembe@epsv.cg' => 'kimbembe', 'prof.ngoma@epsv.cg' => 'ngoma'] as $email => $key) {
            $teachers[$key] = User::where('email', $email)->firstOrFail();
        }

        return $teachers;
    }

    /**
     * @param  array<string, GradeLevel>  $levels
     * @param  array<string, User>  $teachers
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function seedClasses(AcademicYear $year, array $levels, array $teachers): \Illuminate\Support\Collection
    {
        $definitions = [
            ['CM2 A', 'CM2', 'kimbembe', 15],
            ['CM2 B', 'CM2', 'bakala', 12],
            ['6ème A', '6EME', 'mabiala', 18],
            ['6ème B', '6EME', 'malonga', 16],
            ['5ème A', '5EME', 'ngoma', 14],
            ['4ème A', '4EME', 'bakala', 12],
            ['3ème A', '3EME', 'mabiala', 10],
        ];

        $classes = collect();

        foreach ($definitions as [$name, $levelCode, $teacherKey, $capacity]) {
            $classes->push(SchoolClass::create([
                'academic_year_id' => $year->id,
                'grade_level_id' => $levels[$levelCode]->id,
                'name' => $name,
                'capacity' => $capacity + 5,
                'main_teacher_id' => $teachers[$teacherKey]->id,
                'room' => 'Salle '.fake()->numberBetween(1, 12),
                'is_active' => true,
            ]));
        }

        return $classes;
    }

    /**
     * @param  array<string, GradeLevel>  $levels
     * @param  array<string, User>  $teachers
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    private function seedPreviousYearClasses(AcademicYear $year, array $levels, array $teachers): \Illuminate\Support\Collection
    {
        $definitions = [
            ['CM2 A', 'CM2', 'kimbembe'],
            ['6ème A', '6EME', 'mabiala'],
            ['5ème A', '5EME', 'ngoma'],
            ['4ème A', '4EME', 'bakala'],
            ['3ème A', '3EME', 'malonga'],
        ];

        $classes = collect();

        foreach ($definitions as [$name, $levelCode, $teacherKey]) {
            $classes->push(SchoolClass::create([
                'academic_year_id' => $year->id,
                'grade_level_id' => $levels[$levelCode]->id,
                'name' => $name,
                'capacity' => 50,
                'main_teacher_id' => $teachers[$teacherKey]->id,
                'is_active' => true,
            ]));
        }

        return $classes;
    }

    private function seedClassSubjects(\Illuminate\Support\Collection $classes, array $subjects, array $levels): void
    {
        $coefficients = [
            'FR' => 4, 'MATH' => 4, 'SVT' => 2, 'HG' => 2,
            'ANG' => 2, 'ECM' => 1, 'EPS' => 1, 'INFO' => 1,
        ];

        foreach ($classes as $class) {
            $isPrimary = $class->gradeLevel->cycle->code === 'PRIM';

            foreach ($coefficients as $code => $coefficient) {
                if ($isPrimary && in_array($code, ['INFO'], true)) {
                    continue;
                }

                ClassSubject::create([
                    'class_id' => $class->id,
                    'subject_id' => $subjects[$code]->id,
                    'coefficient' => $coefficient,
                    'max_score' => 20,
                    'is_graded' => true,
                ]);
            }
        }
    }

    private function seedAssignments(\Illuminate\Support\Collection $classes, array $subjects, array $teachers, AcademicYear $year): void
    {
        $mapping = [
            'FR' => 'kimbembe',
            'MATH' => 'mabiala',
            'SVT' => 'bakala',
            'HG' => 'ngoma',
            'ANG' => 'malonga',
            'ECM' => 'ngoma',
            'EPS' => 'malonga',
            'INFO' => 'mabiala',
        ];

        foreach ($classes as $class) {
            foreach ($class->classSubjects as $classSubject) {
                $subjectCode = $classSubject->subject->code;
                $teacherKey = $mapping[$subjectCode] ?? 'mabiala';

                TeacherAssignment::firstOrCreate([
                    'user_id' => $teachers[$teacherKey]->id,
                    'class_id' => $class->id,
                    'subject_id' => $classSubject->subject_id,
                    'academic_year_id' => $year->id,
                ]);
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Student>
     */
    private function seedStudents(
        \Illuminate\Support\Collection $classes,
        AcademicYear $year,
        \Illuminate\Support\Collection $previousClasses,
        AcademicYear $previousYear,
    ): \Illuminate\Support\Collection {
        $generator = app(MatriculeGenerator::class);

        $counts = ['CM2 A' => 15, 'CM2 B' => 12, '6ème A' => 18, '6ème B' => 16, '5ème A' => 14, '4ème A' => 12, '3ème A' => 10];

        // Correspondance classe actuelle → classe de l'année précédente (niveau d'avant).
        $previousMapping = [
            'CM2 A' => 'CM2 A',
            'CM2 B' => 'CM2 A',
            '6ème A' => 'CM2 A',
            '6ème B' => 'CM2 A',
            '5ème A' => '6ème A',
            '4ème A' => '5ème A',
            '3ème A' => '4ème A',
        ];

        $students = collect();

        foreach ($classes as $class) {
            $count = $counts[$class->name] ?? 10;
            $ageBase = match ($class->gradeLevel->code) {
                'CM2' => 11, '6EME' => 12, '5EME' => 13, '4EME' => 14, '3EME' => 15, default => 10,
            };

            for ($i = 0; $i < $count; $i++) {
                $gender = mt_rand(0, 1) === 0 ? 'M' : 'F';
                $firstName = $gender === 'M'
                    ? $this->firstNamesM[array_rand($this->firstNamesM)]
                    : $this->firstNamesF[array_rand($this->firstNamesF)];

                $student = Student::create([
                    'matricule' => $generator->next('2026'),
                    'first_name' => $firstName,
                    'last_name' => $this->lastNames[array_rand($this->lastNames)],
                    'gender' => $gender,
                    'birth_date' => now()->subYears($ageBase)->subDays(mt_rand(0, 360))->toDateString(),
                    'birth_place' => fake()->randomElement(['Brazzaville', 'Pointe-Noire', 'Dolisie', 'Owando']),
                    'nationality' => 'Congolaise',
                    'status' => Student::STATUS_ACTIVE,
                ]);

                $guardianCount = mt_rand(0, 9) < 8 ? 1 : 2;

                for ($g = 0; $g < $guardianCount; $g++) {
                    $guardian = Guardian::create([
                        'full_name' => ($g === 0 ? 'M./Mme ' : '').$this->lastNames[array_rand($this->lastNames)].' '.($gender === 'M' ? $this->firstNamesM[array_rand($this->firstNamesM)] : $this->firstNamesF[array_rand($this->firstNamesF)]),
                        'phone' => '+242 06 '.mt_rand(100, 999).' '.mt_rand(10, 99).' '.mt_rand(10, 99),
                        'profession' => fake()->randomElement(['Commerçant(e)', 'Enseignant(e)', 'Infirmier(ère)', 'Fonctionnaire', 'Ménagère', 'Ingénieur(e)']),
                    ]);

                    $student->guardians()->attach($guardian->id, [
                        'relationship' => $g === 0 ? ($gender === 'M' ? 'père' : 'mère') : 'tuteur',
                        'is_primary' => $g === 0,
                        'is_emergency_contact' => $g === 0,
                    ]);
                }

                Enrollment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'class_id' => $class->id,
                    'enrolled_on' => '2026-09-15',
                    'status' => Enrollment::STATUS_ACTIVE,
                ]);

                $previousClass = $previousClasses->firstWhere('name', $previousMapping[$class->name] ?? null);

                if ($previousClass) {
                    Enrollment::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $previousYear->id,
                        'class_id' => $previousClass->id,
                        'enrolled_on' => '2025-09-15',
                        'status' => Enrollment::STATUS_ACTIVE,
                    ]);
                }

                $students->push($student);
            }
        }

        return $students;
    }

    private function seedEvaluationsAndGrades(\Illuminate\Support\Collection $classes, SchoolPeriod $period, array $teachers): void
    {
        $teacherBySubject = [
            'FR' => 'kimbembe', 'MATH' => 'mabiala', 'SVT' => 'bakala',
            'HG' => 'ngoma', 'ANG' => 'malonga', 'ECM' => 'ngoma',
            'EPS' => 'malonga', 'INFO' => 'mabiala',
        ];

        foreach ($classes as $class) {
            $studentIds = $class->enrollments()
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->pluck('student_id');

            foreach ($class->classSubjects as $classSubject) {
                $code = $classSubject->subject->code;

                $definitions = [
                    ['Devoir n°1', 'devoir', 1, now()->subWeeks(3)->toDateString()],
                    ['Composition n°1', 'composition', 2, now()->subWeek()->toDateString()],
                ];

                foreach ($definitions as [$name, $type, $coefficient, $date]) {
                    $evaluation = Evaluation::create([
                        'class_subject_id' => $classSubject->id,
                        'school_period_id' => $period->id,
                        'name' => $name,
                        'type' => $type,
                        'evaluation_date' => $date,
                        'max_score' => 20,
                        'coefficient' => $coefficient,
                        'status' => Evaluation::STATUS_PUBLISHED,
                        'published_at' => now()->subDays(2),
                        'created_by' => $teachers[$teacherBySubject[$code] ?? 'mabiala']->id,
                    ]);

                    foreach ($studentIds as $studentId) {
                        $roll = mt_rand(1, 100);

                        if ($roll <= 4) {
                            $status = Grade::STATUS_ABSENT;
                            $value = null;
                        } elseif ($roll <= 6) {
                            $status = Grade::STATUS_EXEMPT;
                            $value = null;
                        } elseif ($roll <= 9) {
                            $status = Grade::STATUS_MISSING;
                            $value = null;
                        } else {
                            $status = Grade::STATUS_ENTERED;
                            $value = min(20, max(2, mt_rand(60, 190) / 10));
                        }

                        Grade::create([
                            'evaluation_id' => $evaluation->id,
                            'student_id' => $studentId,
                            'value' => $value,
                            'status' => $status,
                            'entered_by' => $status === Grade::STATUS_ENTERED ? $teachers[$teacherBySubject[$code] ?? 'mabiala']->id : null,
                            'entered_at' => $status === Grade::STATUS_ENTERED ? now()->subDays(3) : null,
                        ]);
                    }
                }
            }
        }
    }

    private function seedAttendance(\Illuminate\Support\Collection $classes, array $teachers): void
    {
        $dates = collect(range(0, 13))
            ->map(fn ($i) => now()->subDays($i))
            ->filter(fn ($d) => ! $d->isWeekend())
            ->take(10);

        foreach ($classes as $class) {
            $studentIds = $class->enrollments()
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->pluck('student_id');

            $takenBy = $class->main_teacher_id ?? $teachers['mabiala']->id;

            foreach ($dates as $date) {
                $session = AttendanceSession::create([
                    'class_id' => $class->id,
                    'session_date' => $date->toDateString(),
                    'slot' => AttendanceSession::SLOT_MORNING,
                    'taken_by' => $takenBy,
                ]);

                foreach ($studentIds as $studentId) {
                    $roll = mt_rand(1, 100);

                    if ($roll <= 4) {
                        $status = AttendanceRecord::STATUS_ABSENT;
                    } elseif ($roll <= 7) {
                        $status = AttendanceRecord::STATUS_LATE;
                    } elseif ($roll <= 9) {
                        $status = AttendanceRecord::STATUS_EXCUSED;
                    } else {
                        $status = AttendanceRecord::STATUS_PRESENT;
                    }

                    AttendanceRecord::create([
                        'attendance_session_id' => $session->id,
                        'student_id' => $studentId,
                        'status' => $status,
                        'minutes_late' => $status === AttendanceRecord::STATUS_LATE ? mt_rand(5, 45) : null,
                        'justification' => in_array($status, [AttendanceRecord::STATUS_EXCUSED], true) ? 'Certificat médical' : null,
                        'justified_by' => $status === AttendanceRecord::STATUS_EXCUSED ? $takenBy : null,
                        'justified_at' => $status === AttendanceRecord::STATUS_EXCUSED ? now() : null,
                    ]);
                }
            }
        }
    }
}
