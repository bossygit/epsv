<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\SchoolPeriod;
use App\Services\Grading\GradeCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord : effectifs, présences, résultats, alertes.
 * La direction voit l'ensemble ; un professeur ne voit que ses classes.
 */
class DashboardController extends Controller
{
    public function index(Request $request, GradeCalculator $calculator)
    {
        $user = $request->user();
        $year = AcademicYear::currentYear();
        $period = $year?->currentPeriod();
        $seesAll = $user->can('structure.view');

        $classIds = $seesAll ? null : $user->assignedClassIds($year?->id);

        // Effectifs
        $enrollments = Enrollment::query()
            ->where('enrollments.status', Enrollment::STATUS_ACTIVE)
            ->when($year, fn ($q) => $q->where('enrollments.academic_year_id', $year->id))
            ->when(! $seesAll, fn ($q) => $q->whereIn('enrollments.class_id', $classIds ?? []));

        $totalStudents = (clone $enrollments)->count();
        $girls = (clone $enrollments)->whereHas('student', fn ($q) => $q->where('gender', 'F'))->count();

        $byLevel = (clone $enrollments)
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'classes.grade_level_id')
            ->select('grade_levels.name', DB::raw('count(*) as total'))
            ->groupBy('grade_levels.name')
            ->orderBy('grade_levels.name')
            ->pluck('total', 'grade_levels.name');

        // Présences (30 derniers jours)
        $windowDays = (int) \App\Models\SchoolProfile::current()->attendanceSetting('alert_window_days');
        $since = now()->subDays($windowDays)->toDateString();

        $attendanceQuery = AttendanceRecord::query()
            ->whereHas('session', function ($q) use ($since, $classIds, $seesAll) {
                $q->where('session_date', '>=', $since)
                    ->when(! $seesAll, fn ($qq) => $qq->whereIn('class_id', $classIds ?? []));
            });

        $attendanceTotal = (clone $attendanceQuery)->count();
        $attendancePresent = (clone $attendanceQuery)
            ->whereIn('status', [AttendanceRecord::STATUS_PRESENT, AttendanceRecord::STATUS_LATE])
            ->count();
        $attendanceRate = $attendanceTotal > 0 ? round($attendancePresent / $attendanceTotal * 100, 1) : null;

        $unjustifiedAbsences = (clone $attendanceQuery)
            ->where('status', AttendanceRecord::STATUS_ABSENT)
            ->whereNull('justification')
            ->count();

        // Alertes : élèves avec absences répétées
        $alertThreshold = (int) \App\Models\SchoolProfile::current()->attendanceSetting('alert_absences_count');

        $repeatAbsentees = AttendanceRecord::query()
            ->select('student_id', DB::raw('count(*) as absence_count'))
            ->where('status', AttendanceRecord::STATUS_ABSENT)
            ->whereHas('session', function ($q) use ($since, $classIds, $seesAll) {
                $q->where('session_date', '>=', $since)
                    ->when(! $seesAll, fn ($qq) => $qq->whereIn('class_id', $classIds ?? []));
            })
            ->groupBy('student_id')
            ->having('absence_count', '>=', $alertThreshold)
            ->orderByDesc('absence_count')
            ->with('student')
            ->limit(10)
            ->get();

        // Résultats : moyennes de classe pour la période en cours
        $classResults = [];

        if ($period) {
            $classes = \App\Models\SchoolClass::query()
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->when(! $seesAll, fn ($q) => $q->whereIn('id', $classIds ?? []))
                ->with('gradeLevel')
                ->get();

            foreach ($classes as $class) {
                $stats = $calculator->classStats($class, $period);
                $classResults[] = [
                    'class' => $class,
                    'average' => $stats['average'],
                    'ranked' => $stats['ranked'],
                    'total' => $stats['total'],
                    'success_rate' => $stats['success_rate'],
                ];
            }

            usort($classResults, fn ($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1));
        }

        // Alertes : saisies de notes en retard
        $delayDays = (int) \App\Models\SchoolProfile::current()->gradingAlertSetting('grade_entry_delay_days');

        $lateEvaluations = Evaluation::query()
            ->where('status', Evaluation::STATUS_PUBLISHED)
            ->where('evaluation_date', '<=', now()->subDays($delayDays)->toDateString())
            ->whereHas('classSubject.schoolClass', fn ($q) => $q->when(! $seesAll, fn ($qq) => $qq->whereIn('id', $classIds ?? [])))
            ->with(['classSubject.schoolClass', 'classSubject.subject'])
            ->withCount(['grades as missing_count' => fn ($q) => $q->where('status', Grade::STATUS_MISSING)])
            ->get()
            ->filter(fn ($e) => $e->missing_count > 0)
            ->take(10);

        // Alertes : périodes à valider
        $periodsToValidate = SchoolPeriod::query()
            ->where('status', SchoolPeriod::STATUS_SUBMITTED)
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->with('academicYear')
            ->get();

        // Bulletins récents
        $recentReportCards = ReportCard::query()
            ->when(! $seesAll, fn ($q) => $q->whereHas('student.enrollments', fn ($qq) => $qq->whereIn('class_id', $classIds ?? [])))
            ->with(['student', 'period'])
            ->latest('generated_at')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'year',
            'period',
            'totalStudents',
            'girls',
            'byLevel',
            'attendanceRate',
            'attendanceTotal',
            'unjustifiedAbsences',
            'repeatAbsentees',
            'classResults',
            'lateEvaluations',
            'periodsToValidate',
            'recentReportCards',
            'seesAll',
            'windowDays',
            'alertThreshold',
        ));
    }
}
