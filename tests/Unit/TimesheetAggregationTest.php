<?php

namespace Tests\Unit;

use App\Models\WorkSlot;
use App\Support\TimesheetAggregator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * ثوابت التجميع: اليوم = Σ الفترات، الأسبوع = Σ الأيام، الشهر = Σ الأسابيع
 * بلا ازدواج (BR-07 / ADR-09).
 */
class TimesheetAggregationTest extends TestCase
{
    private function slot(string $date, string $start, string $end): WorkSlot
    {
        $slot = new WorkSlot([
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $slot->duration_minutes = WorkSlot::durationMinutes($start, $end);

        return $slot;
    }

    public function test_daily_totals_sum_slots_and_weekly_totals_sum_days(): void
    {
        $slots = collect([
            $this->slot('2026-09-20', '06:00', '08:00'),
            $this->slot('2026-09-20', '14:00', '20:00'),
            $this->slot('2026-09-21', '09:00', '13:00'),
        ]);

        $this->assertSame(720, TimesheetAggregator::totalMinutes($slots));
        $this->assertSame(['2026-09-20' => 480, '2026-09-21' => 240], TimesheetAggregator::minutesByDate($slots));

        $byWeek = TimesheetAggregator::minutesByWeek($slots);
        $this->assertSame(720, array_sum($byWeek));
    }

    public function test_week_starts_on_sunday(): void
    {
        // 2026-09-20 أحد → بداية الأسبوع هي نفس اليوم
        $this->assertSame('2026-09-20', TimesheetAggregator::weekStart('2026-09-20')->toDateString());
        $this->assertSame('2026-09-20', TimesheetAggregator::weekStart('2026-09-26')->toDateString());
        // السبت 2026-09-19 ينتمي لأسبوع يبدأ الأحد 2026-09-13
        $this->assertSame('2026-09-13', TimesheetAggregator::weekStart('2026-09-19')->toDateString());
    }

    public function test_month_weeks_are_clipped_to_month_boundaries(): void
    {
        $weeks = TimesheetAggregator::monthWeeks(2026, 9);

        $this->assertCount(5, $weeks);
        $this->assertSame('2026-09-01', $weeks[0]['start']->toDateString());
        $this->assertSame('2026-09-05', $weeks[0]['end']->toDateString());
        $this->assertTrue($weeks[0]['starts_before_month']);
        $this->assertFalse($weeks[0]['ends_after_month']);
        $this->assertSame('2026-09-27', $weeks[4]['start']->toDateString());
        $this->assertSame('2026-09-30', $weeks[4]['end']->toDateString());
        $this->assertTrue($weeks[4]['ends_after_month']);
    }

    public function test_straddling_week_is_not_double_counted(): void
    {
        $slots = collect([
            $this->slot('2026-09-30', '09:00', '11:00'),
            $this->slot('2026-10-01', '09:00', '11:00'),
        ]);

        $september = $slots->filter(fn (WorkSlot $slot) => CarbonImmutable::parse($slot->date)->month === 9);
        $october = $slots->filter(fn (WorkSlot $slot) => CarbonImmutable::parse($slot->date)->month === 10);

        $this->assertSame(120, TimesheetAggregator::totalMinutes($september));
        $this->assertSame(120, TimesheetAggregator::totalMinutes($october));
        $this->assertSame(240, TimesheetAggregator::totalMinutes($slots));
    }
}
