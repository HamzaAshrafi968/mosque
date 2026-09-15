<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Tests\TestCase;

/**
 * الدوامات (study sessions): CRUD, provisioning and header switching scope.
 */
class StudySessionsTest extends TestCase
{
    private function mosqueWithSessions(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        $sessions = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->get();

        return [$mosque, $manager, $sessions->first(), $sessions->last()];
    }

    public function test_provisioning_creates_two_default_sessions_per_mosque(): void
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $this->assertSame(2, StudySession::where('tenant_id', $mosque->id)->count());
        $this->assertSame(
            ['الدوام الأول', 'الدوام الثاني'],
            StudySession::where('tenant_id', $mosque->id)->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_mosque_manager_can_create_update_and_delete_sessions(): void
    {
        [$mosque, $manager] = $this->mosqueWithSessions();

        $this->actingAs($manager)
            ->post(route('admin.sessions.store'), ['name' => 'الدوام الثالث'])
            ->assertRedirect(route('admin.sessions.index'));

        $third = StudySession::where('tenant_id', $mosque->id)->where('name', 'الدوام الثالث')->firstOrFail();

        $this->actingAs($manager)
            ->patch(route('admin.sessions.update', $third), ['name' => 'الدوام المسائي', 'description' => 'بعد العصر'])
            ->assertRedirect();

        $this->assertDatabaseHas('study_sessions', ['id' => $third->id, 'name' => 'الدوام المسائي']);

        $this->actingAs($manager)->delete(route('admin.sessions.destroy', $third))->assertRedirect();
        $this->assertDatabaseMissing('study_sessions', ['id' => $third->id]);
    }

    public function test_session_with_records_cannot_be_deleted(): void
    {
        [$mosque, $manager, $first] = $this->mosqueWithSessions();

        Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $first->id]);

        $this->actingAs($manager)
            ->delete(route('admin.sessions.destroy', $first))
            ->assertSessionHasErrors('session');

        $this->assertDatabaseHas('study_sessions', ['id' => $first->id]);
    }

    public function test_switching_session_filters_students_and_teachers(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        $classroomA = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
        $sectionA = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroomA->id, 'name' => 'أ', 'study_session_id' => $first->id]);
        $sectionB = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroomA->id, 'name' => 'ب', 'study_session_id' => $second->id]);

        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $first->id,
            'section_id' => $sectionA->id,
            'classroom_id' => $classroomA->id,
            'name' => 'طالب الدوام الأول',
        ]);
        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $second->id,
            'section_id' => $sectionB->id,
            'classroom_id' => $classroomA->id,
            'name' => 'طالب الدوام الثاني',
        ]);

        Teacher::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $first->id, 'name' => 'أستاذ الدوام الأول']);
        Teacher::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $second->id, 'name' => 'أستاذ الدوام الثاني']);

        // Default: كل الدوامات — everything is visible.
        $this->actingAs($manager)->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('طالب الدوام الأول')
            ->assertSee('طالب الدوام الثاني');

        // Switch to الدوام الأول — only its students/teachers are listed.
        session(['study_session_id' => $first->id]);

        $this->actingAs($manager)
            ->post(route('admin.sessions.switch'), ['study_session_id' => $first->id])
            ->assertRedirect();

        $this->actingAs($manager)->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('طالب الدوام الأول')
            ->assertDontSee('طالب الدوام الثاني');

        $this->actingAs($manager)->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertSee('أستاذ الدوام الأول')
            ->assertDontSee('أستاذ الدوام الثاني');

        // Back to كل الدوامات — both again.
        session()->forget('study_session_id');

        $this->actingAs($manager)
            ->post(route('admin.sessions.switch'), ['study_session_id' => ''])
            ->assertRedirect();

        $this->actingAs($manager)->get(route('admin.students.index'))
            ->assertSee('طالب الدوام الأول')
            ->assertSee('طالب الدوام الثاني');
    }

    public function test_teacher_can_belong_to_multiple_sessions_and_shows_in_each(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        $third = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'الدوام الثالث']);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $first->id,
            'name' => 'أستاذ الأول والثالث',
        ]);
        $teacher->studySessions()->sync([$first->id, $third->id]);

        // يظهر في الدوام الأول والثالث...
        session(['study_session_id' => $first->id]);
        $this->actingAs($manager)->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertSee('أستاذ الأول والثالث');

        session(['study_session_id' => $third->id]);
        $this->actingAs($manager)->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertSee('أستاذ الأول والثالث');

        // ...ولا يظهر في الدوام الثاني.
        session(['study_session_id' => $second->id]);
        $this->actingAs($manager)->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertDontSee('أستاذ الأول والثالث');
    }

    public function test_teacher_form_saves_multiple_sessions(): void
    {
        [$mosque, $manager, $first] = $this->mosqueWithSessions();

        $third = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'الدوام الثالث']);

        $this->actingAs($manager)
            ->post(route('admin.teachers.store'), [
                'name' => 'أستاذ دوامات متعددة',
                'gender' => 'male',
                'study_session_ids' => [$first->id, $third->id],
            ])
            ->assertRedirect(route('admin.teachers.index'));

        $teacher = Teacher::withoutGlobalScopes()->where('name', 'أستاذ دوامات متعددة')->firstOrFail();

        // الدوام الأساسي = أول اختيار، والجدول الوسيط يحفظ الاثنين.
        $this->assertSame($first->id, $teacher->study_session_id);
        $this->assertEqualsCanonicalizing(
            [$first->id, $third->id],
            $teacher->studySessions()->pluck('study_sessions.id')->all()
        );
    }

    public function test_sections_follow_the_selected_session(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        $classroomA = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول', 'study_session_id' => $first->id]);
        $classroomB = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الثاني', 'study_session_id' => $second->id]);
        Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroomA->id, 'name' => 'أ', 'study_session_id' => $first->id]);
        Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroomB->id, 'name' => 'ب', 'study_session_id' => $second->id]);

        session(['study_session_id' => $first->id]);

        // الصفوف تتبع الدوام المختار: صف الثاني لا يظهر في الدوام الأول.
        $this->actingAs($manager)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('الصف الأول')
            ->assertDontSee('الصف الثاني');

        $this->actingAs($manager)
            ->get(route('admin.classrooms.show', $classroomA))
            ->assertOk()
            ->assertSee('>الدوام الأول</span>', false)
            ->assertDontSee('>الدوام الثاني</span>', false);
    }

    public function test_bulk_assign_unassigned_records(): void
    {
        [$mosque, $manager, $first] = $this->mosqueWithSessions();

        Student::factory()->create(['tenant_id' => $mosque->id]);

        $this->actingAs($manager)
            ->post(route('admin.sessions.assign-unassigned'), ['type' => 'students', 'study_session_id' => $first->id])
            ->assertRedirect();

        $this->assertSame(1, Student::where('tenant_id', $mosque->id)->where('study_session_id', $first->id)->count());
    }

    public function test_super_admin_can_switch_sessions_inside_a_mosque(): void
    {
        $roles = app(RoleService::class);
        $roles->ensureGlobalSuperAdminRole();

        [$mosque, , $first] = $this->mosqueWithSessions();

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $roles->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $first->id, 'name' => 'طالب الأول فقط']);
        Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'طالب بدون دوام']);

        $this->actingAs($superAdmin)
            ->withSession(['super_admin_mosque_id' => $mosque->id])
            ->get(route('admin.sessions.index'))
            ->assertOk();

        session(['super_admin_mosque_id' => $mosque->id, 'study_session_id' => $first->id]);

        $this->actingAs($superAdmin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('طالب الأول فقط')
            ->assertDontSee('طالب بدون دوام');
    }
}
