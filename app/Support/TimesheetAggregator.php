<?php

namespace App\Support;

use App\Models\WorkSlot;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * تجميع فترات العمل الفعلية على مستويات اليوم/الأسبوع/الشهر.
 *
 * القواعد الحاكمة (BR-07 / ADR-09):
 * - الأسبوع يبدأ الأحد وينتهي السبت.
 * - اليوم ينتمي لشهره الميلادي؛ الأسبوع العابر لشهرين يظهر مقصوصاً في كل شهر
 *   (تُعلَّم بدايته/نهايته) حتى لا يُحتسب أي يوم مرتين.
 */
final class TimesheetAggregator
{
    public static function weekStart(CarbonInterface|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfWeek(Carbon::SUNDAY);
    }

    public static function weekEnd(CarbonInterface|string $date): CarbonImmutable
    {
        return self::weekStart($date)->endOfWeek(Carbon::SATURDAY);
    }

    /** @param  Collection<int, WorkSlot>  $slots */
    public static function totalMinutes(Collection $slots): int
    {
        return (int) $slots->sum(fn (WorkSlot $slot) => (int) $slot->duration_minutes);
    }

    /**
     * دقائق كل يوم.
     *
     * @param  Collection<int, WorkSlot>  $slots
     * @return array<string, int> ['Y-m-d' => minutes]
     */
    public static function minutesByDate(Collection $slots): array
    {
        return $slots
            ->groupBy(fn (WorkSlot $slot) => CarbonImmutable::parse($slot->date)->toDateString())
            ->map(fn (Collection $day) => self::totalMinutes($day))
            ->all();
    }

    /**
     * دقائق كل أسبوع (مفتاح = تاريخ بداية الأسبوع الأحد).
     *
     * @param  Collection<int, WorkSlot>  $slots
     * @return array<string, int>
     */
    public static function minutesByWeek(Collection $slots): array
    {
        return $slots
            ->groupBy(fn (WorkSlot $slot) => self::weekStart($slot->date)->toDateString())
            ->map(fn (Collection $week) => self::totalMinutes($week))
            ->all();
    }

    /**
     * كتل الشهر الأربع الثابتة حسب رقم اليوم (١–٧، ٨–١٤، ١٥–٢١، ٢٢–نهاية الشهر)
     * — هذا هو العرض المعتمد في واجهات الرواتب: كل شهر ٤ أسابيع واضحة.
     *
     * @return array<int, array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     label: string,
     *     number: int
     * }>
     */
    public static function monthDayBlocks(int $year, int $month): array
    {
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $lastDay = (int) $monthEnd->format('j');

        $definitions = [
            [1, 7, 'الأول'],
            [8, 14, 'الثاني'],
            [15, 21, 'الثالث'],
            [22, null, 'الرابع'],
        ];

        $blocks = [];

        foreach ($definitions as $index => [$from, $to, $ordinal]) {
            $start = $monthStart->setDay($from);
            $end = $to === null ? $monthEnd : $monthStart->setDay(min($to, $lastDay));

            $blocks[] = [
                'start' => $start,
                'end' => $end,
                'label' => 'الأسبوع '.$ordinal.' ('.self::arabicDigits($start->format('j')).'–'.self::arabicDigits($end->format('j')).')',
                'number' => $index + 1,
            ];
        }

        return $blocks;
    }

    /** تحويل الأرقام إلى أرقام عربية-هندية للعناوين (١٢٣). */
    public static function arabicDigits(string|int $value): string
    {
        return strtr((string) $value, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }

    /**
     * أسابيع الشهر (الأحد–السبت) مقصوصة على حدود الشهر.
     *
     * @return array<int, array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     label: string,
     *     starts_before_month: bool,
     *     ends_after_month: bool
     * }>
     */
    public static function monthWeeks(int $year, int $month): array
    {
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $cursor = self::weekStart($monthStart);

        $weeks = [];
        $index = 0;

        while ($cursor->lte($monthEnd)) {
            $weekStart = $cursor;
            $weekEnd = self::weekEnd($cursor);

            $start = $weekStart->lt($monthStart) ? $monthStart : $weekStart;
            $end = $weekEnd->gt($monthEnd) ? $monthEnd : $weekEnd;

            $weeks[] = [
                'start' => $start,
                'end' => $end,
                'label' => 'أسبوع '.($index + 1).' ('.$start->format('j/n').' – '.$end->format('j/n').')',
                'starts_before_month' => $weekStart->lt($monthStart),
                'ends_after_month' => $weekEnd->gt($monthEnd),
            ];

            $cursor = $cursor->addWeek();
            $index++;
        }

        return $weeks;
    }
}
