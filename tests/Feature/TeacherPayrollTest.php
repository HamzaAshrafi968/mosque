<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\RoleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * رواتب المعلمين: المدير يحدد الراتب الشهري، ساعات العمل تُعرض شهرياً، كل دفعة
 * تصفّر عدّاد الساعات وتُرسل إشعاراً للأستاذ، والأستاذ يرى دفعاته فقط.
 */
class TeacherPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function mosque(): Tenant
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);
        app(RoleService::class)->provisionTenantRoles($tenant);

        return $tenant;
    }

    private function manager(Tenant $mosque): User
    {
        return User::factory()->admin()->for($mosque)->create();
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque, array $attributes = []): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            ...$attributes,
        ]);

        return [$user, $teacher];
    }

    /** فترة عمل يومية 09:00-11:00 (ساعتان لكل يوم من الأسبوع). */
    private function dailyHours(Tenant $mosque, Teacher $teacher): void
    {
        foreach (range(0, 6) as $day) {
            TeacherWorkHour::create([
                'tenant_id' => $mosque->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '11:00',
            ]);
        }
    }

    public function test_admin_sets_the_monthly_salary(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['monthly_salary' => null]);

        $this->actingAs($manager)
            ->post(route('admin.payroll.salary', $teacher), ['monthly_salary' => 1200])
            ->assertRedirect();

        $this->assertSame('1200.00', $teacher->fresh()->monthly_salary);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.salary_updated']);
    }

    public function test_payroll_page_lists_monthly_hours_salary_and_last_payment(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['monthly_salary' => 1500, 'hired_at' => now()->subDays(10)]);
        $this->dailyHours($mosque, $teacher);

        $this->actingAs($manager)
            ->get(route('admin.payroll.index'))
            ->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('1,500.00')
            ->assertSee((string) (2 * now()->daysInMonth));
    }

    public function test_recording_a_payment_resets_the_hours_counter_and_notifies_the_teacher(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [$teacherUser, $teacher] = $this->teacher($mosque, ['hired_at' => now()->subDays(10)]);
        $this->dailyHours($mosque, $teacher);

        $payroll = app(PayrollService::class);

        // قبل الدفع: عدّاد الساعات المتراكمة أكبر من صفر
        $this->assertGreaterThan(0, $payroll->hoursSinceLastPayment($teacher));

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacher), ['amount' => 900, 'description' => 'راتب الشهر'])
            ->assertRedirect();

        $this->assertDatabaseHas('financial_transactions', [
            'person_type' => 'teacher',
            'person_id' => $teacher->id,
            'transaction_type' => 'payment',
            'direction' => 'money_in',
            'amount' => 900,
        ]);

        // الدفعة صفّرت العدّاد (يبدأ من الغد)
        $this->assertSame(0.0, $payroll->hoursSinceLastPayment($teacher->fresh()));

        // وصل إشعار للأستاذ
        $this->assertSame(1, $teacherUser->notifications()->count());
        $this->assertSame('تم إيداع دفعة لك', $teacherUser->notifications()->first()->data['title']);
    }

    public function test_teacher_sees_only_his_own_deposits(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [$teacherAUser, $teacherA] = $this->teacher($mosque);
        [, $teacherB] = $this->teacher($mosque);

        $this->actingAs($manager)
            ->post(route('admin.payroll.pay', $teacherA), ['amount' => 1234.56])
            ->assertRedirect();

        $this->actingAs($teacherAUser)
            ->get(route('teacher.finance.index'))
            ->assertOk()
            ->assertSee('1,234.56')
            ->assertDontSee($teacherB->name);

        $teacherBUser = User::findOrFail($teacherB->user_id);
        $this->actingAs($teacherBUser)
            ->get(route('teacher.finance.index'))
            ->assertOk()
            ->assertDontSee('1,234.56');
    }

    public function test_monthly_hours_count_weekday_occurrences(): void
    {
        $mosque = $this->mosque();
        [, $teacher] = $this->teacher($mosque);

        // كل إثنين 09:00-11:00 → أيلول 2026 فيه 4 أيام إثنين
        TeacherWorkHour::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $september = CarbonImmutable::parse('2026-09-01');

        $this->assertSame(8.0, TeacherWorkHour::monthlyHours($teacher->id, $september));
        $this->assertSame(
            4.0,
            TeacherWorkHour::hoursBetween($teacher->id, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-15'))
        );
    }

    public function test_payroll_page_follows_the_finance_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('admin.payroll.index'))->assertOk();

        $permission = Permission::where('code', 'finance.view')->firstOrFail();
        Role::where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($manager)->get(route('admin.payroll.index'))->assertForbidden();
    }

    public function test_reversed_payment_is_not_counted_as_last_payment(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['hired_at' => now()->subDays(5)]);

        $this->actingAs($manager)->post(route('admin.payroll.pay', $teacher), ['amount' => 500]);

        $payment = FinancialTransaction::where('person_id', $teacher->id)->where('transaction_type', 'payment')->firstOrFail();

        $this->actingAs($manager)->post(route('admin.finance.reverse', $payment))->assertRedirect();

        $this->assertNull(app(PayrollService::class)->lastPayment($teacher));
    }
}
