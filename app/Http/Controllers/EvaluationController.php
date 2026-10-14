<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Evaluation;
use App\Models\SchoolClass;
use App\Models\SchoolPeriod;
use App\Services\Grading\GradeEntryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvaluationController extends Controller
{
    public function __construct(private readonly GradeEntryService $grades) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $seesAll = $user->can('structure.view');
        $year = AcademicYear::currentYear();

        $classes = SchoolClass::query()
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(! $seesAll, fn ($q) => $q->whereIn('id', $user->assignedClassIds($year?->id)))
            ->with('gradeLevel')
            ->orderBy('name')
            ->get();

        $periods = $year?->periods ?? collect();

        $selectedClass = $request->filled('class_id')
            ? $classes->firstWhere('id', $request->integer('class_id'))
            : $classes->first();

        $selectedPeriod = $request->filled('period_id')
            ? $periods->firstWhere('id', $request->integer('period_id'))
            : $periods->firstWhere('status', SchoolPeriod::STATUS_OPEN) ?? $periods->first();

        $evaluations = Evaluation::query()
            ->when($selectedClass, fn ($q) => $q->whereHas('classSubject', fn ($qq) => $qq->where('class_id', $selectedClass->id)))
            ->when($selectedPeriod, fn ($q) => $q->where('school_period_id', $selectedPeriod->id))
            ->with(['classSubject.subject', 'period', 'creator'])
            ->withCount([
                'grades',
                'grades as entered_count' => fn ($q) => $q->where('status', \App\Models\Grade::STATUS_ENTERED),
                'grades as missing_count' => fn ($q) => $q->where('status', \App\Models\Grade::STATUS_MISSING),
            ])
            ->orderByDesc('evaluation_date')
            ->get();

        return view('grades.evaluations.index', compact('classes', 'periods', 'evaluations', 'selectedClass', 'selectedPeriod', 'seesAll'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $seesAll = $user->can('structure.view');
        $year = AcademicYear::currentYear();

        $classes = SchoolClass::query()
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(! $seesAll, fn ($q) => $q->whereIn('id', $user->assignedClassIds($year?->id)))
            ->with('gradeLevel')
            ->orderBy('name')
            ->get();

        $periods = $year?->periods ?? collect();

        $classSubjects = ClassSubject::query()
            ->whereIn('class_id', $classes->pluck('id'))
            ->where('is_graded', true)
            ->with('subject')
            ->get();

        $selectedClassId = $request->integer('class_id') ?: null;
        $selectedPeriodId = $request->integer('period_id') ?: null;

        return view('grades.evaluations.create', compact('classes', 'periods', 'classSubjects', 'selectedClassId', 'selectedPeriodId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_subject_id' => ['required', 'exists:class_subjects,id'],
            'school_period_id' => ['required', 'exists:school_periods,id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['devoir', 'composition', 'interrogation', 'autre'])],
            'evaluation_date' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'coefficient' => ['required', 'numeric', 'min:0.5', 'max:10'],
        ]);

        $classSubject = ClassSubject::with('schoolClass')->findOrFail($data['class_subject_id']);
        $this->authorizeClassSubject($request, $classSubject);

        $period = SchoolPeriod::findOrFail($data['school_period_id']);

        if ($period->academic_year_id !== $classSubject->schoolClass->academic_year_id) {
            throw ValidationException::withMessages([
                'school_period_id' => 'La période choisie n\'appartient pas à l\'année de cette classe.',
            ]);
        }

        if ($period->isValidated()) {
            throw ValidationException::withMessages([
                'school_period_id' => 'Impossible de créer une évaluation sur une période validée ou verrouillée.',
            ]);
        }

        $evaluation = Evaluation::create($data + [
            'status' => Evaluation::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('grades.entry', $evaluation)
            ->with('success', 'Évaluation créée. Saisissez les notes puis publiez-la.');
    }

    public function edit(Request $request, Evaluation $evaluation)
    {
        $this->authorizeClassSubject($request, $evaluation->classSubject);

        if ($evaluation->period->isValidated()) {
            return redirect()->route('grades.entry', $evaluation)
                ->withErrors(['status' => 'Période validée : cette évaluation n\'est plus modifiable.']);
        }

        return view('grades.evaluations.edit', compact('evaluation'));
    }

    public function update(Request $request, Evaluation $evaluation)
    {
        $this->authorizeClassSubject($request, $evaluation->classSubject);

        if ($evaluation->period->isValidated()) {
            throw ValidationException::withMessages([
                'status' => 'Période validée : cette évaluation n\'est plus modifiable.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['devoir', 'composition', 'interrogation', 'autre'])],
            'evaluation_date' => ['required', 'date'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
            'coefficient' => ['required', 'numeric', 'min:0.5', 'max:10'],
        ]);

        $evaluation->update($data);

        return redirect()->route('grades.entry', $evaluation)->with('success', 'Évaluation mise à jour.');
    }

    public function publish(Request $request, Evaluation $evaluation)
    {
        $this->authorizeClassSubject($request, $evaluation->classSubject);

        $this->grades->publish($evaluation, $request->user());

        return back()->with('success', 'Évaluation publiée : elle entre désormais dans les calculs.');
    }

    public function cancel(Request $request, Evaluation $evaluation)
    {
        $this->authorizeClassSubject($request, $evaluation->classSubject);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->grades->cancel($evaluation, $request->user(), $data['reason']);

        return back()->with('success', 'Évaluation annulée : elle est exclue des calculs.');
    }

    public function destroy(Request $request, Evaluation $evaluation)
    {
        $this->authorizeClassSubject($request, $evaluation->classSubject);

        if ($evaluation->period->isValidated()) {
            throw ValidationException::withMessages([
                'status' => 'Période validée : suppression impossible.',
            ]);
        }

        if ($evaluation->grades()->where('status', '!=', \App\Models\Grade::STATUS_MISSING)->exists()) {
            return back()->withErrors(['evaluation' => 'Cette évaluation contient des notes : annulez-la plutôt que de la supprimer.']);
        }

        $evaluation->delete();

        return redirect()->route('evaluations.index')->with('success', 'Évaluation supprimée.');
    }

    private function authorizeClassSubject(Request $request, ClassSubject $classSubject): void
    {
        $user = $request->user();

        if ($user->can('structure.manage')) {
            return;
        }

        $assigned = $user->teacherAssignments()
            ->where('class_id', $classSubject->class_id)
            ->where('subject_id', $classSubject->subject_id)
            ->exists();

        abort_unless($assigned, 403, 'Vous n\'êtes pas affecté à cette matière dans cette classe.');
    }
}
