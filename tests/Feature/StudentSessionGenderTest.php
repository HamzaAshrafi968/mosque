<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * فصل الطلاب حسب جنس الدوام: دوام الذكور لا يضم إناثاً والعكس — في القوائم
 * وفي التسجيل/النقل وفي توزيع غير المصنفين وفي البذور.
 */
class StudentSessionGenderTest extends TestCase
{
    /** @return array{0: Tenant, 1: User, 2: StudySession, 3: StudySession} */
    private function mosqueWithGenderedSessions(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $sessions = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->get();
        $male = $sessions->first();
        $female = $sessions->last();

        $male->update(['gender' => 'male']);
        $female->update(['gender' => 'female']);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager, $male->fresh(), $female->fresh()];
    }

    /** @return array{0: Classroom, 1: Section} */
    private function classroomIn(Tenant $mosque, StudySession $session, string $name): array
    {
        $classroom = Classroom::create([
            'tenant_id' => $mosque->id,
            'name' => $name,
            'study_session_id' => $session->id,
        ]);

        $section = Section::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'name' => 'أ',
        ]);

        return [$classroom, $section->fresh()];
    }

    public function test_selecting_a_gendered_session_lists_only_matching_students(): void
    {
        [$mosque, $manager, $male, $female] = $this->mosqueWithGenderedSessions();

        Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $male->id, 'gender' => 'male', 'name' => 'طالب الذكور']);
        Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $male->id, 'gender' => 'female', 'name' => 'طالبة في دوام الذكور']);
        Student::factory()->create(['tenant_id' => $mosque->id, 'study_session_id' => $female->id, 'gender' => 'female', 'name' => 'طالبة الإناث']);

        $this->actingAs($manager)
            ->post(route('admin.sessions.switch'), ['study_session_id' => $male->id])
            ->assertRedirect();

        $this->actingAs($manager)->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('طالب الذكور')
            ->assertDontSee('طالبة في دوام الذكور')
            ->assertDontSee('طالبة الإناث');

        $this->actingAs($manager)
            ->post(route('admin.sessions.switch'), ['study_session_id' => $female->id])
            ->assertRedirect();

        $this->actingAs($manager)->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('طالبة الإناث')
            ->assertDontSee('طالب الذكور')
            ->assertDontSee('طالبة في دوام الذكور');
    }

    public function test_student_form_rejects_a_session_of_another_gender(): void
    {
        [$mosque, $manager, $male] = $this->mosqueWithGenderedSessions();

        $this->actingAs($manager)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), [
                'name' => 'طالبة',
                'gender' => 'female',
                'study_session_id' => $male->id,
            ])
            ->assertSessionHasErrors('study_session_id');

        $this->assertDatabaseMissing('students', ['tenant_id' => $mosque->id, 'name' => 'طالبة']);
    }

    public function test_student_form_rejects_a_section_of_another_gender(): void
    {
        [$mosque, $manager, , $female] = $this->mosqueWithGenderedSessions();

        [, $femaleSection] = $this->classroomIn($mosque, $female, 'صف الإناث');

        $this->actingAs($manager)
            ->from(route('admin.students.create'))
            ->post(route('admin.students.store'), [
                'name' => 'طالب',
                'gender' => 'male',
                'section_id' => $femaleSection->id,
            ])
            ->assertSessionHasErrors('section_id');

        $this->assertDatabaseMissing('students', ['tenant_id' => $mosque->id, 'name' => 'طالب']);
    }

    public function test_enrollment_rejects_a_student_from_another_gender(): void
    {
        [$mosque, , , $female] = $this->mosqueWithGenderedSessions();

        [, $femaleSection] = $this->classroomIn($mosque, $female, 'صف الإناث');
        $maleStudent = Student::factory()->create(['tenant_id' => $mosque->id, 'gender' => 'male', 'name' => 'طالب']);

        $this->expectException(ValidationException::class);

        app(EnrollmentService::class)->enroll($maleStudent, $femaleSection);
    }

    public function test_transfer_route_rejects_a_student_from_another_gender(): void
    {
        [$mosque, $manager, $male, $female] = $this->mosqueWithGenderedSessions();

        [, $maleSection] = $this->classroomIn($mosque, $male, 'صف الذكور');
        [, $femaleSection] = $this->classroomIn($mosque, $female, 'صف الإناث');

        $student = Student::factory()->create(['tenant_id' => $mosque->id, 'gender' => 'male', 'name' => 'طالب']);
        app(EnrollmentService::class)->enroll($student, $maleSection);

        $this->actingAs($manager)
            ->from(route('admin.students.show', $student))
            ->post(route('admin.students.transfer', $student), ['section_id' => $femaleSection->id])
            ->assertSessionHasErrors('section_id');

        $this->assertSame($maleSection->id, $student->fresh()->section_id);
    }

    public function test_classroom_shift_move_rejects_mismatched_students(): void
    {
        [$mosque, $manager, $male, $female] = $this->mosqueWithGenderedSessions();

        [$classroom, $section] = $this->classroomIn($mosque, $female, 'صف الإناث');

        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $female->id,
            'section_id' => $section->id,
            'classroom_id' => $classroom->id,
            'gender' => 'female',
        ]);

        $this->actingAs($manager)
            ->from(route('admin.classrooms.edit', $classroom))
            ->patch(route('admin.classrooms.update', $classroom), [
                'name' => $classroom->name,
                'study_session_id' => $male->id,
            ])
            ->assertSessionHasErrors('study_session_id');

        $this->assertSame($female->id, $classroom->fresh()->study_session_id);
    }

    public function test_bulk_assign_moves_only_matching_gender_students(): void
    {
        [$mosque, $manager, $male] = $this->mosqueWithGenderedSessions();

        $maleStudent = Student::factory()->create(['tenant_id' => $mosque->id, 'gender' => 'male']);
        $femaleStudent = Student::factory()->create(['tenant_id' => $mosque->id, 'gender' => 'female']);

        $this->actingAs($manager)
            ->post(route('admin.sessions.assign-unassigned'), ['type' => 'students', 'study_session_id' => $male->id])
            ->assertRedirect();

        $this->assertSame($male->id, $maleStudent->fresh()->study_session_id);
        $this->assertNull($femaleStudent->fresh()->study_session_id);
    }

    public function test_database_seeder_never_mixes_genders_in_a_session(): void
    {
        $this->seed();

        $mixed = DB::table('students')
            ->join('study_sessions', 'study_sessions.id', '=', 'students.study_session_id')
            ->whereNotNull('study_sessions.gender')
            ->whereColumn('students.gender', '!=', 'study_sessions.gender')
            ->count();

        $this->assertSame(0, $mixed);
    }
}
