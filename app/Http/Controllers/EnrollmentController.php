<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $year = AcademicYear::find($request->integer('year_id')) ?? AcademicYear::currentYear();

        $enrollments = Enrollment::query()
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->with(['student', 'schoolClass.gradeLevel'])
            ->orderByDesc('enrolled_on')
            ->paginate(50)
            ->withQueryString();

        $years = AcademicYear::orderByDesc('start_date')->get();
        $classes = $year
            ? SchoolClass::where('academic_year_id', $year->id)->with('gradeLevel')->orderBy('name')->get()
            : collect();

        return view('students.enrollments', compact('enrollments', 'years', 'classes', 'year'));
    }

    /** Inscrit un élève dans une classe pour une année (une seule inscription par année). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'enrolled_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);

        $existing = Enrollment::where('student_id', $data['student_id'])
            ->where('academic_year_id', $class->academic_year_id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'student_id' => 'Cet élève a déjà une inscription pour l\'année '.$class->academicYear->name.'.',
            ]);
        }

        if ($class->capacity && $class->activeEnrollments()->count() >= $class->capacity) {
            throw ValidationException::withMessages([
                'class_id' => 'La classe est pleine (capacité : '.$class->capacity.').',
            ]);
        }

        $enrollment = Enrollment::create($data + [
            'academic_year_id' => $class->academic_year_id,
            'status' => Enrollment::STATUS_ACTIVE,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log('enrollment.created', $enrollment, [], $data);

        return back()->with('success', 'Élève inscrit avec succès.');
    }

    /** Changement de classe ou retrait (départ / transfert). */
    public function update(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'status' => ['required', Rule::in([Enrollment::STATUS_ACTIVE, Enrollment::STATUS_LEFT, Enrollment::STATUS_TRANSFERRED])],
            'left_on' => ['nullable', 'date'],
            'left_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $enrollment->only(array_keys($data));

        $enrollment->update($data);

        $this->audit->log('enrollment.updated', $enrollment, $old, $data);

        return back()->with('success', 'Inscription mise à jour.');
    }

    public function destroy(Enrollment $enrollment)
    {
        $this->audit->log('enrollment.deleted', null, $enrollment->toArray(), []);
        $enrollment->delete();

        return back()->with('success', 'Inscription supprimée.');
    }
}
