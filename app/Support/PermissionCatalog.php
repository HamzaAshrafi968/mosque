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
        // Study sessions (الدوامين / الفترات)
        ['sessions', 'view', 'مشاهدة الدوامات'],
        ['sessions', 'create', 'إضافة دوام'],
        ['sessions', 'update', 'تعديل دوام'],
        ['sessions', 'delete', 'حذف دوام'],
        // Students
        ['students', 'view', 'مشاهدة الطلاب'],
        ['students', 'create', 'إضافة طالب'],
        ['students', 'update', 'تعديل طالب'],
        ['students', 'delete', 'حذف طالب'],
        ['students', 'archive', 'أرشفة طالب'],
        ['students', 'transfer', 'نقل طالب'],
        // Guardians (أولياء الأمور)
        ['parents', 'view', 'مشاهدة أولياء الأمور'],
        ['parents', 'create', 'إضافة ولي أمر'],
        ['parents', 'update', 'تعديل ولي أمر'],
        ['parents', 'delete', 'حذف ولي أمر'],
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
        // Finance (العمليات المالية)
        ['finance', 'view', 'مشاهدة العمليات المالية'],
        ['finance', 'create', 'تسجيل عملية مالية'],
        ['finance', 'update', 'تعديل/عكس عملية مالية'],
        ['finance', 'adjust', 'تسوية عملية مالية'],
        ['finance', 'transfer', 'تحويل بين الأشخاص'],
        ['finance', 'report', 'تقارير مالية'],
        // Audit
        ['audit_logs', 'view', 'مشاهدة سجل العمليات'],
        // Quran programs (spec: mosque_management_quran_programs.md §15)
        ['quran', 'tasmee.view', 'مشاهدة التسميع'],
        ['quran', 'tasmee.create', 'تسجيل تسميع جديد'],
        ['quran', 'tasmee.update', 'تعديل تسميع'],
        ['quran', 'completion.view', 'مشاهدة إتمام الحفظ'],
        ['quran', 'completion.confirm', 'تأكيد إتمام الحفظ'],
        // Quran review (مراجعة القرآن)
        ['quran_review', 'view', 'مشاهدة مراجعة القرآن'],
        ['quran_review', 'create', 'تسجيل مراجعة قرآن'],
        // Reward points (نقاط المكافآت)
        ['reward_points', 'view', 'مشاهدة نقاط المكافآت'],
        ['reward_points', 'create', 'منح نقاط مكافأة'],
        ['reward_points', 'delete', 'حذف نقاط مكافأة'],
        ['qualifying', 'view', 'مشاهدة البرنامج التأهيلي'],
        ['qualifying', 'create', 'تسجيل تقييم أسبوعي'],
        ['qualifying', 'update', 'تعديل تقييم أسبوعي'],
        ['qualifying', 'complete', 'إنهاء البرنامج التأهيلي'],
        ['ijazah', 'view', 'مشاهدة برنامج الإجازة'],
        ['ijazah', 'create', 'تسجيل تقييم شهري'],
        ['ijazah', 'update', 'تعديل تقييم شهري'],
        ['ijazah', 'complete', 'إنهاء برنامج الإجازة'],
        ['hafiz_exams', 'view', 'مشاهدة اختبارات الحفاظ الشهرية'],
        ['hafiz_exams', 'create', 'تسجيل اختبار حافظ'],
        ['hafiz_exams', 'update', 'تعديل اختبار حافظ'],
        ['hafiz_exams', 'grade', 'تصحيح اختبارات الحفاظ'],
        ['hafiz_profile', 'view', 'مشاهدة ملفات الحفاظ'],
        ['hafiz_profile', 'update', 'تعديل ملف حافظ'],
        ['faith_meetings', 'view', 'مشاهدة اللقاءات الإيمانية'],
        ['faith_meetings', 'create', 'إنشاء لقاء إيماني'],
        ['faith_meetings', 'update', 'تعديل لقاء إيماني'],
        ['faith_meetings', 'attendance', 'تسجيل حضور اللقاءات'],
        // Teacher work hours (ساعات عمل المشرفين)
        ['work_hours', 'view', 'مشاهدة ساعات العمل'],
        ['work_hours', 'manage', 'إدارة ساعات العمل'],
        // Sharia courses (الدورات الشرعية)
        ['sharia_courses', 'view', 'مشاهدة الدورات الشرعية'],
        ['sharia_courses', 'create', 'إنشاء دورة شرعية'],
        ['sharia_courses', 'update', 'تعديل دورة شرعية'],
        ['sharia_courses', 'delete', 'حذف دورة شرعية'],
        ['sharia_courses', 'attendance', 'تسجيل حضور الدورات الشرعية'],
    ];

    /** Default grants for the per-mosque manager role: code => scope. */
    public const MOSQUE_MANAGER = [
        'students.view' => 'mosque', 'students.create' => 'mosque', 'students.update' => 'mosque', 'students.delete' => 'mosque', 'students.archive' => 'mosque', 'students.transfer' => 'mosque',
        'parents.view' => 'mosque', 'parents.create' => 'mosque', 'parents.update' => 'mosque', 'parents.delete' => 'mosque',
        'teachers.view' => 'mosque', 'teachers.create' => 'mosque', 'teachers.update' => 'mosque', 'teachers.delete' => 'mosque',
        'sessions.view' => 'mosque', 'sessions.create' => 'mosque', 'sessions.update' => 'mosque', 'sessions.delete' => 'mosque',
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
        'finance.view' => 'mosque', 'finance.create' => 'mosque', 'finance.update' => 'mosque', 'finance.adjust' => 'mosque', 'finance.transfer' => 'mosque', 'finance.report' => 'mosque',
        'audit_logs.view' => 'mosque',
        'quran.tasmee.view' => 'mosque', 'quran.tasmee.create' => 'mosque', 'quran.tasmee.update' => 'mosque',
        'quran.completion.view' => 'mosque', 'quran.completion.confirm' => 'mosque',
        'quran_review.view' => 'mosque', 'quran_review.create' => 'mosque',
        'reward_points.view' => 'mosque', 'reward_points.create' => 'mosque', 'reward_points.delete' => 'mosque',
        'qualifying.view' => 'mosque', 'qualifying.create' => 'mosque', 'qualifying.update' => 'mosque', 'qualifying.complete' => 'mosque',
        'ijazah.view' => 'mosque', 'ijazah.create' => 'mosque', 'ijazah.update' => 'mosque', 'ijazah.complete' => 'mosque',
        'hafiz_exams.view' => 'mosque', 'hafiz_exams.create' => 'mosque', 'hafiz_exams.update' => 'mosque', 'hafiz_exams.grade' => 'mosque',
        'hafiz_profile.view' => 'mosque', 'hafiz_profile.update' => 'mosque',
        'faith_meetings.view' => 'mosque', 'faith_meetings.create' => 'mosque', 'faith_meetings.update' => 'mosque', 'faith_meetings.attendance' => 'mosque',
        'work_hours.view' => 'mosque', 'work_hours.manage' => 'mosque',
        'sharia_courses.view' => 'mosque', 'sharia_courses.create' => 'mosque', 'sharia_courses.update' => 'mosque', 'sharia_courses.delete' => 'mosque', 'sharia_courses.attendance' => 'mosque',
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
        'quran.tasmee.view' => 'own', 'quran.tasmee.create' => 'own', 'quran.tasmee.update' => 'own',
        'quran.completion.view' => 'own',
        'quran_review.view' => 'own', 'quran_review.create' => 'own',
        'reward_points.view' => 'own', 'reward_points.create' => 'own', 'reward_points.delete' => 'own',
        'qualifying.view' => 'own', 'qualifying.create' => 'own', 'qualifying.update' => 'own',
        'ijazah.view' => 'own', 'ijazah.create' => 'own', 'ijazah.update' => 'own',
        'hafiz_exams.view' => 'own', 'hafiz_exams.create' => 'own', 'hafiz_exams.update' => 'own', 'hafiz_exams.grade' => 'own',
        'hafiz_profile.view' => 'own',
        'faith_meetings.view' => 'own', 'faith_meetings.create' => 'own', 'faith_meetings.update' => 'own', 'faith_meetings.attendance' => 'own',
        'work_hours.view' => 'own',
        'sharia_courses.view' => 'own', 'sharia_courses.update' => 'own', 'sharia_courses.attendance' => 'own',
    ];

    /** Default grants for the guardian portal role: code => scope (read-only). */
    public const GUARDIAN = [
        'students.view' => 'own',
        'teachers.view' => 'own',
        'classes.view' => 'own',
        'sections.view' => 'own',
        'subjects.view' => 'own',
        'attendance.view' => 'own',
        'exams.view' => 'own',
        'grades.view' => 'own',
        'assignments.view' => 'own',
        'lessons.view' => 'own',
        'announcements.view' => 'mosque',
        'messages.view' => 'own', 'messages.create' => 'own',
    ];

    /** Default grants for the student portal role: code => scope (read-only + own homework submission). */
    public const STUDENT = [
        'students.view' => 'own',
        'teachers.view' => 'own',
        'classes.view' => 'own',
        'sections.view' => 'own',
        'subjects.view' => 'own',
        'attendance.view' => 'own',
        'exams.view' => 'own',
        'grades.view' => 'own',
        'assignments.view' => 'own',
        'lessons.view' => 'own',
        'announcements.view' => 'mosque',
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
