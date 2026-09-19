<?php

namespace Tests\Feature;

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
 * مركز «دفعات المعلمين» المبسّط: الإدخال السريع للساعات بعددها،
 * الدفع السريع، التحذيرات، وقفل الشهر بزر واحد.
 */
class QuickPayrollHubTest extends TestCase
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

    private function teacher(Tenant $mosque, array $attributes = []): Teacher
    {
        $user = User::factory()->for($mosque)->create();

        return Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id, ...$attributes]);
    }

    public function test_quick_hours_entry_creates_a_slot_from_hours_only(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), [
                'teacher_id' => $teacher->id,
                'date' => '2026-09-20',
                'hours' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('work_slots', [
            'teacher_id' => $teacher->id,
            'start_time' => '08:00',
            'end_time' => '11:00',
            'duration_minutes' => 180,
        ]);
    }

    public function test_quick_hours_entry_uses_the_provided_start_time(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), [
                'teacher_id' => $teacher->id,
                'date' => '2026-09-20',
                'hours' => 2.5,
                'start_time' => '14:00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('work_slots', [
            'teacher_id' => $teacher->id,
            'start_time' => '14:00',
            'end_time' => '16:30',
            'duration_minutes' => 150,
        ]);
    }

    public function test_quick_hours_entry_rejects_crossing_midnight(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), [
                'teacher_id' => $teacher->id,
                'date' => '2026-09-20',
                'hours' => 5,
                'start_time' => '21:00',
            ])
            ->assertSessionHasErrors('hours');

        $this->assertSame(0, WorkSlot::query()->count());
    }

    public function test_quick_hours_entry_rejects_more_than_the_configured_maximum(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), [
                'teacher_id' => $teacher->id,
                'date' => '2026-09-20',
                'hours' => 13,
            ])
            ->assertSessionHasErrors('hours');

        $this->assertSame(0, WorkSlot::query()->count());
    }

    public function test_quick_hours_entry_blocks_overlapping_slots(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque);

        WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'duration_minutes' => 120,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), [
                'teacher_id' => $teacher->id,
                'date' => '2026-09-20',
                'hours' => 2,
            ])
            ->assertSessionHasErrors('start_time');

        $this->assertSame(1, WorkSlot::query()->count());
    }

    public function test_hub_shows_the_paid_state_when_settled(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque, ['monthly_salary' => 1000]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), [
                'month' => '2026-09',
                'amount' => 1000,
                'payment_method' => 'نقد',
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->get(route('admin.payroll.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('مدفوع');
    }

    public function test_hub_warns_when_an_hourly_teacher_has_no_rate(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque, ['pay_type' => 'hourly']);

        WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'duration_minutes' => 120,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.payroll.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('لا يوجد سعر');
    }

    public function test_lock_month_button_closes_the_period_and_disables_editing(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $teacher = $this->teacher($mosque, ['monthly_salary' => 1000]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.close-all'), ['month' => '2026-09', 'confirm_month' => '2026-09'])
            ->assertRedirect(route('admin.payroll.index', ['month' => '2026-09']));

        $this->actingAs($manager)
            ->get(route('admin.payroll.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('الشهر مغلق');
    }

    public function test_quick_hours_button_follows_the_manage_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->get(route('admin.payroll.index'))
            ->assertOk()
            ->assertSee('تسجيل ساعات');

        $permission = Permission::query()->where('code', 'work_hours.manage')->firstOrFail();
        Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($manager)
            ->get(route('admin.payroll.index'))
            ->assertOk()
            ->assertDontSee('تسجيل ساعات');
    }
}
