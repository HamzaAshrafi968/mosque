<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

Route::post('register', [Api\AuthController::class, 'register']);
Route::post('login', [Api\AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('logout', [Api\AuthController::class, 'logout']);
    Route::get('me', [Api\AuthController::class, 'me']);

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', [Api\Admin\DashboardController::class, 'index']);

        Route::patch('students/{student}/archive', [Api\Admin\StudentController::class, 'archive']);
        Route::apiResource('students', Api\Admin\StudentController::class);

        Route::apiResource('teachers', Api\Admin\TeacherController::class)->except(['show']);

        Route::get('classrooms', [Api\Admin\ClassroomController::class, 'index']);
        Route::post('classrooms', [Api\Admin\ClassroomController::class, 'store']);
        Route::delete('classrooms/{classroom}', [Api\Admin\ClassroomController::class, 'destroy']);
        Route::post('classrooms/{classroom}/sections', [Api\Admin\ClassroomController::class, 'storeSection']);
        Route::delete('sections/{section}', [Api\Admin\ClassroomController::class, 'destroySection']);

        Route::apiResource('subjects', Api\Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('schedules', [Api\Admin\ScheduleController::class, 'index']);
        Route::post('schedules', [Api\Admin\ScheduleController::class, 'store']);
        Route::delete('schedules/{schedule}', [Api\Admin\ScheduleController::class, 'destroy']);

        Route::get('attendance', [Api\Admin\AttendanceController::class, 'index']);

        Route::apiResource('exams', Api\Admin\ExamController::class)->only(['index', 'store', 'destroy']);

        Route::get('grades', [Api\Admin\GradeController::class, 'index']);
        Route::get('grades/{exam}', [Api\Admin\GradeController::class, 'show']);
        Route::patch('grades/{exam}/approve', [Api\Admin\GradeController::class, 'approve']);

        Route::get('reports', [Api\Admin\ReportController::class, 'index']);

        Route::get('quran-review', [Api\Admin\QuranReviewController::class, 'index']);
        Route::get('quran-review/statistics', [Api\Admin\QuranReviewController::class, 'statistics']);
        Route::get('quran-review/student/{student}', [Api\Admin\QuranReviewController::class, 'studentReport']);
        Route::get('quran-review/{id}', [Api\Admin\QuranReviewController::class, 'show']);

        Route::get('announcements', [Api\Admin\AnnouncementController::class, 'index']);
        Route::post('announcements', [Api\Admin\AnnouncementController::class, 'store']);
        Route::delete('announcements/{announcement}', [Api\Admin\AnnouncementController::class, 'destroy']);

        Route::apiResource('users', Api\Admin\UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('role:teacher')->prefix('teacher')->group(function () {
        Route::get('dashboard', [Api\Teacher\DashboardController::class, 'index']);

        Route::get('schedule', [Api\Teacher\ScheduleController::class, 'index']);

        Route::get('attendance/students', [Api\Teacher\AttendanceController::class, 'students']);
        Route::post('attendance', [Api\Teacher\AttendanceController::class, 'store']);

        Route::get('homeworks/{homework}/submissions', [Api\Teacher\HomeworkController::class, 'submissions']);
        Route::patch('submissions/{submission}', [Api\Teacher\HomeworkController::class, 'updateSubmission']);
        Route::apiResource('homeworks', Api\Teacher\HomeworkController::class)->only(['index', 'store', 'destroy']);

        Route::apiResource('exams', Api\Teacher\ExamController::class)->only(['index', 'store']);

        Route::get('exams/{exam}/grades', [Api\Teacher\GradeController::class, 'show']);
        Route::post('exams/{exam}/grades', [Api\Teacher\GradeController::class, 'store']);

        Route::apiResource('lessons', Api\Teacher\LessonController::class)->only(['index', 'store', 'destroy']);

        Route::get('messages', [Api\Teacher\MessageController::class, 'index']);
        Route::post('messages', [Api\Teacher\MessageController::class, 'store']);

        Route::get('profile', [Api\Teacher\ProfileController::class, 'show']);
        Route::patch('profile', [Api\Teacher\ProfileController::class, 'update']);

        Route::get('quran-review', [Api\Teacher\QuranReviewController::class, 'index']);
        Route::post('quran-review', [Api\Teacher\QuranReviewController::class, 'store']);
        Route::get('quran-review/ayahs', [Api\Teacher\QuranReviewController::class, 'getAyahs']);
        Route::get('quran-review/student/{student}', [Api\Teacher\QuranReviewController::class, 'studentReport']);
        Route::get('quran-review/{id}', [Api\Teacher\QuranReviewController::class, 'show']);
    });
});
