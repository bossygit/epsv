<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\MatriculeGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MatriculeGenerator $matricules,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $seesAll = $user->can('structure.view');
        $year = AcademicYear::currentYear();

        $query = Student::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                // Recherche insensible à la casse, portable MySQL/PostgreSQL/SQLite.
                $term = '%'.mb_strtolower(trim((string) $request->string('search'))).'%';
                $q->where(fn ($qq) => $qq
                    ->whereRaw('lower(first_name) like ?', [$term])
                    ->orWhereRaw('lower(last_name) like ?', [$term])
                    ->orWhereRaw('lower(matricule) like ?', [$term]));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('class_id'), fn ($q) => $q->whereHas('enrollments', fn ($qq) => $qq->where('class_id', $request->integer('class_id'))))
            ->when(! $seesAll, fn ($q) => $q->whereHas('enrollments', fn ($qq) => $qq
                ->where('academic_year_id', $year?->id)
                ->whereIn('class_id', $user->assignedClassIds($year?->id))))
            ->with(['enrollments' => fn ($q) => $q->when($year, fn ($qq) => $qq->where('academic_year_id', $year->id))->with('schoolClass.gradeLevel')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $classes = SchoolClass::query()
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->with('gradeLevel')
            ->orderBy('name')
            ->get();

        return view('students.index', compact('query', 'classes', 'year'));
    }

    public function create()
    {
        $year = AcademicYear::currentYear();
        $classes = $year
            ? SchoolClass::where('academic_year_id', $year->id)->with('gradeLevel')->orderBy('name')->get()
            : collect();

        return view('students.create', compact('classes', 'year'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'guardian_full_name' => ['nullable', 'string', 'max:150'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_phone_alt' => ['nullable', 'string', 'max:30'],
            'class_id' => ['nullable', 'exists:classes,id'],
        ]);

        $student = Student::create([
            'matricule' => $this->matricules->next(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'nationality' => $data['nationality'] ?? 'Congolaise',
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => Student::STATUS_ACTIVE,
        ]);

        if (! empty($data['guardian_full_name']) && ! empty($data['guardian_phone'])) {
            $guardian = Guardian::create([
                'full_name' => $data['guardian_full_name'],
                'phone' => $data['guardian_phone'],
                'phone_alt' => $data['guardian_phone_alt'] ?? null,
            ]);

            $student->guardians()->attach($guardian->id, [
                'relationship' => $data['guardian_relationship'] ?? 'tuteur',
                'is_primary' => true,
                'is_emergency_contact' => true,
            ]);
        }

        if (! empty($data['class_id'])) {
            $class = SchoolClass::find($data['class_id']);

            Enrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $class->academic_year_id,
                'class_id' => $class->id,
                'enrolled_on' => now()->toDateString(),
                'status' => Enrollment::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
            ]);
        }

        $this->audit->log('student.created', $student, [], $student->toArray());

        return redirect()->route('students.show', $student)
            ->with('success', "Élève inscrit : {$student->full_name} ({$student->matricule}).");
    }

    public function show(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);

        $student->load([
            'guardians',
            'enrollments.schoolClass.gradeLevel',
            'enrollments.academicYear',
        ]);

        $year = AcademicYear::currentYear();
        $currentEnrollment = $student->enrollmentFor($year);

        $attendanceSummary = $student->attendanceRecords()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentAbsences = $student->attendanceRecords()
            ->whereIn('status', ['absent', 'late'])
            ->with('session.schoolClass')
            ->latest('id')
            ->limit(10)
            ->get();

        $reportCards = $student->reportCards()
            ->with('period')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $classes = $year
            ? SchoolClass::where('academic_year_id', $year->id)->with('gradeLevel')->orderBy('name')->get()
            : collect();

        return view('students.show', compact(
            'student',
            'currentEnrollment',
            'attendanceSummary',
            'recentAbsences',
            'reportCards',
            'classes',
            'year',
        ));
    }

    public function edit(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);

        return view('students.edit', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Student::STATUS_ACTIVE, Student::STATUS_INACTIVE, Student::STATUS_TRANSFERRED, Student::STATUS_GRADUATED])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $student->only(array_keys($data));
        $student->update($data);

        $this->audit->log('student.updated', $student, $old, $data);

        return redirect()->route('students.show', $student)->with('success', 'Fiche élève mise à jour.');
    }

    /** Archivage (suppression douce) : jamais de suppression destructive. */
    public function archive(Request $request, Student $student)
    {
        $student->update(['status' => Student::STATUS_INACTIVE]);
        $student->delete();

        $this->audit->log('student.archived', null, $student->toArray(), [], null, $request->user());

        return redirect()->route('students.index')->with('success', 'Élève archivé. Les données restent consultables dans l\'historique.');
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        $user = $request->user();

        if ($user->can('structure.view')) {
            return;
        }

        $year = AcademicYear::currentYear();
        $classIds = $user->assignedClassIds($year?->id);

        abort_unless(
            $student->enrollments()->when($year, fn ($q) => $q->where('academic_year_id', $year->id))->whereIn('class_id', $classIds)->exists(),
            403,
        );
    }
}
