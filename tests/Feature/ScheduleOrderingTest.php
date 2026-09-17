<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Schedule;
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
 * ترتيب الجداول حسب الدوام: حصص الدوام الأول أولاً ثم الثاني ثم غير المرتبطة،
 * وداخل كل دوام حسب اليوم ثم وقت البداية.
 */
class ScheduleOrderingTest extends TestCase
{
    /** @return array{0: Tenant, 1: User, 2: StudySession, 3: StudySession, 4: Classroom, 5: Teacher, 6: Subject} */
    private function mosqueWithDependencies(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        $sessions = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->get();

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);
        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'القرآن']);

        return [$mosque, $manager, $sessions->first(), $sessions->last(), $classroom, $teacher, $subject];
    }

    private function schedule(
        Tenant $mosque,
        Classroom $classroom,
        Teacher $teacher,
        Subject $subject,
        ?string $sessionId,
        int $day,
        string $startsAt,
        string $endsAt,
    ): Schedule {
        return Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $sessionId,
            'day_of_week' => $day,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function test_schedules_are_ordered_by_study_session_then_day_and_time(): void
    {
        [$mosque, $manager, $first, $second, $classroom, $teacher, $subject] = $this->mosqueWithDependencies();

        // تُنشأ بترتيب عشوائي لاختبار الترتيب لا ترتيب الإدخال.
        $secondShiftLater = $this->schedule($mosque, $classroom, $teacher, $subject, $second->id, 1, '08:00', '09:00');
        $firstShiftLaterDay = $this->schedule($mosque, $classroom, $teacher, $subject, $first->id, 2, '07:00', '08:00');
        $firstShiftEarly = $this->schedule($mosque, $classroom, $teacher, $subject, $first->id, 0, '09:00', '10:00');
        $secondShiftEarly = $this->schedule($mosque, $classroom, $teacher, $subject, $second->id, 0, '06:00', '07:00');
        $withoutSession = $this->schedule($mosque, $classroom, $teacher, $subject, null, 0, '10:00', '11:00');

        $expected = [
            $firstShiftEarly->id,
            $firstShiftLaterDay->id,
            $secondShiftEarly->id,
            $secondShiftLater->id,
            $withoutSession->id,
        ];

        $this->assertSame($expected, Schedule::orderByStudySession()->pluck('id')->all());

        $this->actingAs($manager)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSeeInOrder(['09:00–10:00', '07:00–08:00', '06:00–07:00', '08:00–09:00', '10:00–11:00']);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/admin/schedules')->assertOk();

        $this->assertSame($expected, collect($response->json('data.schedules'))->pluck('id')->all());
    }
}
