<?php

namespace Tests\Feature;

use App\Enums\ScheduleDuration;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Tests\TestCase;

/**
 * مدة صلاحية الحصة: يوم/أسبوع/شهر/حتى انتهاء الدورة — مع إخفاء المنتهي
 * وحذفه تلقائياً، وتحديث حصص الدورة عند تمديد نهاية البرنامج.
 */
class ScheduleValidityTest extends TestCase
{
    /** @return array{0: Tenant, 1: User} */
    private function mosqueWithPrograms(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);
        app(ProgramService::class)->provisionTenantPrograms($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    private function program(Tenant $mosque, string $code = 'tahfeez'): Program
    {
        return Program::where('tenant_id', $mosque->id)->where('code', $code)->firstOrFail();
    }

    private function classroom(Tenant $mosque, string $name = 'الصف الأول'): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function teacher(Tenant $mosque, string $name = 'المعلم'): Teacher
    {
        return Teacher::factory()->create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(Tenant $mosque, Program $program, array $overrides = []): array
    {
        return $overrides + [
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'program_id' => $program->id,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'days' => [0],
        ];
    }

    // ------------------------------------------------------- durations

    public function test_day_duration_ends_the_same_day(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->program($mosque), [
                'starts_on' => '2030-01-05',
                'duration' => ScheduleDuration::Day->value,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = Schedule::firstOrFail();

        $this->assertSame('2030-01-05', $schedule->starts_on->toDateString());
        $this->assertSame('2030-01-05', $schedule->ends_on->toDateString());
        $this->assertSame(ScheduleDuration::Day, $schedule->duration);
    }

    public function test_week_duration_ends_six_days_later(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->program($mosque), [
                'starts_on' => '2030-01-05',
                'duration' => ScheduleDuration::Week->value,
            ]))
            ->assertRedirect();

        $schedule = Schedule::firstOrFail();

        $this->assertSame('2030-01-05', $schedule->starts_on->toDateString());
        $this->assertSame('2030-01-11', $schedule->ends_on->toDateString());
    }

    public function test_month_duration_ends_before_the_same_day_next_month(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->program($mosque), [
                'starts_on' => '2030-03-10',
                'duration' => ScheduleDuration::Month->value,
            ]))
            ->assertRedirect();

        $schedule = Schedule::firstOrFail();

        $this->assertSame('2030-04-09', $schedule->ends_on->toDateString());
    }

    public function test_course_duration_uses_the_program_end_date(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $program->update(['ends_on' => '2030-06-30']);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $program, [
                'starts_on' => '2030-01-05',
                'duration' => ScheduleDuration::Course->value,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = Schedule::firstOrFail();

        $this->assertSame('2030-06-30', $schedule->ends_on->toDateString());
    }

    public function test_course_duration_requires_a_program_end_date(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->program($mosque), [
                'starts_on' => '2030-01-05',
                'duration' => ScheduleDuration::Course->value,
            ]))
            ->assertSessionHasErrors('duration');

        $this->assertSame(0, Schedule::count());
    }

    public function test_course_duration_rejects_a_start_after_the_course_end(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $program->update(['ends_on' => '2030-01-01']);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $program, [
                'starts_on' => '2030-02-01',
                'duration' => ScheduleDuration::Course->value,
            ]))
            ->assertSessionHasErrors('duration');

        $this->assertSame(0, Schedule::count());
    }

    // ------------------------------------------------------- hiding & purging

    public function test_expired_schedules_are_hidden_from_the_schedule_page(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $expired = Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque, 'أستاذ منتهي')->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
            'duration' => ScheduleDuration::Day,
        ]);

        $open = Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الثاني')->id,
            'teacher_id' => $this->teacher($mosque, 'أستاذ مستمر')->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee($open->id)
            ->assertDontSee($expired->id);
    }

    public function test_purge_expired_command_removes_expired_and_keeps_open_schedules(): void
    {
        [$mosque] = $this->mosqueWithPrograms();

        $expired = Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque, 'منتهي')->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
            'duration' => ScheduleDuration::Week,
        ]);

        $open = Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الثاني')->id,
            'teacher_id' => $this->teacher($mosque, 'مفتوح')->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->artisan('schedules:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('schedules', ['id' => $expired->id]);
        $this->assertDatabaseHas('schedules', ['id' => $open->id]);
    }

    public function test_purge_expired_dry_run_keeps_the_rows(): void
    {
        [$mosque] = $this->mosqueWithPrograms();

        $expired = Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
            'duration' => ScheduleDuration::Day,
        ]);

        $this->artisan('schedules:purge-expired', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('schedules', ['id' => $expired->id]);
    }

    // ------------------------------------------------------- program end date

    public function test_extending_the_program_end_date_updates_course_schedules(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $program->update(['ends_on' => '2030-06-30']);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $program, [
                'starts_on' => '2030-01-05',
                'duration' => ScheduleDuration::Course->value,
            ]))
            ->assertRedirect();

        $this->actingAs($manager)
            ->patch(route('admin.programs.update', $program), [
                'name' => $program->name,
                'type' => $program->type->value,
                'ends_on' => '2030-12-31',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('2030-12-31', Schedule::firstOrFail()->ends_on->toDateString());
    }

    // ------------------------------------------------------- conflicts

    public function test_schedules_with_non_overlapping_validity_do_not_conflict(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $teacher = $this->teacher($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الأول')->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'starts_on' => '2030-01-01',
            'ends_on' => '2030-01-05',
            'duration' => ScheduleDuration::Week,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->program($mosque), [
                'teacher_id' => $teacher->id,
                'starts_on' => '2030-01-06',
                'duration' => ScheduleDuration::Week->value,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Schedule::count());
    }

    public function test_schedules_with_overlapping_validity_still_conflict(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $teacher = $this->teacher($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الأول')->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
            'starts_on' => '2030-01-01',
            'ends_on' => '2030-01-05',
            'duration' => ScheduleDuration::Week,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $this->payload($mosque, $this->program($mosque), [
                'teacher_id' => $teacher->id,
                'starts_on' => '2030-01-05',
                'duration' => ScheduleDuration::Week->value,
            ]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, Schedule::count());
    }
}
