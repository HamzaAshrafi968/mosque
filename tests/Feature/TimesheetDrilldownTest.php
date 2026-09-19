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
 * واجهات الساعات الجديدة: مركز الدفعات، كشف المعلم بأسابيع الشهر الأربعة،
 * بوابة الأستاذ، والتوجيهات من الشاشات القديمة.
 */
class TimesheetDrilldownTest extends TestCase
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
    private function teacher(Tenant $mosque, string $name = 'أحمد المعلم'): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id, 'name' => $name]);

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

    public function test_old_timesheet_screens_redirect_to_the_payroll_hub(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->get(route('admin.timesheet.index', ['view' => 'monthly', 'month' => '2026-09']))
            ->assertRedirect(route('admin.payroll.index', ['month' => '2026-09']));

        $this->actingAs($manager)
            ->get(route('admin.work-hours.index'))
            ->assertRedirect(route('admin.payroll.index'));

        $this->actingAs($manager)
            ->get(route('admin.teachers.timesheet.index', ['teacher' => $teacher, 'month' => '2026-09']))
            ->assertRedirect(route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => '2026-09']));
    }

    public function test_payroll_hub_lists_teacher_hours_and_totals(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->slot($mosque, $teacher, '2026-09-20', '06:00', '08:00');
        $this->slot($mosque, $teacher, '2026-09-20', '14:00', '20:00');

        $this->actingAs($manager)
            ->get(route('admin.payroll.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('8س')
            ->assertSee('دفعات المعلمين');
    }

    public function test_teacher_sheet_shows_four_week_blocks_with_from_to_hours(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque);

        $this->slot($mosque, $teacher, '2026-09-20', '06:00', '08:00');

        $this->actingAs($manager)
            ->get(route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('06:00')
            ->assertSee('الأحد')
            ->assertSee('الأسبوع الأول (١–٧)')
            ->assertSee('الأسبوع الرابع (٢٢–٣٠)')
            ->assertSee('الشهر مفتوح');
    }

    public function test_empty_month_shows_week_blocks_and_teacher_portal_empty_state(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [$teacherUser, $teacher] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->get(route('admin.payroll.sheet', ['teacher' => $teacher, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('الأسبوع الأول (١–٧)')
            ->assertSee('لا ساعات');

        $this->actingAs($teacherUser)
            ->get(route('teacher.timesheet.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('لا توجد فترات عمل');
    }

    public function test_teacher_portal_shows_only_own_slots_read_only(): void
    {
        $mosque = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, 'أحمد');
        [, $otherTeacher] = $this->teacher($mosque, 'خالد');

        $this->slot($mosque, $teacher, '2026-09-20', '07:15', '09:45');
        $this->slot($mosque, $otherTeacher, '2026-09-20', '13:20', '15:50');

        $this->actingAs($teacherUser)
            ->get(route('teacher.timesheet.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('07:15')
            ->assertSee('09:45')
            ->assertDontSee('13:20')
            ->assertDontSee('تعديل');
    }

    public function test_teacher_cannot_open_the_admin_timesheet_area(): void
    {
        $mosque = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque);

        $this->actingAs($teacherUser)
            ->get(route('admin.teachers.timesheet.index', ['teacher' => $teacher]))
            ->assertForbidden();
    }

    public function test_timesheet_center_follows_the_view_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->get(route('admin.timesheet.index'))
            ->assertRedirect(route('admin.payroll.index'));

        $permission = Permission::query()->where('code', 'work_hours.view')->firstOrFail();
        Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($manager)->get(route('admin.timesheet.index'))->assertForbidden();
    }
}
