<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Teacher;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'teacher.dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::patch('students/{student}/archive', [Admin\StudentController::class, 'archive'])->name('students.archive');
    Route::resource('students', Admin\StudentController::class);

    Route::resource('teachers', Admin\TeacherController::class)->except(['show']);

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

    Route::resource('exams', Admin\ExamController::class)->only(['index', 'create', 'store', 'destroy']);

    Route::get('grades', [Admin\GradeController::class, 'index'])->name('grades.index');
    Route::get('grades/{exam}', [Admin\GradeController::class, 'show'])->name('grades.show');
    Route::patch('grades/{exam}/approve', [Admin\GradeController::class, 'approve'])->name('grades.approve');

    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');

    Route::get('announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('announcements/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

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
});
