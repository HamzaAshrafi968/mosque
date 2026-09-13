<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkHoursTest extends TestCase
{
    use RefreshDatabase;

    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $admin];
    }

    private function makeTeacher(string $tenantId): array
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    public function test_admin_creates_work_hour_for_teacher_in_his_mosque(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);

        $this->actingAs($admin)
            ->post(route('admin.teachers.work-hours.store', $teacher), [
                'day_of_week' => 0,
                'start_time' => '08:00',
                'end_time' => '12:00',
                'notes' => 'الفترة الصباحية',
            ])
            ->assertRedirect(route('admin.teachers.work-hours.index', $teacher));

        $this->assertDatabaseHas('teacher_work_hours', [
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'work_hours.created']);
    }

    public function test_teacher_from_another_mosque_cannot_receive_work_hours(): void
    {
        [, $admin] = $this->mosque();

        $otherMosque = Tenant::factory()->create();
        [, $otherTeacher] = $this->makeTeacher($otherMosque->id);

        $this->actingAs($admin)
            ->post(route('admin.teachers.work-hours.store', $otherTeacher), [
                'day_of_week' => 1,
                'start_time' => '08:00',
                'end_time' => '10:00',
            ])
            ->assertNotFound();

        $this->assertSame(0, TeacherWorkHour::query()->count());
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);

        $this->actingAs($admin)
            ->post(route('admin.teachers.work-hours.store', $teacher), [
                'day_of_week' => 2,
                'start_time' => '12:00',
                'end_time' => '09:00',
            ])
            ->assertSessionHasErrors('end_time');

        $this->assertSame(0, TeacherWorkHour::query()->count());
    }

    public function test_overlapping_periods_are_rejected(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);

        TeacherWorkHour::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 3,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.teachers.work-hours.store', $teacher), [
                'day_of_week' => 3,
                'start_time' => '11:00',
                'end_time' => '14:00',
            ])
            ->assertSessionHasErrors('start_time');

        $this->assertSame(1, TeacherWorkHour::query()->count());
    }

    public function test_update_allows_same_period_but_still_checks_overlap(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);

        $first = TeacherWorkHour::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 4,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);
        $second = TeacherWorkHour::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 4,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.work-hours.update', $second), [
                'day_of_week' => 4,
                'start_time' => '10:30',
                'end_time' => '12:00',
            ])
            ->assertRedirect();

        $this->assertSame('10:30', substr($second->fresh()->start_time, 0, 5));

        $this->actingAs($admin)
            ->patch(route('admin.work-hours.update', $second), [
                'day_of_week' => 4,
                'start_time' => '09:00',
                'end_time' => '11:00',
            ])
            ->assertSessionHasErrors('start_time');

        $this->assertSame('10:30', substr($second->fresh()->start_time, 0, 5));
        $this->assertSame('08:00', substr($first->fresh()->start_time, 0, 5));
    }

    public function test_teacher_sees_only_his_own_work_hours(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        [, $otherTeacher] = $this->makeTeacher($mosque->id);

        TeacherWorkHour::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'start_time' => '07:15',
            'end_time' => '09:45',
        ]);
        TeacherWorkHour::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 0,
            'start_time' => '13:20',
            'end_time' => '15:50',
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.work-hours.index'))
            ->assertOk()
            ->assertSee('07:15')
            ->assertSee('09:45')
            ->assertDontSee('13:20');
    }

    public function test_user_without_manage_permission_is_forbidden(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);

        $managerRole = Role::query()->where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->firstOrFail();
        $manage = Permission::query()->where('code', 'work_hours.manage')->firstOrFail();
        $managerRole->permissions()->detach($manage->id);

        $this->actingAs($admin)
            ->post(route('admin.teachers.work-hours.store', $teacher), [
                'day_of_week' => 0,
                'start_time' => '08:00',
                'end_time' => '10:00',
            ])
            ->assertForbidden();

        $this->assertSame(0, TeacherWorkHour::query()->count());
    }

    public function test_weekly_total_is_computed_from_periods(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);

        TeacherWorkHour::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '12:00']);
        TeacherWorkHour::create(['tenant_id' => $mosque->id, 'teacher_id' => $teacher->id, 'day_of_week' => 2, 'start_time' => '14:00', 'end_time' => '16:30']);

        $this->assertSame(6.5, TeacherWorkHour::weeklyTotalHours($teacher->id));

        $this->actingAs($admin)
            ->get(route('admin.work-hours.index'))
            ->assertOk()
            ->assertSee('6.5');
    }
}
