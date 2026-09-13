<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Program;
use App\Models\ProgramPeriod;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * مولّد الجدول الأسبوعي للفترات (shift schedules): توليد عدة أيام لبرنامج/فترة
 * واحد — مثال: برنامج التحفيظ الفترة الأولى فقط — مع تخطي المكرر ورفض التعارض.
 */
class ScheduleGenerationTest extends TestCase
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

    private function period(Tenant $mosque, Program $program, string $name, string $start, string $end): ProgramPeriod
    {
        return ProgramPeriod::create([
            'tenant_id' => $mosque->id,
            'program_id' => $program->id,
            'name' => $name,
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }

    private function classroom(Tenant $mosque, string $name = 'الصف الأول'): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function teacher(Tenant $mosque, string $name = 'المعلم'): Teacher
    {
        return Teacher::factory()->create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    // ------------------------------------------------------- generation

    public function test_schedules_page_shows_the_weekly_generator_form(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');

        $this->actingAs($manager)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('توليد جدول أسبوعي')
            ->assertSee('أيام الأسبوع')
            ->assertSee('برنامج التحفيظ');
    }

    public function test_manager_can_generate_a_weekly_schedule_for_one_period_only(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $first = $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');
        $second = $this->period($mosque, $program, 'الفترة الثانية', '07:00', '08:00');

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $program->id,
                'program_period_id' => $first->id,
                'days' => [0, 1, 2],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            [0, 1, 2],
            Schedule::where('program_period_id', $first->id)->orderBy('day_of_week')->pluck('day_of_week')->all()
        );
        $this->assertSame(0, Schedule::where('program_period_id', $second->id)->count());

        $schedule = Schedule::where('program_period_id', $first->id)->firstOrFail();

        $this->assertNull($schedule->subject_id);
        $this->assertSame('06:00', substr($schedule->starts_at, 0, 5));
        $this->assertSame('07:00', substr($schedule->ends_at, 0, 5));
    }

    public function test_generation_works_for_each_default_program(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $programs = [
            ['tahfeez', '06:00', '07:00'],
            ['ijazah', '09:00', '10:00'],
            ['hafiz_exams', '10:00', '11:00'],
            ['sharia_courses', '16:00', '17:00'],
            ['quran', '17:00', '18:00'],
        ];

        foreach ($programs as $index => [$code, $start, $end]) {
            $program = $this->program($mosque, $code);
            $period = $this->period($mosque, $program, 'الفترة الأولى', $start, $end);

            $this->actingAs($manager)
                ->post(route('admin.schedules.generate'), [
                    'classroom_id' => $this->classroom($mosque, 'الصف '.($index + 1))->id,
                    'teacher_id' => $this->teacher($mosque, 'معلم '.($index + 1))->id,
                    'program_id' => $program->id,
                    'program_period_id' => $period->id,
                    'days' => [0],
                ])
                ->assertRedirect();
        }

        $this->assertSame(5, Schedule::count());
        $this->assertSame(5, Schedule::whereNotNull('program_id')->count());
    }

    public function test_regenerating_the_same_week_skips_the_existing_rows(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $period = $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');

        $payload = [
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'program_id' => $program->id,
            'program_period_id' => $period->id,
            'days' => [0, 1],
        ];

        $this->actingAs($manager)->post(route('admin.schedules.generate'), $payload)->assertRedirect();

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'تم توليد 0')
                && str_contains($message, 'تخطي 2'));

        $this->assertSame(2, Schedule::where('program_period_id', $period->id)->count());
    }

    public function test_generation_rejects_a_teacher_double_booking_and_creates_nothing(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $period = $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');
        $classroom = $this->classroom($mosque);
        $teacher = $this->teacher($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الثاني')->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '06:30',
            'ends_at' => '07:30',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $classroom->id,
                'teacher_id' => $teacher->id,
                'program_id' => $program->id,
                'program_period_id' => $period->id,
                'days' => [0, 1, 2],
            ])
            ->assertSessionHasErrors('teacher_id');

        // The whole batch rolls back: no rows for days 1 and 2 either.
        $this->assertSame(0, Schedule::where('classroom_id', $classroom->id)->count());
    }

    public function test_generation_rejects_a_classroom_section_double_booking(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $period = $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');
        $classroom = $this->classroom($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $this->teacher($mosque, 'معلم آخر')->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $classroom->id,
                'teacher_id' => $this->teacher($mosque, 'المعلم الجديد')->id,
                'program_id' => $program->id,
                'program_period_id' => $period->id,
                'days' => [0, 1],
            ])
            ->assertSessionHasErrors('classroom_id');

        $this->assertSame(1, Schedule::count());
    }

    public function test_generation_without_a_period_uses_the_manual_times(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $program->id,
                'starts_at' => '18:00',
                'ends_at' => '19:00',
                'days' => [3, 4],
            ])
            ->assertRedirect();

        $this->assertSame(2, Schedule::where('program_id', $program->id)->count());
        $this->assertSame('18:00', substr(Schedule::first()->starts_at, 0, 5));
    }

    // ------------------------------------------------------- validation

    public function test_generation_requires_at_least_one_day(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $period = $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $program->id,
                'program_period_id' => $period->id,
                'days' => [],
            ])
            ->assertSessionHasErrors('days');

        $this->assertSame(0, Schedule::count());
    }

    public function test_generation_without_a_period_requires_times(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $program->id,
                'days' => [0],
            ])
            ->assertSessionHasErrors(['starts_at', 'ends_at']);
    }

    public function test_generation_rejects_a_period_from_another_program(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $tahfeez = $this->program($mosque, 'tahfeez');
        $ijazah = $this->program($mosque, 'ijazah');
        $ijazahPeriod = $this->period($mosque, $ijazah, 'فترة الإجازة', '09:00', '10:00');

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $tahfeez->id,
                'program_period_id' => $ijazahPeriod->id,
                'days' => [0],
            ])
            ->assertSessionHasErrors('program_period_id');

        $this->assertSame(0, Schedule::count());
    }

    public function test_generation_rejects_records_from_another_mosque(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $other = Tenant::factory()->create();
        config(['app.current_tenant_id' => $other->id]);
        app(ProgramService::class)->provisionTenantPrograms($other);

        $otherProgram = Program::where('tenant_id', $other->id)->where('code', 'tahfeez')->firstOrFail();
        $otherPeriod = $this->period($other, $otherProgram, 'فترة خارجية', '06:00', '07:00');

        config(['app.current_tenant_id' => $mosque->id]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $otherProgram->id,
                'program_period_id' => $otherPeriod->id,
                'days' => [0],
            ])
            ->assertSessionHasErrors('program_id');

        $this->assertSame(0, Schedule::count());
    }

    // ------------------------------------------------------- authorization

    public function test_revoking_schedule_create_blocks_the_generator(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $role = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->firstOrFail();
        app(RoleService::class)->syncRolePermissions($role, ['students.view' => 'mosque']);

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), ['days' => [0]])
            ->assertForbidden();
    }

    public function test_teacher_cannot_use_the_generator(): void
    {
        [$mosque] = $this->mosqueWithPrograms();

        $teacherUser = User::factory()->for($mosque)->create();

        $this->actingAs($teacherUser)
            ->post(route('admin.schedules.generate'), ['days' => [0]])
            ->assertForbidden();
    }

    // ------------------------------------------------------- API

    public function test_admin_api_can_generate_a_weekly_schedule(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $period = $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');
        $classroom = $this->classroom($mosque);
        $teacher = $this->teacher($mosque);

        Sanctum::actingAs($manager);

        $payload = [
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'program_period_id' => $period->id,
            'days' => [0, 4],
        ];

        $this->postJson('/api/v1/admin/schedules/generate', $payload)
            ->assertCreated()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped', 0);

        $this->assertSame(2, Schedule::where('program_period_id', $period->id)->count());

        $this->postJson('/api/v1/admin/schedules/generate', $payload)
            ->assertCreated()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 2);
    }

    public function test_api_generation_rejects_a_teacher_double_booking(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->program($mosque);
        $period = $this->period($mosque, $program, 'الفترة الأولى', '06:00', '07:00');
        $teacher = $this->teacher($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque, 'الصف الثاني')->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 2,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/admin/schedules/generate', [
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'program_period_id' => $period->id,
            'days' => [2],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('teacher_id');
    }
}
