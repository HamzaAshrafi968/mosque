<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * إلغاء/تأجيل/استرجاع حصة يوم واحد (ClassSession) + العزل والصلاحيات.
 */
class SessionScheduleTest extends TestCase
{
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    private function classroom(Tenant $mosque): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
    }

    private function section(Tenant $mosque, Classroom $classroom): Section
    {
        return Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
    }

    private function teacherUser(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function schedule(Tenant $mosque, Classroom $classroom, ?Section $section, Teacher $teacher, int $day = 0): Schedule
    {
        return Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section?->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => $day,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);
    }

    /** تاريخ يطابق يوم الحصة (اليوم نفسه أو الأقرب القادم). */
    private function dateFor(int $day): string
    {
        $today = now();

        return $today->dayOfWeek === $day ? $today->toDateString() : $today->copy()->next($day)->toDateString();
    }

    // ------------------------------------------------------- cancel

    public function test_manager_can_cancel_a_single_day(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $section = $this->section($mosque, $classroom);
        $schedule = $this->schedule($mosque, $classroom, $section, $this->teacherUser($mosque)[1]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.cancel', $schedule), [
                'date' => $this->dateFor(0),
                'reason' => 'ظرف طارئ',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $exception = ClassSession::where('schedule_id', $schedule->id)->firstOrFail();

        $this->assertSame(SessionStatus::Cancelled, $exception->status);
        $this->assertSame('ظرف طارئ', $exception->reason);
        $this->assertSame($manager->id, $exception->changed_by);
    }

    public function test_cancel_rejects_a_date_that_does_not_match_the_slot_day(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $schedule = $this->schedule($mosque, $classroom, null, $this->teacherUser($mosque)[1], day: 0);

        $mismatched = $this->dateFor(3);

        $this->actingAs($manager)
            ->post(route('admin.schedules.cancel', $schedule), ['date' => $mismatched])
            ->assertSessionHasErrors('date');

        $this->assertSame(0, ClassSession::count());
    }

    public function test_cancelling_notifies_the_teacher_and_the_roster(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $section = $this->section($mosque, $classroom);
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $schedule = $this->schedule($mosque, $classroom, $section, $teacher);

        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
            'user_id' => User::factory()->for($mosque)->create()->id,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.cancel', $schedule), ['date' => $this->dateFor(0)])
            ->assertRedirect();

        $this->assertGreaterThanOrEqual(2, DB::table('notifications')->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $teacherUser->id)->count());
    }

    // ------------------------------------------------------- postpone

    public function test_manager_can_postpone_a_session_to_a_new_time(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $schedule = $this->schedule($mosque, $classroom, null, $this->teacherUser($mosque)[1]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.postpone', $schedule), [
                'date' => $this->dateFor(0),
                'postponed_date' => $this->dateFor(2),
                'postponed_starts_at' => '10:00',
                'postponed_ends_at' => '11:00',
                'reason' => 'تعارض طارئ',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $exception = ClassSession::where('schedule_id', $schedule->id)->firstOrFail();

        $this->assertSame(SessionStatus::Postponed, $exception->status);
        $this->assertSame('10:00', substr((string) $exception->postponed_starts_at, 0, 5));
        $this->assertSame($this->dateFor(2), $exception->postponed_date->toDateString());
    }

    public function test_postpone_rejects_a_conflict_with_another_teacher_slot(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $schedule = $this->schedule($mosque, $classroom, null, $teacher);

        // Same teacher has another class on the new date/time.
        $this->schedule($mosque, $this->classroom($mosque), null, $teacher, day: 2)
            ->update(['starts_at' => '10:30', 'ends_at' => '11:30']);

        $this->actingAs($manager)
            ->post(route('admin.schedules.postpone', $schedule), [
                'date' => $this->dateFor(0),
                'postponed_date' => $this->dateFor(2),
                'postponed_starts_at' => '10:00',
                'postponed_ends_at' => '11:00',
            ])
            ->assertSessionHasErrors('postponed_starts_at');

        $this->assertSame(0, ClassSession::count());
    }

    // ------------------------------------------------------- restore

    public function test_restore_removes_the_exception(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $schedule = $this->schedule($mosque, $classroom, null, $this->teacherUser($mosque)[1]);

        $this->actingAs($manager)->post(route('admin.schedules.cancel', $schedule), ['date' => $this->dateFor(0)]);

        $exception = ClassSession::firstOrFail();

        $this->actingAs($manager)
            ->delete(route('admin.schedules.restore', $exception))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, ClassSession::count());
    }

    // ------------------------------------------------------- teacher scope

    public function test_teacher_can_cancel_their_own_schedule(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        [$teacherUser, $teacher] = $this->teacherUser($mosque);
        $schedule = $this->schedule($mosque, $classroom, null, $teacher);

        $this->actingAs($teacherUser)
            ->post(route('teacher.schedule.cancel', $schedule), ['date' => $this->dateFor(0)])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, ClassSession::count());
    }

    public function test_teacher_cannot_cancel_another_teachers_schedule(): void
    {
        [$mosque] = $this->mosque();
        $classroom = $this->classroom($mosque);
        [, $otherTeacher] = $this->teacherUser($mosque);
        [$teacherUser] = $this->teacherUser($mosque);
        $schedule = $this->schedule($mosque, $classroom, null, $otherTeacher);

        $this->actingAs($teacherUser)
            ->post(route('teacher.schedule.cancel', $schedule), ['date' => $this->dateFor(0)])
            ->assertForbidden();

        $this->assertSame(0, ClassSession::count());
    }

    // ------------------------------------------------------- grid

    public function test_schedule_page_shows_upcoming_exceptions(): void
    {
        [$mosque, $manager] = $this->mosque();
        $classroom = $this->classroom($mosque);
        $schedule = $this->schedule($mosque, $classroom, null, $this->teacherUser($mosque)[1]);

        $this->actingAs($manager)->post(route('admin.schedules.cancel', $schedule), [
            'date' => $this->dateFor(0),
            'reason' => 'صيانة القاعة',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('الجدول الأسبوعي')
            ->assertSee('ملغاة')
            ->assertSee('صيانة القاعة');
    }
}
