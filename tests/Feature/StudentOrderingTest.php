<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudySession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Tests\TestCase;

/**
 * ترتيب صفحة الطلاب حسب الدوام: طلاب الدوام الأول أولاً ثم الثاني ثم غير المرتبطين،
 * وداخل كل دوام حسب الاسم.
 */
class StudentOrderingTest extends TestCase
{
    public function test_students_index_is_ordered_by_study_session_then_name(): void
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        $sessions = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->get();
        $first = $sessions->first();
        $second = $sessions->last();

        // تُنشأ بترتيب عشوائي لاختبار الترتيب لا ترتيب الإدخال.
        $secondShiftB = Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $second->id, 'name' => 'طالب الثاني ب']);
        $firstShiftB = Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $first->id, 'name' => 'طالب الأول ب']);
        $unassigned = Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => null, 'name' => 'طالب بلا دوام']);
        $firstShiftA = Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $first->id, 'name' => 'طالب الأول أ']);
        $secondShiftA = Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $second->id, 'name' => 'طالب الثاني أ']);

        $expected = [
            $firstShiftA->id,
            $firstShiftB->id,
            $secondShiftA->id,
            $secondShiftB->id,
            $unassigned->id,
        ];

        $this->assertSame($expected, Student::orderByStudySession()->pluck('id')->all());

        $this->actingAs($manager)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSeeInOrder(['طالب الأول أ', 'طالب الأول ب', 'طالب الثاني أ', 'طالب الثاني ب', 'طالب بلا دوام']);
    }
}
