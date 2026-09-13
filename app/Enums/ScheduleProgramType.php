<?php

namespace App\Enums;

/**
 * نوع تخصص الجدول (program). الخمسة الأولى افتراضية لكل جامع،
 * و«مخصص» لأي برنامج قرآني أو دعوي يضيفه المدير.
 */
enum ScheduleProgramType: string
{
    case Tahfeez = 'tahfeez';
    case Ijazah = 'ijazah';
    case HafizExams = 'hafiz_exams';
    case ShariaCourses = 'sharia_courses';
    case Quran = 'quran';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Tahfeez => 'برنامج التحفيظ',
            self::Ijazah => 'برنامج الإجازة',
            self::HafizExams => 'اختبارات الحفظ',
            self::ShariaCourses => 'الدورات الشرعية',
            self::Quran => 'البرامج القرآنية',
            self::Custom => 'برنامج مخصص',
        };
    }

    /** لون البطاقة في الواجهة (hex). */
    public function color(): string
    {
        return match ($this) {
            self::Tahfeez => '#047857',
            self::Ijazah => '#b45309',
            self::HafizExams => '#0369a1',
            self::ShariaCourses => '#7c3aed',
            self::Quran => '#0f766e',
            self::Custom => '#475569',
        };
    }

    /** اسم مسار الوحدة المرتبطة بالنوع (للانتقال السريع) أو null. */
    public function moduleRoute(): ?string
    {
        return match ($this) {
            self::Tahfeez => 'admin.quran.tasmee.index',
            self::Ijazah => 'admin.quran.ijazah.index',
            self::HafizExams => 'admin.quran.exams.index',
            self::ShariaCourses => 'admin.sharia-courses.index',
            self::Quran => 'admin.quran.index',
            self::Custom => null,
        };
    }
}
