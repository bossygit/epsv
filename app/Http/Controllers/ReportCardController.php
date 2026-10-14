<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\SchoolPeriod;
use App\Models\Student;
use App\Services\Grading\GradeCalculator;
use App\Services\Grading\ReportCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportCardController extends Controller
{
    public function __construct(
        private readonly ReportCardService $service,
        private readonly GradeCalculator $calculator,
    ) {}

    /** Liste des élèves d'une classe avec l'état de leurs bulletins pour une période. */
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
            : $periods->first();

        $students = collect();
        $reportCards = collect();
        $stats = null;

        if ($selectedClass && $selectedPeriod) {
            $students = $selectedClass->enrollments()
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->with('student')
                ->get()
                ->sortBy(fn ($e) => $e->student->last_name)
                ->values();

            $reportCards = ReportCard::query()
                ->where('school_period_id', $selectedPeriod->id)
                ->whereIn('student_id', $students->pluck('student_id'))
                ->orderByDesc('version')
                ->get()
                ->groupBy('student_id')
                ->map(fn ($group) => $group->first());

            $stats = $this->calculator->classStats($selectedClass, $selectedPeriod);
        }

        return view('grades.report-cards.index', compact(
            'classes',
            'periods',
            'selectedClass',
            'selectedPeriod',
            'students',
            'reportCards',
            'stats',
            'seesAll',
        ));
    }

    /** Génère le bulletin d'un élève. */
    public function generate(Request $request, Student $student)
    {
        $data = $request->validate([
            'school_period_id' => ['required', 'exists:school_periods,id'],
        ]);

        $period = SchoolPeriod::findOrFail($data['school_period_id']);

        $reportCard = $this->service->generate($student, $period, $request->user());

        return redirect()->route('report-cards.show', $reportCard)
            ->with('success', "Bulletin de {$student->full_name} généré (version {$reportCard->version}).");
    }

    /** Génère les bulletins de toute une classe. */
    public function bulkGenerate(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'school_period_id' => ['required', 'exists:school_periods,id'],
        ]);

        $period = SchoolPeriod::findOrFail($data['school_period_id']);

        $students = $schoolClass->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->with('student')
            ->get()
            ->pluck('student');

        $generated = 0;
        $errors = [];

        foreach ($students as $student) {
            try {
                $this->service->generate($student, $period, $request->user());
                $generated++;
            } catch (\Throwable $e) {
                $errors[] = "{$student->full_name} : {$e->getMessage()}";
            }
        }

        $message = "{$generated} bulletin(s) généré(s) sur {$students->count()}.";
        $redirect = redirect()->route('report-cards.index', [
            'class_id' => $schoolClass->id,
            'period_id' => $period->id,
        ]);

        if ($errors) {
            return $redirect->with('success', $message)->withErrors(['bulk' => $errors]);
        }

        return $redirect->with('success', $message);
    }

    /** Aperçu HTML du bulletin (données recalculées). */
    public function show(Request $request, ReportCard $reportCard)
    {
        $reportCard->load(['student', 'period.academicYear']);

        $data = $this->service->buildData($reportCard->student, $reportCard->period);

        return view('grades.report-cards.show', [
            'reportCard' => $reportCard,
            'data' => $data,
            'profile' => \App\Models\SchoolProfile::current(),
            'config' => $this->calculator->config(),
        ]);
    }

    /** Téléchargement du PDF stocké (disque privé). */
    public function download(ReportCard $reportCard)
    {
        abort_unless($reportCard->file_path && Storage::disk('local')->exists($reportCard->file_path), 404, 'PDF non disponible.');

        $filename = sprintf(
            'bulletin-%s-%s-v%d.pdf',
            $reportCard->student->matricule,
            str_replace(' ', '-', $reportCard->period->name),
            $reportCard->version,
        );

        return Storage::disk('local')->download($reportCard->file_path, $filename);
    }

    public function validate(Request $request, ReportCard $reportCard)
    {
        $this->service->validate($reportCard, $request->user());

        return back()->with('success', 'Bulletin validé.');
    }

    public function lock(Request $request, ReportCard $reportCard)
    {
        $this->service->lock($reportCard, $request->user());

        return back()->with('success', 'Bulletin verrouillé : il ne pourra plus être modifié.');
    }

    /** Procès-verbal : classement complet de la classe pour une période. */
    public function classSummary(Request $request, SchoolClass $schoolClass)
    {
        $data = $request->validate([
            'school_period_id' => ['required', 'exists:school_periods,id'],
        ]);

        $period = SchoolPeriod::findOrFail($data['school_period_id']);

        $ranking = $this->calculator->classRanking($schoolClass, $period);
        $stats = $this->calculator->classStats($schoolClass, $period);

        $students = $schoolClass->enrollments()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->with('student')
            ->get()
            ->pluck('student')
            ->keyBy('id');

        $rows = collect($ranking)
            ->map(fn ($row, $studentId) => [
                'student' => $students[$studentId] ?? null,
                'average' => $row['average'],
                'rank' => $row['rank'],
                'complete' => $row['complete'],
            ])
            ->filter(fn ($row) => $row['student'] !== null)
            ->sortBy(fn ($row) => $row['rank'] ?? PHP_INT_MAX)
            ->values();

        return view('grades.report-cards.summary', compact('schoolClass', 'period', 'rows', 'stats'));
    }
}
