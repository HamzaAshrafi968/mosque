<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Teacher;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(match (true) {
        auth()->user()->isSuperAdmin() => 'super-admin.dashboard',
        auth()->user()->isAdmin() => 'admin.dashboard',
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

    Route::patch('students/{student}/archive', [Admin\StudentController::class, 'archive'])->name('students.archive');
    Route::resource('students', Admin\StudentController::class);

    Route::resource('teachers', Admin\TeacherController::class);
    Route::post('teachers/{teacher}/ratings', [Admin\TeacherController::class, 'storeRating'])->name('teachers.ratings.store');
    Route::delete('teachers/{teacher}/ratings/{rating}', [Admin\TeacherController::class, 'destroyRating'])->name('teachers.ratings.destroy');
    Route::post('teachers/{teacher}/certificates', [Admin\TeacherController::class, 'storeCertificate'])->name('teachers.certificates.store');
    Route::delete('teachers/{teacher}/certificates/{certificate}', [Admin\TeacherController::class, 'destroyCertificate'])->name('teachers.certificates.destroy');

    Route::get('classrooms', [Admin\ClassroomController::class, 'index'])->name('classrooms.index');
    Route::post('classrooms', [Admin\ClassroomController::class, 'store'])->name('classrooms.store');
    Route::delete('classrooms/{classroom}', [Admin\ClassroomController::class, 'destroy'])->name('classrooms.destroy');
    Route::post('classrooms/{classroom}/sections', [Admin\ClassroomController::class, 'storeSection'])->name('sections.store');
    Route::delete('sections/{section}', [Admin\ClassroomController::class, 'destroySection'])->name('sections.destroy');

    Route::resource('subjects', Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('schedules', [Admin\ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules', [Admin\ScheduleController::class, 'store'])->name('schedules.store');
    Route::delete('schedules/{schedule}', [Admin\ScheduleController::class, 'destroy'])->name('schedules.destroy');

    Route::get('attendance', [Admin\AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [Admin\AttendanceController::class, 'store'])->name('attendance.store');

    Route::resource('exams', Admin\ExamController::class)->only(['index', 'create', 'store', 'destroy']);

    Route::get('grades', [Admin\GradeController::class, 'index'])->name('grades.index');
    Route::get('grades/{exam}', [Admin\GradeController::class, 'show'])->name('grades.show');
    Route::patch('grades/{exam}/approve', [Admin\GradeController::class, 'approve'])->name('grades.approve');

    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');

    Route::get('announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('announcements/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::get('quran-review', [Admin\QuranReviewController::class, 'index'])->name('quran-review.index');
    Route::get('quran-review/statistics', [Admin\QuranReviewController::class, 'statistics'])->name('quran-review.statistics');
    Route::get('quran-review/{id}', [Admin\QuranReviewController::class, 'show'])->name('quran-review.show');
    Route::get('quran-review/student/{student}', [Admin\QuranReviewController::class, 'studentReport'])->name('quran-review.student-report');

    Route::get('reward-points', [Admin\RewardPointController::class, 'index'])->name('reward-points.index');

    Route::resource('users', Admin\UserController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('dashboard', [Teacher\DashboardController::class, 'index'])->name('dashboard');

    Route::get('schedule', [Teacher\ScheduleController::class, 'index'])->name('schedule');

    Route::get('attendance', [Teacher\AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('attendance', [Teacher\AttendanceController::class, 'store'])->name('attendance.store');

    Route::get('homeworks/{homework}/submissions', [Teacher\HomeworkController::class, 'submissions'])->name('homeworks.submissions');
    Route::patch('submissions/{submission}', [Teacher\HomeworkController::class, 'updateSubmission'])->name('submissions.update');
    Route::resource('homeworks', Teacher\HomeworkController::class)->only(['index', 'create', 'store', 'destroy']);

    Route::resource('exams', Teacher\ExamController::class)->only(['index', 'create', 'store']);

    Route::get('exams/{exam}/grades', [Teacher\GradeController::class, 'edit'])->name('grades.edit');
    Route::post('exams/{exam}/grades', [Teacher\GradeController::class, 'store'])->name('grades.store');

    Route::resource('lessons', Teacher\LessonController::class)->only(['index', 'create', 'store', 'destroy']);

    Route::get('messages', [Teacher\MessageController::class, 'index'])->name('messages.index');
    Route::post('messages', [Teacher\MessageController::class, 'store'])->name('messages.store');

    Route::get('profile', [Teacher\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [Teacher\ProfileController::class, 'update'])->name('profile.update');

    Route::get('quran-review', [Teacher\QuranReviewController::class, 'index'])->name('quran-review.index');
    Route::get('quran-review/create', [Teacher\QuranReviewController::class, 'create'])->name('quran-review.create');
    Route::post('quran-review', [Teacher\QuranReviewController::class, 'store'])->name('quran-review.store');
    Route::get('quran-review/{id}', [Teacher\QuranReviewController::class, 'show'])->name('quran-review.show');
    Route::get('quran-review/student/{student}', [Teacher\QuranReviewController::class, 'studentReport'])->name('quran-review.student-report');
    Route::get('quran-review/ayahs/json', [Teacher\QuranReviewController::class, 'getAyahs'])->name('quran-review.ayahs');

    Route::get('reward-points', [Teacher\RewardPointController::class, 'index'])->name('reward-points.index');
    Route::get('reward-points/create', [Teacher\RewardPointController::class, 'create'])->name('reward-points.create');
    Route::post('reward-points', [Teacher\RewardPointController::class, 'store'])->name('reward-points.store');
    Route::delete('reward-points/{id}', [Teacher\RewardPointController::class, 'destroy'])->name('reward-points.destroy');
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
    Route::post('exit', [SuperAdmin\MosqueController::class, 'exit'])->name('exit');

    Route::get('mosques/{mosque}/users', [SuperAdmin\MosqueUserController::class, 'index'])->name('mosques.users.index');
    Route::post('mosques/{mosque}/users', [SuperAdmin\MosqueUserController::class, 'store'])->name('mosques.users.store');
    Route::patch('mosques/{mosque}/users/{user}/role', [SuperAdmin\MosqueUserController::class, 'updateRole'])->name('mosques.users.role');
    Route::delete('mosques/{mosque}/users/{user}', [SuperAdmin\MosqueUserController::class, 'destroy'])->name('mosques.users.destroy');

    Route::get('mosques/{mosque}/roles', [SuperAdmin\MosqueRoleController::class, 'index'])->name('mosques.roles.index');
    Route::post('mosques/{mosque}/roles', [SuperAdmin\MosqueRoleController::class, 'store'])->name('mosques.roles.store');
    Route::get('mosques/{mosque}/roles/{role}/edit', [SuperAdmin\MosqueRoleController::class, 'edit'])->name('mosques.roles.edit');
    Route::patch('mosques/{mosque}/roles/{role}', [SuperAdmin\MosqueRoleController::class, 'updatePermissions'])->name('mosques.roles.update');
    Route::delete('mosques/{mosque}/roles/{role}', [SuperAdmin\MosqueRoleController::class, 'destroy'])->name('mosques.roles.destroy');
});
