<?php

namespace Tests\Feature;

use App\Enums\PayType;
use App\Models\HourlyRate;
use App\Models\PayrollPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSlot;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * سجل أسعار الساعة: الإضافة والتدقيق ومنع التداخل وتأثير التعديل على الكشوف.
 */
class HourlyRateHistoryTest extends TestCase
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

    public function test_manager_adds_a_rate_and_it_is_audited(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.payroll.rates.store'), [
                'teacher_id' => $teacher->id,
                'rate' => 20,
                'effective_from' => '2026-09-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hourly_rates', [
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hourly_rate.created']);
    }

    public function test_overlapping_rates_are_rejected(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)->post(route('admin.payroll.rates.store'), [
            'teacher_id' => $teacher->id,
            'rate' => 18,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('admin.payroll.rates.store'), [
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-06-01',
        ])->assertSessionHasErrors('effective_from');

        $this->assertSame(1, HourlyRate::query()->count());
    }

    public function test_rate_change_refreshes_open_periods_but_not_closed_ones(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'hourly', 'monthly_salary' => null]);

        WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '09:00',
            'end_time' => '15:00',
            'duration_minutes' => 360,
        ]);

        // كشف مغلق لشهر آخر — يجب ألا يتأثر بأي سعر جديد
        $closed = PayrollPeriod::create([
            'tenant_id' => $mosque->id, 'teacher_id' => $teacher->id,
            'year' => 2026, 'month' => 8, 'total_minutes' => 360,
            'pay_type_snapshot' => 'hourly', 'gross_amount' => 100,
            'paid_amount' => 0, 'status' => 'closed',
        ]);

        $this->actingAs($manager)->post(route('admin.payroll.rates.store'), [
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-09-01',
        ])->assertRedirect();

        // لا يوجد كشف مفتوح بعد → يُنشأ عند فتح الصفحة/الدفع، لكن الكشف المغلق ثابت
        $this->assertSame('100.00', $closed->fresh()->gross_amount);

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['month' => '2026-09', 'amount' => 120])
            ->assertRedirect();

        $period = PayrollPeriod::query()
            ->where('teacher_id', $teacher->id)
            ->where('year', 2026)->where('month', 9)
            ->firstOrFail();

        $this->assertSame('120.00', $period->gross_amount);
        $this->assertSame('120.00', $period->paid_amount);
    }

    public function test_adding_rate_switches_teacher_to_hourly_and_refreshes_open_period(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'monthly', 'monthly_salary' => 500]);

        WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '09:00',
            'end_time' => '15:00',
            'duration_minutes' => 360,
        ]);

        $period = PayrollPeriod::create([
            'tenant_id' => $mosque->id, 'teacher_id' => $teacher->id,
            'year' => 2026, 'month' => 9, 'total_minutes' => 0,
            'pay_type_snapshot' => 'monthly', 'monthly_salary_snapshot' => 500,
            'gross_amount' => 500, 'paid_amount' => 0, 'status' => 'open',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.hourly-rates.store'), [
                'teacher_id' => $teacher->id,
                'rate' => 20,
                'effective_from' => '2026-09-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PayType::Hourly, $teacher->fresh()->pay_type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.salary_updated']);

        $period->refresh();
        $this->assertSame('hourly', $period->pay_type_snapshot->value);
        $this->assertSame(360, $period->total_minutes);
        $this->assertSame('120.00', $period->gross_amount);

        // ملخص الشهر الحالي في الإعدادات يعرض الساعات × السعر تلقائياً.
        $this->actingAs($manager)
            ->get(route('admin.settings.hourly-rates.index'))
            ->assertOk()
            ->assertSee('6س')
            ->assertSee('120.00')
            ->assertSee('مكتمل التسعير');
    }

    public function test_settings_center_exposes_hourly_rates_tab_and_legacy_url_redirects(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('أسعار الساعة');

        $this->actingAs($manager)->get(route('admin.settings.hourly-rates.index'))
            ->assertOk()
            ->assertSee('إضافة سعر');

        $this->actingAs($manager)->get(route('admin.payroll.rates.index'))
            ->assertRedirect(route('admin.settings.hourly-rates.index'));
    }

    public function test_rate_management_follows_the_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.settings.hourly-rates.index'))->assertOk();

        $permission = Permission::query()->where('code', 'hourly_rates.manage')->firstOrFail();
        Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($manager)->get(route('admin.settings.hourly-rates.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.payroll.rates.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee(route('admin.settings.hourly-rates.index'));
    }
}
