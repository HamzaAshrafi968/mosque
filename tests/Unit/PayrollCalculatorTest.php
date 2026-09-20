<?php

namespace Tests\Unit;

use App\Enums\PaymentState;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\WorkSlot;
use App\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * احتساب الإجمالي بالساعات فقط: Σ (دقائق × سعر تاريخها) ÷ 60.
 */
class PayrollCalculatorTest extends TestCase
{
    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        return $mosque;
    }

    private function slot(string $date, int $minutes): WorkSlot
    {
        $slot = new WorkSlot(['date' => $date, 'start_time' => '09:00', 'end_time' => '10:00']);
        $slot->duration_minutes = $minutes;

        return $slot;
    }

    public function test_gross_multiplies_minutes_by_rate(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);

        HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-09-01',
        ]);

        $slots = collect([$this->slot('2026-09-10', 120), $this->slot('2026-09-11', 240)]);

        $result = app(PayrollCalculator::class)->calculate($teacher, $slots);

        $this->assertSame(360, $result['minutes']);
        $this->assertSame(120.0, $result['gross']);
        $this->assertSame(20.0, $result['hourly_rate']);
        $this->assertFalse($result['rate_is_mixed']);
    }

    public function test_rate_change_mid_month_prices_each_slot_by_its_date(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);

        HourlyRate::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'rate' => 18, 'effective_from' => '2026-09-01', 'effective_to' => '2026-09-15']);
        HourlyRate::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'rate' => 20, 'effective_from' => '2026-09-16']);

        $slots = collect([$this->slot('2026-09-10', 120), $this->slot('2026-09-20', 240)]);

        $result = app(PayrollCalculator::class)->calculate($teacher, $slots);

        $this->assertSame(116.0, $result['gross']);
        $this->assertCount(2, $result['breakdown']);
        $this->assertNull($result['hourly_rate']);
        $this->assertTrue($result['rate_is_mixed']);
    }

    public function test_amounts_are_rounded_half_up_to_two_decimals(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);

        HourlyRate::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'rate' => 19.99, 'effective_from' => '2026-09-01']);

        $result = app(PayrollCalculator::class)->calculate($teacher, collect([$this->slot('2026-09-10', 50)]));

        $this->assertSame(16.66, $result['gross']);
    }

    public function test_missing_rates_are_reported_and_excluded_from_the_gross(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);

        $result = app(PayrollCalculator::class)->calculate($teacher, collect([$this->slot('2026-09-10', 120)]));

        $this->assertSame(0.0, $result['gross']);
        $this->assertSame(['2026-09-10'], $result['missing']);
    }

    public function test_payment_state_is_derived_from_gross_and_paid(): void
    {
        $calculator = app(PayrollCalculator::class);

        $this->assertSame(PaymentState::Unpaid, $calculator->paymentState(100, 0));
        $this->assertSame(PaymentState::Partial, $calculator->paymentState(100, 40));
        $this->assertSame(PaymentState::Paid, $calculator->paymentState(100, 100));
        $this->assertSame(PaymentState::Unpaid, $calculator->paymentState(0, 0));
    }
}
