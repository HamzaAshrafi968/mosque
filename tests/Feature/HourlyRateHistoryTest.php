<?php

namespace Tests\Feature;

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

    public function test_adding_a_new_rate_auto_closes_the_previous_open_rate(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $previous = HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-09-01',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.hourly-rates.store'), [
                'teacher_id' => $teacher->id,
                'rate' => 30,
                'effective_from' => '2026-09-20',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('2026-09-19', $previous->fresh()->effective_to->toDateString());

        $current = HourlyRate::query()
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('effective_from')
            ->firstOrFail();

        $this->assertSame('30.00', $current->rate);
        $this->assertSame('2026-09-20', $current->effective_from->toDateString());
        $this->assertNull($current->effective_to);
        $this->assertSame(2, HourlyRate::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'hourly_rate.updated']);
    }

    public function test_adding_a_rate_on_the_same_start_date_replaces_the_open_rate(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-09-20',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.hourly-rates.store'), [
                'teacher_id' => $teacher->id,
                'rate' => 30,
                'effective_from' => '2026-09-20',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $rates = HourlyRate::query()->where('teacher_id', $teacher->id)->get();

        $this->assertCount(1, $rates);
        $this->assertSame('30.00', $rates->first()->rate);
        $this->assertNull($rates->first()->effective_to);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hourly_rate.deleted']);
    }

    public function test_a_closed_rate_shifts_the_open_rate_to_after_its_end(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $open = HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-01-15',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.hourly-rates.store'), [
                'teacher_id' => $teacher->id,
                'rate' => 30,
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-01-31',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $open->refresh();
        $this->assertSame('2026-02-01', $open->effective_from->toDateString());
        $this->assertNull($open->effective_to);
        $this->assertSame('20.00', $open->rate);

        $new = HourlyRate::query()
            ->where('teacher_id', $teacher->id)
            ->where('rate', 30)
            ->firstOrFail();

        $this->assertSame('2026-01-01', $new->effective_from->toDateString());
        $this->assertSame('2026-01-31', $new->effective_to->toDateString());
    }

    public function test_rate_change_refreshes_open_periods_but_not_closed_ones(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

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
            'gross_amount' => 100,
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

    public function test_adding_rate_refreshes_open_period(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

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
            'gross_amount' => 0, 'paid_amount' => 0, 'status' => 'open',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.settings.hourly-rates.store'), [
                'teacher_id' => $teacher->id,
                'rate' => 20,
                'effective_from' => '2026-09-01',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $period->refresh();
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

    public function test_manager_changes_the_rate_from_the_sheet_and_the_previous_rate_is_closed(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $previous = HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-09-01',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.rate', $teacher), [
                'rate' => 30,
                'effective_from' => '2026-09-20',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('2026-09-19', $previous->fresh()->effective_to->toDateString());

        $current = HourlyRate::query()
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('effective_from')
            ->firstOrFail();

        $this->assertSame('30.00', $current->rate);
        $this->assertSame('2026-09-20', $current->effective_from->toDateString());
        $this->assertNull($current->effective_to);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hourly_rate.updated']);
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
