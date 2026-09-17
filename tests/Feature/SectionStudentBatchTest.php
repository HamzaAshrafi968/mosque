<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إضافة الطلاب للشعبة: القائمة تعرض كل الطلاب النشطين (المقيدين بغيرها
 * وغير المقيدين)، والمدير يحدد عدة أسماء فتُسجَّل أو تُنقل للشعبة.
 */
class SectionStudentBatchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: User, 2: Section, 3: Section} */
    private function mosque(): array
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        app(RoleService::class)->provisionTenantRoles($tenant);

        $admin = User::factory()->admin()->for($tenant)->create();

        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الأول']);
        $sectionA = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $sectionB = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'ب']);

        return [$tenant, $admin, $sectionA, $sectionB];
    }

    public function test_section_page_lists_all_active_students_including_those_in_other_sections(): void
    {
        [, $admin, $sectionA, $sectionB] = $this->mosque();

        Student::factory()->create(['tenant_id' => config('app.current_tenant_id'), 'name' => 'طالب حر']);
        $inOther = Student::factory()->create(['tenant_id' => config('app.current_tenant_id'), 'name' => 'طالب في شعبة أخرى']);

        $this->actingAs($admin)->post(route('admin.sections.students.store', $sectionB), [
            'student_ids' => [$inOther->id],
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get(route('admin.sections.show', $sectionA));

        $response->assertOk();
        $response->assertSee('طالب حر');
        $response->assertSee('طالب في شعبة أخرى');
        $response->assertSee('سيُنقل من');
    }

    public function test_batch_enrollment_enrolls_unassigned_and_transfers_enrolled_students(): void
    {
        [, $admin, $sectionA, $sectionB] = $this->mosque();

        $first = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);
        $second = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);
        $third = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);

        // الطالب الثالث مسجل مسبقاً في الشعبة ب.
        $this->actingAs($admin)->post(route('admin.sections.students.store', $sectionB), [
            'student_ids' => [$third->id],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.sections.students.store', $sectionA), [
            'student_ids' => [$first->id, $second->id, $third->id],
        ])->assertRedirect()->assertSessionHas('success');

        foreach ([$first, $second, $third] as $student) {
            $this->assertDatabaseHas('students', [
                'id' => $student->id,
                'section_id' => $sectionA->id,
                'classroom_id' => $sectionA->classroom_id,
            ]);
        }

        // عضويته السابقة أُغلقت كنقل مع بقاء السجل.
        $this->assertDatabaseHas('section_students', [
            'student_id' => $third->id,
            'section_id' => $sectionB->id,
            'status' => 'transferred',
        ]);
        $this->assertDatabaseHas('section_students', [
            'student_id' => $third->id,
            'section_id' => $sectionA->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, SectionStudent::where('student_id', $third->id)->where('status', 'active')->count());
    }

    public function test_batch_enrollment_skips_students_already_in_the_section(): void
    {
        [, $admin, $sectionA] = $this->mosque();
        $student = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);

        $this->actingAs($admin)->post(route('admin.sections.students.store', $sectionA), [
            'student_ids' => [$student->id],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.sections.students.store', $sectionA), [
            'student_ids' => [$student->id],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(
            1,
            SectionStudent::where('student_id', $student->id)->where('section_id', $sectionA->id)->count()
        );
    }

    public function test_batch_enrollment_requires_at_least_one_student(): void
    {
        [, $admin, $sectionA] = $this->mosque();

        $this->actingAs($admin)
            ->from(route('admin.sections.show', $sectionA))
            ->post(route('admin.sections.students.store', $sectionA), [])
            ->assertRedirect(route('admin.sections.show', $sectionA))
            ->assertSessionHasErrors('student_ids');
    }
}
