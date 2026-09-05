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

            Route::patch('students/{id}/archive', [V1\Admin\StudentController::class, 'archive']);
            Route::post('students/{id}/transfer', [V1\Admin\StudentController::class, 'transfer']);
            Route::apiResource('students', V1\Admin\StudentController::class);

            Route::apiResource('teachers', V1\Admin\TeacherController::class)->except(['show']);

            Route::get('classrooms', [V1\Admin\ClassroomController::class, 'index']);
            Route::post('classrooms', [V1\Admin\ClassroomController::class, 'store']);
            Route::delete('classrooms/{id}', [V1\Admin\ClassroomController::class, 'destroy']);
            Route::post('classrooms/{classroomId}/sections', [V1\Admin\ClassroomController::class, 'storeSection']);
            Route::get('sections/{sectionId}', [V1\Admin\ClassroomController::class, 'showSection']);
            Route::patch('sections/{sectionId}', [V1\Admin\ClassroomController::class, 'updateSection']);
            Route::delete('sections/{sectionId}', [V1\Admin\ClassroomController::class, 'destroySection']);
            Route::post('sections/{sectionId}/students', [V1\Admin\ClassroomController::class, 'enrollStudent']);
            Route::delete('sections/{sectionId}/students/{studentId}', [V1\Admin\ClassroomController::class, 'removeStudent']);
            Route::post('sections/{sectionId}/teachers', [V1\Admin\ClassroomController::class, 'assignTeacher']);
            Route::delete('sections/{sectionId}/teachers/{teacherId}', [V1\Admin\ClassroomController::class, 'removeTeacher']);

            Route::get('custom-fields', [V1\Admin\CustomFieldController::class, 'index']);
            Route::post('custom-fields', [V1\Admin\CustomFieldController::class, 'store']);
            Route::patch('custom-fields/{id}', [V1\Admin\CustomFieldController::class, 'update']);
            Route::delete('custom-fields/{id}', [V1\Admin\CustomFieldController::class, 'destroy']);

            Route::apiResource('subjects', V1\Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy']);

            Route::get('schedules', [V1\Admin\ScheduleController::class, 'index']);
            Route::post('schedules', [V1\Admin\ScheduleController::class, 'store']);
            Route::delete('schedules/{id}', [V1\Admin\ScheduleController::class, 'destroy']);

            Route::get('attendance', [V1\Admin\AttendanceController::class, 'index']);
            Route::post('attendance/students', [V1\Admin\AttendanceController::class, 'storeStudents']);
            Route::post('attendance/teachers', [V1\Admin\AttendanceController::class, 'storeTeachers']);

            Route::get('finance/people', [V1\Admin\FinanceController::class, 'people']);
            Route::get('finance/people/{personType}/{personId}', [V1\Admin\FinanceController::class, 'person']);
            Route::post('finance/transactions', [V1\Admin\FinanceController::class, 'store']);
            Route::post('finance/transactions/{transactionId}/reverse', [V1\Admin\FinanceController::class, 'reverse']);
            Route::post('finance/transfers', [V1\Admin\FinanceController::class, 'transfer']);

            Route::apiResource('exams', V1\Admin\ExamController::class)->only(['index', 'store', 'destroy']);

            Route::get('grades', [V1\Admin\GradeController::class, 'index']);
            Route::get('grades/{examId}', [V1\Admin\GradeController::class, 'show']);
            Route::patch('grades/{examId}/approve', [V1\Admin\GradeController::class, 'approve']);

            Route::get('reports', [V1\Admin\ReportController::class, 'index']);

            Route::get('quran-review', [V1\Admin\QuranReviewController::class, 'index']);
            Route::get('quran-review/statistics', [V1\Admin\QuranReviewController::class, 'statistics']);
            Route::get('quran-review/student/{studentId}', [V1\Admin\QuranReviewController::class, 'studentReport']);
            Route::get('quran-review/{id}', [V1\Admin\QuranReviewController::class, 'show']);

            Route::get('announcements', [V1\Admin\AnnouncementController::class, 'index']);
            Route::post('announcements', [V1\Admin\AnnouncementController::class, 'store']);
            Route::delete('announcements/{id}', [V1\Admin\AnnouncementController::class, 'destroy']);

            Route::apiResource('users', V1\Admin\UserController::class)->only(['index', 'store', 'update', 'destroy']);

            Route::get('reward-points', [V1\Admin\RewardPointController::class, 'index']);
        });

        Route::middleware('role:teacher')->prefix('teacher')->group(function () {
            Route::get('dashboard', [V1\Teacher\DashboardController::class, 'index']);

            Route::get('schedule', [V1\Teacher\ScheduleController::class, 'index']);

            Route::get('attendance/sections', [V1\Teacher\AttendanceController::class, 'sections']);
            Route::get('attendance/students', [V1\Teacher\AttendanceController::class, 'students']);
            Route::post('attendance', [V1\Teacher\AttendanceController::class, 'store']);

            Route::get('homeworks/{homework}/submissions', [V1\Teacher\HomeworkController::class, 'submissions']);
            Route::patch('submissions/{submission}', [V1\Teacher\HomeworkController::class, 'updateSubmission']);
            Route::apiResource('homeworks', V1\Teacher\HomeworkController::class)->only(['index', 'store', 'destroy']);

            Route::apiResource('exams', V1\Teacher\ExamController::class)->only(['index', 'store']);

            Route::get('exams/{exam}/grades', [V1\Teacher\GradeController::class, 'show']);
            Route::post('exams/{exam}/grades', [V1\Teacher\GradeController::class, 'store']);

            Route::apiResource('lessons', V1\Teacher\LessonController::class)->only(['index', 'store', 'destroy']);

            Route::get('messages', [V1\Teacher\MessageController::class, 'index']);
            Route::post('messages', [V1\Teacher\MessageController::class, 'store']);

            Route::get('profile', [V1\Teacher\ProfileController::class, 'show']);
            Route::patch('profile', [V1\Teacher\ProfileController::class, 'update']);

            Route::get('quran-review', [V1\Teacher\QuranReviewController::class, 'index']);
            Route::post('quran-review', [V1\Teacher\QuranReviewController::class, 'store']);
            Route::get('quran-review/ayahs', [V1\Teacher\QuranReviewController::class, 'getAyahs']);
            Route::get('quran-review/student/{studentId}', [V1\Teacher\QuranReviewController::class, 'studentReport']);
            Route::get('quran-review/{id}', [V1\Teacher\QuranReviewController::class, 'show']);

            Route::get('reward-points', [V1\Teacher\RewardPointController::class, 'index']);
            Route::post('reward-points', [V1\Teacher\RewardPointController::class, 'store']);
            Route::delete('reward-points/{id}', [V1\Teacher\RewardPointController::class, 'destroy']);
        });
    });
});
