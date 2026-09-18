<?php

namespace Tests\Feature;

use App\Models\PayrollPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSlot;
use App\Services\RoleService;
use App\Services\WorkHoursSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * فترات العمل الفعلية: التسجيل والتحقق والعزل وقفل الشهر المغلق (BR-02..BR-12).
 */
class WorkSlotCrudTest extends TestCase
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
    private function teacher(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function payload(Teacher $teacher, string $date, string $start, string $end): array
    {
        return [
            'teacher_id' => $teacher->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    public function test_manager_creates_a_slot_with_computed_duration_and_audit(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '06:00', '08:00'))
            ->assertRedirect();

        $this->assertDatabaseHas('work_slots', [
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'duration_minutes' => 120,
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'work_slot.created']);
    }

    public function test_end_equal_start_is_rejected(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '10:00', '10:00'))
            ->assertSessionHasErrors('end_time');

        $this->assertSame(0, WorkSlot::query()->count());
    }

    public function test_midnight_crossing_is_rejected(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '22:00', '02:00'))
            ->assertSessionHasErrors('end_time');

        $this->assertSame(0, WorkSlot::query()->count());
    }

    public function test_slot_longer_than_twelve_hours_is_rejected(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '06:00', '20:00'))
            ->assertSessionHasErrors('end_time');

        $this->assertSame(0, WorkSlot::query()->count());
    }

    public function test_overlapping_slots_are_rejected_but_adjacent_are_allowed(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '06:00', '10:00'))
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '08:00', '12:00'))
            ->assertSessionHasErrors('start_time');

        $this->assertSame(1, WorkSlot::query()->count());

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '10:00', '12:00'))
            ->assertRedirect();

        $this->assertSame(2, WorkSlot::query()->count());
    }

    public function test_slots_of_another_mosque_teacher_are_not_reachable(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $otherMosque = Tenant::factory()->create();
        [, $otherTeacher] = $this->teacher($otherMosque);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($otherTeacher, '2026-09-20', '08:00', '10:00'))
            ->assertNotFound();

        $this->assertSame(0, WorkSlot::query()->count());
    }

    public function test_slots_in_a_closed_month_are_locked(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $slot = WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '06:00',
            'end_time' => '08:00',
            'duration_minutes' => 120,
        ]);

        PayrollPeriod::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'year' => 2026,
            'month' => 9,
            'status' => 'closed',
            'total_minutes' => 120,
            'gross_amount' => 0,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-21', '08:00', '10:00'))
            ->assertSessionHasErrors('date');

        $this->actingAs($manager)
            ->delete(route('admin.timesheet.slots.destroy', $slot))
            ->assertSessionHasErrors('date');

        $this->assertDatabaseHas('work_slots', ['id' => $slot->id]);
    }

    public function test_generate_from_schedule_copies_periods_and_skips_conflicts(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        // الأحد 2026-09-20
        TeacherWorkHour::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'day_of_week' => 0, 'start_time' => '06:00', 'end_time' => '08:00']);
        TeacherWorkHour::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'day_of_week' => 0, 'start_time' => '14:00', 'end_time' => '20:00']);

        WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '06:00',
            'end_time' => '08:00',
            'duration_minutes' => 120,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.teachers.timesheet.generate', $teacher), ['date' => '2026-09-20'])
            ->assertRedirect();

        $this->assertSame(2, WorkSlot::query()->count());
        $this->assertDatabaseHas('work_slots', [
            'teacher_id' => $teacher->id,
            'start_time' => '14:00',
        ]);
    }

    public function test_max_slot_hours_setting_is_enforced(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        app(WorkHoursSettingsService::class)->setMaxSlotHours(8);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '06:00', '16:00'))
            ->assertSessionHasErrors('end_time');

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '06:00', '14:00'))
            ->assertRedirect();

        $this->assertSame(1, WorkSlot::query()->count());
    }

    public function test_work_hours_settings_page_updates_max_hours_and_audits(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.settings.work-hours.edit'))->assertOk();

        $this->actingAs($manager)
            ->patch(route('admin.settings.work-hours.update'), [
                'max_slot_hours' => 8,
                'timezone' => 'Asia/Riyadh',
            ])
            ->assertRedirect();

        $settings = app(WorkHoursSettingsService::class);

        $this->assertSame(8.0, $settings->maxSlotHours());
        $this->assertSame('Asia/Riyadh', $settings->timezone());
        $this->assertDatabaseHas('audit_logs', ['action' => 'work_hours.settings.updated']);
    }

    public function test_user_without_manage_permission_cannot_create_slots(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $permission = Permission::query()->where('code', 'work_hours.manage')->firstOrFail();
        Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($manager)
            ->post(route('admin.timesheet.slots.store'), $this->payload($teacher, '2026-09-20', '08:00', '10:00'))
            ->assertForbidden();

        $this->assertSame(0, WorkSlot::query()->count());
    }
}
