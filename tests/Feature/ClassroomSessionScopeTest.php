<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * لكل دوام صفوفه وشعبه: عزل الصفوف حسب الدوام النشط، وراثة الشعبة لدوام
 * صفها، ونقل الصف بكل ما يتبعه (شعب/طلاب/جداول) عند تغيير دوامه.
 */
class ClassroomSessionScopeTest extends TestCase
{
    /** @return array{0: Tenant, 1: User, 2: StudySession, 3: StudySession} */
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

    private function classroom(Tenant $mosque, string $name, ?string $sessionId = null): Classroom
    {
        return Classroom::create([
            'tenant_id' => $mosque->id,
            'name' => $name,
            'study_session_id' => $sessionId,
        ]);
    }

    private function section(Tenant $mosque, Classroom $classroom, string $name, ?string $sessionId = null): Section
    {
        return Section::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'name' => $name,
            'study_session_id' => $sessionId,
        ]);
    }

    public function test_classrooms_are_filtered_by_the_active_session(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        $this->classroom($mosque, 'صف الدوام الأول', $first->id);
        $this->classroom($mosque, 'صف الدوام الثاني', $second->id);
        $this->classroom($mosque, 'صف مشترك');

        // كل الدوامات: الكل ظاهر.
        $this->actingAs($manager)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('صف الدوام الأول')
            ->assertSee('صف الدوام الثاني')
            ->assertSee('صف مشترك');

        // الدوام الأول: صفه + الصف المشترك فقط.
        session(['study_session_id' => $first->id]);

        $this->actingAs($manager)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('صف الدوام الأول')
            ->assertDontSee('صف الدوام الثاني')
            ->assertSee('صف مشترك');

        // الدوام الثاني: صفه + الصف المشترك فقط.
        session(['study_session_id' => $second->id]);

        $this->actingAs($manager)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('صف الدوام الثاني')
            ->assertDontSee('صف الدوام الأول')
            ->assertSee('صف مشترك');
    }

    public function test_classroom_form_saves_the_session_and_rejects_foreign_sessions(): void
    {
        [$mosque, $manager, $first] = $this->mosqueWithSessions();

        $this->actingAs($manager)
            ->post(route('admin.classrooms.store'), [
                'name' => 'صف الدوام الأول',
                'study_session_id' => $first->id,
            ])
            ->assertRedirect();

        $classroom = Classroom::withoutGlobalScope('study_session')->where('name', 'صف الدوام الأول')->firstOrFail();
        $this->assertSame($first->id, $classroom->study_session_id);

        $foreign = StudySession::create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'دوام خارجي']);

        $this->actingAs($manager)
            ->post(route('admin.classrooms.store'), [
                'name' => 'صف بدوام خارجي',
                'study_session_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('study_session_id');
    }

    public function test_section_inherits_its_classroom_session(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        $classroom = $this->classroom($mosque, 'صف الدوام الأول', $first->id);

        // حتى لو أرسل النموذج دواماً آخر، تبقى الشعبة على دوام صفها.
        $this->actingAs($manager)
            ->post(route('admin.sections.store', $classroom), [
                'name' => 'أ',
                'study_session_id' => $second->id,
            ])
            ->assertRedirect();

        $section = Section::withoutGlobalScope('study_session')
            ->where('classroom_id', $classroom->id)
            ->firstOrFail();

        $this->assertSame($first->id, $section->study_session_id);

        // الصف المشترك: يمكن تحديد دوام الشعبة.
        $shared = $this->classroom($mosque, 'قاعة مشتركة');

        $this->actingAs($manager)
            ->post(route('admin.sections.store', $shared), [
                'name' => 'ب',
                'study_session_id' => $second->id,
            ])
            ->assertRedirect();

        $sharedSection = Section::withoutGlobalScope('study_session')
            ->where('classroom_id', $shared->id)
            ->firstOrFail();

        $this->assertSame($second->id, $sharedSection->study_session_id);
    }

    public function test_binding_a_classroom_moves_its_sections_students_and_schedules(): void
    {
        [$mosque, $manager, $first] = $this->mosqueWithSessions();

        $classroom = $this->classroom($mosque, 'قاعة مشتركة');
        $section = $this->section($mosque, $classroom, 'أ');
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'الفقه']);

        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
        ]);

        $schedule = Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        $this->actingAs($manager)
            ->patch(route('admin.classrooms.update', $classroom), [
                'name' => $classroom->name,
                'study_session_id' => $first->id,
            ])
            ->assertRedirect();

        $this->assertSame($first->id, $classroom->refresh()->study_session_id);
        $this->assertSame($first->id, $section->refresh()->study_session_id);
        $this->assertSame($first->id, $student->refresh()->study_session_id);
        $this->assertSame($first->id, $schedule->refresh()->study_session_id);
    }

    public function test_changing_a_shared_section_session_moves_its_students(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        $classroom = $this->classroom($mosque, 'قاعة مشتركة');
        $section = $this->section($mosque, $classroom, 'أ', $first->id);

        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
            'study_session_id' => $first->id,
        ]);

        $this->actingAs($manager)
            ->patch(route('admin.sections.update', $section), [
                'name' => $section->name,
                'study_session_id' => $second->id,
            ])
            ->assertRedirect();

        $this->assertSame($second->id, $section->refresh()->study_session_id);
        $this->assertSame($second->id, $student->refresh()->study_session_id);
    }

    public function test_session_with_classrooms_cannot_be_deleted(): void
    {
        [$mosque, $manager, $first] = $this->mosqueWithSessions();

        $this->classroom($mosque, 'صف الدوام الأول', $first->id);

        $this->actingAs($manager)
            ->delete(route('admin.sessions.destroy', $first))
            ->assertSessionHasErrors('session');

        $this->assertDatabaseHas('study_sessions', ['id' => $first->id]);
    }

    public function test_bulk_assign_unassigned_classrooms_moves_their_records(): void
    {
        [$mosque, $manager, $first] = $this->mosqueWithSessions();

        $classroom = $this->classroom($mosque, 'قاعة مشتركة');
        $section = $this->section($mosque, $classroom, 'أ');
        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.sessions.assign-unassigned'), [
                'type' => 'classrooms',
                'study_session_id' => $first->id,
            ])
            ->assertRedirect();

        $this->assertSame($first->id, $classroom->refresh()->study_session_id);
        $this->assertSame($first->id, $section->refresh()->study_session_id);
        $this->assertSame($first->id, $student->refresh()->study_session_id);
    }

    public function test_schedule_shift_must_match_the_classroom_session(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        $classroom = $this->classroom($mosque, 'صف الدوام الأول', $first->id);
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'الفقه']);

        $payload = [
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ];

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), [...$payload, 'study_session_id' => $second->id])
            ->assertSessionHasErrors('study_session_id');

        // بدون تحديد دوام: يُورَّث دوام الصف تلقائياً.
        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('schedules', [
            'classroom_id' => $classroom->id,
            'study_session_id' => $first->id,
        ]);
    }

    public function test_api_classroom_and_section_expose_and_inherit_the_session(): void
    {
        [$mosque, $manager, $first, $second] = $this->mosqueWithSessions();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/admin/classrooms', [
            'name' => 'صف API',
            'study_session_id' => $first->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.study_session_id', $first->id)
            ->assertJsonPath('data.study_session.name', $first->name);

        $classroom = Classroom::withoutGlobalScope('study_session')->where('name', 'صف API')->firstOrFail();

        $this->postJson("/api/v1/admin/classrooms/{$classroom->id}/sections", [
            'name' => 'أ',
            'study_session_id' => $second->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.study_session_id', $first->id);
    }
}
