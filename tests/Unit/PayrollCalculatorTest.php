<?php

namespace Tests\Unit;

use App\Enums\PaymentState;
use App\Enums\PayType;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\WorkSlot;
use App\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * احتساب الإجمالي: الشهري ثابت، وبالساعة = Σ (دقائق × سعر تاريخها) ÷ 60.
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

    public function test_monthly_teacher_gross_is_the_monthly_salary_regardless_of_slots(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'pay_type' => 'monthly',
            'monthly_salary' => 1500,
        ]);

        $result = app(PayrollCalculator::class)->calculate($teacher, collect([$this->slot('2026-09-20', 480)]));

        $this->assertSame(PayType::Monthly, $result['pay_type']);
        $this->assertSame(480, $result['minutes']);
        $this->assertSame(1500.0, $result['gross']);
    }

    public function test_hourly_teacher_gross_multiplies_minutes_by_rate(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'pay_type' => 'hourly', 'monthly_salary' => null]);

        HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-09-01',
        ]);

        $slots = collect([$this->slot('2026-09-10', 120), $this->slot('2026-09-11', 240)]);

        $result = app(PayrollCalculator::class)->calculate($teacher, $slots);

        $this->assertSame(PayType::Hourly, $result['pay_type']);
        $this->assertSame(360, $result['minutes']);
        $this->assertSame(120.0, $result['gross']);
        $this->assertSame(20.0, $result['hourly_rate']);
    }

    public function test_rate_change_mid_month_prices_each_slot_by_its_date(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'pay_type' => 'hourly']);

        HourlyRate::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'rate' => 18, 'effective_from' => '2026-09-01', 'effective_to' => '2026-09-15']);
        HourlyRate::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'rate' => 20, 'effective_from' => '2026-09-16']);

        $slots = collect([$this->slot('2026-09-10', 120), $this->slot('2026-09-20', 240)]);

        $result = app(PayrollCalculator::class)->calculate($teacher, $slots);

        $this->assertSame(116.0, $result['gross']);
        $this->assertCount(2, $result['breakdown']);
        $this->assertNull($result['hourly_rate']);
    }

    public function test_amounts_are_rounded_half_up_to_two_decimals(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'pay_type' => 'hourly']);

        HourlyRate::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'rate' => 19.99, 'effective_from' => '2026-09-01']);

        $result = app(PayrollCalculator::class)->calculate($teacher, collect([$this->slot('2026-09-10', 50)]));

        $this->assertSame(16.66, $result['gross']);
    }

    public function test_missing_rates_are_reported_and_excluded_from_the_gross(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'pay_type' => 'hourly']);

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
