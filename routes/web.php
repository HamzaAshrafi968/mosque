<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Guardian;
use App\Http\Controllers\NotificationsController;
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

    Route::patch('students/{student}/archive', [Admin\StudentController::class, 'archive'])->name('students.archive');
    Route::resource('students', Admin\StudentController::class);
    Route::post('students/{student}/transfer', [Admin\StudentController::class, 'transfer'])->name('students.transfer');

    Route::resource('parents', Admin\ParentController::class)->except(['show']);

    Route::resource('teachers', Admin\TeacherController::class);
    Route::post('teachers/{teacher}/ratings', [Admin\TeacherController::class, 'storeRating'])->name('teachers.ratings.store');
    Route::delete('teachers/{teacher}/ratings/{rating}', [Admin\TeacherController::class, 'destroyRating'])->name('teachers.ratings.destroy');
    Route::post('teachers/{teacher}/certificates', [Admin\TeacherController::class, 'storeCertificate'])->name('teachers.certificates.store');
    Route::delete('teachers/{teacher}/certificates/{certificate}', [Admin\TeacherController::class, 'destroyCertificate'])->name('teachers.certificates.destroy');

    Route::get('classrooms', [Admin\ClassroomController::class, 'index'])->name('classrooms.index');
    Route::get('classrooms/create', [Admin\ClassroomController::class, 'create'])->name('classrooms.create')->middleware('permission:classes.create');
    Route::post('classrooms', [Admin\ClassroomController::class, 'store'])->name('classrooms.store');
    Route::get('classrooms/{classroom}', [Admin\ClassroomController::class, 'show'])->name('classrooms.show');
    Route::get('classrooms/{classroom}/edit', [Admin\ClassroomController::class, 'edit'])->name('classrooms.edit')->middleware('permission:classes.update');
    Route::patch('classrooms/{classroom}', [Admin\ClassroomController::class, 'update'])->name('classrooms.update');
    Route::delete('classrooms/{classroom}', [Admin\ClassroomController::class, 'destroy'])->name('classrooms.destroy');

    Route::get('sections/{section}', [Admin\ClassroomController::class, 'showSection'])->name('sections.show');
    Route::post('classrooms/{classroom}/sections', [Admin\ClassroomController::class, 'storeSection'])->name('sections.store');
    Route::patch('sections/{section}', [Admin\ClassroomController::class, 'updateSection'])->name('sections.update');
    Route::delete('sections/{section}', [Admin\ClassroomController::class, 'destroySection'])->name('sections.destroy');

    Route::post('sections/{section}/students', [Admin\ClassroomController::class, 'enrollStudent'])->name('sections.students.store');
    Route::delete('sections/{section}/students/{student}', [Admin\ClassroomController::class, 'removeStudent'])->name('sections.students.destroy');
    Route::post('sections/{section}/teachers', [Admin\ClassroomController::class, 'assignTeacher'])->name('sections.teachers.store');
    Route::delete('sections/{section}/teachers/{teacher}', [Admin\ClassroomController::class, 'removeTeacher'])->name('sections.teachers.destroy');

    Route::get('custom-fields', [Admin\CustomFieldController::class, 'index'])->name('custom-fields.index')->middleware('permission:custom_fields.view');
    Route::post('custom-fields', [Admin\CustomFieldController::class, 'store'])->name('custom-fields.store')->middleware('permission:custom_fields.create');
    Route::patch('custom-fields/{customField}', [Admin\CustomFieldController::class, 'update'])->name('custom-fields.update')->middleware('permission:custom_fields.update');
    Route::delete('custom-fields/{customField}', [Admin\CustomFieldController::class, 'destroy'])->name('custom-fields.destroy')->middleware('permission:custom_fields.delete');

    Route::resource('subjects', Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('schedules', [Admin\ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules', [Admin\ScheduleController::class, 'store'])->name('schedules.store');
    Route::delete('schedules/{schedule}', [Admin\ScheduleController::class, 'destroy'])->name('schedules.destroy');

    Route::get('attendance', [Admin\AttendanceController::class, 'index'])->name('attendance.index')->middleware('permission:attendance.view');
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

    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:audit_logs.view');

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
    Route::get('attendance/history', [Teacher\AttendanceController::class, 'history'])->name('attendance.history');
    Route::get('attendance/sessions/{session}/edit', [Teacher\AttendanceController::class, 'edit'])->name('attendance.sessions.edit');
    Route::patch('attendance/sessions/{session}', [Teacher\AttendanceController::class, 'update'])->name('attendance.sessions.update');

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
    Route::post('switch-mosque', [SuperAdmin\MosqueController::class, 'switchMosque'])->name('switch-mosque');
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
    Route::get('grades', [StudentPortal\PortalController::class, 'grades'])->name('grades');
    Route::get('homeworks', [StudentPortal\PortalController::class, 'homeworks'])->name('homeworks');
    Route::post('homeworks/{homework}/submit', [StudentPortal\PortalController::class, 'submitHomework'])->name('homeworks.submit');
    Route::get('announcements', [StudentPortal\PortalController::class, 'announcements'])->name('announcements');
});

// ---- Sheikh portal additions: sections & finance ledger (spec §19-§32) ----
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('sections', [Teacher\SectionController::class, 'index'])->name('sections.index');
    Route::get('sections/{section}', [Teacher\SectionController::class, 'show'])->name('sections.show');

    Route::get('finance', [Teacher\FinanceController::class, 'index'])->name('finance.index')->middleware('permission:finance.view');
    Route::get('finance/receive', [Teacher\FinanceController::class, 'receiveForm'])->name('finance.receive')->middleware('permission:finance.create');
    Route::post('finance/receive', [Teacher\FinanceController::class, 'receive'])->name('finance.receive.store')->middleware('permission:finance.create');
    Route::get('finance/transfer', [Teacher\FinanceController::class, 'transferForm'])->name('finance.transfer')->middleware('permission:finance.transfer');
    Route::post('finance/transfer', [Teacher\FinanceController::class, 'transfer'])->name('finance.transfer.store')->middleware('permission:finance.transfer');
    Route::post('finance/adjust', [Teacher\FinanceController::class, 'adjust'])->name('finance.adjust')->middleware('permission:finance.adjust');
    Route::post('finance/transactions/{transaction}/reverse', [Teacher\FinanceController::class, 'reverse'])->name('finance.reverse')->middleware('permission:finance.adjust');
});
