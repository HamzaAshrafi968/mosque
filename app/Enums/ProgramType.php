<?php

namespace App\Enums;

enum ProgramType: string
{
    case Qualifying = 'qualifying';
    case Ijazah = 'ijazah';
    case Readings = 'readings';

    public function label(): string
    {
        return match ($this) {
            self::Qualifying => 'البرنامج التأهيلي',
            self::Ijazah => 'برنامج الإجازة',
            self::Readings => 'برنامج القراءات',
        };
    }
}
