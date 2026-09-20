<?php

namespace Tests\Feature;

use App\Enums\PaymentState;
use App\Models\FinancialTransaction;
use App\Models\HourlyRate;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSlot;
use App\Services\RoleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كشوف الرواتب بالساعات: الاحتساب، الدفع الجزئي، الزائد، العكس، ولقطة الإغلاق.
 */
class PayrollPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function manager(Tenant $mosque): User
    {
        return User::factory()->admin()->for($mosque)->create();
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque, array $attributes = []): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id, ...$attributes]);

        return [$user, $teacher];
    }

    private function slot(Tenant $mosque, Teacher $teacher, string $date, string $start, string $end): WorkSlot
    {
        return WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => WorkSlot::durationMinutes($start, $end),
        ]);
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

    public function test_sheet_gross_uses_slots_and_rates(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->rate($mosque, $teacher, 20, '2026-09-01');
        $this->slot($mosque, $teacher, '2026-09-20', '09:00', '15:00');

        $this->actingAs($manager)
            ->get(route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('6س')
            ->assertSee('120.00');
    }

    public function test_partial_payments_update_remaining_and_state(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->rate($mosque, $teacher, 20, '2026-09-01');
        $this->slot($mosque, $teacher, '2026-09-20', '09:00', '19:00');

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['month' => '2026-09', 'amount' => 80])
            ->assertRedirect();

        $period = PayrollPeriod::query()->where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame('200.00', $period->gross_amount);
        $this->assertSame('80.00', $period->paid_amount);
        $this->assertSame(120.0, $period->remainingAmount());
        $this->assertSame(PaymentState::Partial, $period->paymentState());

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['month' => '2026-09', 'amount' => 120])
            ->assertRedirect();

        $period->refresh();
        $this->assertSame('200.00', $period->paid_amount);
        $this->assertSame(PaymentState::Paid, $period->paymentState());
    }

    public function test_overpayment_is_rejected(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->rate($mosque, $teacher, 20, '2026-09-01');
        $this->slot($mosque, $teacher, '2026-09-20', '09:00', '14:00');

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['month' => '2026-09', 'amount' => 600])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, FinancialTransaction::query()->count());
    }

    public function test_reversed_payment_restores_the_remaining_amount(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->rate($mosque, $teacher, 20, '2026-09-01');
        $this->slot($mosque, $teacher, '2026-09-20', '09:00', '19:00');

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['month' => '2026-09', 'amount' => 200])
            ->assertRedirect();

        $payment = FinancialTransaction::query()
            ->where('person_id', $teacher->id)
            ->where('transaction_type', 'payment')
            ->firstOrFail();

        $this->actingAs($manager)
            ->post(route('admin.finance.reverse', $payment))
            ->assertRedirect();

        $period = PayrollPeriod::query()->where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame('0.00', $period->fresh()->paid_amount);
        $this->assertSame(PaymentState::Unpaid, $period->fresh()->paymentState());
    }

    public function test_closed_snapshot_is_immune_to_rate_changes(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $rate = $this->rate($mosque, $teacher, 20, '2026-09-01');
        $this->slot($mosque, $teacher, '2026-09-20', '09:00', '15:00');

        $this->actingAs($manager)
            ->post(route('admin.payroll.close', $teacher), ['month' => '2026-09'])
            ->assertRedirect();

        $rate->delete();
        $this->rate($mosque, $teacher, 30, '2026-09-01');

        $period = PayrollPeriod::query()->where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertTrue($period->isClosed());
        $this->assertSame('120.00', $period->gross_amount);

        $this->actingAs($manager)
            ->get(route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('120.00')
            ->assertDontSee('180.00');
    }

    public function test_zero_hours_month_shows_unpaid_zero(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->get(route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('0.00')
            ->assertSee('غير مدفوع');
    }

    public function test_teacher_portal_lists_only_his_own_periods(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00:00'));

        $mosque = $this->mosque();
        [$teacherAUser, $teacherA] = $this->teacher($mosque);
        [, $teacherB] = $this->teacher($mosque);

        PayrollPeriod::create([
            'tenant_id' => $mosque->id, 'teacher_id' => $teacherA->id,
            'year' => 2026, 'month' => 9, 'total_minutes' => 0,
            'gross_amount' => 100, 'paid_amount' => 0, 'status' => 'open',
        ]);
        $other = PayrollPeriod::create([
            'tenant_id' => $mosque->id, 'teacher_id' => $teacherB->id,
            'year' => 2026, 'month' => 10, 'total_minutes' => 0,
            'gross_amount' => 200, 'paid_amount' => 0, 'status' => 'open',
        ]);

        $this->actingAs($teacherAUser)
            ->get(route('teacher.payroll.index'))
            ->assertOk()
            ->assertSee('سبتمبر')
            ->assertDontSee('أكتوبر');

        $this->actingAs($teacherAUser)
            ->get(route('teacher.payroll.show', $other))
            ->assertForbidden();
    }

    public function test_teacher_can_open_his_own_period_details(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [$teacherUser, $teacher] = $this->teacher($mosque);

        $this->rate($mosque, $teacher, 10, '2026-09-01');
        $this->slot($mosque, $teacher, '2026-09-20', '09:00', '19:00');

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['month' => '2026-09', 'amount' => 40])
            ->assertRedirect();

        $period = PayrollPeriod::query()->where('teacher_id', $teacher->id)->firstOrFail();

        $this->actingAs($teacherUser)
            ->get(route('teacher.payroll.show', $period))
            ->assertOk()
            ->assertSee('سبتمبر')
            ->assertSee('100.00')
            ->assertSee('60.00');
    }
}
