<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class AuditActionCatalog
{
    public const HIDDEN_FIELDS = [
        'id',
        'tenant_id',
        'user_id',
        'created_by',
        'updated_by',
        'recorded_by',
        'awarded_by',
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
        'email_verified_at',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public const GROUPS = [
        'students' => ['label' => 'الطلاب', 'icon' => 'students', 'tone' => 'emerald', 'prefixes' => ['student.']],
        'guardians' => ['label' => 'أولياء الأمور', 'icon' => 'children', 'tone' => 'teal', 'prefixes' => ['guardian.']],
        'teachers' => ['label' => 'المعلمون', 'icon' => 'teachers', 'tone' => 'sky', 'prefixes' => ['teacher.']],
        'academics' => ['label' => 'الصفوف والشعب', 'icon' => 'classrooms', 'tone' => 'violet', 'prefixes' => ['class.', 'section.']],
        'sessions' => ['label' => 'الدوامات والحصص', 'icon' => 'schedule', 'tone' => 'amber', 'prefixes' => ['session.']],
        'attendance' => ['label' => 'الحضور والغياب', 'icon' => 'attendance', 'tone' => 'emerald', 'prefixes' => ['attendance.']],
        'exams' => ['label' => 'الامتحانات', 'icon' => 'exam', 'tone' => 'rose', 'prefixes' => ['exam.']],
        'quran' => [
            'label' => 'القرآن والحفظ',
            'icon' => 'quran',
            'tone' => 'gold',
            'prefixes' => [
                'quran.', 'quran_listening.', 'quran_training.', 'khamsa_review.',
                'memorization_batch.', 'juz_memorization.', 'hafiz.', 'hafiz_exam.',
                'qualifying.', 'ijazah.', 'readings.',
            ],
        ],
        'finance' => [
            'label' => 'المالية والرواتب',
            'icon' => 'wallet',
            'tone' => 'emerald',
            'prefixes' => ['finance.', 'payroll.', 'work_slot.', 'work_hours.', 'hourly_rate.'],
        ],
        'donations' => ['label' => 'التبرعات والمساهمات', 'icon' => 'gift', 'tone' => 'gold', 'prefixes' => ['donation.']],
        'programs' => ['label' => 'البرامج والتخصصات', 'icon' => 'qualifying', 'tone' => 'sky', 'prefixes' => ['program.']],
        'sharia' => ['label' => 'الدورات الشرعية', 'icon' => 'mosque', 'tone' => 'gold', 'prefixes' => ['sharia_course.']],
        'faith' => ['label' => 'اللقاءات الإيمانية', 'icon' => 'faith', 'tone' => 'violet', 'prefixes' => ['faith_meeting.', 'faith_meeting_template.']],
        'rewards' => ['label' => 'نقاط المكافآت', 'icon' => 'trophy', 'tone' => 'gold', 'prefixes' => ['reward_points.']],
        'settings' => ['label' => 'الإعدادات والحقول', 'icon' => 'settings', 'tone' => 'gray', 'prefixes' => ['custom_field.']],
        'other' => ['label' => 'عمليات أخرى', 'icon' => 'info', 'tone' => 'gray', 'prefixes' => []],
    ];

    public const ACTIONS = [
        'student.created' => ['label' => 'إضافة طالب جديد', 'group' => 'students'],
        'student.updated' => ['label' => 'تعديل بيانات طالب', 'group' => 'students'],
        'student.deleted' => ['label' => 'حذف طالب', 'group' => 'students'],
        'student.archived' => ['label' => 'أرشفة طالب', 'group' => 'students'],
        'student.transferred' => ['label' => 'نقل طالب إلى شعبة أخرى', 'group' => 'students'],
        'student.removed_from_section' => ['label' => 'إخراج طالب من شعبة', 'group' => 'students'],
        'student.enrolled' => ['label' => 'تسجيل طالب في شعبة', 'group' => 'students'],
        'student.enrollment.reactivated' => ['label' => 'إعادة تنشيط تسجيل طالب', 'group' => 'students'],
        'student.portal_account_created' => ['label' => 'إنشاء حساب دخول لطالب', 'group' => 'students'],

        'guardian.created' => ['label' => 'إضافة ولي أمر', 'group' => 'guardians'],
        'guardian.updated' => ['label' => 'تعديل بيانات ولي أمر', 'group' => 'guardians'],
        'guardian.deleted' => ['label' => 'حذف ولي أمر', 'group' => 'guardians'],

        'teacher.created' => ['label' => 'إضافة معلم', 'group' => 'teachers'],
        'teacher.updated' => ['label' => 'تعديل بيانات معلم', 'group' => 'teachers'],
        'teacher.deleted' => ['label' => 'حذف معلم', 'group' => 'teachers'],
        'teacher.salary_updated' => ['label' => 'تعديل راتب معلم', 'group' => 'teachers'],
        'teacher.assigned_to_section' => ['label' => 'إسناد معلم إلى شعبة', 'group' => 'teachers'],
        'teacher.removed_from_section' => ['label' => 'إزالة معلم من شعبة', 'group' => 'teachers'],

        'class.created' => ['label' => 'إضافة صف', 'group' => 'academics'],
        'class.updated' => ['label' => 'تعديل صف', 'group' => 'academics'],
        'class.deleted' => ['label' => 'حذف صف', 'group' => 'academics'],
        'section.created' => ['label' => 'إضافة شعبة', 'group' => 'academics'],
        'section.updated' => ['label' => 'تعديل شعبة', 'group' => 'academics'],
        'section.deleted' => ['label' => 'حذف شعبة', 'group' => 'academics'],

        'session.created' => ['label' => 'إنشاء دوام', 'group' => 'sessions'],
        'session.updated' => ['label' => 'تعديل دوام', 'group' => 'sessions'],
        'session.deleted' => ['label' => 'حذف دوام', 'group' => 'sessions'],
        'session.programs_updated' => ['label' => 'تعديل برامج دوام', 'group' => 'sessions'],
        'session.bulk_assign' => ['label' => 'إسناد جماعي إلى دوام', 'group' => 'sessions'],
        'session.cancelled' => ['label' => 'إلغاء حصة في يوم محدد', 'group' => 'sessions'],
        'session.postponed' => ['label' => 'تأجيل حصة إلى موعد آخر', 'group' => 'sessions'],
        'session.restored' => ['label' => 'إرجاع حصة إلى موعدها', 'group' => 'sessions'],

        'attendance.session_created' => ['label' => 'إنشاء جلسة حضور', 'group' => 'attendance'],
        'attendance.session_updated' => ['label' => 'تحديث جلسة حضور', 'group' => 'attendance'],
        'attendance.teacher_marked' => ['label' => 'تسجيل حضور معلم', 'group' => 'attendance'],

        'exam.published' => ['label' => 'نشر امتحان', 'group' => 'exams'],
        'exam.attempt.submitted' => ['label' => 'تسليم إجابة امتحان', 'group' => 'exams'],
        'exam.attempt.manual_graded' => ['label' => 'تصحيح إجابة امتحان يدوياً', 'group' => 'exams'],

        'quran.tasmee.created' => ['label' => 'تسجيل تسميع جديد', 'group' => 'quran'],
        'quran.tasmee.updated' => ['label' => 'تعديل تسميع', 'group' => 'quran'],
        'quran.tasmee.result_changed' => ['label' => 'تغيير نتيجة تسميع', 'group' => 'quran'],
        'quran.completion.recorded' => ['label' => 'تسجيل إتمام حفظ', 'group' => 'quran'],
        'quran.completion.confirmed' => ['label' => 'تأكيد إتمام حفظ', 'group' => 'quran'],
        'quran.settings.updated' => ['label' => 'تعديل إعدادات برنامج القرآن', 'group' => 'quran'],
        'quran_listening.plan.created' => ['label' => 'إنشاء خطة استماع', 'group' => 'quran'],
        'quran_listening.plan.completed' => ['label' => 'إتمام خطة استماع', 'group' => 'quran'],
        'quran_listening.plan.cancelled' => ['label' => 'إلغاء خطة استماع', 'group' => 'quran'],
        'quran_listening.item_listened' => ['label' => 'تسجيل استماع عنصر في خطة', 'group' => 'quran'],
        'quran_listening.test.recorded' => ['label' => 'تسجيل نتيجة اختبار استماع', 'group' => 'quran'],
        'khamsa_review.created' => ['label' => 'إنشاء مراجعة خمسات', 'group' => 'quran'],
        'khamsa_review.completed' => ['label' => 'إتمام مراجعة خمسات', 'group' => 'quran'],
        'khamsa_review.cancelled' => ['label' => 'إلغاء مراجعة خمسات', 'group' => 'quran'],
        'khamsa_review.item_completed' => ['label' => 'إتمام خمسة من المراجعة', 'group' => 'quran'],
        'juz_memorization.recorded' => ['label' => 'تسجيل حفظ جزء', 'group' => 'quran'],
        'juz_memorization.removed' => ['label' => 'إزالة حفظ جزء', 'group' => 'quran'],
        'memorization_batch.opened' => ['label' => 'فتح دفعة حفظ جديدة', 'group' => 'quran'],
        'memorization_batch.test_recorded' => ['label' => 'تسجيل اختبار دفعة حفظ', 'group' => 'quran'],
        'memorization_batch.passed' => ['label' => 'نجاح في دفعة حفظ', 'group' => 'quran'],
        'memorization_batch.needs_repeat' => ['label' => 'رسوب في دفعة حفظ (يحتاج إعادة)', 'group' => 'quran'],
        'memorization_batch.retake_review_created' => ['label' => 'إنشاء مراجعة إعادة لدفعة', 'group' => 'quran'],
        'memorization_batch.review_regenerated' => ['label' => 'إعادة توليد مراجعة دفعة', 'group' => 'quran'],
        'memorization_batch.cycle_generated' => ['label' => 'توليد دورة دفعة حفظ', 'group' => 'quran'],
        'memorization_batch.journey_completed' => ['label' => 'إتمام رحلة الحفظ', 'group' => 'quran'],
        'quran_training.program_created' => ['label' => 'إنشاء برنامج قرآني', 'group' => 'quran'],
        'quran_training.program_completed' => ['label' => 'إتمام برنامج قرآني', 'group' => 'quran'],
        'quran_training.program_cancelled' => ['label' => 'إلغاء برنامج قرآني', 'group' => 'quran'],
        'quran_training.batch_passed' => ['label' => 'نجاح دفعة برنامج قرآني', 'group' => 'quran'],
        'quran_training.batch_needs_repeat' => ['label' => 'رسوب دفعة برنامج قرآني', 'group' => 'quran'],
        'quran_training.item_tasmee_recorded' => ['label' => 'تسجيل تسميع عنصر برنامج', 'group' => 'quran'],
        'quran_training.item_partial_listened' => ['label' => 'تسجيل استماع جزئي لعنصر برنامج', 'group' => 'quran'],
        'quran_training.test_recorded' => ['label' => 'تسجيل اختبار برنامج قرآني', 'group' => 'quran'],
        'hafiz.profile.created' => ['label' => 'إنشاء ملف حافظ', 'group' => 'quran'],
        'hafiz.profile.updated' => ['label' => 'تعديل ملف حافظ', 'group' => 'quran'],
        'hafiz_exam.month_opened' => ['label' => 'فتح شهر امتحانات الحفاظ', 'group' => 'quran'],
        'hafiz_exam.graded' => ['label' => 'تصحيح امتحان حافظ', 'group' => 'quran'],
        'hafiz_exam.revision_recorded' => ['label' => 'تسجيل مراجعة حافظ', 'group' => 'quran'],
        'hafiz_exam.revision_completed' => ['label' => 'إتمام مراجعة حافظ', 'group' => 'quran'],
        'hafiz_exam.revision_approved' => ['label' => 'اعتماد مراجعة حافظ', 'group' => 'quran'],
        'qualifying.weekly_recorded' => ['label' => 'تسجيل تقييم أسبوعي (تأهيلي)', 'group' => 'quran'],
        'qualifying.enrollment.manual' => ['label' => 'ترحيل حافظ إلى البرنامج التأهيلي', 'group' => 'quran'],
        'qualifying.completed' => ['label' => 'إتمام البرنامج التأهيلي', 'group' => 'quran'],
        'ijazah.weekly_recorded' => ['label' => 'تسجيل تقييم أسبوعي (إجازة)', 'group' => 'quran'],
        'ijazah.weekly_updated' => ['label' => 'تعديل تقييم أسبوعي (إجازة)', 'group' => 'quran'],
        'ijazah.weekly_deleted' => ['label' => 'حذف تقييم أسبوعي (إجازة)', 'group' => 'quran'],
        'ijazah.monthly_recorded' => ['label' => 'تسجيل تقييم شهري (إجازة)', 'group' => 'quran'],
        'ijazah.completed' => ['label' => 'إتمام برنامج الإجازة', 'group' => 'quran'],
        'readings.completed' => ['label' => 'إتمام قراءة من القراءات العشر', 'group' => 'quran'],

        'finance.transaction_created' => ['label' => 'تسجيل عملية مالية', 'group' => 'finance'],
        'finance.transaction_reversed' => ['label' => 'عكس عملية مالية', 'group' => 'finance'],
        'finance.transfer_created' => ['label' => 'تسجيل تحويل مالي', 'group' => 'finance'],
        'payroll.closed' => ['label' => 'إغلاق كشف رواتب', 'group' => 'finance'],
        'payroll.reopened' => ['label' => 'إعادة فتح كشف رواتب', 'group' => 'finance'],
        'work_slot.created' => ['label' => 'تسجيل فترة عمل', 'group' => 'finance'],
        'work_slot.updated' => ['label' => 'تعديل فترة عمل', 'group' => 'finance'],
        'work_slot.deleted' => ['label' => 'حذف فترة عمل', 'group' => 'finance'],
        'work_hours.created' => ['label' => 'إضافة ساعات عمل أسبوعية', 'group' => 'finance'],
        'work_hours.updated' => ['label' => 'تعديل ساعات عمل أسبوعية', 'group' => 'finance'],
        'work_hours.deleted' => ['label' => 'حذف ساعات عمل أسبوعية', 'group' => 'finance'],
        'work_hours.settings.updated' => ['label' => 'تعديل إعدادات ساعات العمل', 'group' => 'finance'],
        'hourly_rate.created' => ['label' => 'إضافة سعر ساعة', 'group' => 'finance'],
        'hourly_rate.deleted' => ['label' => 'حذف سعر ساعة', 'group' => 'finance'],

        'program.created' => ['label' => 'إضافة برنامج أو تخصص', 'group' => 'programs'],
        'program.updated' => ['label' => 'تعديل برنامج أو تخصص', 'group' => 'programs'],
        'program.deleted' => ['label' => 'حذف برنامج أو تخصص', 'group' => 'programs'],
        'program.enrollment.created' => ['label' => 'تسجيل طالب في برنامج', 'group' => 'programs'],

        'sharia_course.created' => ['label' => 'إنشاء دورة شرعية', 'group' => 'sharia'],
        'sharia_course.updated' => ['label' => 'تعديل دورة شرعية', 'group' => 'sharia'],
        'sharia_course.deleted' => ['label' => 'حذف دورة شرعية', 'group' => 'sharia'],
        'sharia_course.attendance_saved' => ['label' => 'حفظ حضور دورة شرعية', 'group' => 'sharia'],
        'sharia_course.students_enrolled' => ['label' => 'تسجيل طلاب في دورة شرعية', 'group' => 'sharia'],
        'sharia_course.student_updated' => ['label' => 'تعديل طالب في دورة شرعية', 'group' => 'sharia'],
        'sharia_course.student_removed' => ['label' => 'إزالة طالب من دورة شرعية', 'group' => 'sharia'],
        'sharia_course.memorization_updated' => ['label' => 'تحديث حالة حفظ طالب في دورة', 'group' => 'sharia'],
        'sharia_course.lesson_added' => ['label' => 'إضافة درس لدورة شرعية', 'group' => 'sharia'],
        'sharia_course.lesson_updated' => ['label' => 'تعديل درس دورة شرعية', 'group' => 'sharia'],
        'sharia_course.lesson_removed' => ['label' => 'حذف درس من دورة شرعية', 'group' => 'sharia'],

        'faith_meeting.created' => ['label' => 'إنشاء لقاء إيماني', 'group' => 'faith'],
        'faith_meeting.updated' => ['label' => 'تعديل لقاء إيماني', 'group' => 'faith'],
        'faith_meeting.deleted' => ['label' => 'حذف لقاء إيماني', 'group' => 'faith'],
        'faith_meeting.attendance_changed' => ['label' => 'تعديل حضور لقاء إيماني', 'group' => 'faith'],
        'faith_meeting.students_changed' => ['label' => 'تعديل طلاب لقاء إيماني', 'group' => 'faith'],
        'faith_meeting.note_added' => ['label' => 'إضافة ملاحظة إلى لقاء', 'group' => 'faith'],
        'faith_meeting.note_updated' => ['label' => 'تعديل ملاحظة لقاء', 'group' => 'faith'],
        'faith_meeting.note_deleted' => ['label' => 'حذف ملاحظة لقاء', 'group' => 'faith'],
        'faith_meeting_template.created' => ['label' => 'إنشاء قالب لقاء إيماني', 'group' => 'faith'],
        'faith_meeting_template.updated' => ['label' => 'تعديل قالب لقاء إيماني', 'group' => 'faith'],
        'faith_meeting_template.deleted' => ['label' => 'حذف قالب لقاء إيماني', 'group' => 'faith'],

        'reward_points.settings.updated' => ['label' => 'تعديل إعدادات نقاط المكافآت', 'group' => 'rewards'],

        'donation.created' => ['label' => 'تسجيل تبرع أو مساهمة', 'group' => 'donations'],
        'donation.updated' => ['label' => 'تعديل تبرع أو مساهمة', 'group' => 'donations'],
        'donation.accepted' => ['label' => 'قبول تبرع أو مساهمة', 'group' => 'donations'],
        'donation.rejected' => ['label' => 'رفض تبرع أو مساهمة', 'group' => 'donations'],
        'donation.deleted' => ['label' => 'حذف تبرع أو مساهمة', 'group' => 'donations'],

        'custom_field.created' => ['label' => 'إضافة حقل مخصص', 'group' => 'settings'],
        'custom_field.updated' => ['label' => 'تعديل حقل مخصص', 'group' => 'settings'],
        'custom_field.deleted' => ['label' => 'حذف حقل مخصص', 'group' => 'settings'],
    ];

    public const ENTITIES = [
        'student' => 'طالب',
        'guardian' => 'ولي أمر',
        'teacher' => 'معلم',
        'user' => 'مستخدم',
        'classroom' => 'صف',
        'section' => 'شعبة',
        'section_student' => 'طالب في شعبة',
        'section_teacher' => 'معلم في شعبة',
        'subject' => 'مادة',
        'schedule' => 'حصة في الجدول',
        'class_session' => 'حصة (إلغاء أو تأجيل)',
        'study_session' => 'دوام',
        'attendance_session' => 'جلسة حضور',
        'attendance_record' => 'سجل حضور',
        'exam' => 'امتحان',
        'exam_question' => 'سؤال امتحان',
        'exam_attempt' => 'محاولة امتحان',
        'exam_answer' => 'إجابة امتحان',
        'grade' => 'درجة',
        'program' => 'برنامج أو تخصص',
        'program_enrollment' => 'تسجيل في برنامج',
        'financial_transaction' => 'عملية مالية',
        'payroll_period' => 'كشف رواتب',
        'work_slot' => 'فترة عمل',
        'teacher_work_hour' => 'ساعات عمل معلم',
        'hourly_rate' => 'سعر ساعة',
        'quran_recitation_session' => 'جلسة تسميع',
        'quran_review_session' => 'جلسة استماع تفصيلي',
        'quran_listening_plan' => 'خطة استماع',
        'quran_listening_plan_item' => 'عنصر خطة استماع',
        'quran_listening_test' => 'اختبار استماع',
        'quran_listening_test_item' => 'نتيجة عنصر في اختبار',
        'quran_memorization_batch' => 'دفعة حفظ',
        'quran_khamsa_review' => 'مراجعة خمسات',
        'quran_khamsa_review_item' => 'خمسة في المراجعة',
        'student_juz_memorization' => 'حفظ جزء',
        'quran_completion' => 'إتمام حفظ',
        'hafiz_profile' => 'ملف حافظ',
        'hafiz_exam' => 'امتحان حافظ',
        'hafiz_monthly_exam' => 'امتحان شهري للحافظ',
        'hafiz_exam_month' => 'شهر امتحانات الحفاظ',
        'hafiz_exam_revision' => 'مراجعة حافظ',
        'homework_submission' => 'تسليم واجب',
        'faith_meeting_student' => 'طالب في لقاء إيماني',
        'sharia_course_attendance' => 'حضور دورة شرعية',
        'qualifying_weekly_evaluation' => 'تقييم أسبوعي (تأهيلي)',
        'ijazah_weekly_evaluation' => 'تقييم أسبوعي (إجازة)',
        'ijazah_monthly_evaluation' => 'تقييم شهري (إجازة)',
        'program_attribute' => 'خاصية برنامج',
        'program_period' => 'فترة برنامج',
        'teacher_certificate' => 'شهادة معلم',
        'teacher_rating' => 'تقييم معلم',
        'parent_student' => 'ربط ولي أمر بطالب',
        'custom_field_value' => 'قيمة حقل مخصص',
        'quran_review_word' => 'كلمة مراجعة قرآنية',
        'quran_ayah' => 'آية قرآنية',
        'quran_surah' => 'سورة قرآنية',
        'audit_log' => 'سجل عملية',
        'sharia_course' => 'دورة شرعية',
        'sharia_course_student' => 'طالب في دورة شرعية',
        'sharia_course_lesson' => 'درس دورة شرعية',
        'faith_meeting' => 'لقاء إيماني',
        'faith_meeting_template' => 'قالب لقاء إيماني',
        'faith_meeting_note' => 'ملاحظة لقاء',
        'custom_field' => 'حقل مخصص',
        'tenant_setting' => 'إعدادات الجامع',
        'donation' => 'تبرع أو مساهمة',
        'reward_point_rule' => 'قاعدة نقاط مكافآت',
        'reward_point' => 'نقطة مكافأة',
        'quran_listening_program' => 'برنامج قرآني',
        'quran_listening_program_batch' => 'دفعة برنامج قرآني',
        'quran_listening_program_item' => 'عنصر برنامج قرآني',
        'qualifying_evaluation' => 'تقييم تأهيلي',
        'ijazah_evaluation' => 'تقييم إجازة',
        'announcement' => 'إعلان',
        'homework' => 'واجب',
        'lesson' => 'درس',
        'message' => 'رسالة',
    ];

    public const FIELDS = [
        'name' => 'الاسم',
        'full_name' => 'الاسم الكامل',
        'title' => 'العنوان',
        'description' => 'الوصف',
        'body' => 'النص',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'phone_number' => 'رقم الهاتف',
        'gender' => 'الجنس',
        'birth_date' => 'تاريخ الميلاد',
        'date' => 'التاريخ',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
        'status' => 'الحالة',
        'is_active' => 'مُفعّل',
        'active' => 'مُفعّل',
        'role' => 'الدور',
        'notes' => 'ملاحظات',
        'note' => 'ملاحظة',
        'address' => 'العنوان السكني',
        'city' => 'المدينة',
        'section_id' => 'الشعبة',
        'classroom_id' => 'الصف',
        'teacher_id' => 'المعلم',
        'student_id' => 'الطالب',
        'guardian_id' => 'ولي الأمر',
        'subject_id' => 'المادة',
        'schedule_id' => 'الحصة في الجدول',
        'study_session_id' => 'الدوام',
        'program_id' => 'البرنامج',
        'exam_id' => 'الامتحان',
        'course_id' => 'الدورة',
        'lesson_id' => 'الدرس',
        'plan_id' => 'الخطة',
        'batch_id' => 'الدفعة',
        'enrollment_id' => 'التسجيل',
        'grade' => 'الدرجة',
        'marks' => 'العلامة',
        'total_marks' => 'الدرجة الكلية',
        'pass_marks' => 'درجة النجاح',
        'pass_mark' => 'درجة النجاح',
        'duration_minutes' => 'المدة (دقائق)',
        'duration' => 'المدة',
        'start_time' => 'وقت البداية',
        'end_time' => 'وقت النهاية',
        'from_page' => 'من صفحة',
        'to_page' => 'إلى صفحة',
        'juz' => 'الجزء',
        'juz_number' => 'رقم الجزء',
        'khamsa' => 'الخمسة',
        'pages_count' => 'عدد الصفحات',
        'points' => 'النقاط',
        'amount' => 'المبلغ',
        'paid_amount' => 'المبلغ المدفوع',
        'payment_method' => 'طريقة الدفع',
        'type' => 'النوع',
        'rule_type' => 'نوع القاعدة',
        'category' => 'التصنيف',
        'level' => 'المستوى',
        'capacity' => 'السعة',
        'location' => 'المكان',
        'day_of_week' => 'يوم الأسبوع',
        'period' => 'الفترة',
        'period_number' => 'رقم الحصة',
        'session_number' => 'رقم الحصة',
        'monthly_salary' => 'الراتب الشهري',
        'salary' => 'الراتب',
        'pay_type' => 'نوع الأجر',
        'hired_at' => 'تاريخ التعيين',
        'hourly_rate' => 'سعر الساعة',
        'rate' => 'السعر',
        'currency' => 'العملة',
        'reason' => 'السبب',
        'month' => 'الشهر',
        'year' => 'السنة',
        'published_at' => 'تاريخ النشر',
        'expires_at' => 'تاريخ الانتهاء',
        'postponed_starts_at' => 'موعد التأجيل الجديد',
        'postponed_date' => 'تاريخ التأجيل',
        'cancelled_at' => 'تاريخ الإلغاء',
        'memorized_juz' => 'عدد الأجزاء المحفوظة',
        'memorized_juz_numbers' => 'الأجزاء المحفوظة',
        'current_juz' => 'الجزء الحالي',
        'result' => 'النتيجة',
        'score' => 'الدرجة',
        'percentage' => 'النسبة',
        'minimum_passing_percentage' => 'حد النجاح (%)',
        'auto_confirm_completion' => 'اعتماد إتمام الحفظ تلقائياً',
        'enabled' => 'مُفعّل',
        'automatic_enabled' => 'المنح التلقائي مُفعّل',
        'max_slot_hours' => 'الحد الأقصى لساعات الفترة',
        'timezone' => 'المنطقة الزمنية',
        'settings' => 'الإعدادات',
        'rules' => 'القواعد',
        'audio_path' => 'الملف الصوتي',
        'audio_original_name' => 'اسم الملف الصوتي',
        'attachment_key' => 'المرفق',
        'attachment_name' => 'اسم المرفق',
        'photo' => 'الصورة',
        'avatar' => 'الصورة الشخصية',
        'code' => 'الرمز',
        'kind' => 'النوع',
        'mode' => 'النمط',
        'gate_size' => 'حجم الدفعة المفتوحة',
        'batch_number' => 'رقم الدفعة',
        'student_name' => 'اسم الطالب',
        'teacher_name' => 'اسم المعلم',
        'guardian_name' => 'اسم ولي الأمر',
        'classroom_name' => 'اسم الصف',
        'section_name' => 'اسم الشعبة',
        'total' => 'الإجمالي',
        'count' => 'العدد',
        'sort_order' => 'الترتيب',
        'display_order' => 'الترتيب',
        'is_default' => 'افتراضي',
        'color' => 'اللون',
        'relationship' => 'صلة القرابة',
        'supervisor_ids' => 'المشرفون',
        'supervisors' => 'المشرفون',
        'students' => 'الطلاب',
        'sections' => 'الشعب',
        'classes' => 'الصفوف',
        'from' => 'من',
        'to' => 'إلى',
        'recorded_at' => 'تاريخ التسجيل',
        'approved_at' => 'تاريخ الاعتماد',
        'completed_at' => 'تاريخ الإتمام',
        'completed_by' => 'مَن أتمّ',
        'confirmed_at' => 'تاريخ التأكيد',
        'confirmed_by' => 'مَن أكّد',
        'enrolled_at' => 'تاريخ التسجيل في الشعبة',
        'left_at' => 'تاريخ الانصراف',
        'started_at' => 'تاريخ البدء',
        'assigned_at' => 'تاريخ الإسناد',
        'assigned_by' => 'مَن أسند',
        'assigned_to' => 'المُسنَد إليه',
        'granted_at' => 'تاريخ المنح',
        'read_at' => 'تاريخ القراءة',
        'listened_at' => 'تاريخ الاستماع',
        'listened_by' => 'مَن سجّل الاستماع',
        'tested_at' => 'تاريخ الاختبار',
        'tested_by' => 'مَن اختبر',
        'evaluated_by' => 'مَن قيّم',
        'passed_at' => 'تاريخ النجاح',
        'passed_by' => 'مَن اعتمد النجاح',
        'closed_at' => 'تاريخ الإغلاق',
        'closed_by' => 'مَن أغلق',
        'changed_by' => 'مَن عدّل',
        'due_date' => 'تاريخ الاستحقاق',
        'exam_date' => 'تاريخ الامتحان',
        'effective_from' => 'ساري من',
        'effective_to' => 'ساري إلى',
        'starts_at' => 'يبدأ في',
        'ends_at' => 'ينتهي في',
        'finished_at' => 'تاريخ الانتهاء',
        'available_at' => 'متاح في',
        'reserved_at' => 'تاريخ الحجز',
        'submitted_at' => 'تاريخ التسليم',
        'calculated_at' => 'تاريخ الاحتساب',
        'last_activity' => 'آخر نشاط',
        'last_result' => 'آخر نتيجة',
        'last_used_at' => 'آخر استخدام',
        'expiration' => 'تاريخ الانتهاء',
        'from_juz' => 'من الجزء',
        'to_juz' => 'إلى الجزء',
        'from_surah' => 'من السورة',
        'to_surah' => 'إلى السورة',
        'from_ayah' => 'من الآية',
        'to_ayah' => 'إلى الآية',
        'surah_id' => 'السورة',
        'ayah_id' => 'الآية',
        'ayah_number' => 'رقم الآية',
        'num_ayahs' => 'عدد الآيات',
        'revelation_type' => 'نوع النزول',
        'page' => 'الصفحة',
        'word_text' => 'الكلمة',
        'word_position' => 'موضع الكلمة',
        'word_statuses' => 'حالات الكلمات',
        'memorized_from_surah_id' => 'السورة (من)',
        'memorized_to_surah_id' => 'السورة (إلى)',
        'memorized_from_ayah' => 'من الآية المحفوظة',
        'memorized_to_ayah' => 'إلى الآية المحفوظة',
        'memorized_at' => 'تاريخ الحفظ',
        'memorization_notes' => 'ملاحظات الحفظ',
        'memorization_status' => 'حالة الحفظ',
        'memorization_updated_at' => 'تاريخ تحديث الحفظ',
        'memorization_updated_by' => 'مَن حدّث الحفظ',
        'mujaz' => 'مُجاز',
        'mujiz' => 'مُجيز',
        'scientific_certificate' => 'الشهادة العلمية',
        'training_courses' => 'الدورات التدريبية',
        'sharia_academic_study' => 'الدراسة الشرعية الأكاديمية',
        'weekly_lessons' => 'الدروس الأسبوعية',
        'ijazah_jazariyyah' => 'الإجازة الجزرية',
        'issuer' => 'الجهة المانحة',
        'general_notes' => 'ملاحظات عامة',
        'recited_portion' => 'المقدار المتلو',
        'from_section_id' => 'من شعبة',
        'to_section_id' => 'إلى شعبة',
        'exam_status' => 'حالة الامتحان',
        'program_type' => 'نوع البرنامج',
        'riwayah' => 'الرواية',
        'specialty' => 'التخصص',
        'records' => 'عدد السجلات',
        'statuses' => 'الحالات',
        'added' => 'المضافون',
        'removed' => 'المحذوفون',
        'meeting_id' => 'اللقاء',
        'session_id' => 'الدوام',
        'source_type' => 'نوع المصدر',
        'source_id' => 'المصدر',
        'source_pages' => 'الصفحات المصدر',
        'listening_plan_id' => 'خطة الاستماع',
        'khamsa_review_id' => 'مراجعة الخمسات',
        'khamsa_review_item_id' => 'خمسة المراجعة',
        'retake_review_id' => 'مراجعة الإعادة',
        'last_test_id' => 'آخر اختبار',
        'listening_batch_id' => 'دفعة البرنامج',
        'program_batch_id' => 'دفعة البرنامج',
        'plan_item_id' => 'عنصر الخطة',
        'test_id' => 'الاختبار',
        'review_id' => 'المراجعة',
        'review_session_id' => 'جلسة المراجعة',
        'quran_review_session_id' => 'جلسة الاستماع التفصيلي',
        'quran_recitation_session_id' => 'جلسة التسميع',
        'attempt_id' => 'المحاولة',
        'question_id' => 'السؤال',
        'homework_id' => 'الواجب',
        'custom_field_id' => 'الحقل المخصص',
        'recipient_id' => 'المستلم',
        'sender_id' => 'المرسل',
        'notifiable' => 'الجهة المستهدفة',
        'is_correct' => 'صحيحة',
        'correct_answer' => 'الإجابة الصحيحة',
        'answer_text' => 'نص الإجابة',
        'marks_awarded' => 'العلامة الممنوحة',
        'needs_repeat' => 'يحتاج إعادة',
        'attempts' => 'المحاولات',
        'passing_percentage' => 'نسبة النجاح',
        'total_minutes' => 'إجمالي الدقائق',
        'gross_amount' => 'المبلغ الإجمالي',
        'hourly_rate_snapshot' => 'سعر الساعة (لقطة)',
        'monthly_salary_snapshot' => 'الراتب الشهري (لقطة)',
        'pay_type_snapshot' => 'نوع الأجر (لقطة)',
        'rate_breakdown' => 'تفصيل التسعير',
        'transaction_type' => 'نوع العملية المالية',
        'direction' => 'الاتجاه',
        'person_type' => 'نوع الشخص',
        'person_id' => 'الشخص',
        'related_person_type' => 'نوع الشخص المرتبط',
        'related_person_id' => 'الشخص المرتبط',
        'payroll_period_id' => 'كشف الرواتب',
        'reverses_id' => 'العملية المعكوسة',
        'note_type' => 'نوع الملاحظة',
        'audience' => 'الجمهور',
        'file_path' => 'مسار الملف',
        'attachment_path' => 'مسار المرفق',
        'url' => 'الرابط',
        'content' => 'المحتوى',
        'text' => 'النص',
        'data' => 'البيانات',
        'options_config' => 'إعدادات الخيارات',
        'options_source' => 'مصدر الخيارات',
        'value' => 'القيمة',
        'key' => 'المفتاح',
        'week' => 'الأسبوع',
        'week_start' => 'بداية الأسبوع',
        'week_end' => 'نهاية الأسبوع',
        'guardian_phone' => 'هاتف ولي الأمر',
        'name_arabic' => 'الاسم بالعربية',
        'name_english' => 'الاسم بالإنجليزية',
        'logo' => 'الشعار',
        'owner' => 'المالك',
        'resource' => 'المورد',
        'abilities' => 'الصلاحيات',
        'position' => 'الترتيب',
        'rating' => 'التقييم',
        'comment' => 'التعليق',
        'feedback' => 'الملاحظات',
        'total_words' => 'إجمالي الكلمات',
        'correct_words' => 'الكلمات الصحيحة',
        'incorrect_words' => 'الكلمات الخاطئة',
        'hesitation_words' => 'كلمات التردد',
        'forgotten_words' => 'الكلمات المنسية',
        'added_words' => 'كلمات مضافة',
        'tajweed_error_words' => 'أخطاء التجويد',
        'mastery_percentage' => 'نسبة الإتقان',
        'selected_options' => 'الخيارات المحددة',
        'field_key' => 'مفتاح الحقل',
        'field_type' => 'نوع الحقل',
        'is_system' => 'حقل نظامي',
        'is_primary' => 'أساسي',
        'parent_id' => 'العنصر الأب',
        'role_id' => 'الدور',
        'permission_id' => 'الصلاحية',
        'scope' => 'النطاق',
        'effect' => 'الأثر',
    ];

    public const RULE_TYPES = [
        'tasmee_pages' => 'حفظ صفحات جديدة',
        'khamsa_review' => 'إتمام خمسة',
        'test_pass' => 'اجتياز اختبار دفعة',
        'listening_plan_complete' => 'إتمام خطة استماع',
        'sharia_memorization_complete' => 'حفظ الدورة الشرعية كاملاً',
    ];

    public const FALLBACK_VERBS = [
        'created' => 'إنشاء',
        'updated' => 'تعديل',
        'deleted' => 'حذف',
        'recorded' => 'تسجيل',
        'added' => 'إضافة',
        'removed' => 'إزالة',
        'completed' => 'إتمام',
        'cancelled' => 'إلغاء',
        'confirmed' => 'تأكيد',
        'published' => 'نشر',
        'passed' => 'نجاح',
        'failed' => 'رسوب',
        'closed' => 'إغلاق',
        'reopened' => 'إعادة فتح',
        'approved' => 'اعتماد',
        'generated' => 'توليد',
        'opened' => 'فتح',
        'assigned' => 'إسناد',
        'enrolled' => 'تسجيل',
        'transferred' => 'نقل',
        'restored' => 'إرجاع',
        'updated_settings' => 'تعديل إعدادات',
        'changed' => 'تغيير',
    ];

    /**
     * مفردات عربية تُبنى منها أسماء الحقول والكيانات غير المسجّلة صراحةً،
     * حتى لا تظهر أي كلمة إنجليزية في سجل العمليات.
     */
    public const SEGMENTS = [
        'from' => 'من', 'to' => 'إلى', 'juz' => 'الجزء', 'surah' => 'السورة',
        'ayah' => 'الآية', 'ayahs' => 'الآيات', 'page' => 'الصفحة', 'pages' => 'الصفحات',
        'count' => 'عدد', 'number' => 'رقم', 'date' => 'التاريخ', 'time' => 'الوقت',
        'start' => 'البداية', 'end' => 'النهاية', 'starts' => 'البداية', 'ends' => 'النهاية',
        'status' => 'الحالة', 'type' => 'النوع', 'kind' => 'النوع', 'mode' => 'النمط',
        'name' => 'الاسم', 'names' => 'الأسماء', 'title' => 'العنوان', 'code' => 'الرمز',
        'teacher' => 'المعلم', 'student' => 'الطالب', 'guardian' => 'ولي الأمر',
        'parent' => 'ولي الأمر', 'classroom' => 'الصف', 'class' => 'الصف', 'section' => 'الشعبة',
        'session' => 'الدوام', 'subject' => 'المادة', 'program' => 'البرنامج', 'course' => 'الدورة',
        'plan' => 'الخطة', 'item' => 'العنصر', 'items' => 'العناصر', 'test' => 'الاختبار',
        'batch' => 'الدفعة', 'review' => 'المراجعة', 'khamsa' => 'الخمسة',
        'memorization' => 'الحفظ', 'memorized' => 'المحفوظ', 'listening' => 'الاستماع',
        'notes' => 'ملاحظات', 'note' => 'ملاحظة', 'amount' => 'المبلغ', 'salary' => 'الراتب',
        'rate' => 'السعر', 'points' => 'النقاط', 'point' => 'النقطة', 'reason' => 'السبب',
        'role' => 'الدور', 'gender' => 'الجنس', 'email' => 'البريد', 'phone' => 'الهاتف',
        'address' => 'العنوان', 'city' => 'المدينة', 'birth' => 'الميلاد', 'hired' => 'التعيين',
        'month' => 'الشهر', 'monthly' => 'الشهري', 'year' => 'السنة', 'week' => 'الأسبوع',
        'day' => 'اليوم', 'duration' => 'المدة', 'minutes' => 'الدقائق', 'total' => 'الإجمالي',
        'enrolled' => 'التسجيل', 'completed' => 'الإتمام', 'confirmed' => 'التأكيد',
        'approved' => 'الاعتماد', 'closed' => 'الإغلاق', 'opened' => 'الفتح',
        'recorded' => 'التسجيل', 'updated' => 'التحديث', 'created' => 'الإنشاء',
        'deleted' => 'الحذف', 'score' => 'الدرجة', 'marks' => 'العلامات', 'result' => 'النتيجة',
        'message' => 'الرسالة', 'description' => 'الوصف', 'body' => 'النص', 'content' => 'المحتوى',
        'color' => 'اللون', 'order' => 'الترتيب', 'active' => 'مُفعّل', 'system' => 'النظام',
        'primary' => 'أساسي', 'level' => 'المستوى', 'capacity' => 'السعة', 'location' => 'المكان',
        'category' => 'التصنيف', 'value' => 'القيمة', 'field' => 'الحقل', 'custom' => 'المخصص',
        'weekly' => 'الأسبوعي', 'evaluation' => 'التقييم', 'evaluations' => 'التقييمات',
        'word' => 'الكلمة', 'words' => 'الكلمات', 'quran' => 'القرآن', 'hafiz' => 'الحافظ',
        'exam' => 'الامتحان', 'exams' => 'الامتحانات', 'question' => 'السؤال',
        'attempt' => 'المحاولة', 'answer' => 'الإجابة', 'grade' => 'الدرجة', 'schedule' => 'الحصة',
        'attendance' => 'الحضور', 'submission' => 'التسليم', 'homework' => 'الواجب',
        'sharia' => 'الشرعية', 'period' => 'الفترة', 'attribute' => 'الخاصية',
        'certificate' => 'الشهادة', 'rating' => 'التقييم', 'completion' => 'الإتمام',
        'profile' => 'الملف', 'revision' => 'المراجعة', 'enrollment' => 'التسجيل',
        'meeting' => 'اللقاء', 'template' => 'القالب', 'announcement' => 'الإعلان',
        'financial' => 'المالية', 'transaction' => 'العملية', 'payroll' => 'الرواتب',
        'work' => 'العمل', 'slot' => 'الفترة', 'hour' => 'الساعة', 'setting' => 'الإعداد',
        'settings' => 'الإعدادات', 'reward' => 'المكافأة', 'rule' => 'القاعدة',
        'faith' => 'الإيماني', 'user' => 'المستخدم',
        'source' => 'المصدر', 'target' => 'الهدف',
    ];

    /** الحقول التي تُترجم قيمها إلى العربية عند عرضها في سجل العمليات. */
    public const VALUE_FIELDS = [
        'status', 'statuses', 'attendance_status', 'gender', 'program_type', 'exam_status',
        'result', 'direction', 'transaction_type', 'person_type', 'related_person_type',
        'pay_type', 'payment_state', 'role', 'effect', 'scope', 'type', 'kind', 'mode',
        'memorization_status', 'audience', 'note_type', 'relationship', 'source',
        'source_type', 'revelation_type', 'payment_method', 'question_type', 'rule_type',
        'exam_kind', 'delivery_mode', 'session_status', 'section_student_status',
        'course_status', 'lesson_type', 'batch_status', 'item_status', 'plan_status',
        'review_status', 'enrollment_status', 'payroll_status', 'completion_status',
        'sharia_status',
    ];

    /** ترجمة قيم الحقول المعروفة (حسب اسم الحقل ثم حسب القيمة الشائعة). */
    public const VALUE_LABELS = [
        'status' => [
            'active' => 'نشط', 'inactive' => 'غير نشط', 'archived' => 'مؤرشف',
            'locked' => 'مقفل', 'listening' => 'قيد الاستماع', 'available' => 'متاح',
            'listened' => 'تم الاستماع', 'pending_memorization' => 'بانتظار الحفظ',
            'pending_review_5' => 'بانتظار مراجعة ٥', 'ready_for_test' => 'جاهز للاختبار',
            'passed' => 'ناجح', 'needs_repeat' => 'يحتاج إعادة', 'open' => 'مفتوح',
            'closed' => 'مغلق', 'pending' => 'قيد الانتظار', 'completed' => 'مكتمل',
            'cancelled' => 'ملغى', 'draft' => 'مسودة', 'submitted' => 'مُسلَّم',
            'approved' => 'معتمد', 'published' => 'منشور', 'scheduled' => 'مجدول',
            'postponed' => 'مؤجل', 'present' => 'حاضر', 'absent' => 'غائب',
            'late' => 'متأخر', 'excused' => 'إذن', 'attended' => 'حاضر',
            'transferred' => 'منقول', 'confirmed' => 'مؤكد', 'not_tested' => 'لم يُختبَر',
            'tested' => 'مُختبَر',
        ],
        'statuses' => [
            'active' => 'نشط', 'inactive' => 'غير نشط', 'archived' => 'مؤرشف',
            'locked' => 'مقفل', 'listening' => 'قيد الاستماع', 'available' => 'متاح',
            'listened' => 'تم الاستماع', 'pending_memorization' => 'بانتظار الحفظ',
            'pending_review_5' => 'بانتظار مراجعة ٥', 'ready_for_test' => 'جاهز للاختبار',
            'passed' => 'ناجح', 'needs_repeat' => 'يحتاج إعادة', 'open' => 'مفتوح',
            'closed' => 'مغلق', 'pending' => 'قيد الانتظار', 'completed' => 'مكتمل',
            'cancelled' => 'ملغى', 'draft' => 'مسودة', 'submitted' => 'مُسلَّم',
            'approved' => 'معتمد', 'published' => 'منشور', 'scheduled' => 'مجدول',
            'postponed' => 'مؤجل', 'present' => 'حاضر', 'absent' => 'غائب',
            'late' => 'متأخر', 'excused' => 'إذن', 'attended' => 'حاضر',
            'transferred' => 'منقول', 'confirmed' => 'مؤكد', 'not_tested' => 'لم يُختبَر',
            'tested' => 'مُختبَر',
        ],
        'attendance_status' => [
            'present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر',
            'excused' => 'إذن', 'attended' => 'حاضر',
        ],
        'gender' => ['male' => 'ذكر', 'female' => 'أنثى'],
        'program_type' => ['qualifying' => 'تأهيلي', 'ijazah' => 'إجازة', 'training' => 'تدريبي'],
        'exam_status' => [
            'not_tested' => 'لم يُختبَر', 'tested' => 'مُختبَر',
            'passed' => 'ناجح', 'failed' => 'راسب',
        ],
        'result' => [
            'excellent' => 'ممتاز', 'very_good' => 'جيد جداً', 'good' => 'جيد',
            'needs_review' => 'يحتاج مراجعة', 'pass' => 'ناجح', 'fail' => 'راسب',
            'passed' => 'ناجح', 'failed' => 'راسب',
        ],
        'direction' => ['money_in' => 'وارد', 'money_out' => 'صادر'],
        'transaction_type' => [
            'charge' => 'استحقاق', 'payment' => 'دفعة', 'refund' => 'استرداد',
            'transfer' => 'تحويل', 'adjustment' => 'تسوية',
        ],
        'person_type' => ['student' => 'طالب', 'teacher' => 'معلم', 'guardian' => 'ولي أمر'],
        'related_person_type' => ['student' => 'طالب', 'teacher' => 'معلم', 'guardian' => 'ولي أمر'],
        'pay_type' => ['monthly' => 'شهري', 'hourly' => 'بالساعة'],
        'payment_state' => ['unpaid' => 'غير مدفوع', 'partial' => 'جزئي', 'paid' => 'مدفوع'],
        'role' => [
            'lead' => 'رئيسي', 'assistant' => 'مساعد', 'admin' => 'مدير',
            'teacher' => 'معلم', 'super_admin' => 'مدير الجوامع',
            'student' => 'طالب', 'guardian' => 'ولي أمر',
        ],
        'effect' => ['allow' => 'سماح', 'deny' => 'منع'],
        'scope' => ['global' => 'عام', 'mosque' => 'جامع', 'class' => 'صف', 'section' => 'شعبة', 'own' => 'خاص'],
        'type' => [
            'new' => 'جديد', 'revision' => 'مراجعة', 'review' => 'مراجعة',
            'students' => 'الطلاب', 'teachers' => 'الأساتذة', 'classrooms' => 'الصفوف',
            'sections' => 'الشعب', 'lesson' => 'درس', 'lecture' => 'محاضرة',
            'file' => 'ملف', 'video' => 'فيديو', 'link' => 'رابط', 'presentation' => 'عرض',
            'manual' => 'يدوي', 'exam' => 'امتحان', 'quiz' => 'اختبار قصير',
        ],
        'kind' => ['exam' => 'امتحان', 'quiz' => 'اختبار قصير'],
        'mode' => ['onsite' => 'حضوري', 'online' => 'عن بعد', 'hybrid' => 'مدمج'],
        'memorization_status' => [
            'not_memorized' => 'لم يحفظ', 'parts_memorized' => 'حفظ أجزاء منه',
            'half_memorized' => 'حفظ النصف', 'memorized' => 'حفظ كاملاً',
        ],
        'audience' => [
            'all' => 'الجميع', 'teachers' => 'المعلمون', 'guardians' => 'أولياء الأمور',
            'classroom' => 'صف', 'classrooms' => 'صفوف',
        ],
        'note_type' => ['note' => 'ملاحظة', 'suggestion' => 'اقتراح', 'action_item' => 'إجراء'],
        'relationship' => ['father' => 'أب', 'mother' => 'أم', 'guardian' => 'ولي أمر', 'other' => 'أخرى'],
        'source' => [
            'super_admin' => 'مدير الجوامع', 'admin' => 'الإدارة',
            'system' => 'النظام', 'manual' => 'يدوي',
        ],
        'source_type' => [
            'automatic' => 'تلقائي', 'manual' => 'يدوي', 'tasmee_pages' => 'حفظ صفحات جديدة',
            'khamsa_review' => 'إتمام خمسة', 'test_pass' => 'اجتياز اختبار دفعة',
            'listening_plan_complete' => 'إتمام خطة استماع',
            'sharia_memorization_complete' => 'حفظ الدورة الشرعية كاملاً',
        ],
        'revelation_type' => [
            'meccan' => 'مكية', 'medinan' => 'مدنية',
            'Meccan' => 'مكية', 'Medinan' => 'مدنية',
        ],
        'payment_method' => [
            'cash' => 'نقد', 'bank' => 'تحويل بنكي', 'bank_transfer' => 'تحويل بنكي',
        ],
        'question_type' => [
            'mcq' => 'اختيار من متعدد', 'checkbox' => 'متعدد الإجابات',
            'true_false' => 'صح/خطأ', 'short' => 'إجابة قصيرة', 'essay' => 'مقالي',
        ],
        'rule_type' => [
            'tasmee_pages' => 'حفظ صفحات جديدة', 'khamsa_review' => 'إتمام خمسة',
            'test_pass' => 'اجتياز اختبار دفعة', 'listening_plan_complete' => 'إتمام خطة استماع',
            'sharia_memorization_complete' => 'حفظ الدورة الشرعية كاملاً',
        ],
    ];

    /** قيم شائعة تُترجم في أي حقل من حقول VALUE_FIELDS. */
    public const VALUE_FALLBACK = [
        'active' => 'نشط', 'inactive' => 'غير نشط', 'archived' => 'مؤرشف',
        'male' => 'ذكر', 'female' => 'أنثى',
        'qualifying' => 'تأهيلي', 'ijazah' => 'إجازة', 'training' => 'تدريبي',
        'pass' => 'ناجح', 'fail' => 'راسب', 'passed' => 'ناجح', 'failed' => 'راسب',
        'money_in' => 'وارد', 'money_out' => 'صادر',
        'monthly' => 'شهري', 'hourly' => 'بالساعة',
        'unpaid' => 'غير مدفوع', 'partial' => 'جزئي', 'paid' => 'مدفوع',
        'allow' => 'سماح', 'deny' => 'منع',
        'global' => 'عام', 'mosque' => 'جامع', 'own' => 'خاص',
        'student' => 'طالب', 'teacher' => 'معلم', 'guardian' => 'ولي أمر',
        'admin' => 'مدير', 'super_admin' => 'مدير الجوامع',
        'new' => 'جديد', 'revision' => 'مراجعة',
        'exam' => 'امتحان', 'quiz' => 'اختبار قصير',
        'onsite' => 'حضوري', 'online' => 'عن بعد', 'hybrid' => 'مدمج',
        'excellent' => 'ممتاز', 'very_good' => 'جيد جداً', 'good' => 'جيد',
        'needs_review' => 'يحتاج مراجعة', 'needs_repeat' => 'يحتاج إعادة',
        'completed' => 'مكتمل', 'cancelled' => 'ملغى', 'pending' => 'قيد الانتظار',
        'scheduled' => 'مجدول', 'postponed' => 'مؤجل', 'closed' => 'مغلق', 'open' => 'مفتوح',
        'present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر',
        'excused' => 'إذن', 'attended' => 'حاضر',
        'draft' => 'مسودة', 'submitted' => 'مُسلَّم', 'approved' => 'معتمد',
        'published' => 'منشور', 'locked' => 'مقفل', 'available' => 'متاح',
        'listened' => 'تم الاستماع', 'listening' => 'قيد الاستماع',
        'ready_for_test' => 'جاهز للاختبار', 'pending_memorization' => 'بانتظار الحفظ',
        'pending_review_5' => 'بانتظار مراجعة ٥', 'not_tested' => 'لم يُختبَر',
        'tested' => 'مُختبَر', 'confirmed' => 'مؤكد', 'transferred' => 'منقول',
        'not_memorized' => 'لم يحفظ', 'parts_memorized' => 'حفظ أجزاء منه',
        'half_memorized' => 'حفظ النصف', 'memorized' => 'حفظ كاملاً',
        'all' => 'الجميع', 'teachers' => 'المعلمون', 'guardians' => 'أولياء الأمور',
        'classrooms' => 'الصفوف', 'sections' => 'الشعب', 'students' => 'الطلاب',
        'father' => 'أب', 'mother' => 'أم', 'other' => 'أخرى',
        'note' => 'ملاحظة', 'suggestion' => 'اقتراح', 'action_item' => 'إجراء',
        'manual' => 'يدوي', 'automatic' => 'تلقائي',
        'lead' => 'رئيسي', 'assistant' => 'مساعد',
        'mcq' => 'اختيار من متعدد', 'checkbox' => 'متعدد الإجابات',
        'true_false' => 'صح/خطأ', 'short' => 'إجابة قصيرة', 'essay' => 'مقالي',
        'lesson' => 'درس', 'lecture' => 'محاضرة',
        'file' => 'ملف', 'video' => 'فيديو', 'link' => 'رابط', 'presentation' => 'عرض',
        'meccan' => 'مكية', 'medinan' => 'مدنية', 'Meccan' => 'مكية', 'Medinan' => 'مدنية',
        'cash' => 'نقد', 'bank' => 'تحويل بنكي', 'bank_transfer' => 'تحويل بنكي',
    ];

    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function groupExists(string $group): bool
    {
        return $group !== '' && array_key_exists($group, self::GROUPS);
    }

    public static function groupPrefixes(string $group): array
    {
        return self::GROUPS[$group]['prefixes'] ?? [];
    }

    public static function groupLabel(string $group): string
    {
        return self::GROUPS[$group]['label'] ?? self::GROUPS['other']['label'];
    }

    public static function groupIcon(string $group): string
    {
        return self::GROUPS[$group]['icon'] ?? self::GROUPS['other']['icon'];
    }

    public static function groupTone(string $group): string
    {
        return self::GROUPS[$group]['tone'] ?? self::GROUPS['other']['tone'];
    }

    public static function actionLabel(string $action): string
    {
        if (isset(self::ACTIONS[$action])) {
            return self::ACTIONS[$action]['label'];
        }

        $parts = explode('.', $action, 2);
        $resource = $parts[0];
        $verb = $parts[1] ?? '';

        $verbLabel = self::FALLBACK_VERBS[$verb] ?? null;

        if ($verbLabel === null) {
            $segments = preg_split('/[._]/', $verb) ?: [];
            $last = end($segments);
            $verbLabel = is_string($last) ? (self::FALLBACK_VERBS[$last] ?? null) : null;
        }

        $entity = self::entityLabel($resource);

        return $verbLabel === null ? $entity : $verbLabel.' — '.$entity;
    }

    public static function actionGroup(string $action): string
    {
        if (isset(self::ACTIONS[$action])) {
            return self::ACTIONS[$action]['group'];
        }

        $best = 'other';
        $bestLength = 0;

        foreach (self::GROUPS as $group => $meta) {
            foreach ($meta['prefixes'] as $prefix) {
                if (str_starts_with($action, $prefix) && strlen($prefix) > $bestLength) {
                    $best = $group;
                    $bestLength = strlen($prefix);
                }
            }
        }

        return $best;
    }

    public static function actionsMatching(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $matches = [];

        foreach (self::ACTIONS as $action => $meta) {
            if (mb_stripos($meta['label'], $term) !== false) {
                $matches[] = $action;
            }
        }

        return array_slice($matches, 0, 200);
    }

    public static function entityLabel(string $entityType): string
    {
        if ($entityType === '') {
            return 'عنصر';
        }

        if (isset(self::ENTITIES[$entityType])) {
            return self::ENTITIES[$entityType];
        }

        $words = self::translateSegments($entityType);

        return $words === [] ? 'كيان' : implode(' ', $words);
    }

    public static function fieldLabel(string $key): string
    {
        if (isset(self::FIELDS[$key])) {
            return self::FIELDS[$key];
        }

        if (str_contains($key, ':')) {
            $type = substr($key, (int) strrpos($key, ':') + 1);

            if (isset(self::RULE_TYPES[$type])) {
                return self::RULE_TYPES[$type];
            }
        }

        if (Str::isUuid($key)) {
            return 'عنصر مرتبط';
        }

        if (str_ends_with($key, '_count')) {
            return 'عدد '.self::fieldLabel(substr($key, 0, -6));
        }

        if (str_ends_with($key, '_id')) {
            return self::fieldLabel(substr($key, 0, -3)).' (المعرّف)';
        }

        if (str_ends_with($key, '_at')) {
            return 'تاريخ '.self::fieldLabel(substr($key, 0, -3));
        }

        if (str_ends_with($key, '_by')) {
            return 'مَن '.self::fieldLabel(substr($key, 0, -3));
        }

        $words = self::translateSegments($key);

        return $words === [] ? 'حقل إضافي' : implode(' ', $words);
    }

    /** ترجمة أجزاء المفتاح الإنجليزي إلى كلمات عربية (المفردات المعروفة فقط). */
    private static function translateSegments(string $key): array
    {
        $words = [];

        foreach (explode('_', $key) as $segment) {
            if (isset(self::SEGMENTS[$segment])) {
                $words[] = self::SEGMENTS[$segment];
            }
        }

        return $words;
    }

    public static function formatValue(mixed $value, int $depth = 0, ?string $field = null): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '—';
            }

            if ($depth >= 2) {
                return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—', 160);
            }

            if (array_is_list($value)) {
                return implode('، ', array_map(
                    fn (mixed $item) => self::formatValue($item, $depth + 1, $field),
                    $value
                ));
            }

            $parts = [];

            foreach ($value as $key => $item) {
                $childField = isset(self::VALUE_LABELS[$field ?? '']) ? $field : (string) $key;
                $parts[] = self::fieldLabel((string) $key).': '.self::formatValue($item, $depth + 1, $childField);
            }

            return implode('؛ ', $parts);
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return '—';
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $value)) {
                try {
                    return self::dateTimeLabel(Carbon::parse($value));
                } catch (\Throwable) {
                }
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                try {
                    return self::dateLabel(Carbon::parse($value));
                } catch (\Throwable) {
                }
            }

            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                try {
                    return self::timeLabel(Carbon::parse($value));
                } catch (\Throwable) {
                }
            }

            $label = self::valueLabel($field, $value);

            if ($label !== null) {
                return $label;
            }

            return Str::limit($value, 160);
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }

        return (string) $value;
    }

    /** الترجمة العربية لقيمة حقل معروف (مثال: status = active → نشط). */
    public static function valueLabel(?string $field, string $value): ?string
    {
        if ($field === null) {
            return null;
        }

        if (isset(self::VALUE_LABELS[$field][$value])) {
            return self::VALUE_LABELS[$field][$value];
        }

        if (! isset(self::VALUE_LABELS[$field]) && ! in_array($field, self::VALUE_FIELDS, true)) {
            return null;
        }

        return self::VALUE_FALLBACK[$value] ?? null;
    }

    public static function relativeTime(?CarbonInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        $now = Carbon::now();

        if ($date->greaterThan($now)) {
            return 'الآن';
        }

        $seconds = (int) $date->diffInSeconds($now);

        if ($seconds < 60) {
            return 'قبل لحظات';
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return self::ago($minutes, 'دقيقة', 'دقيقتين', 'دقائق');
        }

        $hours = intdiv($seconds, 3600);

        if ($hours < 24) {
            return self::ago($hours, 'ساعة', 'ساعتين', 'ساعات');
        }

        $days = intdiv($seconds, 86400);

        if ($days < 7) {
            return self::ago($days, 'يوم', 'يومين', 'أيام');
        }

        return self::dateLabel($date);
    }

    public static function dateLabel(CarbonInterface $date): string
    {
        return $date->format('j').' '.self::monthName((int) $date->month).' '.$date->format('Y');
    }

    public static function dateTimeLabel(CarbonInterface $date): string
    {
        return self::dateLabel($date).' — '.self::timeLabel($date);
    }

    public static function timeLabel(CarbonInterface $date): string
    {
        $hour = (int) $date->format('G');
        $hour12 = $hour % 12;

        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return $hour12.':'.$date->format('i').' '.($hour < 12 ? 'ص' : 'م');
    }

    public static function dayLabel(CarbonInterface $date): string
    {
        $day = Carbon::parse($date)->startOfDay();
        $today = Carbon::today();

        $prefix = match (true) {
            $day->equalTo($today) => 'اليوم',
            $day->equalTo($today->copy()->subDay()) => 'أمس',
            default => self::weekdayName((int) $date->dayOfWeek),
        };

        return $prefix.' — '.self::dateLabel($date);
    }

    public static function weekdayName(int $dayOfWeek): string
    {
        return [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ][$dayOfWeek] ?? '';
    }

    public static function monthName(int $month): string
    {
        return [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ][$month] ?? (string) $month;
    }

    private static function ago(int $count, string $one, string $two, string $many): string
    {
        return match (true) {
            $count <= 1 => 'قبل '.$one,
            $count === 2 => 'قبل '.$two,
            $count <= 10 => 'قبل '.$count.' '.$many,
            default => 'قبل '.$count.' '.$one,
        };
    }
}
