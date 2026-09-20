<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * مدة صلاحية الحصة في الجدول: ليوم واحد، لأسبوع، لشهر، حتى انتهاء دورة
 * البرنامج، أو مفتوحة بلا نهاية. بعد انتهاء المدة تُحذف الحصة تلقائياً
 * (`schedules:purge-expired`) وتختفي من الجدول.
 */
enum ScheduleDuration: string
{
    case Open = 'open';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Course = 'course';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة (بدون نهاية)',
            self::Day => 'ليوم واحد',
            self::Week => 'لأسبوع',
            self::Month => 'لشهر',
            self::Course => 'حتى انتهاء الدورة',
        };
    }

    /** تاريخ النهاية المحسوب من تاريخ البداية (وللدورة: تاريخ نهاية البرنامج). */
    public function endDate(CarbonInterface $startsOn, ?CarbonInterface $courseEndsOn = null): ?CarbonInterface
    {
        $start = Carbon::parse($startsOn);

        return match ($this) {
            self::Open => null,
            self::Day => $start,
            self::Week => $start->copy()->addDays(6),
            self::Month => $start->copy()->addMonthNoOverflow()->subDay(),
            self::Course => $courseEndsOn !== null ? Carbon::parse($courseEndsOn) : null,
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
