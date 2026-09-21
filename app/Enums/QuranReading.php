<?php

namespace App\Enums;

/**
 * القراءات العشر الكبرى — يختار الطالب/المشرف قراءةً واحدة لكل دورة قراءات.
 */
enum QuranReading: string
{
    case Nafeh = 'nafeh';
    case IbnKathir = 'ibn_kathir';
    case AbuAmr = 'abu_amr';
    case IbnAmir = 'ibn_amir';
    case Asim = 'asim';
    case Hamzah = 'hamzah';
    case AlKisai = 'al_kisai';
    case AbuJafar = 'abu_jafar';
    case Yaqub = 'yaqub';
    case Khalaf = 'khalaf';

    public function label(): string
    {
        return match ($this) {
            self::Nafeh => 'نافع المدني',
            self::IbnKathir => 'ابن كثير المكي',
            self::AbuAmr => 'أبو عمرو البصري',
            self::IbnAmir => 'ابن عامر الشامي',
            self::Asim => 'عاصم الكوفي',
            self::Hamzah => 'حمزة الكوفي',
            self::AlKisai => 'الكسائي الكوفي',
            self::AbuJafar => 'أبو جعفر المدني',
            self::Yaqub => 'يعقوب الحضرمي',
            self::Khalaf => 'خلف العاشر',
        };
    }

    /** «قراءة نافع المدني» — للعرض المدمج مع اسم البرنامج. */
    public function programLabel(): string
    {
        return 'قراءة '.$this->label();
    }
}
