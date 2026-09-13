<?php

namespace Tests\Feature;

use App\Models\HafizMonthlyExam;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranProgramService;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HafizExamMonthsTest extends TestCase
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

    private function makeHafiz(string $tenantId, User $actor): Student
    {
        $student = Student::factory()->create(['tenant_id' => $tenantId]);
        $programs = app(QuranProgramService::class);
        $programs->confirmCompletion($programs->recordCompletion($student, null, null, $actor), $actor);

        return $student;
    }

    public function test_year_grid_renders_twelve_months(): void
    {
        [$mosque, $admin] = $this->mosque();
        $this->makeHafiz($mosque->id, $admin);

        $response = $this->actingAs($admin)->get(route('admin.quran.exams.index'));

        $response->assertOk();
        $this->assertSame(12, substr_count($response->getContent(), '/admin/quran/exams/month/'));
        $response->assertSee('سنة '.now()->format('Y'));
    }

    public function test_year_grid_does_not_create_exam_rows(): void
    {
        [$mosque, $admin] = $this->mosque();
        $this->makeHafiz($mosque->id, $admin);

        $targetMonth = now()->subMonths(2)->format('Y-m');

        $this->actingAs($admin)->get(route('admin.quran.exams.index', ['year' => now()->year]))->assertOk();

        $this->assertSame(0, HafizMonthlyExam::query()->where('month', $targetMonth)->count());
    }

    public function test_invalid_year_falls_back_to_current_year(): void
    {
        [$mosque, $admin] = $this->mosque();
        $this->makeHafiz($mosque->id, $admin);

        $this->actingAs($admin)
            ->get(route('admin.quran.exams.index', ['year' => 'not-a-year']))
            ->assertOk()
            ->assertSee('سنة '.now()->format('Y'));
    }

    public function test_month_page_shows_only_that_month_hafiz(): void
    {
        [$mosque, $admin] = $this->mosque();
        $hafiz = $this->makeHafiz($mosque->id, $admin);

        $month = now()->format('Y-m');

        $this->actingAs($admin)
            ->get(route('admin.quran.exams.month', $month))
            ->assertOk()
            ->assertSee($hafiz->name);

        $this->assertDatabaseHas('hafiz_monthly_exams', [
            'student_id' => $hafiz->id,
            'month' => $month,
        ]);
    }

    public function test_legacy_month_query_redirects_to_month_route(): void
    {
        [$mosque, $admin] = $this->mosque();
        $this->makeHafiz($mosque->id, $admin);

        $month = now()->format('Y-m');

        $this->actingAs($admin)
            ->get(route('admin.quran.exams.index', ['month' => $month]))
            ->assertRedirect(route('admin.quran.exams.month', $month));
    }

    public function test_invalid_month_format_returns_404(): void
    {
        [$mosque, $admin] = $this->mosque();

        $this->actingAs($admin)
            ->get('/admin/quran/exams/month/2026-13')
            ->assertNotFound();
    }

    public function test_other_mosque_manager_does_not_see_hafiz(): void
    {
        [$mosque, $admin] = $this->mosque();
        $hafiz = $this->makeHafiz($mosque->id, $admin);

        $otherMosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $otherMosque->id]);
        app(RoleService::class)->provisionTenantRoles($otherMosque);
        $otherAdmin = User::factory()->admin()->for($otherMosque)->create();

        $this->actingAs($otherAdmin)
            ->get(route('admin.quran.exams.index'))
            ->assertOk()
            ->assertSee('لا يوجد حفاظ مسجلون')
            ->assertDontSee($hafiz->name);
    }
}
