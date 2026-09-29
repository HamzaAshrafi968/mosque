<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\PortalNotification;
use App\Services\StudentAcademicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@mosque.test')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'تعميم على كل الصفوف',
            'body' => 'نص الإعلان',
            'audience' => 'classrooms',
        ], $overrides);
    }

    public function test_manager_can_publish_announcement_to_all_classrooms(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload())
            ->assertSessionHas('success');

        $announcement = Announcement::withoutGlobalScope('tenant')
            ->where('title', 'تعميم على كل الصفوف')
            ->firstOrFail();

        $this->assertSame('classrooms', $announcement->audience);
        $this->assertNull($announcement->classroom_id);
    }

    public function test_specific_classroom_audience_still_requires_a_classroom(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload(['audience' => 'classroom']))
            ->assertSessionHasErrors('classroom_id');
    }

    public function test_all_classrooms_announcement_notifies_students_and_guardians_across_shifts(): void
    {
        Notification::fake();

        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        $firstSession = StudySession::query()->orderBy('name')->firstOrFail();
        $secondSession = StudySession::query()->whereKeyNot($firstSession->id)->firstOrFail();

        $secondShiftStudentUser = User::create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'طالب الدوام الثاني',
            'email' => 'shift2-student@mosque.test',
            'password' => 'password',
            'role' => User::ROLE_STUDENT,
        ]);

        Student::query()
            ->where('study_session_id', $secondSession->id)
            ->firstOrFail()
            ->update(['user_id' => $secondShiftStudentUser->id]);

        $this->withSession(['study_session_id' => $firstSession->id])
            ->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload())
            ->assertSessionHas('success');

        $guardianUser = User::where('email', 'parent@mosque.test')->firstOrFail();
        $teacherUser = User::where('email', 'teacher@mosque.test')->firstOrFail();

        Notification::assertSentTo($secondShiftStudentUser, PortalNotification::class);
        Notification::assertSentTo($guardianUser, PortalNotification::class);
        Notification::assertNotSentTo($teacherUser, PortalNotification::class);
    }

    public function test_all_classrooms_announcement_is_visible_to_every_student(): void
    {
        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        $announcement = Announcement::create([
            'title' => 'تعميم لكل الصفوف',
            'body' => 'نص',
            'audience' => 'classrooms',
            'published_at' => now(),
        ]);

        $students = Student::active()->orderBy('name')->get();
        $first = $students->firstOrFail();
        $second = $students->first(fn (Student $student) => $student->classroom_id !== $first->classroom_id);

        $this->assertNotNull($second);

        $service = app(StudentAcademicService::class);

        $this->assertTrue($service->announcements($first)->contains('id', $announcement->id));
        $this->assertTrue($service->announcements($second)->contains('id', $announcement->id));
        $this->assertTrue($service->announcements($second, true)->contains('id', $announcement->id));
    }

    public function test_specific_classroom_announcement_only_reaches_its_students(): void
    {
        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        $student = Student::active()->firstOrFail();
        $other = Student::active()->where('classroom_id', '!=', $student->classroom_id)->firstOrFail();

        $announcement = Announcement::create([
            'title' => 'لصف محدد',
            'body' => 'نص',
            'audience' => 'classroom',
            'classroom_id' => $student->classroom_id,
            'published_at' => now(),
        ]);

        $service = app(StudentAcademicService::class);

        $this->assertTrue($service->announcements($student)->contains('id', $announcement->id));
        $this->assertFalse($service->announcements($other)->contains('id', $announcement->id));
    }

    public function test_api_manager_can_publish_all_classrooms_announcement(): void
    {
        Sanctum::actingAs($this->admin());

        $this->post('/api/v1/admin/announcements', [
            'title' => 'تعميم API',
            'body' => 'نص',
            'audience' => 'classrooms',
        ])->assertCreated()->assertJsonPath('data.audience', 'classrooms');
    }

    public function test_shift_targeted_announcement_only_notifies_that_shift(): void
    {
        Notification::fake();

        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        [$firstSession, $secondSession] = $this->sessions();

        [, $firstShiftStudentUser] = $this->linkStudentUser($admin->tenant_id, $firstSession->id, 'first-shift-student@mosque.test');
        [, $secondShiftStudentUser] = $this->linkStudentUser($admin->tenant_id, $secondSession->id, 'second-shift-student@mosque.test');

        $secondShiftTeacherUser = User::create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'أستاذ الدوام الثاني',
            'email' => 'second-shift-teacher@mosque.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);

        Teacher::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'user_id' => $secondShiftTeacherUser->id,
            'study_session_id' => $secondSession->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload([
                'audience' => 'all',
                'study_session_id' => $firstSession->id,
            ]))
            ->assertSessionHas('success');

        $firstShiftTeacherUser = User::where('email', 'teacher@mosque.test')->firstOrFail();

        Notification::assertSentTo($firstShiftStudentUser, PortalNotification::class);
        Notification::assertSentTo($firstShiftTeacherUser, PortalNotification::class);
        Notification::assertNotSentTo($secondShiftStudentUser, PortalNotification::class);
        Notification::assertNotSentTo($secondShiftTeacherUser, PortalNotification::class);
    }

    public function test_shift_targeted_classrooms_announcement_stays_within_shift(): void
    {
        Notification::fake();

        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        [$firstSession, $secondSession] = $this->sessions();

        [, $firstShiftStudentUser] = $this->linkStudentUser($admin->tenant_id, $firstSession->id, 'first-shift-classrooms@mosque.test');
        [, $secondShiftStudentUser] = $this->linkStudentUser($admin->tenant_id, $secondSession->id, 'second-shift-classrooms@mosque.test');

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload([
                'audience' => 'classrooms',
                'study_session_id' => $secondSession->id,
            ]))
            ->assertSessionHas('success');

        Notification::assertSentTo($secondShiftStudentUser, PortalNotification::class);
        Notification::assertNotSentTo($firstShiftStudentUser, PortalNotification::class);
    }

    public function test_classroom_from_another_shift_is_rejected_for_the_selected_shift(): void
    {
        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        [$firstSession, $secondSession] = $this->sessions();

        $secondShiftClassroom = Classroom::withoutGlobalScope('study_session')
            ->where('study_session_id', $secondSession->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload([
                'audience' => 'classroom',
                'classroom_id' => $secondShiftClassroom->id,
                'study_session_id' => $firstSession->id,
            ]))
            ->assertSessionHasErrors('classroom_id');

        $firstShiftClassroom = Classroom::withoutGlobalScope('study_session')
            ->where('study_session_id', $firstSession->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload([
                'audience' => 'classroom',
                'classroom_id' => $firstShiftClassroom->id,
                'study_session_id' => $firstSession->id,
            ]))
            ->assertSessionHas('success');
    }

    public function test_student_only_sees_announcements_of_their_own_shift(): void
    {
        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        [$firstSession, $secondSession] = $this->sessions();

        $firstShiftStudent = $this->studentInSession($firstSession->id);
        $secondShiftStudent = $this->studentInSession($secondSession->id);

        $shiftAnnouncement = Announcement::create([
            'title' => 'للدوام الأول',
            'body' => 'نص',
            'audience' => 'all',
            'study_session_id' => $firstSession->id,
            'published_at' => now(),
        ]);

        $globalAnnouncement = Announcement::create([
            'title' => 'لكل الدوام',
            'body' => 'نص',
            'audience' => 'all',
            'published_at' => now(),
        ]);

        $service = app(StudentAcademicService::class);

        $this->assertTrue($service->announcements($firstShiftStudent)->contains('id', $shiftAnnouncement->id));
        $this->assertFalse($service->announcements($secondShiftStudent)->contains('id', $shiftAnnouncement->id));
        $this->assertTrue($service->announcements($secondShiftStudent)->contains('id', $globalAnnouncement->id));
    }

    public function test_api_manager_can_publish_shift_targeted_announcement(): void
    {
        $admin = $this->admin();
        config(['app.current_tenant_id' => $admin->tenant_id]);

        [$firstSession] = $this->sessions();

        Sanctum::actingAs($admin);

        $this->post('/api/v1/admin/announcements', [
            'title' => 'إعلان الدوام الأول',
            'body' => 'نص',
            'audience' => 'all',
            'study_session_id' => $firstSession->id,
        ])->assertCreated()->assertJsonPath('data.study_session_id', $firstSession->id);
    }

    /** @return array{0: StudySession, 1: StudySession} */
    private function sessions(): array
    {
        $sessions = StudySession::query()->orderBy('name')->get();

        return [$sessions[0], $sessions[1]];
    }

    private function studentInSession(string $sessionId): Student
    {
        return Student::query()
            ->withoutGlobalScope('study_session')
            ->where('study_session_id', $sessionId)
            ->firstOrFail();
    }

    /** @return array{0: Student, 1: User} */
    private function linkStudentUser(string $tenantId, string $sessionId, string $email): array
    {
        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => 'طالب '.$email,
            'email' => $email,
            'password' => 'password',
            'role' => User::ROLE_STUDENT,
        ]);

        $student = Student::query()
            ->withoutGlobalScope('study_session')
            ->where('study_session_id', $sessionId)
            ->whereNull('user_id')
            ->firstOrFail();

        $student->update(['user_id' => $user->id]);

        return [$student, $user];
    }
}
