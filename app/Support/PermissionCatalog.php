<?php

namespace App\Support;

/**
 * Central catalog of every permission in the system (resource.action).
 *
 * Single source of truth for: seeding, the permission matrix UI,
 * the AuthorizationService and the 'permission:' middleware.
 */
final class PermissionCatalog
{
    /**
     * List of permission rows: [resource, action, arabic label].
     */
    public const ITEMS = [
        // Mosque management
        ['mosques', 'view', 'مشاهدة الجوامع'],
        ['mosques', 'create', 'إنشاء جامع'],
        ['mosques', 'update', 'تعديل جامع'],
        ['mosques', 'delete', 'حذف جامع'],
        // Students
        ['students', 'view', 'مشاهدة الطلاب'],
        ['students', 'create', 'إضافة طالب'],
        ['students', 'update', 'تعديل طالب'],
        ['students', 'delete', 'حذف طالب'],
        ['students', 'archive', 'أرشفة طالب'],
        ['students', 'transfer', 'نقل طالب'],
        // Teachers
        ['teachers', 'view', 'مشاهدة الأساتذة'],
        ['teachers', 'create', 'إضافة أستاذ'],
        ['teachers', 'update', 'تعديل أستاذ'],
        ['teachers', 'delete', 'حذف أستاذ'],
        // Classes & sections
        ['classes', 'view', 'مشاهدة الصفوف'],
        ['classes', 'create', 'إضافة صف'],
        ['classes', 'update', 'تعديل صف'],
        ['classes', 'delete', 'حذف صف'],
        ['sections', 'view', 'مشاهدة الشعب'],
        ['sections', 'create', 'إضافة شعبة'],
        ['sections', 'update', 'تعديل شعبة'],
        ['sections', 'delete', 'حذف شعبة'],
        // Subjects
        ['subjects', 'view', 'مشاهدة المواد'],
        ['subjects', 'create', 'إضافة مادة'],
        ['subjects', 'update', 'تعديل مادة'],
        ['subjects', 'delete', 'حذف مادة'],
        // Schedules
        ['schedule', 'view', 'مشاهدة الجداول'],
        ['schedule', 'create', 'إنشاء جدول'],
        ['schedule', 'update', 'تعديل جدول'],
        ['schedule', 'delete', 'حذف جدول'],
        ['schedule', 'approve', 'اعتماد الجداول'],
        // Attendance
        ['attendance', 'view', 'مشاهدة الحضور'],
        ['attendance', 'create', 'تسجيل الحضور'],
        ['attendance', 'update', 'تعديل الحضور'],
        ['attendance', 'approve', 'اعتماد الحضور'],
        // Exams & grades
        ['exams', 'view', 'مشاهدة الامتحانات'],
        ['exams', 'create', 'إنشاء امتحان'],
        ['exams', 'update', 'تعديل امتحان'],
        ['exams', 'delete', 'حذف امتحان'],
        ['grades', 'view', 'مشاهدة الدرجات'],
        ['grades', 'create', 'إدخال الدرجات'],
        ['grades', 'update', 'تعديل الدرجات'],
        ['grades', 'submit', 'إرسال الدرجات للاعتماد'],
        ['grades', 'approve', 'اعتماد الدرجات'],
        // Assignments (الواجبات)
        ['assignments', 'view', 'مشاهدة الواجبات'],
        ['assignments', 'create', 'إنشاء واجب'],
        ['assignments', 'update', 'تعديل واجب'],
        ['assignments', 'delete', 'حذف واجب'],
        ['assignments', 'grade', 'تصحيح الواجبات'],
        // Lessons
        ['lessons', 'view', 'مشاهدة الدروس'],
        ['lessons', 'create', 'إضافة درس'],
        ['lessons', 'update', 'تعديل درس'],
        ['lessons', 'delete', 'حذف درس'],
        // Announcements & messages
        ['announcements', 'view', 'مشاهدة الإعلانات'],
        ['announcements', 'create', 'إنشاء إعلان'],
        ['announcements', 'update', 'تعديل إعلان'],
        ['announcements', 'delete', 'حذف إعلان'],
        ['messages', 'view', 'مشاهدة الرسائل'],
        ['messages', 'create', 'إرسال رسالة'],
        // Reports
        ['reports', 'view', 'مشاهدة التقارير'],
        ['reports', 'export', 'تصدير التقارير'],
        // Users & roles
        ['users', 'view', 'مشاهدة المستخدمين'],
        ['users', 'create', 'إضافة مستخدم'],
        ['users', 'update', 'تعديل مستخدم'],
        ['users', 'delete', 'حذف مستخدم'],
        ['roles', 'view', 'مشاهدة الأدوار'],
        ['roles', 'create', 'إنشاء دور'],
        ['roles', 'update', 'تعديل دور'],
        ['roles', 'delete', 'حذف دور'],
        ['permissions', 'manage', 'إدارة الصلاحيات'],
        // Custom fields
        ['custom_fields', 'view', 'مشاهدة الحقول المخصصة'],
        ['custom_fields', 'create', 'إنشاء حقل مخصص'],
        ['custom_fields', 'update', 'تعديل حقل مخصص'],
        ['custom_fields', 'delete', 'حذف حقل مخصص'],
        // Audit
        ['audit_logs', 'view', 'مشاهدة سجل العمليات'],
    ];

    /** Default grants for the per-mosque manager role: code => scope. */
    public const MOSQUE_MANAGER = [
        'students.view' => 'mosque', 'students.create' => 'mosque', 'students.update' => 'mosque', 'students.delete' => 'mosque', 'students.archive' => 'mosque', 'students.transfer' => 'mosque',
        'teachers.view' => 'mosque', 'teachers.create' => 'mosque', 'teachers.update' => 'mosque', 'teachers.delete' => 'mosque',
        'classes.view' => 'mosque', 'classes.create' => 'mosque', 'classes.update' => 'mosque', 'classes.delete' => 'mosque',
        'sections.view' => 'mosque', 'sections.create' => 'mosque', 'sections.update' => 'mosque', 'sections.delete' => 'mosque',
        'subjects.view' => 'mosque', 'subjects.create' => 'mosque', 'subjects.update' => 'mosque', 'subjects.delete' => 'mosque',
        'schedule.view' => 'mosque', 'schedule.create' => 'mosque', 'schedule.update' => 'mosque', 'schedule.delete' => 'mosque', 'schedule.approve' => 'mosque',
        'attendance.view' => 'mosque', 'attendance.create' => 'mosque', 'attendance.update' => 'mosque', 'attendance.approve' => 'mosque',
        'exams.view' => 'mosque', 'exams.create' => 'mosque', 'exams.update' => 'mosque', 'exams.delete' => 'mosque',
        'grades.view' => 'mosque', 'grades.create' => 'mosque', 'grades.update' => 'mosque', 'grades.approve' => 'mosque',
        'assignments.view' => 'mosque', 'assignments.create' => 'mosque', 'assignments.update' => 'mosque', 'assignments.delete' => 'mosque', 'assignments.grade' => 'mosque',
        'lessons.view' => 'mosque', 'lessons.create' => 'mosque', 'lessons.update' => 'mosque', 'lessons.delete' => 'mosque',
        'announcements.view' => 'mosque', 'announcements.create' => 'mosque', 'announcements.update' => 'mosque', 'announcements.delete' => 'mosque',
        'messages.view' => 'mosque', 'messages.create' => 'mosque',
        'reports.view' => 'mosque', 'reports.export' => 'mosque',
        'users.view' => 'mosque', 'users.create' => 'mosque', 'users.update' => 'mosque', 'users.delete' => 'mosque',
        'roles.view' => 'mosque', 'roles.create' => 'mosque', 'roles.update' => 'mosque', 'roles.delete' => 'mosque',
        'custom_fields.view' => 'mosque', 'custom_fields.create' => 'mosque', 'custom_fields.update' => 'mosque', 'custom_fields.delete' => 'mosque',
        'audit_logs.view' => 'mosque',
    ];

    /** Default grants for the teacher role: code => scope. */
    public const TEACHER = [
        'students.view' => 'own',
        'teachers.view' => 'own',
        'classes.view' => 'own',
        'sections.view' => 'own',
        'subjects.view' => 'own',
        'schedule.view' => 'own', 'schedule.create' => 'own', 'schedule.update' => 'own',
        'attendance.view' => 'own', 'attendance.create' => 'own', 'attendance.update' => 'own',
        'exams.view' => 'own', 'exams.create' => 'own', 'exams.update' => 'own',
        'grades.view' => 'own', 'grades.create' => 'own', 'grades.update' => 'own', 'grades.submit' => 'own',
        'assignments.view' => 'own', 'assignments.create' => 'own', 'assignments.update' => 'own', 'assignments.delete' => 'own', 'assignments.grade' => 'own',
        'lessons.view' => 'own', 'lessons.create' => 'own', 'lessons.update' => 'own', 'lessons.delete' => 'own',
        'announcements.view' => 'mosque',
        'messages.view' => 'own', 'messages.create' => 'own',
        'users.view' => 'own',
    ];

    public static function codes(): array
    {
        return array_map(
            fn (array $item) => $item[0].'.'.$item[1],
            self::ITEMS
        );
    }

    /** @return array<string, array{resource: string, action: string, label: string}> */
    public static function rows(): array
    {
        $rows = [];

        foreach (self::ITEMS as [$resource, $action, $label]) {
            $rows[$resource.'.'.$action] = [
                'resource' => $resource,
                'action' => $action,
                'label' => $label,
            ];
        }

        return $rows;
    }

    /** Grouped rows by resource for the permission matrix UI. */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::rows() as $code => $row) {
            $groups[$row['resource']][] = ['code' => $code, ...$row];
        }

        return $groups;
    }
}
