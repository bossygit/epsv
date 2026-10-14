<?php

namespace App\Http\Controllers;

use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Services\Grading\GradeEntryService;
use Illuminate\Http\Request;

/**
 * Saisie des notes en grille pour une évaluation.
 */
class GradeEntryController extends Controller
{
    public function __construct(private readonly GradeEntryService $service) {}

    public function show(Request $request, Evaluation $evaluation)
    {
        $this->authorizeEvaluation($request, $evaluation);

        $evaluation->load(['classSubject.subject', 'classSubject.schoolClass.gradeLevel', 'period']);

        $students = $evaluation->classSubject->schoolClass->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name)
            ->values();

        $grades = $evaluation->grades()->get()->keyBy('student_id');

        $period = $evaluation->period;
        $canEdit = ! $period->isValidated() || $request->user()->can('grades.correct');

        $missingCount = $grades->filter(fn ($g) => $g->status === Grade::STATUS_MISSING)->count()
            + max(0, $students->count() - $grades->count());

        return view('grades.entry', compact('evaluation', 'students', 'grades', 'canEdit', 'missingCount'));
    }

    public function store(Request $request, Evaluation $evaluation)
    {
        $this->authorizeEvaluation($request, $evaluation);

        $data = $request->validate([
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.value' => ['nullable', 'numeric', 'min:0'],
            'records.*.status' => ['nullable', 'in:entered,missing,absent,exempt'],
            'records.*.comment' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);

        $this->service->save($evaluation, $data['records'], $request->user(), $data['reason'] ?? null);

        return redirect()->route('grades.entry', $evaluation)->with('success', 'Notes enregistrées.');
    }

    private function authorizeEvaluation(Request $request, Evaluation $evaluation): void
    {
        $user = $request->user();

        if ($user->can('structure.manage')) {
            return;
        }

        $classSubject = $evaluation->classSubject;

        $assigned = $user->teacherAssignments()
            ->where('class_id', $classSubject->class_id)
            ->where('subject_id', $classSubject->subject_id)
            ->exists();

        abort_unless($assigned, 403, 'Vous n\'êtes pas affecté à cette matière dans cette classe.');
    }
}
