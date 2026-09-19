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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API كشوف العمل والرواتب (admin + teacher): التسجيل، الملخصات، الدفع،
 * الإغلاق/الفتح، الأسعار، والعزل والصلاحيات.
 */
class TimesheetPayrollApiTest extends TestCase
{
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

    public function test_admin_creates_a_slot_via_api_and_it_is_audited(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque);

        $this->postJson('/api/v1/admin/timesheet/slots', [
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '06:00',
            'end_time' => '08:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.duration_minutes', 120)
            ->assertJsonPath('data.duration_label', '2س');

        $this->assertDatabaseHas('work_slots', [
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'duration_minutes' => 120,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'work_slot.created']);
    }

    public function test_admin_slot_validation_returns_422(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque);

        $this->postJson('/api/v1/admin/timesheet/slots', [
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '22:00',
            'end_time' => '02:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');

        $this->assertSame(0, WorkSlot::query()->count());
    }

    public function test_admin_creates_a_slot_from_hours_only_via_api(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque);

        $this->postJson('/api/v1/admin/timesheet/slots', [
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'hours' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('data.start_time', '08:00')
            ->assertJsonPath('data.end_time', '11:00')
            ->assertJsonPath('data.duration_minutes', 180);

        $this->assertDatabaseHas('work_slots', [
            'teacher_id' => $teacher->id,
            'start_time' => '08:00',
            'end_time' => '11:00',
            'duration_minutes' => 180,
        ]);
    }

    public function test_admin_timesheet_index_returns_aggregates(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque);

        $this->slot($mosque, $teacher, '2026-09-20', '06:00', '08:00');
        $this->slot($mosque, $teacher, '2026-09-20', '14:00', '20:00');

        $response = $this->getJson('/api/v1/admin/timesheet?view=daily&date=2026-09-20')
            ->assertOk()
            ->assertJsonPath('data.view', 'daily');

        $this->assertSame(480, $response->json('data.teachers.0.total_minutes'));
        $this->assertSame('8س', $response->json('data.teachers.0.total_label'));
        $this->assertSame(480, $response->json('data.teachers.0.by_date.2026-09-20'));
    }

    public function test_admin_payroll_index_and_sheet_return_summaries(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'hourly', 'monthly_salary' => null]);

        HourlyRate::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-09-01',
        ]);
        $this->slot($mosque, $teacher, '2026-09-20', '09:00', '15:00');

        $this->getJson('/api/v1/admin/payroll?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.totals.gross', 120)
            ->assertJsonPath('data.teachers.0.summary.total_label', '6س');

        $this->getJson("/api/v1/admin/payroll/teachers/{$teacher->id}/sheet?month=2026-09")
            ->assertOk()
            ->assertJsonPath('data.summary.gross', 120)
            ->assertJsonPath('data.rates.0.rate', 20)
            ->assertJsonPath('data.payments', []);
    }

    public function test_admin_records_partial_payment_and_overpay_is_rejected(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'monthly', 'monthly_salary' => 1000]);

        $this->postJson("/api/v1/admin/payroll/teachers/{$teacher->id}/pay", [
            'month' => '2026-09',
            'amount' => 400,
            'payment_method' => 'نقد',
        ])
            ->assertCreated()
            ->assertJsonPath('data.paid', 400)
            ->assertJsonPath('data.remaining', 600);

        $this->postJson("/api/v1/admin/payroll/teachers/{$teacher->id}/pay", [
            'month' => '2026-09',
            'amount' => 900,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_admin_closes_and_reopens_the_period_via_api(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'monthly', 'monthly_salary' => 500]);

        $this->postJson("/api/v1/admin/payroll/teachers/{$teacher->id}/close", ['month' => '2026-09'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->postJson("/api/v1/admin/payroll/teachers/{$teacher->id}/reopen", ['month' => '2026-09'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/admin/payroll/teachers/{$teacher->id}/reopen", [
            'month' => '2026-09',
            'reason' => 'تصحيح',
        ])
            ->assertOk();

        $this->assertFalse(
            PayrollPeriod::query()->where('teacher_id', $teacher->id)->firstOrFail()->isClosed()
        );
    }

    public function test_admin_manages_hourly_rates_via_api(): void
    {
        $mosque = $this->mosque();
        Sanctum::actingAs($this->manager($mosque));
        [, $teacher] = $this->teacher($mosque);

        $created = $this->postJson('/api/v1/admin/payroll/rates', [
            'teacher_id' => $teacher->id,
            'rate' => 18,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ])->assertCreated();

        $this->postJson('/api/v1/admin/payroll/rates', [
            'teacher_id' => $teacher->id,
            'rate' => 20,
            'effective_from' => '2026-06-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('effective_from');

        $this->getJson('/api/v1/admin/payroll/rates')
            ->assertOk()
            ->assertJsonPath('data.rates.0.rate', 18);

        $this->deleteJson('/api/v1/admin/payroll/rates/'.$created->json('data.id'))
            ->assertNoContent();

        $this->assertSame(0, HourlyRate::query()->count());
    }

    public function test_teacher_api_shows_only_his_own_timesheet_and_payroll(): void
    {
        $mosque = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, ['pay_type' => 'monthly', 'monthly_salary' => 300]);
        [, $otherTeacher] = $this->teacher($mosque);

        $this->slot($mosque, $teacher, '2026-09-20', '07:15', '09:45');
        $this->slot($mosque, $otherTeacher, '2026-09-20', '13:20', '15:50');

        $otherPeriod = PayrollPeriod::create([
            'tenant_id' => $mosque->id, 'teacher_id' => $otherTeacher->id,
            'year' => 2026, 'month' => 10, 'total_minutes' => 0,
            'pay_type_snapshot' => 'monthly', 'monthly_salary_snapshot' => 300,
            'gross_amount' => 300, 'paid_amount' => 0, 'status' => 'open',
        ]);

        Sanctum::actingAs($teacherUser);

        $this->getJson('/api/v1/teacher/timesheet?month=2026-09')
            ->assertOk()
            ->assertJsonPath('data.slots_by_date.2026-09-20.0.start_time', '07:15')
            ->assertJsonMissing(['start_time' => '13:20']);

        $this->getJson('/api/v1/teacher/payroll')->assertOk();

        $this->getJson('/api/v1/teacher/payroll/'.$otherPeriod->id)->assertForbidden();
    }

    public function test_teacher_api_cannot_open_the_admin_area(): void
    {
        $mosque = $this->mosque();
        [$teacherUser] = $this->teacher($mosque);

        Sanctum::actingAs($teacherUser);

        $this->getJson('/api/v1/admin/timesheet')->assertForbidden();
        $this->getJson('/api/v1/admin/payroll')->assertForbidden();
    }

    public function test_api_payroll_follows_the_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/payroll')->assertOk();

        $permissions = Permission::query()->whereIn('code', ['payroll.view', 'finance.view'])->pluck('id');
        Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permissions->all());

        $this->getJson('/api/v1/admin/payroll')->assertForbidden();
    }
}
