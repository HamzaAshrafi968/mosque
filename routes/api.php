<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [V1\AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::post('logout', [V1\AuthController::class, 'logout']);
        Route::get('me', [V1\AuthController::class, 'me']);

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [V1\Admin\DashboardController::class, 'index']);

            Route::patch('students/{id}/archive', [V1\Admin\StudentController::class, 'archive'])->middleware('permission:students.archive');
            Route::post('students/{id}/transfer', [V1\Admin\StudentController::class, 'transfer'])->middleware('permission:students.transfer');
            Route::apiResource('students', V1\Admin\StudentController::class)
                ->middlewareFor(['index', 'show'], 'permission:students.view')
                ->middlewareFor('store', 'permission:students.create')
                ->middlewareFor('update', 'permission:students.update')
                ->middlewareFor('destroy', 'permission:students.delete');

            Route::apiResource('teachers', V1\Admin\TeacherController::class)->except(['show'])
                ->middlewareFor('index', 'permission:teachers.view')
                ->middlewareFor('store', 'permission:teachers.create')
                ->middlewareFor('update', 'permission:teachers.update')
                ->middlewareFor('destroy', 'permission:teachers.delete');

            Route::get('classrooms', [V1\Admin\ClassroomController::class, 'index'])->middleware('permission:classes.view');
            Route::post('classrooms', [V1\Admin\ClassroomController::class, 'store'])->middleware('permission:classes.create');
            Route::delete('classrooms/{id}', [V1\Admin\ClassroomController::class, 'destroy'])->middleware('permission:classes.delete');
            Route::post('classrooms/{classroomId}/sections', [V1\Admin\ClassroomController::class, 'storeSection'])->middleware('permission:sections.create');
            Route::get('sections/{sectionId}', [V1\Admin\ClassroomController::class, 'showSection'])->middleware('permission:sections.view');
            Route::patch('sections/{sectionId}', [V1\Admin\ClassroomController::class, 'updateSection'])->middleware('permission:sections.update');
            Route::delete('sections/{sectionId}', [V1\Admin\ClassroomController::class, 'destroySection'])->middleware('permission:sections.delete');
            Route::post('sections/{sectionId}/students', [V1\Admin\ClassroomController::class, 'enrollStudent'])->middleware('permission:sections.update');
            Route::delete('sections/{sectionId}/students/{studentId}', [V1\Admin\ClassroomController::class, 'removeStudent'])->middleware('permission:sections.update');
            Route::post('sections/{sectionId}/teachers', [V1\Admin\ClassroomController::class, 'assignTeacher'])->middleware('permission:sections.update');
            Route::delete('sections/{sectionId}/teachers/{teacherId}', [V1\Admin\ClassroomController::class, 'removeTeacher'])->middleware('permission:sections.update');

            Route::get('custom-fields', [V1\Admin\CustomFieldController::class, 'index'])->middleware('permission:custom_fields.view');
            Route::post('custom-fields', [V1\Admin\CustomFieldController::class, 'store'])->middleware('permission:custom_fields.create');
            Route::patch('custom-fields/{id}', [V1\Admin\CustomFieldController::class, 'update'])->middleware('permission:custom_fields.update');
            Route::delete('custom-fields/{id}', [V1\Admin\CustomFieldController::class, 'destroy'])->middleware('permission:custom_fields.delete');

            Route::apiResource('subjects', V1\Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy'])
                ->middlewareFor('index', 'permission:subjects.view')
                ->middlewareFor('store', 'permission:subjects.create')
                ->middlewareFor('update', 'permission:subjects.update')
                ->middlewareFor('destroy', 'permission:subjects.delete');

            Route::get('schedules', [V1\Admin\ScheduleController::class, 'index'])->middleware('permission:schedule.view');
            Route::post('schedules', [V1\Admin\ScheduleController::class, 'store'])->middleware('permission:schedule.create');
            Route::post('schedules/generate', [V1\Admin\ScheduleController::class, 'generate'])->middleware('permission:schedule.create');
            Route::delete('schedules/{id}', [V1\Admin\ScheduleController::class, 'destroy'])->middleware('permission:schedule.delete');

            Route::get('programs', [V1\Admin\ProgramController::class, 'index'])->middleware('permission:programs.view');
            Route::get('programs/{program}', [V1\Admin\ProgramController::class, 'show'])->middleware('permission:programs.view');
            Route::post('programs', [V1\Admin\ProgramController::class, 'store'])->middleware('permission:programs.create');
            Route::patch('programs/{program}', [V1\Admin\ProgramController::class, 'update'])->middleware('permission:programs.update');
            Route::delete('programs/{program}', [V1\Admin\ProgramController::class, 'destroy'])->middleware('permission:programs.delete');

            Route::get('attendance', [V1\Admin\AttendanceController::class, 'index'])->middleware('permission:attendance.view');
            Route::post('attendance/students', [V1\Admin\AttendanceController::class, 'storeStudents'])->middleware('permission:attendance.create');
            Route::post('attendance/teachers', [V1\Admin\AttendanceController::class, 'storeTeachers'])->middleware('permission:attendance.create');

            Route::get('finance/people', [V1\Admin\FinanceController::class, 'people'])->middleware('permission:finance.view');
            Route::get('finance/people/{personType}/{personId}', [V1\Admin\FinanceController::class, 'person'])->middleware('permission:finance.view');
            Route::post('finance/transactions', [V1\Admin\FinanceController::class, 'store'])->middleware('permission:finance.create');
            Route::post('finance/transactions/{transactionId}/reverse', [V1\Admin\FinanceController::class, 'reverse'])->middleware('permission:finance.update');
            Route::post('finance/transfers', [V1\Admin\FinanceController::class, 'transfer'])->middleware('permission:finance.transfer');

            Route::apiResource('exams', V1\Admin\ExamController::class)->only(['index', 'store', 'destroy'])
                ->middlewareFor('index', 'permission:exams.view')
                ->middlewareFor('store', 'permission:exams.create')
                ->middlewareFor('destroy', 'permission:exams.delete');

            Route::get('grades', [V1\Admin\GradeController::class, 'index'])->middleware('permission:grades.view');
            Route::get('grades/{examId}', [V1\Admin\GradeController::class, 'show'])->middleware('permission:grades.view');
            Route::patch('grades/{examId}/approve', [V1\Admin\GradeController::class, 'approve'])->middleware('permission:grades.approve');

            Route::get('reports', [V1\Admin\ReportController::class, 'index'])->middleware('permission:reports.view');

            Route::get('quran-review', [V1\Admin\QuranReviewController::class, 'index'])->middleware('permission:quran_review.view');
            Route::get('quran-review/statistics', [V1\Admin\QuranReviewController::class, 'statistics'])->middleware('permission:quran_review.view');
            Route::get('quran-review/student/{studentId}', [V1\Admin\QuranReviewController::class, 'studentReport'])->middleware('permission:quran_review.view');
            Route::get('quran-review/{id}', [V1\Admin\QuranReviewController::class, 'show'])->middleware('permission:quran_review.view');

            Route::get('quran/pages/{page}', [V1\Admin\QuranPageController::class, 'show'])->whereNumber('page')->middleware('permission:quran.tasmee.view');

            Route::get('announcements', [V1\Admin\AnnouncementController::class, 'index'])->middleware('permission:announcements.view');
            Route::post('announcements', [V1\Admin\AnnouncementController::class, 'store'])->middleware('permission:announcements.create');
            Route::delete('announcements/{id}', [V1\Admin\AnnouncementController::class, 'destroy'])->middleware('permission:announcements.delete');

            Route::apiResource('users', V1\Admin\UserController::class)->only(['index', 'store', 'update', 'destroy'])
                ->middlewareFor('index', 'permission:users.view')
                ->middlewareFor('store', 'permission:users.create')
                ->middlewareFor('update', 'permission:users.update')
                ->middlewareFor('destroy', 'permission:users.delete');

            Route::get('reward-points', [V1\Admin\RewardPointController::class, 'index'])->middleware('permission:reward_points.view');
        });

        Route::middleware('role:teacher')->prefix('teacher')->group(function () {
            Route::get('dashboard', [V1\Teacher\DashboardController::class, 'index']);

            Route::get('schedule', [V1\Teacher\ScheduleController::class, 'index'])->middleware('permission:schedule.view');

            Route::get('attendance/sections', [V1\Teacher\AttendanceController::class, 'sections'])->middleware('permission:attendance.view');
            Route::get('attendance/students', [V1\Teacher\AttendanceController::class, 'students'])->middleware('permission:attendance.view');
            Route::post('attendance', [V1\Teacher\AttendanceController::class, 'store'])->middleware('permission:attendance.create');

            Route::get('homeworks/{homework}/submissions', [V1\Teacher\HomeworkController::class, 'submissions'])->middleware('permission:assignments.grade');
            Route::patch('submissions/{submission}', [V1\Teacher\HomeworkController::class, 'updateSubmission'])->middleware('permission:assignments.grade');
            Route::apiResource('homeworks', V1\Teacher\HomeworkController::class)->only(['index', 'store', 'destroy'])
                ->middlewareFor('index', 'permission:assignments.view')
                ->middlewareFor('store', 'permission:assignments.create')
                ->middlewareFor('destroy', 'permission:assignments.delete');

            Route::apiResource('exams', V1\Teacher\ExamController::class)->only(['index', 'store'])
                ->middlewareFor('index', 'permission:exams.view')
                ->middlewareFor('store', 'permission:exams.create');

            Route::get('exams/{exam}/grades', [V1\Teacher\GradeController::class, 'show'])->middleware('permission:grades.view');
            Route::post('exams/{exam}/grades', [V1\Teacher\GradeController::class, 'store'])->middleware('permission:grades.create,grades.update');

            Route::apiResource('lessons', V1\Teacher\LessonController::class)->only(['index', 'store', 'destroy'])
                ->middlewareFor('index', 'permission:lessons.view')
                ->middlewareFor('store', 'permission:lessons.create')
                ->middlewareFor('destroy', 'permission:lessons.delete');

            Route::get('messages', [V1\Teacher\MessageController::class, 'index'])->middleware('permission:messages.view');
            Route::post('messages', [V1\Teacher\MessageController::class, 'store'])->middleware('permission:messages.create');

            Route::get('profile', [V1\Teacher\ProfileController::class, 'show']);
            Route::patch('profile', [V1\Teacher\ProfileController::class, 'update']);

            Route::get('quran-review', [V1\Teacher\QuranReviewController::class, 'index'])->middleware('permission:quran_review.view');
            Route::post('quran-review', [V1\Teacher\QuranReviewController::class, 'store'])->middleware('permission:quran_review.create');
            Route::get('quran-review/ayahs', [V1\Teacher\QuranReviewController::class, 'getAyahs'])->middleware('permission:quran_review.view');
            Route::get('quran-review/student/{studentId}', [V1\Teacher\QuranReviewController::class, 'studentReport'])->middleware('permission:quran_review.view');
            Route::get('quran-review/{id}', [V1\Teacher\QuranReviewController::class, 'show'])->middleware('permission:quran_review.view');

            Route::get('quran/pages/{page}', [V1\Teacher\QuranPageController::class, 'show'])->whereNumber('page')->middleware('permission:quran.tasmee.view');

            Route::get('reward-points', [V1\Teacher\RewardPointController::class, 'index'])->middleware('permission:reward_points.view');
            Route::post('reward-points', [V1\Teacher\RewardPointController::class, 'store'])->middleware('permission:reward_points.create');
            Route::delete('reward-points/{id}', [V1\Teacher\RewardPointController::class, 'destroy'])->middleware('permission:reward_points.delete');
        });
    });
});
