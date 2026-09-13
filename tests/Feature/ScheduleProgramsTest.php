<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Program;
use App\Models\ProgramAttribute;
use App\Models\ProgramPeriod;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use App\Support\PermissionCatalog;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * تخصصات الجداول (schedule programs): provisioning، CRUD البرامج والفترات
 * والخصائص، ربط الحصص بالبرنامج/الفترة/الدوام، والعزل بين الجوامع.
 */
class ScheduleProgramsTest extends TestCase
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

    private function tahfeez(Tenant $mosque): Program
    {
        return Program::where('tenant_id', $mosque->id)->where('code', 'tahfeez')->firstOrFail();
    }

    private function classroom(Tenant $mosque, string $name = 'الصف الأول'): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function teacher(Tenant $mosque, ?string $sessionId = null): Teacher
    {
        return Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $sessionId,
        ]);
    }

    // ------------------------------------------------------- provisioning

    public function test_provisioning_creates_the_five_default_programs_per_mosque(): void
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        $service = app(ProgramService::class);
        $service->provisionTenantPrograms($mosque);
        $service->provisionTenantPrograms($mosque);

        $programs = Program::where('tenant_id', $mosque->id)->orderBy('sort_order')->get();

        $this->assertCount(5, $programs);
        $this->assertSame(
            ['tahfeez', 'ijazah', 'hafiz_exams', 'sharia_courses', 'quran'],
            $programs->pluck('code')->all()
        );
        $this->assertSame('برنامج التحفيظ', $programs->first()->name);
    }

    public function test_program_permissions_exist_in_catalog_and_are_granted_to_the_manager(): void
    {
        [$mosque] = $this->mosqueWithPrograms();

        foreach (['programs.view', 'programs.create', 'programs.update', 'programs.delete'] as $code) {
            $this->assertContains($code, PermissionCatalog::codes());
            $this->assertDatabaseHas('permissions', ['code' => $code]);
        }

        $role = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->firstOrFail();

        foreach (['programs.view', 'programs.create', 'programs.update', 'programs.delete'] as $code) {
            $this->assertTrue(
                $role->permissions()->where('permissions.code', $code)->exists(),
                "manager missing {$code}"
            );
        }
    }

    public function test_demo_seeder_creates_periods_and_weekly_schedules_for_the_five_programs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mosque = Tenant::where('code', 'NUR')->firstOrFail();
        config(['app.current_tenant_id' => $mosque->id]);

        $programs = Program::where('tenant_id', $mosque->id)->get();

        $this->assertCount(5, $programs);

        foreach ($programs as $program) {
            $this->assertTrue($program->periods()->exists(), "missing periods for {$program->code}");
            $this->assertSame(
                5,
                Schedule::where('program_id', $program->id)->count(),
                "missing weekly schedule for {$program->code}"
            );
        }
    }

    public function test_program_pages_render_with_periods_and_attributes(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->tahfeez($mosque);

        ProgramPeriod::create([
            'tenant_id' => $mosque->id,
            'program_id' => $program->id,
            'name' => 'الفترة الأولى',
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        ProgramAttribute::create([
            'tenant_id' => $mosque->id,
            'program_id' => $program->id,
            'name' => 'عدد الأجزاء',
            'field_key' => 'weekly_juz',
            'field_type' => 'number',
            'value' => '5',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('برنامج التحفيظ')
            ->assertSee('البرامج والتخصصات');

        $this->actingAs($manager)
            ->get(route('admin.programs.create'))
            ->assertOk()
            ->assertSee('الفترات')
            ->assertSee('الخصائص المخصصة')
            ->assertSee('اختيار من الطلاب');

        $this->actingAs($manager)
            ->get(route('admin.programs.edit', $program))
            ->assertOk()
            ->assertSee('الفترة الأولى')
            ->assertSee('عدد الأجزاء')
            ->assertSee('weekly_juz');

        $this->actingAs($manager)
            ->get(route('admin.schedules.index'))
            ->assertOk();
    }

    // ------------------------------------------------------- program CRUD

    public function test_manager_can_create_a_program_with_periods_and_attributes(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)
            ->post(route('admin.programs.store'), [
                'name' => 'برنامج التحفيظ الصيفي',
                'type' => 'quran',
                'description' => 'برنامج صيفي مكثف',
                'sort_order' => 3,
                'is_active' => 1,
                'periods' => [
                    ['name' => 'الفترة الأولى', 'starts_at' => '06:00', 'ends_at' => '07:00', 'sort_order' => 0, 'is_active' => 1],
                    ['name' => 'الفترة الثانية', 'starts_at' => '07:00', 'ends_at' => '08:00', 'sort_order' => 1, 'is_active' => 1],
                ],
                'attributes' => [
                    ['name' => 'عدد الأجزاء', 'field_key' => 'weekly_juz', 'field_type' => 'number', 'value' => '5', 'is_active' => 1],
                    ['name' => 'المستوى', 'field_key' => 'level', 'field_type' => 'select', 'options' => "مبتدئ\nمتوسط\nمتقدم", 'value' => 'متوسط', 'is_active' => 1],
                ],
            ])
            ->assertRedirect(route('admin.programs.index'));

        $program = Program::where('tenant_id', $mosque->id)->where('name', 'برنامج التحفيظ الصيفي')->firstOrFail();

        $this->assertSame('quran', $program->type->value);
        $this->assertNotEmpty($program->code);
        $this->assertSame(2, $program->periods()->count());
        $this->assertSame(2, $program->attributes()->count());
        $this->assertSame(['مبتدئ', 'متوسط', 'متقدم'], $program->attributes()->where('field_key', 'level')->first()->options);
    }

    public function test_required_and_select_attributes_are_validated(): void
    {
        [, $manager] = $this->mosqueWithPrograms();

        // Required attribute without a value.
        $this->actingAs($manager)
            ->post(route('admin.programs.store'), [
                'name' => 'برنامج اختبار',
                'type' => 'custom',
                'attributes' => [
                    ['name' => 'المشرف المسؤول', 'field_type' => 'text', 'required' => 1, 'value' => '', 'is_active' => 1],
                ],
            ])
            ->assertSessionHasErrors();

        // Select value outside the allowed options.
        $this->actingAs($manager)
            ->post(route('admin.programs.store'), [
                'name' => 'برنامج اختبار 2',
                'type' => 'custom',
                'attributes' => [
                    ['name' => 'المستوى', 'field_type' => 'select', 'options' => "مبتدئ\nمتقدم", 'value' => 'خيالي', 'is_active' => 1],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_multiselect_attribute_values_are_normalised_from_the_form(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)
            ->post(route('admin.programs.store'), [
                'name' => 'برنامج الإتقان',
                'type' => 'custom',
                'attributes' => [
                    [
                        'name' => 'أيام البرنامج',
                        'field_key' => 'days',
                        'field_type' => 'multiselect',
                        'options' => "الأحد\nالاثنين\nالثلاثاء",
                        'value' => 'الأحد، الثلاثاء',
                        'is_active' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.programs.index'));

        $attribute = ProgramAttribute::where('tenant_id', $mosque->id)->where('field_key', 'days')->firstOrFail();

        $this->assertSame(['الأحد', 'الثلاثاء'], json_decode($attribute->value, true));
    }

    public function test_program_periods_cannot_overlap(): void
    {
        [, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)
            ->post(route('admin.programs.store'), [
                'name' => 'برنامج متداخل',
                'type' => 'custom',
                'periods' => [
                    ['name' => 'الأولى', 'starts_at' => '06:00', 'ends_at' => '07:00', 'is_active' => 1],
                    ['name' => 'الثانية', 'starts_at' => '06:30', 'ends_at' => '07:30', 'is_active' => 1],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_manager_can_update_a_program_and_sync_its_periods(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->tahfeez($mosque);

        $period = ProgramPeriod::create([
            'tenant_id' => $mosque->id,
            'program_id' => $program->id,
            'name' => 'الفترة القديمة',
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        $this->actingAs($manager)
            ->patch(route('admin.programs.update', $program), [
                'name' => 'برنامج التحفيظ المطور',
                'type' => 'tahfeez',
                'is_active' => 1,
                'periods' => [
                    ['name' => 'الفترة الجديدة', 'starts_at' => '08:00', 'ends_at' => '09:00', 'is_active' => 1],
                ],
            ])
            ->assertRedirect(route('admin.programs.index'));

        $program->refresh();

        $this->assertSame('برنامج التحفيظ المطور', $program->name);
        $this->assertDatabaseMissing('program_periods', ['id' => $period->id]);
        $this->assertSame(1, $program->periods()->count());
        $this->assertSame('الفترة الجديدة', $program->periods()->first()->name);
    }

    public function test_program_with_schedules_cannot_be_deleted(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->tahfeez($mosque);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'program_id' => $program->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        $this->actingAs($manager)
            ->delete(route('admin.programs.destroy', $program))
            ->assertSessionHasErrors('program');

        $this->assertDatabaseHas('programs', ['id' => $program->id]);

        // A program without schedules can be deleted.
        $empty = Program::where('tenant_id', $mosque->id)->where('code', 'quran')->firstOrFail();

        $this->actingAs($manager)
            ->delete(route('admin.programs.destroy', $empty))
            ->assertRedirect(route('admin.programs.index'));

        $this->assertDatabaseMissing('programs', ['id' => $empty->id]);
    }

    // ------------------------------------------------------- schedules

    public function test_schedule_uses_program_period_times_and_subject_is_optional(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $program = $this->tahfeez($mosque);

        $period = ProgramPeriod::create([
            'tenant_id' => $mosque->id,
            'program_id' => $program->id,
            'name' => 'الفترة الأولى',
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $program->id,
                'program_period_id' => $period->id,
                'day_of_week' => 0,
            ])
            ->assertRedirect();

        $schedule = Schedule::where('program_id', $program->id)->firstOrFail();

        $this->assertNull($schedule->subject_id);
        $this->assertSame($period->id, $schedule->program_period_id);
        $this->assertSame('06:00', substr($schedule->starts_at, 0, 5));
        $this->assertSame('07:00', substr($schedule->ends_at, 0, 5));
    }

    public function test_schedule_rejects_a_period_from_another_program(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $programA = $this->tahfeez($mosque);
        $programB = Program::where('tenant_id', $mosque->id)->where('code', 'ijazah')->firstOrFail();

        $periodB = ProgramPeriod::create([
            'tenant_id' => $mosque->id,
            'program_id' => $programB->id,
            'name' => 'فترة الإجازة',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $programA->id,
                'program_period_id' => $periodB->id,
                'day_of_week' => 1,
            ])
            ->assertSessionHasErrors('program_period_id');
    }

    public function test_schedule_rejects_program_and_period_from_another_tenant(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $other = Tenant::factory()->create();
        config(['app.current_tenant_id' => $other->id]);
        app(ProgramService::class)->provisionTenantPrograms($other);
        $otherProgram = Program::where('tenant_id', $other->id)->where('code', 'tahfeez')->firstOrFail();
        $otherPeriod = ProgramPeriod::create([
            'tenant_id' => $other->id,
            'program_id' => $otherProgram->id,
            'name' => 'فترة خارجية',
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        config(['app.current_tenant_id' => $mosque->id]);

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque)->id,
                'program_id' => $otherProgram->id,
                'program_period_id' => $otherPeriod->id,
                'day_of_week' => 1,
            ])
            ->assertSessionHasErrors(['program_id']);
    }

    public function test_active_shift_filters_the_schedule_list(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first, $second] = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->get();

        $programA = $this->tahfeez($mosque);
        $programB = Program::where('tenant_id', $mosque->id)->where('code', 'ijazah')->firstOrFail();

        $classroom = $this->classroom($mosque);
        $teacherA = Teacher::factory()->create(['tenant_id' => $mosque->id, 'name' => 'أستاذ الدوام الأول', 'study_session_id' => $first->id]);
        $teacherB = Teacher::factory()->create(['tenant_id' => $mosque->id, 'name' => 'أستاذ الدوام الثاني', 'study_session_id' => $second->id]);

        Schedule::create([
            'tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'teacher_id' => $teacherA->id,
            'program_id' => $programA->id, 'study_session_id' => $first->id,
            'day_of_week' => 0, 'starts_at' => '06:00', 'ends_at' => '07:00',
        ]);

        Schedule::create([
            'tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'teacher_id' => $teacherB->id,
            'program_id' => $programB->id, 'study_session_id' => $second->id,
            'day_of_week' => 0, 'starts_at' => '08:00', 'ends_at' => '09:00',
        ]);

        session(['study_session_id' => $first->id]);

        $this->actingAs($manager)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('أستاذ الدوام الأول')
            ->assertDontSee('أستاذ الدوام الثاني');
    }

    public function test_teacher_schedule_shows_program_and_period(): void
    {
        [$mosque] = $this->mosqueWithPrograms();
        $program = $this->tahfeez($mosque);

        $period = ProgramPeriod::create([
            'tenant_id' => $mosque->id,
            'program_id' => $program->id,
            'name' => 'الفترة الأولى',
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        Schedule::create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $teacher->id,
            'program_id' => $program->id,
            'program_period_id' => $period->id,
            'day_of_week' => 2,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ]);

        $this->actingAs($user)
            ->get(route('teacher.schedule'))
            ->assertOk()
            ->assertSee('برنامج التحفيظ')
            ->assertSee('الفترة الأولى');
    }

    public function test_revoking_the_programs_permission_blocks_the_manager(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();

        $this->actingAs($manager)->get(route('admin.programs.index'))->assertOk();

        $role = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->firstOrFail();
        app(RoleService::class)->syncRolePermissions($role, ['students.view' => 'mosque']);

        $this->actingAs($manager)->get(route('admin.programs.index'))->assertForbidden();
    }

    public function test_teacher_cannot_open_program_management(): void
    {
        [$mosque] = $this->mosqueWithPrograms();

        $teacherUser = User::factory()->for($mosque)->create();

        $this->actingAs($teacherUser)->get(route('admin.programs.index'))->assertForbidden();
    }

    // ------------------------------------------------------- API

    public function test_admin_api_can_manage_programs_and_schedules_with_program_data(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/admin/programs', [
            'name' => 'برنامج الإتقان',
            'type' => 'quran',
            'periods' => [
                ['name' => 'الفترة الأولى', 'starts_at' => '06:00', 'ends_at' => '07:00', 'is_active' => 1],
            ],
            'attributes' => [
                ['name' => 'عدد الأجزاء', 'field_key' => 'weekly_juz', 'field_type' => 'number', 'value' => '10', 'is_active' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'برنامج الإتقان')
            ->assertJsonCount(1, 'data.periods')
            ->assertJsonCount(1, 'data.attributes');

        $program = Program::where('tenant_id', $mosque->id)->where('name', 'برنامج الإتقان')->firstOrFail();
        $period = $program->periods()->first();

        $this->getJson('/api/v1/admin/programs')
            ->assertOk()
            ->assertJsonFragment(['name' => 'برنامج الإتقان']);

        $this->postJson('/api/v1/admin/schedules', [
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque)->id,
            'program_id' => $program->id,
            'program_period_id' => $period->id,
            'day_of_week' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('data.program.name', 'برنامج الإتقان')
            ->assertJsonPath('data.program_period.name', 'الفترة الأولى');

        $this->patchJson("/api/v1/admin/programs/{$program->id}", [
            'name' => 'برنامج الإتقان المطور',
            'type' => 'quran',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'برنامج الإتقان المطور');

        $this->deleteJson("/api/v1/admin/programs/{$program->id}")
            ->assertStatus(422);
    }

    public function test_programs_are_isolated_between_mosques_in_the_api(): void
    {
        [, $manager] = $this->mosqueWithPrograms();

        $other = Tenant::factory()->create();
        config(['app.current_tenant_id' => $other->id]);
        app(ProgramService::class)->provisionTenantPrograms($other);
        $otherProgram = Program::where('tenant_id', $other->id)->where('code', 'tahfeez')->firstOrFail();

        config(['app.current_tenant_id' => null]);
        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/admin/programs/{$otherProgram->id}")->assertNotFound();

        $this->getJson('/api/v1/admin/programs')
            ->assertOk()
            ->assertJsonMissing(['id' => $otherProgram->id]);
    }

    // ------------------------------------------------------- dynamic options

    public function test_super_admin_inside_a_mosque_can_create_and_update_programs(): void
    {
        [$mosque] = $this->mosqueWithPrograms();

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);
        app(RoleService::class)->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)->withSession(['super_admin_mosque_id' => $mosque->id]);

        $this->get(route('admin.programs.create'))->assertOk();

        $this->post(route('admin.programs.store'), [
            'name' => 'برنامج مدير الجوامع',
            'type' => 'custom',
            'is_active' => 1,
        ])->assertRedirect(route('admin.programs.index'))->assertSessionHasNoErrors();

        $program = Program::withoutGlobalScope('tenant')
            ->where('tenant_id', $mosque->id)
            ->where('name', 'برنامج مدير الجوامع')
            ->firstOrFail();

        $this->assertSame($mosque->id, $program->tenant_id);

        $this->patch(route('admin.programs.update', $program), [
            'name' => 'برنامج مدير الجوامع المطور',
            'type' => 'custom',
            'is_active' => 1,
        ])->assertRedirect(route('admin.programs.index'))->assertSessionHasNoErrors();

        $this->assertSame('برنامج مدير الجوامع المطور', $program->fresh()->name);
    }

    public function test_student_sourced_attribute_options_are_resolved_with_filters(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        $classroom = $this->classroom($mosque);

        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'name' => 'أحمد الحافظ',
            'gender' => 'male',
            'status' => 'active',
            'birth_date' => now()->subYears(12)->toDateString(),
            'memorized_juz' => 30,
            'classroom_id' => $classroom->id,
        ]);

        // Excluded: too few juz.
        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'name' => 'سعيد المبتدئ',
            'gender' => 'male',
            'status' => 'active',
            'birth_date' => now()->subYears(12)->toDateString(),
            'memorized_juz' => 5,
        ]);

        // Excluded: wrong gender.
        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'name' => 'فاطمة الحافظة',
            'gender' => 'female',
            'status' => 'active',
            'birth_date' => now()->subYears(12)->toDateString(),
            'memorized_juz' => 30,
        ]);

        // Excluded: outside the age range.
        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'name' => 'خالد الكبير',
            'gender' => 'male',
            'status' => 'active',
            'birth_date' => now()->subYears(20)->toDateString(),
            'memorized_juz' => 30,
        ]);

        // Excluded: not active.
        Student::factory()->create([
            'tenant_id' => $mosque->id,
            'name' => 'معتزل',
            'gender' => 'male',
            'status' => 'inactive',
            'birth_date' => now()->subYears(12)->toDateString(),
            'memorized_juz' => 30,
        ]);

        // Excluded: another mosque.
        $otherMosque = Tenant::factory()->create();
        Student::factory()->create([
            'tenant_id' => $otherMosque->id,
            'name' => 'طالب جامع آخر',
            'gender' => 'male',
            'status' => 'active',
            'birth_date' => now()->subYears(12)->toDateString(),
            'memorized_juz' => 30,
        ]);

        $config = [
            'student_status' => 'active',
            'gender' => 'male',
            'age_from' => 10,
            'age_to' => 14,
            'juz_from' => 30,
            'label_mode' => 'name_age_juz',
        ];

        $expected = ['أحمد الحافظ — 12 سنة — 30 جزء'];

        $this->actingAs($manager)
            ->post(route('admin.programs.store'), [
                'name' => 'برنامج الحفظ المكثف',
                'type' => 'custom',
                'is_active' => 1,
                'attributes' => [[
                    'name' => 'الطلاب المرشحون',
                    'field_key' => 'candidates',
                    'field_type' => 'multiselect',
                    'options_source' => 'students',
                    'options_config' => $config,
                    'value' => $expected,
                    'is_active' => 1,
                ]],
            ])
            ->assertRedirect(route('admin.programs.index'))
            ->assertSessionHasNoErrors();

        $attribute = ProgramAttribute::withoutGlobalScope('tenant')
            ->where('tenant_id', $mosque->id)
            ->where('field_key', 'candidates')
            ->firstOrFail();

        $this->assertSame('students', $attribute->options_source->value);
        $this->assertNull($attribute->options);
        $this->assertEquals($config, $attribute->options_config);
        $this->assertSame($expected, app(ProgramService::class)->resolvedOptions($attribute));

        // A value outside the resolved student options is rejected.
        $this->actingAs($manager)
            ->post(route('admin.programs.store'), [
                'name' => 'برنامج مرفوض',
                'type' => 'custom',
                'attributes' => [[
                    'name' => 'الطلاب المرشحون',
                    'field_type' => 'multiselect',
                    'options_source' => 'students',
                    'options_config' => $config,
                    'value' => ['سعيد المبتدئ — 12 سنة — 5 جزء'],
                    'is_active' => 1,
                ]],
            ])
            ->assertSessionHasErrors('attributes.0.value');

        // The API exposes the resolved student options.
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/programs')
            ->assertOk()
            ->assertJsonFragment([
                'options_source' => 'students',
                'options' => $expected,
            ]);
    }
}
