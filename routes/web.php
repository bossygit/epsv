<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\GradeEntryController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolPeriodController;
use App\Http\Controllers\SchoolProfileController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('chat.index')
        : redirect()->route('login');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |----------------------------------------------------------------------
    | Messagerie interne (tout le personnel actif)
    |----------------------------------------------------------------------
    */
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/unread', [ChatController::class, 'unread'])->name('chat.unread');
    Route::post('chat/conversations', [ChatController::class, 'storeConversation'])->name('chat.conversations.store');
    Route::patch('chat/conversations/{conversation}', [ChatController::class, 'updateConversation'])->name('chat.conversations.update');
    Route::post('chat/conversations/{conversation}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::get('chat/conversations/{conversation}/messages', [ChatController::class, 'poll'])->name('chat.messages.poll');
    Route::get('chat/messages/{message}/attachment', [ChatController::class, 'downloadAttachment'])->name('chat.attachment');
    Route::get('chat/messages/{message}/pdf', [ChatController::class, 'viewPdf'])->name('chat.pdf');
    Route::get('chat/messages/{message}/document', [ChatController::class, 'editDocument'])->name('chat.documents.edit');
    Route::post('chat/messages/{message}/document', [ChatController::class, 'saveDocument'])->name('chat.documents.save');
    Route::delete('chat/messages/{message}', [ChatController::class, 'destroyMessage'])->name('chat.messages.destroy');

    /*
    |----------------------------------------------------------------------
    | Notifications Web Push
    |----------------------------------------------------------------------
    */
    Route::post('push/subscriptions', [PushSubscriptionController::class, 'store'])->name('push.subscriptions.store');
    Route::patch('push/subscriptions/disable', [PushSubscriptionController::class, 'disable'])->name('push.subscriptions.disable');
    Route::delete('push/subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.subscriptions.destroy');

    /*
    |----------------------------------------------------------------------
    | Structure scolaire
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:structure.view')->group(function () {
        Route::get('structure', [StructureController::class, 'index'])->name('structure.index');
        Route::get('academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
    });

    // Les classes sont visibles par la structure, les professeurs (leurs
    // classes) et le personnel qui suit les élèves — le filtrage par
    // affectation est appliqué dans le contrôleur.
    Route::middleware('permission:structure.view|students.view')->group(function () {
        Route::get('classes', [SchoolClassController::class, 'index'])->name('classes.index');
        Route::get('classes/{schoolClass}', [SchoolClassController::class, 'show'])->name('classes.show');
    });

    Route::middleware('permission:structure.manage')->group(function () {
        Route::post('academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
        Route::put('academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
        Route::post('academic-years/{academicYear}/set-current', [AcademicYearController::class, 'setCurrent'])->name('academic-years.set-current');
        Route::post('academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('academic-years.close');
        Route::delete('academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])->name('academic-years.destroy');

        Route::post('academic-years/{academicYear}/periods', [SchoolPeriodController::class, 'store'])->name('periods.store');
        Route::put('periods/{schoolPeriod}', [SchoolPeriodController::class, 'update'])->name('periods.update');
        Route::delete('periods/{schoolPeriod}', [SchoolPeriodController::class, 'destroy'])->name('periods.destroy');

        Route::post('structure/cycles', [StructureController::class, 'storeCycle'])->name('cycles.store');
        Route::put('structure/cycles/{cycle}', [StructureController::class, 'updateCycle'])->name('cycles.update');
        Route::delete('structure/cycles/{cycle}', [StructureController::class, 'destroyCycle'])->name('cycles.destroy');

        Route::post('structure/levels', [StructureController::class, 'storeLevel'])->name('levels.store');
        Route::put('structure/levels/{gradeLevel}', [StructureController::class, 'updateLevel'])->name('levels.update');
        Route::delete('structure/levels/{gradeLevel}', [StructureController::class, 'destroyLevel'])->name('levels.destroy');

        Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        Route::post('classes', [SchoolClassController::class, 'store'])->name('classes.store');
        Route::put('classes/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');
        Route::delete('classes/{schoolClass}', [SchoolClassController::class, 'destroy'])->name('classes.destroy');

        Route::post('classes/{schoolClass}/subjects', [SchoolClassController::class, 'storeSubject'])->name('classes.subjects.store');
        Route::put('classes/{schoolClass}/subjects/{classSubject}', [SchoolClassController::class, 'updateSubject'])->name('classes.subjects.update');
        Route::delete('classes/{schoolClass}/subjects/{classSubject}', [SchoolClassController::class, 'destroySubject'])->name('classes.subjects.destroy');

        Route::post('classes/{schoolClass}/assignments', [SchoolClassController::class, 'storeAssignment'])->name('classes.assignments.store');
        Route::delete('classes/{schoolClass}/assignments/{assignment}', [SchoolClassController::class, 'destroyAssignment'])->name('classes.assignments.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Workflow des périodes (soumission → validation → verrouillage)
    |----------------------------------------------------------------------
    */
    Route::post('periods/{schoolPeriod}/submit', [SchoolPeriodController::class, 'submit'])
        ->middleware('permission:grades.submit')->name('periods.submit');
    Route::post('periods/{schoolPeriod}/validate', [SchoolPeriodController::class, 'validate'])
        ->middleware('permission:grades.validate')->name('periods.validate');
    Route::post('periods/{schoolPeriod}/lock', [SchoolPeriodController::class, 'lock'])
        ->middleware('permission:grades.lock')->name('periods.lock');
    Route::post('periods/{schoolPeriod}/reopen', [SchoolPeriodController::class, 'reopen'])
        ->middleware('permission:grades.lock')->name('periods.reopen');

    /*
    |----------------------------------------------------------------------
    | Élèves, responsables légaux, inscriptions
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:students.view')->group(function () {
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    });

    Route::middleware('permission:students.manage')->group(function () {
        Route::get('students-create', [StudentController::class, 'create'])->name('students.create');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
        Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('students/{student}', [StudentController::class, 'archive'])->name('students.archive');

        Route::post('students/{student}/guardians', [GuardianController::class, 'store'])->name('guardians.store');
        Route::put('students/{student}/guardians/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
        Route::delete('students/{student}/guardians/{guardian}', [GuardianController::class, 'destroy'])->name('guardians.destroy');
    });

    Route::middleware('permission:enrollments.manage')->group(function () {
        Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::put('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Présences
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:attendance.view')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/session/{session}', [AttendanceController::class, 'show'])->name('attendance.session');
        Route::get('attendance/report/{schoolClass}', [AttendanceController::class, 'report'])->name('attendance.report');
    });

    Route::middleware('permission:attendance.take')->group(function () {
        Route::post('attendance/{schoolClass}/take', [AttendanceController::class, 'take'])->name('attendance.take');
        Route::post('attendance/session/{session}', [AttendanceController::class, 'store'])->name('attendance.store');
    });

    Route::post('attendance/records/{record}/correct', [AttendanceRecordController::class, 'correct'])
        ->middleware('permission:attendance.correct')->name('attendance.records.correct');
    Route::post('attendance/records/{record}/justify', [AttendanceRecordController::class, 'justify'])
        ->middleware('permission:attendance.justify')->name('attendance.records.justify');

    /*
    |----------------------------------------------------------------------
    | Notes et évaluations
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:grades.view')->group(function () {
        Route::get('evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
        Route::get('grades/entry/{evaluation}', [GradeEntryController::class, 'show'])->name('grades.entry');
    });

    Route::middleware('permission:grades.enter')->group(function () {
        Route::get('evaluations/create', [EvaluationController::class, 'create'])->name('evaluations.create');
        Route::post('evaluations', [EvaluationController::class, 'store'])->name('evaluations.store');
        Route::get('evaluations/{evaluation}/edit', [EvaluationController::class, 'edit'])->name('evaluations.edit');
        Route::put('evaluations/{evaluation}', [EvaluationController::class, 'update'])->name('evaluations.update');
        Route::post('evaluations/{evaluation}/publish', [EvaluationController::class, 'publish'])->name('evaluations.publish');
        Route::post('evaluations/{evaluation}/cancel', [EvaluationController::class, 'cancel'])->name('evaluations.cancel');
        Route::delete('evaluations/{evaluation}', [EvaluationController::class, 'destroy'])->name('evaluations.destroy');
    });

    // Saisie des notes : professeur (grades.enter) ou correction par la
    // direction (grades.correct) — la distinction est appliquée dans le service.
    Route::post('grades/entry/{evaluation}', [GradeEntryController::class, 'store'])
        ->middleware('permission:grades.enter|grades.correct')
        ->name('grades.entry.store');

    /*
    |----------------------------------------------------------------------
    | Bulletins
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:report_cards.view')->group(function () {
        Route::get('report-cards', [ReportCardController::class, 'index'])->name('report-cards.index');
        Route::get('report-cards/{reportCard}', [ReportCardController::class, 'show'])->name('report-cards.show');
        Route::get('report-cards/{reportCard}/download', [ReportCardController::class, 'download'])->name('report-cards.download');
        Route::get('classes/{schoolClass}/summary', [ReportCardController::class, 'classSummary'])->name('report-cards.summary');
    });

    Route::post('report-cards/generate/{student}', [ReportCardController::class, 'generate'])
        ->middleware('permission:report_cards.generate')->name('report-cards.generate');
    Route::post('classes/{schoolClass}/report-cards/bulk', [ReportCardController::class, 'bulkGenerate'])
        ->middleware('permission:report_cards.generate')->name('report-cards.bulk');
    Route::post('report-cards/{reportCard}/validate', [ReportCardController::class, 'validate'])
        ->middleware('permission:report_cards.validate')->name('report-cards.validate');
    Route::post('report-cards/{reportCard}/lock', [ReportCardController::class, 'lock'])
        ->middleware('permission:report_cards.validate')->name('report-cards.lock');

    /*
    |----------------------------------------------------------------------
    | Administration
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SchoolProfileController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SchoolProfileController::class, 'update'])->name('settings.update');
    });

    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')->name('audit.index');
});

require __DIR__.'/auth.php';
