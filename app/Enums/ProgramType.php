<?php

namespace App\Enums;

enum ProgramType: string
{
    case Training = 'training';
    case Qualifying = 'qualifying';
    case Ijazah = 'ijazah';

    public function label(): string
    {
        return match ($this) {
            self::Training => 'البرنامج التدريبي',
            self::Qualifying => 'البرنامج التأهيلي',
            self::Ijazah => 'برنامج الإجازة',
        };
    }

    /** البرنامج التالي في سلسلة التحويل التلقائي عند إتمام دورة الاستماع. */
    public function nextProgram(): ?self
    {
        return match ($this) {
            self::Training => self::Ijazah,
            self::Ijazah => self::Qualifying,
            self::Qualifying => null,
        };
    }
}
