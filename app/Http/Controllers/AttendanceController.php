<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    /** Page d'accueil des présences : choix de classe + séances récentes. */
    public function index(Request $request)
    {
        $user = $request->user();
        $seesAll = $user->can('structure.view');
        $year = AcademicYear::currentYear();

        $classes = SchoolClass::query()
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(! $seesAll, fn ($q) => $q->whereIn('id', $user->assignedClassIds($year?->id)))
            ->with('gradeLevel')
            ->withCount('activeEnrollments')
            ->orderBy('name')
            ->get();

        $sessions = AttendanceSession::query()
            ->when(! $seesAll, fn ($q) => $q->whereIn('class_id', $classes->pluck('id')))
            ->with(['schoolClass.gradeLevel', 'takenBy'])
            ->withCount([
                'records as absences_count' => fn ($q) => $q->whereIn('status', [AttendanceRecord::STATUS_ABSENT, AttendanceRecord::STATUS_EXCUSED]),
                'records as lates_count' => fn ($q) => $q->where('status', AttendanceRecord::STATUS_LATE),
            ])
            ->latest('session_date')
            ->latest('id')
            ->paginate(15);

        return view('attendance.index', compact('classes', 'sessions', 'year'));
    }

    /** Écran d'appel rapide : crée la séance si besoin puis affiche la grille. */
    public function take(Request $request, SchoolClass $schoolClass)
    {
        $this->authorizeClass($request, $schoolClass);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'slot' => ['required', Rule::in([AttendanceSession::SLOT_MORNING, AttendanceSession::SLOT_AFTERNOON, AttendanceSession::SLOT_FULLDAY])],
        ]);

        $session = $this->service->openSession($schoolClass, $data['date'], $data['slot'], $request->user());

        return redirect()->route('attendance.session', $session);
    }

    /** Grille d'appel / consultation d'une séance. */
    public function show(Request $request, AttendanceSession $session)
    {
        $this->authorizeClass($request, $session->schoolClass);

        $session->load(['schoolClass.gradeLevel', 'takenBy']);

        $students = $session->schoolClass->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name)
            ->values();

        $records = $session->records()->get()->keyBy('student_id');

        $canEdit = $request->user()->can('attendance.take') && ! $session->session_date->isFuture();

        return view('attendance.session', compact('session', 'students', 'records', 'canEdit'));
    }

    /** Enregistre l'appel complet. */
    public function store(Request $request, AttendanceSession $session)
    {
        $this->authorizeClass($request, $session->schoolClass);

        $data = $request->validate([
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'records.*.minutes_late' => ['nullable', 'integer', 'min:1', 'max:600'],
            'records.*.justification' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->saveCall($session, $data['records'], $request->user());

        return redirect()->route('attendance.session', $session)
            ->with('success', 'Appel enregistré.');
    }

    /** Rapport de présences par classe sur une période. */
    public function report(Request $request, SchoolClass $schoolClass)
    {
        $this->authorizeClass($request, $schoolClass);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        $students = $schoolClass->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name)
            ->values();

        $records = AttendanceRecord::query()
            ->whereHas('session', fn ($q) => $q->where('class_id', $schoolClass->id)
                ->whereDate('session_date', '>=', $from)
                ->whereDate('session_date', '<=', $to))
            ->get()
            ->groupBy('student_id');

        $rows = $students->map(function ($enrollment) use ($records) {
            $studentRecords = $records->get($enrollment->student_id, collect());
            $total = $studentRecords->count();

            return [
                'student' => $enrollment->student,
                'total' => $total,
                'present' => $studentRecords->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
                'absent' => $studentRecords->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
                'late' => $studentRecords->where('status', AttendanceRecord::STATUS_LATE)->count(),
                'excused' => $studentRecords->where('status', AttendanceRecord::STATUS_EXCUSED)->count(),
                'rate' => $total > 0
                    ? round(($studentRecords->whereIn('status', [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE])->count()) / $total * 100, 1)
                    : null,
            ];
        });

        return view('attendance.report', compact('schoolClass', 'rows', 'from', 'to'));
    }

    private function authorizeClass(Request $request, SchoolClass $schoolClass): void
    {
        $user = $request->user();

        if ($user->can('structure.view')) {
            return;
        }

        abort_unless(in_array($schoolClass->id, $user->assignedClassIds($schoolClass->academic_year_id), true), 403);
    }
}
