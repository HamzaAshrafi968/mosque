<?php

namespace Tests\Unit;

use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\WorkSlot;
use App\Services\HourlyRateResolver;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * حل سعر الساعة حسب التاريخ وتفصيل التسعير (BR-09 / ADR-10).
 */
class HourlyRateResolverTest extends TestCase
{
    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        return $mosque;
    }

    private function teacher(Tenant $mosque): Teacher
    {
        return Teacher::factory()->create(['tenant_id' => $mosque->id]);
    }

    private function rate(Tenant $mosque, Teacher $teacher, float $rate, string $from, ?string $to = null): HourlyRate
    {
        return HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => $rate,
            'effective_from' => $from,
            'effective_to' => $to,
        ]);
    }

    private function slot(string $date, string $start, string $end): WorkSlot
    {
        $slot = new WorkSlot(['date' => $date, 'start_time' => $start, 'end_time' => $end]);
        $slot->duration_minutes = WorkSlot::durationMinutes($start, $end);

        return $slot;
    }

    public function test_rate_is_resolved_by_the_slot_date(): void
    {
        $mosque = $this->mosque();
        $teacher = $this->teacher($mosque);

        $this->rate($mosque, $teacher, 18, '2026-01-01', '2026-05-31');
        $this->rate($mosque, $teacher, 20, '2026-06-01');

        $resolver = app(HourlyRateResolver::class);

        $this->assertSame(18.0, $resolver->rateFor($teacher, '2026-03-15'));
        $this->assertSame(20.0, $resolver->rateFor($teacher, '2026-06-01'));
        $this->assertSame(20.0, $resolver->rateFor($teacher, '2027-01-01'));
        $this->assertNull($resolver->rateFor($teacher, '2025-12-31'));
    }

    public function test_breakdown_splits_by_rate_when_the_rate_changes_mid_month(): void
    {
        $mosque = $this->mosque();
        $teacher = $this->teacher($mosque);

        $this->rate($mosque, $teacher, 18, '2026-09-01', '2026-09-15');
        $this->rate($mosque, $teacher, 20, '2026-09-16');

        $slots = collect([
            $this->slot('2026-09-10', '09:00', '11:00'),
            $this->slot('2026-09-20', '09:00', '13:00'),
        ]);

        $result = app(HourlyRateResolver::class)->breakdown($teacher, $slots);

        $this->assertSame([], $result['missing']);
        $this->assertCount(2, $result['breakdown']);
        $this->assertSame(18.0, $result['breakdown'][0]['rate']);
        $this->assertSame(120, $result['breakdown'][0]['minutes']);
        $this->assertSame(20.0, $result['breakdown'][1]['rate']);
        $this->assertSame(240, $result['breakdown'][1]['minutes']);
    }

    public function test_missing_rate_dates_are_reported_instead_of_silently_zero(): void
    {
        $mosque = $this->mosque();
        $teacher = $this->teacher($mosque);

        $slots = collect([$this->slot('2026-09-20', '09:00', '11:00')]);

        $result = app(HourlyRateResolver::class)->breakdown($teacher, $slots);

        $this->assertSame([], $result['breakdown']);
        $this->assertSame(['2026-09-20'], $result['missing']);
    }

    public function test_overlapping_rate_periods_are_rejected(): void
    {
        $mosque = $this->mosque();
        $teacher = $this->teacher($mosque);
        $this->rate($mosque, $teacher, 18, '2026-01-01', '2026-06-30');

        $resolver = app(HourlyRateResolver::class);

        $this->expectException(ValidationException::class);
        $resolver->assertNoOverlap($teacher, '2026-06-01', '2026-12-31');
    }

    public function test_open_ended_rates_block_any_later_overlap(): void
    {
        $mosque = $this->mosque();
        $teacher = $this->teacher($mosque);
        $this->rate($mosque, $teacher, 18, '2026-01-01');

        $resolver = app(HourlyRateResolver::class);

        $this->expectException(ValidationException::class);
        $resolver->assertNoOverlap($teacher, '2026-05-01', null);
    }

    public function test_non_overlapping_rate_periods_are_accepted(): void
    {
        $mosque = $this->mosque();
        $teacher = $this->teacher($mosque);
        $this->rate($mosque, $teacher, 18, '2026-01-01', '2026-06-30');

        app(HourlyRateResolver::class)->assertNoOverlap($teacher, '2026-07-01', '2026-12-31');

        $this->assertSame(1, HourlyRate::query()->count());
    }
}
