<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Guardian;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\QuranPageController;
use App\Http\Controllers\Student as StudentPortal;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Teacher;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(match (true) {
        auth()->user()->isSuperAdmin() => 'super-admin.dashboard',
        auth()->user()->isAdmin() => 'admin.dashboard',
        auth()->user()->isGuardian() => 'guardian.dashboard',
        auth()->user()->isStudent() => 'student.dashboard',
        default => 'teacher.dashboard',
    })
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::patch('students/{student}/archive', [Admin\StudentController::class, 'archive'])->name('students.archive')->middleware('permission:students.archive');
    Route::get('students/search', [Admin\StudentController::class, 'search'])->name('students.search')->middleware('permission:students.view,students.create,students.update,parents.view,parents.update');
    Route::resource('students', Admin\StudentController::class)
        ->middlewareFor(['index', 'create', 'show', 'edit'], 'permission:students.view')
        ->middlewareFor('store', 'permission:students.create')
        ->middlewareFor('update', 'permission:students.update')
        ->middlewareFor('destroy', 'permission:students.delete');
    Route::post('students/{student}/transfer', [Admin\StudentController::class, 'transfer'])->name('students.transfer')->middleware('permission:students.transfer');

    Route::get('parents/search', [Admin\ParentController::class, 'search'])->name('parents.search')->middleware('permission:parents.view,students.create,students.update');
    Route::post('parents/quick', [Admin\ParentController::class, 'quickStore'])->name('parents.quick-store')->middleware('permission:parents.create');

    Route::resource('parents', Admin\ParentController::class)->except(['show'])
        ->parameters(['parents' => 'guardian'])
        ->middlewareFor(['index', 'create', 'edit'], 'permission:parents.view')
        ->middlewareFor('store', 'permission:parents.create')
        ->middlewareFor('update', 'permission:parents.update')
        ->middlewareFor('destroy', 'permission:parents.delete');

    Route::resource('teachers', Admin\TeacherController::class)
        ->middlewareFor(['index', 'create', 'show', 'edit'], 'permission:teachers.view')
        ->middlewareFor('store', 'permission:teachers.create')
        ->middlewareFor('update', 'permission:teachers.update')
        ->middlewareFor('destroy', 'permission:teachers.delete');
    Route::post('teachers/{teacher}/ratings', [Admin\TeacherController::class, 'storeRating'])->name('teachers.ratings.store')->middleware('permission:teachers.update');
    Route::delete('teachers/{teacher}/ratings/{rating}', [Admin\TeacherController::class, 'destroyRating'])->name('teachers.ratings.destroy')->middleware('permission:teachers.update');
    Route::post('teachers/{teacher}/certificates', [Admin\TeacherController::class, 'storeCertificate'])->name('teachers.certificates.store')->middleware('permission:teachers.update');
    Route::delete('teachers/{teacher}/certificates/{certificate}', [Admin\TeacherController::class, 'destroyCertificate'])->name('teachers.certificates.destroy')->middleware('permission:teachers.update');

    // ---- ساعات عمل المشرفين (spec: mosque_management_work_hours_sharia_courses_quran_pages.md) ----
    // نظرة «ساعات عمل المشرفين» القديمة أُدمجت في مركز الدفعات والرواتب.
    Route::get('work-hours', fn () => redirect()->route('admin.payroll.index'))->name('work-hours.index')->middleware('permission:work_hours.view');
    Route::get('teachers/{teacher}/work-hours', [Admin\TeacherWorkHourController::class, 'teacherIndex'])->name('teachers.work-hours.index')->middleware('permission:work_hours.view');
    Route::post('teachers/{teacher}/work-hours', [Admin\TeacherWorkHourController::class, 'store'])->name('teachers.work-hours.store')->middleware('permission:work_hours.manage');
    Route::patch('work-hours/{workHour}', [Admin\TeacherWorkHourController::class, 'update'])->name('work-hours.update')->middleware('permission:work_hours.manage');
    Route::delete('work-hours/{workHour}', [Admin\TeacherWorkHourController::class, 'destroy'])->name('work-hours.destroy')->middleware('permission:work_hours.manage');

    // ---- كشوف العمل الفعلية: أُدمجت في كشف راتب المعلم (مركز الدفعات) ----
    Route::get('timesheet', fn () => redirect()->route('admin.payroll.index', array_filter([
        'month' => request()->input('month'),
        'q' => request()->input('q'),
        'session' => request()->input('session'),
    ])))->name('timesheet.index')->middleware('permission:work_hours.view');
    Route::get('timesheet/day-slots', [Admin\TimesheetController::class, 'daySlots'])->name('timesheet.day-slots')->middleware('permission:work_hours.view');
    Route::post('timesheet/slots', [Admin\TimesheetController::class, 'store'])->name('timesheet.slots.store')->middleware('permission:work_hours.manage');
    Route::patch('timesheet/slots/{workSlot}', [Admin\TimesheetController::class, 'update'])->name('timesheet.slots.update')->middleware('permission:work_hours.manage');
    Route::delete('timesheet/slots/{workSlot}', [Admin\TimesheetController::class, 'destroy'])->name('timesheet.slots.destroy')->middleware('permission:work_hours.manage');
    Route::get('teachers/{teacher}/timesheet', fn (string $teacher) => redirect()->route('admin.payroll.sheet', array_filter([
        'teacher' => $teacher,
        'month' => request()->input('month'),
    ])))->name('teachers.timesheet.index')->middleware('permission:work_hours.view');
    Route::get('teachers/{teacher}/timesheet/print', fn (string $teacher) => redirect()->route('admin.payroll.sheet-print', array_filter([
        'teacher' => $teacher,
        'month' => request()->input('month'),
    ])))->name('teachers.timesheet.print')->middleware('permission:work_hours.view');
    Route::post('teachers/{teacher}/timesheet/generate', [Admin\TimesheetController::class, 'generate'])->name('teachers.timesheet.generate')->middleware('permission:work_hours.manage');

    Route::get('classrooms', [Admin\ClassroomController::class, 'index'])->name('classrooms.index')->middleware('permission:classes.view');
    Route::get('classrooms/create', [Admin\ClassroomController::class, 'create'])->name('classrooms.create')->middleware('permission:classes.create');
    Route::post('classrooms', [Admin\ClassroomController::class, 'store'])->name('classrooms.store')->middleware('permission:classes.create');
    Route::get('classrooms/{classroom}', [Admin\ClassroomController::class, 'show'])->name('classrooms.show')->middleware('permission:classes.view');
    Route::get('classrooms/{classroom}/edit', [Admin\ClassroomController::class, 'edit'])->name('classrooms.edit')->middleware('permission:classes.update');
    Route::patch('classrooms/{classroom}', [Admin\ClassroomController::class, 'update'])->name('classrooms.update')->middleware('permission:classes.update');
    Route::delete('classrooms/{classroom}', [Admin\ClassroomController::class, 'destroy'])->name('classrooms.destroy')->middleware('permission:classes.delete');

    Route::get('sections/{section}', [Admin\ClassroomController::class, 'showSection'])->name('sections.show')->middleware('permission:sections.view');
    Route::post('classrooms/{classroom}/sections', [Admin\ClassroomController::class, 'storeSection'])->name('sections.store')->middleware('permission:sections.create');
    Route::patch('sections/{section}', [Admin\ClassroomController::class, 'updateSection'])->name('sections.update')->middleware('permission:sections.update');
    Route::delete('sections/{section}', [Admin\ClassroomController::class, 'destroySection'])->name('sections.destroy')->middleware('permission:sections.delete');

    Route::post('sections/{section}/students', [Admin\ClassroomController::class, 'enrollStudent'])->name('sections.students.store')->middleware('permission:sections.update');
    Route::delete('sections/{section}/students/{student}', [Admin\ClassroomController::class, 'removeStudent'])->name('sections.students.destroy')->middleware('permission:sections.update');
    Route::post('sections/{section}/teachers', [Admin\ClassroomController::class, 'assignTeacher'])->name('sections.teachers.store')->middleware('permission:sections.update');
    Route::delete('sections/{section}/teachers/{teacher}', [Admin\ClassroomController::class, 'removeTeacher'])->name('sections.teachers.destroy')->middleware('permission:sections.update');

    Route::get('custom-fields', [Admin\CustomFieldController::class, 'index'])->name('custom-fields.index')->middleware('permission:custom_fields.view');
    Route::post('custom-fields', [Admin\CustomFieldController::class, 'store'])->name('custom-fields.store')->middleware('permission:custom_fields.create');
    Route::patch('custom-fields/{customField}', [Admin\CustomFieldController::class, 'update'])->name('custom-fields.update')->middleware('permission:custom_fields.update');
    Route::delete('custom-fields/{customField}', [Admin\CustomFieldController::class, 'destroy'])->name('custom-fields.destroy')->middleware('permission:custom_fields.delete');

    Route::resource('subjects', Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:subjects.view')
        ->middlewareFor('store', 'permission:subjects.create')
        ->middlewareFor('update', 'permission:subjects.update')
        ->middlewareFor('destroy', 'permission:subjects.delete');

    Route::get('schedules', [Admin\ScheduleController::class, 'index'])->name('schedules.index')->middleware('permission:schedule.view');
    Route::post('schedules', [Admin\ScheduleController::class, 'store'])->name('schedules.store')->middleware('permission:schedule.create');
    Route::post('schedules/generate', [Admin\ScheduleController::class, 'generate'])->name('schedules.generate')->middleware('permission:schedule.create');
    Route::delete('schedules/{schedule}', [Admin\ScheduleController::class, 'destroy'])->name('schedules.destroy')->middleware('permission:schedule.delete');
    Route::post('schedules/{schedule}/cancel', [Admin\ScheduleController::class, 'cancel'])->name('schedules.cancel')->middleware('permission:schedule.update');
    Route::post('schedules/{schedule}/postpone', [Admin\ScheduleController::class, 'postpone'])->name('schedules.postpone')->middleware('permission:schedule.update');
    Route::delete('schedules/exceptions/{session}', [Admin\ScheduleController::class, 'restore'])->name('schedules.restore')->middleware('permission:schedule.update');

    // ---- تخصصات الجداول (programs: التحفيظ، الإجازة، اختبارات الحفظ، الشرعية، القرآنية) ----
    Route::get('programs', [Admin\ProgramController::class, 'index'])->name('programs.index')->middleware('permission:programs.view');
    Route::get('programs/create', [Admin\ProgramController::class, 'create'])->name('programs.create')->middleware('permission:programs.create');
    Route::post('programs', [Admin\ProgramController::class, 'store'])->name('programs.store')->middleware('permission:programs.create');
    Route::get('programs/{program}/edit', [Admin\ProgramController::class, 'edit'])->name('programs.edit')->middleware('permission:programs.update');
    Route::patch('programs/{program}', [Admin\ProgramController::class, 'update'])->name('programs.update')->middleware('permission:programs.update');
    Route::delete('programs/{program}', [Admin\ProgramController::class, 'destroy'])->name('programs.destroy')->middleware('permission:programs.delete');
    Route::get('programs/{program}', [Admin\ProgramController::class, 'show'])->name('programs.show')->middleware('permission:programs.view');

    Route::get('attendance', [Admin\AttendanceController::class, 'index'])->name('attendance.index')->middleware('permission:attendance.view');
    Route::get('attendance/summary', [Admin\AttendanceController::class, 'summary'])->name('attendance.summary')->middleware('permission:attendance.view');
    Route::post('attendance', [Admin\AttendanceController::class, 'store'])->name('attendance.store')->middleware('permission:attendance.create');
    Route::get('attendance/create', [Admin\AttendanceController::class, 'create'])->name('attendance.create')->middleware('permission:attendance.create');
    Route::post('attendance/students', [Admin\AttendanceController::class, 'storeStudents'])->name('attendance.students.store')->middleware('permission:attendance.create');
    Route::get('attendance/history', [Admin\AttendanceController::class, 'history'])->name('attendance.history')->middleware('permission:attendance.view');
    Route::get('attendance/sessions/{session}/edit', [Admin\AttendanceController::class, 'edit'])->name('attendance.sessions.edit')->middleware('permission:attendance.update');
    Route::patch('attendance/sessions/{session}', [Admin\AttendanceController::class, 'update'])->name('attendance.sessions.update')->middleware('permission:attendance.update');

    Route::get('finance', [Admin\FinanceController::class, 'index'])->name('finance.index')->middleware('permission:finance.view');
    Route::get('finance/{personType}/{person}', [Admin\FinanceController::class, 'show'])->name('finance.show')->middleware('permission:finance.view');
    Route::post('finance/transactions', [Admin\FinanceController::class, 'storeTransaction'])->name('finance.transactions.store')->middleware('permission:finance.create');
    Route::post('finance/transfers', [Admin\FinanceController::class, 'storeTransfer'])->name('finance.transfers.store')->middleware('permission:finance.create');
    Route::post('finance/transactions/{transaction}/reverse', [Admin\FinanceController::class, 'reverse'])->name('finance.reverse')->middleware('permission:finance.update');

    // ---- رواتب المعلمين: الراتب الشهري + عدّاد الساعات + الدفعات ----
    Route::get('payroll', [Admin\PayrollController::class, 'index'])->name('payroll.index')->middleware('permission:finance.view,payroll.view');
    Route::get('payroll/rates', [Admin\HourlyRateController::class, 'legacyIndex'])->name('payroll.rates.index')->middleware('permission:hourly_rates.manage');
    Route::post('payroll/rates', [Admin\HourlyRateController::class, 'store'])->name('payroll.rates.store')->middleware('permission:hourly_rates.manage');
    Route::delete('payroll/rates/{hourlyRate}', [Admin\HourlyRateController::class, 'destroy'])->name('payroll.rates.destroy')->middleware('permission:hourly_rates.manage');
    Route::get('payroll/close', fn () => redirect()->route('admin.payroll.index', array_filter([
        'month' => request()->input('month'),
    ])))->name('payroll.close-preview')->middleware('permission:payroll.close');
    Route::post('payroll/close', [Admin\PayrollController::class, 'closeAll'])->name('payroll.close-all')->middleware('permission:payroll.close');
    Route::get('payroll/export', [Admin\PayrollController::class, 'export'])->name('payroll.export')->middleware('permission:payroll.view,finance.view');
    Route::get('payroll/export-excel', [Admin\PayrollController::class, 'exportExcel'])->name('payroll.export-excel')->middleware('permission:payroll.view,finance.view');
    Route::get('payroll/print', [Admin\PayrollController::class, 'printAll'])->name('payroll.print')->middleware('permission:payroll.view,finance.view');
    Route::get('payroll/{teacher}/sheet', [Admin\PayrollController::class, 'sheet'])->name('payroll.sheet')->middleware('permission:finance.view,payroll.view');
    Route::get('payroll/{teacher}/sheet/print', [Admin\PayrollController::class, 'printSheet'])->name('payroll.sheet-print')->middleware('permission:payroll.view,finance.view');
    Route::post('payroll/{teacher}/pay', [Admin\PayrollController::class, 'pay'])->name('payroll.pay')->middleware('permission:finance.create,payroll.pay');
    Route::post('payroll/{teacher}/salary', [Admin\PayrollController::class, 'updateSalary'])->name('payroll.salary')->middleware('permission:finance.create,payroll.manage');
    Route::post('payroll/{teacher}/close', [Admin\PayrollController::class, 'close'])->name('payroll.close')->middleware('permission:payroll.close');
    Route::post('payroll/{teacher}/reopen', [Admin\PayrollController::class, 'reopen'])->name('payroll.reopen')->middleware('permission:payroll.reopen');

    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:audit_logs.view');

    Route::resource('exams', Admin\ExamController::class)->only(['index', 'create', 'store', 'destroy'])
        ->middlewareFor(['index', 'create'], 'permission:exams.view')
        ->middlewareFor('store', 'permission:exams.create')
        ->middlewareFor('destroy', 'permission:exams.delete');

    // ---- محرّك الاختبارات: الأسئلة، النشر، PDF، النتائج ----
    Route::get('exams/{exam}', [Admin\ExamController::class, 'show'])->name('exams.show')->middleware('permission:exams.view');
    Route::post('exams/{exam}/publish', [Admin\ExamController::class, 'publish'])->name('exams.publish')->middleware('permission:exams.publish');
    Route::post('exams/{exam}/close', [Admin\ExamController::class, 'close'])->name('exams.close')->middleware('permission:exams.publish');
    Route::post('exams/{exam}/questions', [Admin\ExamController::class, 'storeQuestions'])->name('exams.questions.store')->middleware('permission:exams.update');
    Route::put('exams/questions/{question}', [Admin\ExamController::class, 'updateQuestion'])->name('exams.questions.update')->middleware('permission:exams.update');
    Route::delete('exams/questions/{question}', [Admin\ExamController::class, 'destroyQuestion'])->name('exams.questions.destroy')->middleware('permission:exams.update');
    Route::post('exams/{exam}/attachment', [Admin\ExamController::class, 'storeAttachment'])->name('exams.attachment.store')->middleware('permission:exams.update');
    Route::get('exams/{exam}/attachment', [Admin\ExamController::class, 'attachment'])->name('exams.attachment')->middleware('permission:exams.view');
    Route::delete('exams/{exam}/attachment', [Admin\ExamController::class, 'destroyAttachment'])->name('exams.attachment.destroy')->middleware('permission:exams.update');
    Route::post('exam-attempts/{attempt}/grade', [Admin\ExamController::class, 'manualGrade'])->name('exams.attempts.grade')->middleware('permission:grades.update');

    Route::get('grades', [Admin\GradeController::class, 'index'])->name('grades.index')->middleware('permission:grades.view');
    Route::get('grades/{exam}', [Admin\GradeController::class, 'show'])->name('grades.show')->middleware('permission:grades.view');
    Route::patch('grades/{exam}/approve', [Admin\GradeController::class, 'approve'])->name('grades.approve')->middleware('permission:grades.approve');

    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index')->middleware('permission:reports.view');

    Route::get('announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index')->middleware('permission:announcements.view');
    Route::post('announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store')->middleware('permission:announcements.create');
    Route::delete('announcements/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy')->middleware('permission:announcements.delete');

    Route::get('quran-review', [Admin\QuranReviewController::class, 'index'])->name('quran-review.index')->middleware('permission:quran_review.view');
    Route::get('quran-review/statistics', [Admin\QuranReviewController::class, 'statistics'])->name('quran-review.statistics')->middleware('permission:quran_review.view');
    Route::get('quran-review/{id}', [Admin\QuranReviewController::class, 'show'])->name('quran-review.show')->middleware('permission:quran_review.view');
    Route::get('quran-review/student/{student}', [Admin\QuranReviewController::class, 'studentReport'])->name('quran-review.student-report')->middleware('permission:quran_review.view');

    // ---- «مراجعة 5» (الخمسات): كل جزء ٤ خمسات × ٥ صفحات ----
    Route::get('quran/khamsa', [Admin\QuranKhamsaController::class, 'index'])->name('quran.khamsa.index')->middleware('permission:quran_khamsa.view');
    Route::get('quran/khamsa/create', [Admin\QuranKhamsaController::class, 'create'])->name('quran.khamsa.create')->middleware('permission:quran_khamsa.create');
    Route::post('quran/khamsa', [Admin\QuranKhamsaController::class, 'store'])->name('quran.khamsa.store')->middleware('permission:quran_khamsa.create');
    Route::post('quran/khamsa/memorization', [Admin\QuranKhamsaController::class, 'storeMemorization'])->name('quran.khamsa.memorization.store')->middleware('permission:quran.memorization.manage');
    Route::delete('quran/khamsa/memorization', [Admin\QuranKhamsaController::class, 'destroyMemorization'])->name('quran.khamsa.memorization.destroy')->middleware('permission:quran.memorization.manage');
    Route::post('quran/khamsa/items/{item}/complete', [Admin\QuranKhamsaController::class, 'complete'])->name('quran.khamsa.items.complete')->middleware('permission:quran_khamsa.complete');
    Route::get('quran/khamsa/{review}/review', [Admin\QuranKhamsaController::class, 'review'])->name('quran.khamsa.review')->middleware('permission:quran_khamsa.complete');
    Route::post('quran/khamsa/{review}/review', [Admin\QuranKhamsaController::class, 'storeReview'])->name('quran.khamsa.review.store')->middleware('permission:quran_khamsa.complete');
    Route::get('quran/khamsa/{review}', [Admin\QuranKhamsaController::class, 'show'])->name('quran.khamsa.show')->middleware('permission:quran_khamsa.view');
    Route::post('quran/khamsa/{review}/cancel', [Admin\QuranKhamsaController::class, 'cancel'])->name('quran.khamsa.cancel')->middleware('permission:quran_khamsa.update');

    // ---- «خطة الاستماع والاختبار»: أجزاء بنطاق صفحات، واختبار يفتح الدفعة التالية ----
    Route::get('quran/listening', [Admin\QuranListeningController::class, 'index'])->name('quran.listening.index')->middleware('permission:quran_listening.view');
    Route::get('quran/listening/create', [Admin\QuranListeningController::class, 'create'])->name('quran.listening.create')->middleware('permission:quran_listening.create');
    Route::post('quran/listening', [Admin\QuranListeningController::class, 'store'])->name('quran.listening.store')->middleware('permission:quran_listening.create');
    Route::post('quran/listening/items/{item}/listen', [Admin\QuranListeningController::class, 'listen'])->name('quran.listening.items.listen')->middleware('permission:quran_listening.listen');
    Route::get('quran/listening/items/{item}/audio', [Admin\QuranListeningController::class, 'audio'])->name('quran.listening.items.audio')->middleware('permission:quran_listening.view');
    Route::post('quran/listening/items/{item}/progress', [Admin\QuranListeningController::class, 'progress'])->name('quran.listening.items.progress')->middleware('permission:quran_listening.listen');
    Route::get('quran/listening/{plan}', [Admin\QuranListeningController::class, 'show'])->name('quran.listening.show')->middleware('permission:quran_listening.view');
    Route::post('quran/listening/{plan}/test', [Admin\QuranListeningController::class, 'test'])->name('quran.listening.test')->middleware('permission:quran_listening.test');
    Route::post('quran/listening/{plan}/cancel', [Admin\QuranListeningController::class, 'cancel'])->name('quran.listening.cancel')->middleware('permission:quran_listening.update');

    // ---- دفعات الحفظ: كل جزأين دفعة → خمسات → اختبار تراكمي بحد نجاح الجامع ----
    Route::get('quran/batches', [Admin\QuranBatchController::class, 'index'])->name('quran.batches.index')->middleware('permission:quran_batch.view');
    Route::get('quran/batches/session-start', [Admin\QuranBatchController::class, 'sessionStart'])->name('quran.batches.session-start')->middleware('permission:quran.tasmee.create,quran_review.create');
    Route::post('quran/batches/{batch}/repeat', [Admin\QuranBatchController::class, 'repeat'])->name('quran.batches.repeat')->middleware('permission:quran_batch.update');
    Route::post('quran/batches/{batch}/test', [Admin\QuranBatchController::class, 'test'])->name('quran.batches.test')->middleware('permission:quran_listening.test');
    Route::post('quran/batches/{batch}/placement-test', [Admin\QuranBatchController::class, 'placementTest'])->name('quran.batches.placement-test')->middleware('permission:quran_listening.test');
    Route::post('quran/batches/{batch}/retake', [Admin\QuranBatchController::class, 'retake'])->name('quran.batches.retake')->middleware('permission:quran_batch.update');

    // ---- برامج الاستماع: تسميع الأجزاء ← اختبار تراكمي 1–5k (إجازة/تأهيلي) ----
    Route::get('quran/programs', [Admin\QuranListeningProgramController::class, 'index'])->name('quran.programs.index')->middleware('permission:quran_training.view');
    Route::get('quran/programs/items/{item}/tasmee', [Admin\QuranListeningProgramController::class, 'tasmee'])->name('quran.programs.items.tasmee')->middleware('permission:quran_training.listen');
    Route::post('quran/programs/items/{item}/tasmee', [Admin\QuranListeningProgramController::class, 'storeTasmee'])->name('quran.programs.items.tasmee.store')->middleware('permission:quran_training.listen');
    Route::post('quran/programs/batches/{batch}/test', [Admin\QuranListeningProgramController::class, 'test'])->name('quran.programs.batches.test')->middleware('permission:quran_training.test');
    Route::post('quran/programs/batches/{batch}/placement-test', [Admin\QuranListeningProgramController::class, 'placementTest'])->name('quran.programs.batches.placement-test')->middleware('permission:quran_training.test');
    Route::post('quran/programs/{program}/cancel', [Admin\QuranListeningProgramController::class, 'cancel'])->name('quran.programs.cancel')->middleware('permission:quran_training.update');
    Route::get('quran/programs/{program}', [Admin\QuranListeningProgramController::class, 'show'])->name('quran.programs.show')->middleware('permission:quran_training.view');

    // ---- مركز الإعدادات: برنامج القرآن + نقاط المكافآت + الصلاحيات ----
    Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index')->middleware('permission:quran_settings.view,users.view,hourly_rates.manage,work_hours.view');

    // ---- إعدادات برنامج القرآن (حد النجاح في اختبار الدفعات) ----
    Route::get('settings/quran', [Admin\QuranSettingsController::class, 'edit'])->name('settings.quran.edit')->middleware('permission:quran_settings.view');
    Route::patch('settings/quran', [Admin\QuranSettingsController::class, 'update'])->name('settings.quran.update')->middleware('permission:quran_settings.update');

    // ---- إعدادات نقاط المكافآت: قواعد لكل دوام (حفظ/خمسات/اختبار/خطة/دورة) ----
    Route::get('settings/rewards', [Admin\RewardPointSettingsController::class, 'edit'])->name('settings.rewards.edit')->middleware('permission:quran_settings.view');
    Route::patch('settings/rewards', [Admin\RewardPointSettingsController::class, 'update'])->name('settings.rewards.update')->middleware('permission:quran_settings.update');

    Route::get('settings/work-hours', [Admin\WorkHoursSettingsController::class, 'edit'])->name('settings.work-hours.edit')->middleware('permission:work_hours.view');
    Route::patch('settings/work-hours', [Admin\WorkHoursSettingsController::class, 'update'])->name('settings.work-hours.update')->middleware('permission:work_hours.manage');

    // ---- إعدادات أسعار الساعة: سعر ساعة لكل أستاذ (يحوّله تلقائياً إلى الأجر بالساعة) ----
    Route::get('settings/hourly-rates', [Admin\HourlyRateController::class, 'index'])->name('settings.hourly-rates.index')->middleware('permission:hourly_rates.manage');
    Route::post('settings/hourly-rates', [Admin\HourlyRateController::class, 'store'])->name('settings.hourly-rates.store')->middleware('permission:hourly_rates.manage');
    Route::delete('settings/hourly-rates/{hourlyRate}', [Admin\HourlyRateController::class, 'destroy'])->name('settings.hourly-rates.destroy')->middleware('permission:hourly_rates.manage');

    Route::get('reward-points', [Admin\RewardPointController::class, 'index'])->name('reward-points.index')->middleware('permission:reward_points.view');

    Route::resource('users', Admin\UserController::class)->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:users.view')
        ->middlewareFor('store', 'permission:users.create')
        ->middlewareFor('update', 'permission:users.update')
        ->middlewareFor('destroy', 'permission:users.delete');

    // ---- الدوامات (study sessions: الدورة الأولى / الثانية) ----
    Route::get('sessions', [Admin\StudySessionController::class, 'index'])->name('sessions.index')->middleware('permission:sessions.view');
    Route::post('sessions', [Admin\StudySessionController::class, 'store'])->name('sessions.store')->middleware('permission:sessions.create');
    Route::post('sessions/switch', [Admin\StudySessionController::class, 'switch'])->name('sessions.switch')->middleware('permission:sessions.view');
    Route::post('sessions/{session}/programs', [Admin\StudySessionController::class, 'syncPrograms'])->name('sessions.programs')->middleware('permission:sessions.update');
    Route::patch('sessions/{session}', [Admin\StudySessionController::class, 'update'])->name('sessions.update')->middleware('permission:sessions.update');
    Route::delete('sessions/{session}', [Admin\StudySessionController::class, 'destroy'])->name('sessions.destroy')->middleware('permission:sessions.delete');
    Route::post('sessions/assign-unassigned', [Admin\StudySessionController::class, 'assignUnassigned'])->name('sessions.assign-unassigned')->middleware('permission:sessions.update');

    // ---- البرامج القرآنية (spec: mosque_management_quran_programs.md) ----
    Route::get('quran', [Admin\QuranProgramController::class, 'index'])->name('quran.index')->middleware('permission:quran.tasmee.view');
    Route::get('quran/students/{student}', [Admin\QuranProgramController::class, 'journey'])->name('quran.journey')->middleware('permission:quran.tasmee.view');

    Route::get('quran/tasmee', [Admin\QuranTasmeeController::class, 'index'])->name('quran.tasmee.index')->middleware('permission:quran.tasmee.view');
    Route::get('quran/tasmee/create', [Admin\QuranTasmeeController::class, 'create'])->name('quran.tasmee.create')->middleware('permission:quran.tasmee.create');
    Route::get('quran/tasmee/review/{session?}', [Admin\QuranTasmeeController::class, 'review'])->name('quran.tasmee.review')->middleware('permission:quran.tasmee.create,quran.tasmee.update');
    Route::post('quran/tasmee', [Admin\QuranTasmeeController::class, 'store'])->name('quran.tasmee.store')->middleware('permission:quran.tasmee.create');
    Route::get('quran/tasmee/{session}/edit', [Admin\QuranTasmeeController::class, 'edit'])->name('quran.tasmee.edit')->middleware('permission:quran.tasmee.update');
    Route::patch('quran/tasmee/{session}', [Admin\QuranTasmeeController::class, 'update'])->name('quran.tasmee.update')->middleware('permission:quran.tasmee.update');

    // صفحة موحّدة: إتمام الحفظ + الحفاظ (دُمجت صفحة الحفاظ المستقلة هنا).
    Route::get('quran/completions', [Admin\QuranCompletionController::class, 'index'])->name('quran.completions.index')->middleware('permission:quran.completion.view,hafiz_profile.view');
    Route::get('quran/completions/create', [Admin\QuranCompletionController::class, 'create'])->name('quran.completions.create')->middleware('permission:quran.completion.view');
    Route::post('quran/completions', [Admin\QuranCompletionController::class, 'store'])->name('quran.completions.store')->middleware('permission:quran.completion.view');
    Route::post('quran/completions/{completion}/confirm', [Admin\QuranCompletionController::class, 'confirm'])->name('quran.completions.confirm')->middleware('permission:quran.completion.confirm');

    Route::get('quran/hafiz', [Admin\HafizController::class, 'index'])->name('quran.hafiz.index')->middleware('permission:hafiz_profile.view');
    Route::get('quran/hafiz/{student}/profile', [Admin\HafizController::class, 'profile'])->name('quran.hafiz.profile')->middleware('permission:hafiz_profile.view');
    Route::patch('quran/hafiz/{student}/profile', [Admin\HafizController::class, 'update'])->name('quran.hafiz.profile.update')->middleware('permission:hafiz_profile.update');

    Route::get('quran/qualifying', [Admin\QualifyingController::class, 'index'])->name('quran.qualifying.index')->middleware('permission:qualifying.view');
    Route::get('quran/qualifying/evaluations/create', [Admin\QualifyingController::class, 'create'])->name('quran.qualifying.evaluations.create')->middleware('permission:qualifying.create');
    Route::post('quran/qualifying/evaluations', [Admin\QualifyingController::class, 'store'])->name('quran.qualifying.evaluations.store')->middleware('permission:qualifying.create');
    Route::post('quran/qualifying/enrollments/{enrollment}/complete', [Admin\QualifyingController::class, 'complete'])->name('quran.qualifying.enrollments.complete')->middleware('permission:qualifying.complete');

    Route::get('quran/ijazah', [Admin\IjazahController::class, 'index'])->name('quran.ijazah.index')->middleware('permission:ijazah.view');
    Route::get('quran/ijazah/evaluations/create', [Admin\IjazahController::class, 'create'])->name('quran.ijazah.evaluations.create')->middleware('permission:ijazah.create');
    Route::post('quran/ijazah/evaluations', [Admin\IjazahController::class, 'store'])->name('quran.ijazah.evaluations.store')->middleware('permission:ijazah.create');
    Route::get('quran/ijazah/{student}/month/{month}', [Admin\IjazahController::class, 'month'])
        ->where('month', '\d{4}-(0[1-9]|1[0-2])')->name('quran.ijazah.month')->middleware('permission:ijazah.view');
    Route::post('quran/ijazah/weekly', [Admin\IjazahController::class, 'storeWeekly'])->name('quran.ijazah.weekly.store')->middleware('permission:ijazah.create');
    Route::patch('quran/ijazah/weekly/{evaluation}', [Admin\IjazahController::class, 'updateWeekly'])->name('quran.ijazah.weekly.update')->middleware('permission:ijazah.update');
    Route::delete('quran/ijazah/weekly/{evaluation}', [Admin\IjazahController::class, 'destroyWeekly'])->name('quran.ijazah.weekly.destroy')->middleware('permission:ijazah.update');
    Route::post('quran/ijazah/enrollments/{enrollment}/complete', [Admin\IjazahController::class, 'complete'])->name('quran.ijazah.enrollments.complete')->middleware('permission:ijazah.complete');

    Route::get('quran/exams', [Admin\HafizExamController::class, 'index'])->name('quran.exams.index')->middleware('permission:hafiz_exams.view');
    Route::get('quran/exams/month/{month}', [Admin\HafizExamController::class, 'month'])
        ->where('month', '\d{4}-(0[1-9]|1[0-2])')->name('quran.exams.month')->middleware('permission:hafiz_exams.view');
    Route::get('quran/exams/{exam}', [Admin\HafizExamController::class, 'show'])->name('quran.exams.show')->middleware('permission:hafiz_exams.view');
    Route::post('quran/exams/{exam}/grade', [Admin\HafizExamController::class, 'grade'])->name('quran.exams.grade')->middleware('permission:hafiz_exams.grade');
    Route::post('quran/exams/{exam}/revisions', [Admin\HafizExamController::class, 'storeRevision'])->name('quran.exams.revisions.store')->middleware('permission:hafiz_exams.update');
    Route::post('quran/exams/revisions/{revision}/complete', [Admin\HafizExamController::class, 'completeRevision'])->name('quran.exams.revisions.complete')->middleware('permission:hafiz_exams.update');
    Route::post('quran/exams/revisions/{revision}/approve', [Admin\HafizExamController::class, 'approveRevision'])->name('quran.exams.revisions.approve')->middleware('permission:hafiz_exams.grade');

    // ---- اللقاءات الإيمانية ----
    Route::get('faith-meetings', [Admin\FaithMeetingController::class, 'index'])->name('faith-meetings.index')->middleware('permission:faith_meetings.view');
    Route::get('faith-meetings/create', [Admin\FaithMeetingController::class, 'create'])->name('faith-meetings.create')->middleware('permission:faith_meetings.create');
    Route::post('faith-meetings', [Admin\FaithMeetingController::class, 'store'])->name('faith-meetings.store')->middleware('permission:faith_meetings.create');
    Route::get('faith-meetings/{meeting}', [Admin\FaithMeetingController::class, 'show'])->name('faith-meetings.show')->middleware('permission:faith_meetings.view');
    Route::get('faith-meetings/{meeting}/edit', [Admin\FaithMeetingController::class, 'edit'])->name('faith-meetings.edit')->middleware('permission:faith_meetings.update');
    Route::patch('faith-meetings/{meeting}', [Admin\FaithMeetingController::class, 'update'])->name('faith-meetings.update')->middleware('permission:faith_meetings.update');
    Route::post('faith-meetings/{meeting}/status', [Admin\FaithMeetingController::class, 'status'])->name('faith-meetings.status')->middleware('permission:faith_meetings.update');
    Route::delete('faith-meetings/{meeting}', [Admin\FaithMeetingController::class, 'destroy'])->name('faith-meetings.destroy')->middleware('permission:faith_meetings.update');
    Route::post('faith-meetings/{meeting}/attendance', [Admin\FaithMeetingController::class, 'attendance'])->name('faith-meetings.attendance')->middleware('permission:faith_meetings.attendance');
    Route::post('faith-meetings/{meeting}/notes', [Admin\FaithMeetingController::class, 'storeNote'])->name('faith-meetings.notes.store')->middleware('permission:faith_meetings.update');
    Route::post('faith-meetings/notes/{note}/complete', [Admin\FaithMeetingController::class, 'completeNote'])->name('faith-meetings.notes.complete')->middleware('permission:faith_meetings.update');
    Route::delete('faith-meetings/notes/{note}', [Admin\FaithMeetingController::class, 'destroyNote'])->name('faith-meetings.notes.destroy')->middleware('permission:faith_meetings.update');

    Route::get('faith-meeting-templates', [Admin\FaithMeetingTemplateController::class, 'index'])->name('faith-meetings.templates')->middleware('permission:faith_meetings.update');
    Route::post('faith-meeting-templates', [Admin\FaithMeetingTemplateController::class, 'store'])->name('faith-meetings.templates.store')->middleware('permission:faith_meetings.update');
    Route::patch('faith-meeting-templates/{template}', [Admin\FaithMeetingTemplateController::class, 'update'])->name('faith-meetings.templates.update')->middleware('permission:faith_meetings.update');
    Route::delete('faith-meeting-templates/{template}', [Admin\FaithMeetingTemplateController::class, 'destroy'])->name('faith-meetings.templates.destroy')->middleware('permission:faith_meetings.update');

    // ---- الدورات الشرعية (spec: mosque_management_work_hours_sharia_courses_quran_pages.md) ----
    Route::get('sharia-courses', [Admin\ShariaCourseController::class, 'index'])->name('sharia-courses.index')->middleware('permission:sharia_courses.view');
    Route::get('sharia-courses/create', [Admin\ShariaCourseController::class, 'create'])->name('sharia-courses.create')->middleware('permission:sharia_courses.create');
    Route::post('sharia-courses', [Admin\ShariaCourseController::class, 'store'])->name('sharia-courses.store')->middleware('permission:sharia_courses.create');
    Route::get('sharia-courses/{course}', [Admin\ShariaCourseController::class, 'show'])->name('sharia-courses.show')->middleware('permission:sharia_courses.view');
    Route::get('sharia-courses/{course}/edit', [Admin\ShariaCourseController::class, 'edit'])->name('sharia-courses.edit')->middleware('permission:sharia_courses.update');
    Route::patch('sharia-courses/{course}', [Admin\ShariaCourseController::class, 'update'])->name('sharia-courses.update')->middleware('permission:sharia_courses.update');
    Route::delete('sharia-courses/{course}', [Admin\ShariaCourseController::class, 'destroy'])->name('sharia-courses.destroy')->middleware('permission:sharia_courses.delete');

    Route::post('sharia-courses/{course}/lessons', [Admin\ShariaCourseController::class, 'storeLesson'])->name('sharia-courses.lessons.store')->middleware('permission:sharia_courses.update');
    Route::patch('sharia-courses/lessons/{lesson}', [Admin\ShariaCourseController::class, 'updateLesson'])->name('sharia-courses.lessons.update')->middleware('permission:sharia_courses.update');
    Route::delete('sharia-courses/lessons/{lesson}', [Admin\ShariaCourseController::class, 'destroyLesson'])->name('sharia-courses.lessons.destroy')->middleware('permission:sharia_courses.update');

    Route::post('sharia-courses/{course}/students', [Admin\ShariaCourseController::class, 'storeStudent'])->name('sharia-courses.students.store')->middleware('permission:sharia_courses.update');
    Route::post('sharia-courses/{course}/students/existing', [Admin\ShariaCourseController::class, 'storeExistingStudents'])->name('sharia-courses.students.existing')->middleware('permission:sharia_courses.update');
    Route::post('sharia-courses/{course}/students/classroom', [Admin\ShariaCourseController::class, 'storeClassroomStudents'])->name('sharia-courses.students.classroom')->middleware('permission:sharia_courses.update');
    Route::patch('sharia-courses/students/{student}', [Admin\ShariaCourseController::class, 'updateStudent'])->name('sharia-courses.students.update')->middleware('permission:sharia_courses.update');
    Route::patch('sharia-courses/students/{student}/memorization', [Admin\ShariaCourseController::class, 'updateMemorization'])->name('sharia-courses.students.memorization')->middleware('permission:sharia_courses.memorization');
    Route::delete('sharia-courses/students/{student}', [Admin\ShariaCourseController::class, 'destroyStudent'])->name('sharia-courses.students.destroy')->middleware('permission:sharia_courses.update');

    Route::post('sharia-courses/{course}/attendance', [Admin\ShariaCourseController::class, 'storeAttendance'])->name('sharia-courses.attendance.store')->middleware('permission:sharia_courses.attendance');
});

// ---- صفحات المصحف (المدير داخل الجامع + المعلم) ----
Route::middleware(['auth', 'permission:quran.tasmee.view'])->prefix('quran/pages')->name('quran.pages.')->group(function () {
    Route::get('{page}', [QuranPageController::class, 'show'])->whereNumber('page')->name('show');
    Route::get('{page}/preview', [QuranPageController::class, 'preview'])->whereNumber('page')->name('preview');
    Route::get('{page}/json', [QuranPageController::class, 'json'])->whereNumber('page')->name('json');
});

Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('dashboard', [Teacher\DashboardController::class, 'index'])->name('dashboard');

    Route::get('schedule', [Teacher\ScheduleController::class, 'index'])->name('schedule')->middleware('permission:schedule.view');
    Route::post('schedule/{schedule}/cancel', [Teacher\ScheduleController::class, 'cancel'])->name('schedule.cancel')->middleware('permission:schedule.update');
    Route::post('schedule/{schedule}/postpone', [Teacher\ScheduleController::class, 'postpone'])->name('schedule.postpone')->middleware('permission:schedule.update');
    Route::delete('schedule/exceptions/{session}', [Teacher\ScheduleController::class, 'restore'])->name('schedule.restore')->middleware('permission:schedule.update');

    Route::get('work-hours', [Teacher\WorkHourController::class, 'index'])->name('work-hours.index')->middleware('permission:work_hours.view');
    Route::get('timesheet', [Teacher\TimesheetController::class, 'index'])->name('timesheet.index')->middleware('permission:work_hours.view');
    Route::get('payroll', [Teacher\PayrollController::class, 'index'])->name('payroll.index')->middleware('permission:payroll.view');
    Route::get('payroll/{period}', [Teacher\PayrollController::class, 'show'])->name('payroll.show')->middleware('permission:payroll.view');

    Route::get('attendance', [Teacher\AttendanceController::class, 'create'])->name('attendance.create')->middleware('permission:attendance.create');
    Route::post('attendance', [Teacher\AttendanceController::class, 'store'])->name('attendance.store')->middleware('permission:attendance.create');
    Route::get('attendance/history', [Teacher\AttendanceController::class, 'history'])->name('attendance.history')->middleware('permission:attendance.view');
    Route::get('attendance/sessions/{session}/edit', [Teacher\AttendanceController::class, 'edit'])->name('attendance.sessions.edit')->middleware('permission:attendance.update');
    Route::patch('attendance/sessions/{session}', [Teacher\AttendanceController::class, 'update'])->name('attendance.sessions.update')->middleware('permission:attendance.update');

    Route::get('homeworks/{homework}/submissions', [Teacher\HomeworkController::class, 'submissions'])->name('homeworks.submissions')->middleware('permission:assignments.grade');
    Route::patch('submissions/{submission}', [Teacher\HomeworkController::class, 'updateSubmission'])->name('submissions.update')->middleware('permission:assignments.grade');
    Route::resource('homeworks', Teacher\HomeworkController::class)->only(['index', 'create', 'store', 'destroy'])
        ->middlewareFor('index', 'permission:assignments.view')
        ->middlewareFor(['create', 'store'], 'permission:assignments.create')
        ->middlewareFor('destroy', 'permission:assignments.delete');

    Route::resource('exams', Teacher\ExamController::class)->only(['index', 'create', 'store'])
        ->middlewareFor('index', 'permission:exams.view')
        ->middlewareFor(['create', 'store'], 'permission:exams.create');

    // ---- محرّك الاختبارات: الأسئلة، النشر، PDF، النتائج (امتحانات الأستاذ فقط) ----
    Route::get('exams/{exam}', [Teacher\ExamController::class, 'show'])->name('exams.show')->middleware('permission:exams.view');
    Route::post('exams/{exam}/publish', [Teacher\ExamController::class, 'publish'])->name('exams.publish')->middleware('permission:exams.publish');
    Route::post('exams/{exam}/close', [Teacher\ExamController::class, 'close'])->name('exams.close')->middleware('permission:exams.publish');
    Route::post('exams/{exam}/questions', [Teacher\ExamController::class, 'storeQuestions'])->name('exams.questions.store')->middleware('permission:exams.update');
    Route::put('exams/questions/{question}', [Teacher\ExamController::class, 'updateQuestion'])->name('exams.questions.update')->middleware('permission:exams.update');
    Route::delete('exams/questions/{question}', [Teacher\ExamController::class, 'destroyQuestion'])->name('exams.questions.destroy')->middleware('permission:exams.update');
    Route::post('exams/{exam}/attachment', [Teacher\ExamController::class, 'storeAttachment'])->name('exams.attachment.store')->middleware('permission:exams.update');
    Route::get('exams/{exam}/attachment', [Teacher\ExamController::class, 'attachment'])->name('exams.attachment')->middleware('permission:exams.view');
    Route::delete('exams/{exam}/attachment', [Teacher\ExamController::class, 'destroyAttachment'])->name('exams.attachment.destroy')->middleware('permission:exams.update');
    Route::post('exam-attempts/{attempt}/grade', [Teacher\ExamController::class, 'manualGrade'])->name('exams.attempts.grade')->middleware('permission:grades.update');

    Route::get('exams/{exam}/grades', [Teacher\GradeController::class, 'edit'])->name('grades.edit')->middleware('permission:grades.view');
    Route::post('exams/{exam}/grades', [Teacher\GradeController::class, 'store'])->name('grades.store')->middleware('permission:grades.create,grades.update');

    Route::resource('lessons', Teacher\LessonController::class)->only(['index', 'create', 'store', 'destroy'])
        ->middlewareFor('index', 'permission:lessons.view')
        ->middlewareFor(['create', 'store'], 'permission:lessons.create')
        ->middlewareFor('destroy', 'permission:lessons.delete');

    Route::get('messages', [Teacher\MessageController::class, 'index'])->name('messages.index')->middleware('permission:messages.view');
    Route::post('messages', [Teacher\MessageController::class, 'store'])->name('messages.store')->middleware('permission:messages.create');

    Route::get('profile', [Teacher\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [Teacher\ProfileController::class, 'update'])->name('profile.update');

    Route::get('quran-review', [Teacher\QuranReviewController::class, 'index'])->name('quran-review.index')->middleware('permission:quran_review.view');
    Route::get('quran-review/create', [Teacher\QuranReviewController::class, 'create'])->name('quran-review.create')->middleware('permission:quran_review.create');
    Route::post('quran-review', [Teacher\QuranReviewController::class, 'store'])->name('quran-review.store')->middleware('permission:quran_review.create');
    Route::get('quran-review/{id}', [Teacher\QuranReviewController::class, 'show'])->name('quran-review.show')->middleware('permission:quran_review.view');
    Route::get('quran-review/student/{student}', [Teacher\QuranReviewController::class, 'studentReport'])->name('quran-review.student-report')->middleware('permission:quran_review.view');
    Route::get('quran-review/ayahs/json', [Teacher\QuranReviewController::class, 'getAyahs'])->name('quran-review.ayahs')->middleware('permission:quran_review.view');

    // ---- «مراجعة 5» (الخمسات) للمعلم ----
    Route::get('quran/khamsa', [Teacher\QuranKhamsaController::class, 'index'])->name('quran.khamsa.index')->middleware('permission:quran_khamsa.view');
    Route::get('quran/khamsa/create', [Teacher\QuranKhamsaController::class, 'create'])->name('quran.khamsa.create')->middleware('permission:quran_khamsa.create');
    Route::post('quran/khamsa', [Teacher\QuranKhamsaController::class, 'store'])->name('quran.khamsa.store')->middleware('permission:quran_khamsa.create');
    Route::post('quran/khamsa/memorization', [Teacher\QuranKhamsaController::class, 'storeMemorization'])->name('quran.khamsa.memorization.store')->middleware('permission:quran.memorization.manage');
    Route::delete('quran/khamsa/memorization', [Teacher\QuranKhamsaController::class, 'destroyMemorization'])->name('quran.khamsa.memorization.destroy')->middleware('permission:quran.memorization.manage');
    Route::post('quran/khamsa/items/{item}/complete', [Teacher\QuranKhamsaController::class, 'complete'])->name('quran.khamsa.items.complete')->middleware('permission:quran_khamsa.complete');
    Route::get('quran/khamsa/{review}/review', [Teacher\QuranKhamsaController::class, 'review'])->name('quran.khamsa.review')->middleware('permission:quran_khamsa.complete');
    Route::post('quran/khamsa/{review}/review', [Teacher\QuranKhamsaController::class, 'storeReview'])->name('quran.khamsa.review.store')->middleware('permission:quran_khamsa.complete');
    Route::get('quran/khamsa/{review}', [Teacher\QuranKhamsaController::class, 'show'])->name('quran.khamsa.show')->middleware('permission:quran_khamsa.view');
    Route::post('quran/khamsa/{review}/cancel', [Teacher\QuranKhamsaController::class, 'cancel'])->name('quran.khamsa.cancel')->middleware('permission:quran_khamsa.update');

    // ---- «خطة الاستماع والاختبار» للمعلم ----
    Route::get('quran/listening', [Teacher\QuranListeningController::class, 'index'])->name('quran.listening.index')->middleware('permission:quran_listening.view');
    Route::get('quran/listening/create', [Teacher\QuranListeningController::class, 'create'])->name('quran.listening.create')->middleware('permission:quran_listening.create');
    Route::post('quran/listening', [Teacher\QuranListeningController::class, 'store'])->name('quran.listening.store')->middleware('permission:quran_listening.create');
    Route::post('quran/listening/items/{item}/listen', [Teacher\QuranListeningController::class, 'listen'])->name('quran.listening.items.listen')->middleware('permission:quran_listening.listen');
    Route::get('quran/listening/items/{item}/audio', [Teacher\QuranListeningController::class, 'audio'])->name('quran.listening.items.audio')->middleware('permission:quran_listening.view');
    Route::post('quran/listening/items/{item}/progress', [Teacher\QuranListeningController::class, 'progress'])->name('quran.listening.items.progress')->middleware('permission:quran_listening.listen');
    Route::get('quran/listening/{plan}', [Teacher\QuranListeningController::class, 'show'])->name('quran.listening.show')->middleware('permission:quran_listening.view');
    Route::post('quran/listening/{plan}/test', [Teacher\QuranListeningController::class, 'test'])->name('quran.listening.test')->middleware('permission:quran_listening.test');
    Route::post('quran/listening/{plan}/cancel', [Teacher\QuranListeningController::class, 'cancel'])->name('quran.listening.cancel')->middleware('permission:quran_listening.update');

    // ---- دفعات الحفظ: كل جزأين دفعة → خمسات → اختبار تراكمي بحد نجاح الجامع ----
    Route::get('quran/batches', [Teacher\QuranBatchController::class, 'index'])->name('quran.batches.index')->middleware('permission:quran_batch.view');
    Route::get('quran/batches/session-start', [Teacher\QuranBatchController::class, 'sessionStart'])->name('quran.batches.session-start')->middleware('permission:quran.tasmee.create,quran_review.create');
    Route::post('quran/batches/{batch}/repeat', [Teacher\QuranBatchController::class, 'repeat'])->name('quran.batches.repeat')->middleware('permission:quran_batch.update');
    Route::post('quran/batches/{batch}/test', [Teacher\QuranBatchController::class, 'test'])->name('quran.batches.test')->middleware('permission:quran_listening.test');
    Route::post('quran/batches/{batch}/placement-test', [Teacher\QuranBatchController::class, 'placementTest'])->name('quran.batches.placement-test')->middleware('permission:quran_listening.test');
    Route::post('quran/batches/{batch}/retake', [Teacher\QuranBatchController::class, 'retake'])->name('quran.batches.retake')->middleware('permission:quran_batch.update');

    // ---- برامج الاستماع للمعلم: تسميع الأجزاء ← اختبار تراكمي (إجازة/تأهيلي) ----
    Route::get('quran/programs', [Teacher\QuranListeningProgramController::class, 'index'])->name('quran.programs.index')->middleware('permission:quran_training.view');
    Route::get('quran/programs/items/{item}/tasmee', [Teacher\QuranListeningProgramController::class, 'tasmee'])->name('quran.programs.items.tasmee')->middleware('permission:quran_training.listen');
    Route::post('quran/programs/items/{item}/tasmee', [Teacher\QuranListeningProgramController::class, 'storeTasmee'])->name('quran.programs.items.tasmee.store')->middleware('permission:quran_training.listen');
    Route::post('quran/programs/batches/{batch}/test', [Teacher\QuranListeningProgramController::class, 'test'])->name('quran.programs.batches.test')->middleware('permission:quran_training.test');
    Route::post('quran/programs/batches/{batch}/placement-test', [Teacher\QuranListeningProgramController::class, 'placementTest'])->name('quran.programs.batches.placement-test')->middleware('permission:quran_training.test');
    Route::post('quran/programs/{program}/cancel', [Teacher\QuranListeningProgramController::class, 'cancel'])->name('quran.programs.cancel')->middleware('permission:quran_training.update');
    Route::get('quran/programs/{program}', [Teacher\QuranListeningProgramController::class, 'show'])->name('quran.programs.show')->middleware('permission:quran_training.view');

    Route::get('reward-points', [Teacher\RewardPointController::class, 'index'])->name('reward-points.index')->middleware('permission:reward_points.view');
    Route::get('reward-points/create', [Teacher\RewardPointController::class, 'create'])->name('reward-points.create')->middleware('permission:reward_points.create');
    Route::post('reward-points', [Teacher\RewardPointController::class, 'store'])->name('reward-points.store')->middleware('permission:reward_points.create');
    Route::delete('reward-points/{id}', [Teacher\RewardPointController::class, 'destroy'])->name('reward-points.destroy')->middleware('permission:reward_points.delete');

    // ---- البرامج القرآنية للمعلم (spec: mosque_management_quran_programs.md) ----
    Route::get('quran', [Teacher\QuranProgramController::class, 'index'])->name('quran.index')->middleware('permission:quran.tasmee.view');
    Route::get('quran/students/{student}', [Teacher\QuranProgramController::class, 'journey'])->name('quran.students.journey')->middleware('permission:quran.tasmee.view');

    Route::get('quran/tasmee', [Teacher\QuranTasmeeController::class, 'index'])->name('quran.tasmee.index')->middleware('permission:quran.tasmee.view');
    Route::get('quran/tasmee/create', [Teacher\QuranTasmeeController::class, 'create'])->name('quran.tasmee.create')->middleware('permission:quran.tasmee.create');
    Route::get('quran/tasmee/review/{session?}', [Teacher\QuranTasmeeController::class, 'review'])->name('quran.tasmee.review')->middleware('permission:quran.tasmee.create,quran.tasmee.update');
    Route::post('quran/tasmee', [Teacher\QuranTasmeeController::class, 'store'])->name('quran.tasmee.store')->middleware('permission:quran.tasmee.create');
    Route::get('quran/tasmee/{session}/edit', [Teacher\QuranTasmeeController::class, 'edit'])->name('quran.tasmee.edit')->middleware('permission:quran.tasmee.update');
    Route::patch('quran/tasmee/{session}', [Teacher\QuranTasmeeController::class, 'update'])->name('quran.tasmee.update')->middleware('permission:quran.tasmee.update');

    Route::get('quran/qualifying', [Teacher\QualifyingController::class, 'index'])->name('quran.qualifying.index')->middleware('permission:qualifying.view');
    Route::get('quran/qualifying/evaluations/create', [Teacher\QualifyingController::class, 'create'])->name('quran.qualifying.evaluations.create')->middleware('permission:qualifying.create');
    Route::post('quran/qualifying/evaluations', [Teacher\QualifyingController::class, 'store'])->name('quran.qualifying.evaluations.store')->middleware('permission:qualifying.create');

    Route::get('quran/ijazah', [Teacher\IjazahController::class, 'index'])->name('quran.ijazah.index')->middleware('permission:ijazah.view');
    Route::get('quran/ijazah/evaluations/create', [Teacher\IjazahController::class, 'create'])->name('quran.ijazah.evaluations.create')->middleware('permission:ijazah.create');
    Route::post('quran/ijazah/evaluations', [Teacher\IjazahController::class, 'store'])->name('quran.ijazah.evaluations.store')->middleware('permission:ijazah.create');
    Route::get('quran/ijazah/{student}/month/{month}', [Teacher\IjazahController::class, 'month'])
        ->where('month', '\d{4}-(0[1-9]|1[0-2])')->name('quran.ijazah.month')->middleware('permission:ijazah.view');
    Route::post('quran/ijazah/weekly', [Teacher\IjazahController::class, 'storeWeekly'])->name('quran.ijazah.weekly.store')->middleware('permission:ijazah.create');
    Route::patch('quran/ijazah/weekly/{evaluation}', [Teacher\IjazahController::class, 'updateWeekly'])->name('quran.ijazah.weekly.update')->middleware('permission:ijazah.update');

    Route::get('quran/exams', [Teacher\HafizExamController::class, 'index'])->name('quran.exams.index')->middleware('permission:hafiz_exams.view');
    Route::get('quran/exams/month/{month}', [Teacher\HafizExamController::class, 'month'])
        ->where('month', '\d{4}-(0[1-9]|1[0-2])')->name('quran.exams.month')->middleware('permission:hafiz_exams.view');
    Route::get('quran/exams/{exam}', [Teacher\HafizExamController::class, 'show'])->name('quran.exams.show')->middleware('permission:hafiz_exams.view');
    Route::post('quran/exams/{exam}/grade', [Teacher\HafizExamController::class, 'grade'])->name('quran.exams.grade')->middleware('permission:hafiz_exams.grade');
    Route::post('quran/exams/{exam}/revisions', [Teacher\HafizExamController::class, 'storeRevision'])->name('quran.exams.revisions.store')->middleware('permission:hafiz_exams.update');
    Route::post('quran/exams/revisions/{revision}/complete', [Teacher\HafizExamController::class, 'completeRevision'])->name('quran.exams.revisions.complete')->middleware('permission:hafiz_exams.update');

    Route::get('quran/faith-meetings', [Teacher\FaithMeetingController::class, 'index'])->name('quran.faith-meetings.index')->middleware('permission:faith_meetings.view');
    Route::get('quran/faith-meetings/{meeting}', [Teacher\FaithMeetingController::class, 'show'])->name('quran.faith-meetings.show')->middleware('permission:faith_meetings.view');
    Route::post('quran/faith-meetings/{meeting}/attendance', [Teacher\FaithMeetingController::class, 'attendance'])->name('quran.faith-meetings.attendance')->middleware('permission:faith_meetings.attendance');
    Route::post('quran/faith-meetings/{meeting}/notes', [Teacher\FaithMeetingController::class, 'storeNote'])->name('quran.faith-meetings.notes.store')->middleware('permission:faith_meetings.update');
    Route::post('quran/faith-meetings/notes/{note}/complete', [Teacher\FaithMeetingController::class, 'completeNote'])->name('quran.faith-meetings.notes.complete')->middleware('permission:faith_meetings.update');
    Route::post('quran/faith-meetings/{meeting}/complete', [Teacher\FaithMeetingController::class, 'complete'])->name('quran.faith-meetings.complete')->middleware('permission:faith_meetings.update');

    // ---- الدورات الشرعية للمعلم (عرض + حضور فقط) ----
    Route::get('sharia-courses', [Teacher\ShariaCourseController::class, 'index'])->name('sharia-courses.index')->middleware('permission:sharia_courses.view');
    Route::get('sharia-courses/{course}', [Teacher\ShariaCourseController::class, 'show'])->name('sharia-courses.show')->middleware('permission:sharia_courses.view');
    Route::post('sharia-courses/{course}/attendance', [Teacher\ShariaCourseController::class, 'storeAttendance'])->name('sharia-courses.attendance.store')->middleware('permission:sharia_courses.attendance');
    Route::patch('sharia-courses/students/{student}/memorization', [Teacher\ShariaCourseController::class, 'updateMemorization'])->name('sharia-courses.students.memorization')->middleware('permission:sharia_courses.memorization');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('dashboard', [SuperAdmin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('mosques', [SuperAdmin\MosqueController::class, 'index'])->name('mosques.index');
    Route::get('mosques/create', [SuperAdmin\MosqueController::class, 'create'])->name('mosques.create');
    Route::post('mosques', [SuperAdmin\MosqueController::class, 'store'])->name('mosques.store');
    Route::get('mosques/{mosque}/edit', [SuperAdmin\MosqueController::class, 'edit'])->name('mosques.edit');
    Route::patch('mosques/{mosque}', [SuperAdmin\MosqueController::class, 'update'])->name('mosques.update');
    Route::delete('mosques/{mosque}', [SuperAdmin\MosqueController::class, 'destroy'])->name('mosques.destroy');
    Route::post('mosques/{mosque}/enter', [SuperAdmin\MosqueController::class, 'enter'])->name('mosques.enter');
    Route::post('switch-mosque', [SuperAdmin\MosqueController::class, 'switchMosque'])->name('switch-mosque');
    Route::post('exit', [SuperAdmin\MosqueController::class, 'exit'])->name('exit');

    Route::get('mosques/{mosque}/users', [SuperAdmin\MosqueUserController::class, 'index'])->name('mosques.users.index');
    Route::post('mosques/{mosque}/users', [SuperAdmin\MosqueUserController::class, 'store'])->name('mosques.users.store');
    Route::get('mosques/{mosque}/users/{user}/edit', [SuperAdmin\MosqueUserController::class, 'edit'])->name('mosques.users.edit');
    Route::patch('mosques/{mosque}/users/{user}', [SuperAdmin\MosqueUserController::class, 'update'])->name('mosques.users.update');
    Route::patch('mosques/{mosque}/users/{user}/role', [SuperAdmin\MosqueUserController::class, 'updateRole'])->name('mosques.users.role');
    Route::get('mosques/{mosque}/users/{user}/permissions', [SuperAdmin\MosqueUserController::class, 'permissions'])->name('mosques.users.permissions');
    Route::patch('mosques/{mosque}/users/{user}/permissions', [SuperAdmin\MosqueUserController::class, 'updatePermissions'])->name('mosques.users.permissions.update');
    Route::delete('mosques/{mosque}/users/{user}', [SuperAdmin\MosqueUserController::class, 'destroy'])->name('mosques.users.destroy');

    Route::get('mosques/{mosque}/roles', [SuperAdmin\MosqueRoleController::class, 'index'])->name('mosques.roles.index');
    Route::post('mosques/{mosque}/roles', [SuperAdmin\MosqueRoleController::class, 'store'])->name('mosques.roles.store');
    Route::get('mosques/{mosque}/roles/{role}/edit', [SuperAdmin\MosqueRoleController::class, 'edit'])->name('mosques.roles.edit');
    Route::patch('mosques/{mosque}/roles/{role}', [SuperAdmin\MosqueRoleController::class, 'updatePermissions'])->name('mosques.roles.update');
    Route::delete('mosques/{mosque}/roles/{role}', [SuperAdmin\MosqueRoleController::class, 'destroy'])->name('mosques.roles.destroy');

    // ---- الدورات الشرعية: إنشاء مركزي وربط بجامع + إشعار مديره ----
    Route::get('sharia-courses', [SuperAdmin\ShariaCourseController::class, 'index'])->name('sharia-courses.index');
    Route::get('sharia-courses/create', [SuperAdmin\ShariaCourseController::class, 'create'])->name('sharia-courses.create');
    Route::get('sharia-courses/options', [SuperAdmin\ShariaCourseController::class, 'options'])->name('sharia-courses.options');
    Route::post('sharia-courses', [SuperAdmin\ShariaCourseController::class, 'store'])->name('sharia-courses.store');
});

// ---- Shared in-app notifications inbox (all authenticated roles) ----
Route::middleware('auth')->group(function () {
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationsController::class, 'read'])->name('notifications.read');
});

// ---- Parent / Guardian portal (spec §2-§10) ----
Route::middleware(['auth', 'role:guardian'])->prefix('guardian')->name('guardian.')->group(function () {
    Route::get('dashboard', [Guardian\DashboardController::class, 'index'])->name('dashboard');
    Route::get('profile', [Guardian\ProfileController::class, 'show'])->name('profile');

    Route::get('children/{student}/overview', [Guardian\ChildController::class, 'overview'])->name('children.overview');
    Route::get('children/{student}/attendance', [Guardian\ChildController::class, 'attendance'])->name('children.attendance');
    Route::get('children/{student}/subjects', [Guardian\ChildController::class, 'subjects'])->name('children.subjects');
    Route::get('children/{student}/teachers', [Guardian\ChildController::class, 'teachers'])->name('children.teachers');
    Route::get('children/{student}/exams', [Guardian\ChildController::class, 'exams'])->name('children.exams');
    Route::get('children/{student}/grades', [Guardian\ChildController::class, 'grades'])->name('children.grades');
    Route::get('children/{student}/homeworks', [Guardian\ChildController::class, 'homeworks'])->name('children.homeworks');
    Route::get('children/{student}/announcements', [Guardian\ChildController::class, 'announcements'])->name('children.announcements');
});

// ---- Student portal (spec §11-§18) ----
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('dashboard', [StudentPortal\DashboardController::class, 'index'])->name('dashboard');
    Route::get('profile', [StudentPortal\ProfileController::class, 'show'])->name('profile');
    Route::get('attendance', [StudentPortal\PortalController::class, 'attendance'])->name('attendance');
    Route::get('subjects', [StudentPortal\PortalController::class, 'subjects'])->name('subjects');
    Route::get('teachers', [StudentPortal\PortalController::class, 'teachers'])->name('teachers');
    Route::get('exams', [StudentPortal\PortalController::class, 'exams'])->name('exams');
    Route::get('exams/{exam}/start', [StudentPortal\ExamController::class, 'start'])->name('exams.start');
    Route::get('exams/{exam}/take', [StudentPortal\ExamController::class, 'take'])->name('exams.take');
    Route::post('exams/{exam}/submit', [StudentPortal\ExamController::class, 'submit'])->name('exams.submit');
    Route::get('exams/{exam}/result', [StudentPortal\ExamController::class, 'result'])->name('exams.result');
    Route::get('grades', [StudentPortal\PortalController::class, 'grades'])->name('grades');
    Route::get('homeworks', [StudentPortal\PortalController::class, 'homeworks'])->name('homeworks');
    Route::post('homeworks/{homework}/submit', [StudentPortal\PortalController::class, 'submitHomework'])->name('homeworks.submit');
    Route::get('announcements', [StudentPortal\PortalController::class, 'announcements'])->name('announcements');
    Route::get('reward-points', [StudentPortal\RewardPointController::class, 'index'])->name('reward-points');
    Route::get('quran-khamsa', [StudentPortal\KhamsaController::class, 'index'])->name('quran-khamsa');

    // ---- «ملفي القرآني»: الشاشة الموحدة (الإنجاز + الدفعة الحالية + الدورة) ----
    Route::get('quran', [StudentPortal\QuranProfileController::class, 'index'])->name('quran-profile')->middleware('permission:quran_batch.view');

    // ---- «خطة الاستماع»: عرض الخطة + قائمة التشغيل + تسجيل الاستماع ----
    // مسارات العناصر قبل {plan} حتى لا تلتقط الكلمة الثابتة.
    Route::get('quran-listening', [StudentPortal\QuranListeningController::class, 'index'])->name('quran-listening.index')->middleware('permission:quran_listening.view');
    Route::post('quran-listening/items/{item}/listen', [StudentPortal\QuranListeningController::class, 'listen'])->name('quran-listening.items.listen')->middleware('permission:quran_listening.listen');
    Route::get('quran-listening/items/{item}/audio', [StudentPortal\QuranListeningController::class, 'audio'])->name('quran-listening.items.audio')->middleware('permission:quran_listening.view');
    Route::post('quran-listening/items/{item}/progress', [StudentPortal\QuranListeningController::class, 'progress'])->name('quran-listening.items.progress')->middleware('permission:quran_listening.listen');
    Route::get('quran-listening/{plan}', [StudentPortal\QuranListeningController::class, 'show'])->name('quran-listening.show');

    // ---- «برامجي»: برامج الاستماع (إجازة/تأهيلي) — عرض فقط ----
    Route::get('quran-programs', [StudentPortal\QuranListeningProgramController::class, 'index'])->name('quran-programs.index')->middleware('permission:quran_training.view');
});

// ---- Sheikh portal additions: sections & finance ledger (spec §19-§32) ----
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('sections', [Teacher\SectionController::class, 'index'])->name('sections.index')->middleware('permission:sections.view');
    Route::get('sections/{section}', [Teacher\SectionController::class, 'show'])->name('sections.show')->middleware('permission:sections.view');

    Route::get('finance', [Teacher\FinanceController::class, 'index'])->name('finance.index')->middleware('permission:finance.view');
    Route::get('finance/receive', [Teacher\FinanceController::class, 'receiveForm'])->name('finance.receive')->middleware('permission:finance.create');
    Route::post('finance/receive', [Teacher\FinanceController::class, 'receive'])->name('finance.receive.store')->middleware('permission:finance.create');
    Route::get('finance/transfer', [Teacher\FinanceController::class, 'transferForm'])->name('finance.transfer')->middleware('permission:finance.transfer');
    Route::post('finance/transfer', [Teacher\FinanceController::class, 'transfer'])->name('finance.transfer.store')->middleware('permission:finance.transfer');
    Route::post('finance/adjust', [Teacher\FinanceController::class, 'adjust'])->name('finance.adjust')->middleware('permission:finance.adjust');
    Route::post('finance/transactions/{transaction}/reverse', [Teacher\FinanceController::class, 'reverse'])->name('finance.reverse')->middleware('permission:finance.adjust');
});
