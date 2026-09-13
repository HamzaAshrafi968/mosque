<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\IjazahWeeklyEvaluation;
use App\Models\QualifyingWeeklyEvaluation;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranProgramService;
use App\Services\RoleService;
use App\Support\QuranProgramSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IjazahWeeksTest extends TestCase
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

    private function studentInIjazah(string $tenantId, User $actor): Student
    {
        $programs = app(QuranProgramService::class);

        $student = Student::factory()->create(['tenant_id' => $tenantId]);
        $programs->confirmCompletion($programs->recordCompletion($student, null, null, $actor), $actor);

        $qualifying = $programs->activeEnrollment($student, ProgramType::Qualifying);

        foreach (range(1, QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS) as $week) {
            QualifyingWeeklyEvaluation::create([
                'tenant_id' => $tenantId,
                'student_id' => $student->id,
                'week_start' => Carbon::now()->subWeeks($week)->startOfWeek()->toDateString(),
                'week_end' => Carbon::now()->subWeeks($week)->endOfWeek()->toDateString(),
                'amount' => 5,
                'result' => 'passed',
            ]);
        }

        $programs->completeQualifying($qualifying, $actor);

        return $student;
    }

    private function weekPayload(Student $student, int $week, string $month): array
    {
        return [
            'student_id' => $student->id,
            'month' => $month,
            'week' => $week,
            'amount' => 3,
            'recited_portion' => 'الجزء '.$week,
            'result' => 'passed',
        ];
    }

    public function test_four_weeks_can_be_recorded_for_same_student_and_month(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->studentInIjazah($mosque->id, $admin);
        $month = now()->format('Y-m');

        foreach (range(1, 4) as $week) {
            $this->actingAs($admin)
                ->post(route('admin.quran.ijazah.weekly.store'), $this->weekPayload($student, $week, $month))
                ->assertRedirect(route('admin.quran.ijazah.month', [$student->id, $month]));
        }

        $this->assertSame(4, IjazahWeeklyEvaluation::query()
            ->where('student_id', $student->id)
            ->where('month', $month)
            ->count());

        $this->assertDatabaseHas('audit_logs', ['action' => 'ijazah.weekly_recorded']);

        $first = IjazahWeeklyEvaluation::query()->where('week', 1)->first();
        $this->assertSame($month.'-01', $first->week_start->format('Y-m-d'));
        $this->assertSame($month.'-07', $first->week_end->format('Y-m-d'));
    }

    public function test_duplicate_week_is_rejected(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->studentInIjazah($mosque->id, $admin);
        $month = now()->format('Y-m');

        $this->actingAs($admin)->post(route('admin.quran.ijazah.weekly.store'), $this->weekPayload($student, 1, $month))->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.quran.ijazah.weekly.store'), $this->weekPayload($student, 1, $month))
            ->assertSessionHasErrors('week');

        $this->assertSame(1, IjazahWeeklyEvaluation::query()->count());
    }

    public function test_week_out_of_range_is_rejected(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->studentInIjazah($mosque->id, $admin);

        $this->actingAs($admin)
            ->post(route('admin.quran.ijazah.weekly.store'), $this->weekPayload($student, 5, now()->format('Y-m')))
            ->assertSessionHasErrors('week');

        $this->assertSame(0, IjazahWeeklyEvaluation::query()->count());
    }

    public function test_student_not_enrolled_is_rejected(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = Student::factory()->create(['tenant_id' => $mosque->id]);

        $this->actingAs($admin)
            ->post(route('admin.quran.ijazah.weekly.store'), $this->weekPayload($student, 1, now()->format('Y-m')))
            ->assertSessionHasErrors('student_id');

        $this->assertSame(0, IjazahWeeklyEvaluation::query()->count());
    }

    public function test_month_page_shows_four_weeks_and_summary(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->studentInIjazah($mosque->id, $admin);
        $month = now()->format('Y-m');

        IjazahWeeklyEvaluation::create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'month' => $month,
            'week' => 1,
            'amount' => 4,
            'result' => 'passed',
        ]);
        IjazahMonthlyEvaluation::create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'month' => $month,
            'amount' => 20,
            'result' => 'passed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.quran.ijazah.month', [$student, $month]))
            ->assertOk()
            ->assertSee('الأسبوع 1')
            ->assertSee('الأسبوع 2')
            ->assertSee('الأسبوع 3')
            ->assertSee('الأسبوع 4')
            ->assertSee('1 / 4 أسابيع ناجحة')
            ->assertSee($student->name);
    }

    public function test_update_edits_existing_week_without_creating_new_row(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->studentInIjazah($mosque->id, $admin);
        $month = now()->format('Y-m');

        $evaluation = IjazahWeeklyEvaluation::create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'month' => $month,
            'week' => 1,
            'amount' => 3,
            'result' => 'needs_review',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.quran.ijazah.weekly.update', $evaluation), [
                'week' => 1,
                'amount' => 5,
                'result' => 'passed',
                'notes' => 'تحسن',
            ])
            ->assertRedirect();

        $this->assertSame(1, IjazahWeeklyEvaluation::query()->count());
        $this->assertSame('5.00', $evaluation->fresh()->amount);
        $this->assertSame('passed', $evaluation->fresh()->result->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ijazah.weekly_updated']);
    }

    public function test_teacher_outside_scope_gets_forbidden(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->studentInIjazah($mosque->id, $admin);
        [$teacherUser] = $this->makeTeacher($mosque->id);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.ijazah.month', [$student, now()->format('Y-m')]))
            ->assertForbidden();

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.ijazah.weekly.store'), $this->weekPayload($student, 1, now()->format('Y-m')))
            ->assertForbidden();
    }

    public function test_monthly_completion_rule_is_unchanged(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->studentInIjazah($mosque->id, $admin);
        $month = now()->format('Y-m');
        $programs = app(QuranProgramService::class);
        $enrollment = $programs->activeEnrollment($student, ProgramType::Ijazah);

        foreach (range(1, 4) as $week) {
            IjazahWeeklyEvaluation::create([
                'tenant_id' => $mosque->id,
                'student_id' => $student->id,
                'month' => $month,
                'week' => $week,
                'amount' => 3,
                'result' => 'passed',
            ]);
        }

        try {
            $programs->completeIjazah($enrollment, $admin);
            $this->fail('Completion should still require a passing monthly evaluation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('program', $e->errors());
        }

        IjazahMonthlyEvaluation::create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'month' => $month,
            'amount' => 20,
            'result' => 'passed',
        ]);

        $programs->completeIjazah($enrollment->fresh(), $admin);

        $this->assertSame(ProgramEnrollmentStatus::Completed, $enrollment->fresh()->status);
    }
}
