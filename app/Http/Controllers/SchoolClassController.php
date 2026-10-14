<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolClassController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $seesAll = $user->can('structure.view');

        $year = AcademicYear::currentYear();

        $classes = SchoolClass::query()
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(! $seesAll, fn ($q) => $q->whereIn('id', $user->assignedClassIds($year?->id)))
            ->with(['gradeLevel.cycle', 'mainTeacher'])
            ->withCount(['activeEnrollments'])
            ->orderBy('name')
            ->get()
            ->sortBy(fn ($c) => [$c->gradeLevel?->sequence ?? 0, $c->name]);

        $academicYears = AcademicYear::orderByDesc('start_date')->get();
        $levels = GradeLevel::with('cycle')->orderBy('sequence')->get();

        // Enseignants proposés comme professeur titulaire d'une classe.
        $teachers = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [\App\Support\Roles::PROFESSEUR, \App\Support\Roles::DIRECTION]))
            ->orderBy('name')
            ->get();

        return view('structure.classes.index', compact('classes', 'year', 'seesAll', 'academicYears', 'levels', 'teachers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('classes', 'name')->where('academic_year_id', $request->input('academic_year_id')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'main_teacher_id' => ['nullable', 'exists:users,id'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        $class = SchoolClass::create($data + ['is_active' => true]);

        $this->audit->log('class.created', $class, [], $data);

        return back()->with('success', "Classe « {$class->name} » créée.");
    }

    public function show(Request $request, SchoolClass $schoolClass)
    {
        $this->authorizeClassView($request, $schoolClass);

        $schoolClass->load([
            'academicYear',
            'gradeLevel.cycle',
            'mainTeacher',
            'classSubjects.subject',
            'teacherAssignments.subject',
            'teacherAssignments.teacher',
        ]);

        $students = $schoolClass->enrollments()
            ->where('status', \App\Models\Enrollment::STATUS_ACTIVE)
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name)
            ->values();

        $subjects = \App\Models\Subject::where('is_active', true)->orderBy('name')->get();
        $teachers = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [\App\Support\Roles::PROFESSEUR, \App\Support\Roles::DIRECTION]))
            ->orderBy('name')
            ->get();

        $periods = $schoolClass->academicYear->periods;

        $availableStudents = Student::active()
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('academic_year_id', $schoolClass->academic_year_id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('structure.classes.show', compact('schoolClass', 'students', 'subjects', 'teachers', 'periods', 'availableStudents'));
    }

    public function update(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('classes', 'name')
                    ->where('academic_year_id', $schoolClass->academic_year_id)
                    ->ignore($schoolClass->id),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'main_teacher_id' => ['nullable', 'exists:users,id'],
            'room' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $old = $schoolClass->only(array_keys($data));
        $schoolClass->update($data);

        $this->audit->log('class.updated', $schoolClass, $old, $data);

        return back()->with('success', 'Classe mise à jour.');
    }

    public function destroy(SchoolClass $schoolClass)
    {
        if ($schoolClass->enrollments()->exists()) {
            return back()->withErrors(['class' => 'Impossible de supprimer une classe qui contient des inscriptions.']);
        }

        $this->audit->log('class.deleted', null, $schoolClass->toArray(), []);
        $schoolClass->delete();

        return back()->with('success', 'Classe supprimée.');
    }

    /** Ajoute une matière à la classe (coefficient + barème). */
    public function storeSubject(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'subject_id' => [
                'required', 'exists:subjects,id',
                Rule::unique('class_subjects', 'subject_id')->where('class_id', $schoolClass->id),
            ],
            'coefficient' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'is_graded' => ['boolean'],
        ]);

        $schoolClass->classSubjects()->create($data + ['is_graded' => $request->boolean('is_graded', true)]);

        $this->audit->log('class_subject.created', $schoolClass, [], $data);

        return back()->with('success', 'Matière ajoutée à la classe.');
    }

    public function updateSubject(Request $request, SchoolClass $schoolClass, \App\Models\ClassSubject $classSubject)
    {
        abort_unless($classSubject->class_id === $schoolClass->id, 404);

        $data = $request->validate([
            'coefficient' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'is_graded' => ['boolean'],
        ]);

        $old = $classSubject->only(array_keys($data));
        $classSubject->update($data);

        $this->audit->log('class_subject.updated', $classSubject, $old, $data);

        return back()->with('success', 'Paramètres de la matière mis à jour.');
    }

    public function destroySubject(SchoolClass $schoolClass, \App\Models\ClassSubject $classSubject)
    {
        abort_unless($classSubject->class_id === $schoolClass->id, 404);

        if ($classSubject->evaluations()->exists()) {
            return back()->withErrors(['subject' => 'Impossible de retirer une matière qui a des évaluations.']);
        }

        $classSubject->delete();

        return back()->with('success', 'Matière retirée de la classe.');
    }

    /** Affecte un professeur à une matière de la classe. */
    public function storeAssignment(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        TeacherAssignment::firstOrCreate([
            'user_id' => $data['user_id'],
            'class_id' => $schoolClass->id,
            'subject_id' => $data['subject_id'],
            'academic_year_id' => $schoolClass->academic_year_id,
        ]);

        $this->audit->log('teacher_assignment.created', $schoolClass, [], $data);

        return back()->with('success', 'Affectation enregistrée.');
    }

    public function destroyAssignment(SchoolClass $schoolClass, TeacherAssignment $assignment)
    {
        abort_unless($assignment->class_id === $schoolClass->id, 404);

        $assignment->delete();

        $this->audit->log('teacher_assignment.deleted', $schoolClass, $assignment->toArray(), []);

        return back()->with('success', 'Affectation supprimée.');
    }

    private function authorizeClassView(Request $request, SchoolClass $schoolClass): void
    {
        $user = $request->user();

        if ($user->can('structure.view')) {
            return;
        }

        abort_unless(in_array($schoolClass->id, $user->assignedClassIds($schoolClass->academic_year_id), true), 403);
    }
}
