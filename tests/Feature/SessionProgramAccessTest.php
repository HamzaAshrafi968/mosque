<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Program;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * تخصيص البرامج حسب الدوام: لكل دوام برامجه المتاحة (مثال: الأول للتحفيظ
 * والإجازة، والثاني للتسميع فقط)، ويُطبَّق التخصيص على النماذج والتحقق
 * والـ API. بلا تخصيص = كل البرامج متاحة.
 */
class SessionProgramAccessTest extends TestCase
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

    /** @return array{0: StudySession, 1: StudySession} */
    private function sessions(Tenant $mosque): array
    {
        [$first, $second] = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->get()->all();

        return [$first, $second];
    }

    private function program(Tenant $mosque, string $code): Program
    {
        return Program::where('tenant_id', $mosque->id)->where('code', $code)->firstOrFail();
    }

    private function classroom(Tenant $mosque): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
    }

    private function teacher(Tenant $mosque, ?string $sessionId = null): Teacher
    {
        return Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $sessionId,
        ]);
    }

    // ------------------------------------------------------ assignment

    public function test_manager_can_assign_programs_to_a_shift(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');
        $ijazah = $this->program($mosque, 'ijazah');

        $this->actingAs($manager)
            ->post(route('admin.sessions.programs', $first), [
                'programs' => [$tahfeez->id, $ijazah->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_study_session', [
            'study_session_id' => $first->id,
            'program_id' => $tahfeez->id,
        ]);
        $this->assertDatabaseHas('program_study_session', [
            'study_session_id' => $first->id,
            'program_id' => $ijazah->id,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('تخصيص البرامج المتاحة لهذا الدوام')
            ->assertSee($tahfeez->name);
    }

    public function test_clearing_the_selection_restores_all_programs(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');

        $first->programs()->attach($tahfeez->id);

        $this->actingAs($manager)
            ->post(route('admin.sessions.programs', $first), [])
            ->assertRedirect();

        $this->assertDatabaseCount('program_study_session', 0);
        $this->assertFalse(app(ProgramService::class)->sessionRestrictsPrograms($first->id));
    }

    public function test_program_from_another_mosque_is_rejected(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);

        $other = Tenant::factory()->create();
        config(['app.current_tenant_id' => $other->id]);
        app(ProgramService::class)->provisionTenantPrograms($other);
        $foreign = Program::where('tenant_id', $other->id)->firstOrFail();

        config(['app.current_tenant_id' => $mosque->id]);

        $this->actingAs($manager)
            ->post(route('admin.sessions.programs', $first), ['programs' => [$foreign->id]])
            ->assertSessionHasErrors('programs.0');

        $this->assertDatabaseCount('program_study_session', 0);
    }

    public function test_teacher_cannot_assign_programs_to_a_shift(): void
    {
        [$mosque] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');

        $teacherUser = User::factory()->for($mosque)->create();

        $this->actingAs($teacherUser)
            ->post(route('admin.sessions.programs', $first), ['programs' => [$tahfeez->id]])
            ->assertForbidden();
    }

    // ----------------------------------------------------- enforcement

    public function test_schedule_creation_rejects_a_program_not_available_in_the_shift(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');
        $ijazah = $this->program($mosque, 'ijazah');

        $first->programs()->attach($tahfeez->id);

        $classroom = $this->classroom($mosque);
        $teacher = $this->teacher($mosque, $first->id);

        $payload = [
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $first->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ];

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $payload + ['program_id' => $ijazah->id])
            ->assertSessionHasErrors('program_id');

        $this->assertDatabaseCount('schedules', 0);

        // البرنامج المسموح في هذا الدوام يمر.
        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), $payload + ['program_id' => $tahfeez->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_shift_without_links_allows_every_program(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $ijazah = $this->program($mosque, 'ijazah');

        $this->actingAs($manager)
            ->post(route('admin.schedules.store'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque, $first->id)->id,
                'program_id' => $ijazah->id,
                'study_session_id' => $first->id,
                'day_of_week' => 0,
                'starts_at' => '06:00',
                'ends_at' => '07:00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_weekly_generation_respects_the_shift_programs(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');
        $ijazah = $this->program($mosque, 'ijazah');

        $first->programs()->attach($tahfeez->id);

        $this->actingAs($manager)
            ->post(route('admin.schedules.generate'), [
                'classroom_id' => $this->classroom($mosque)->id,
                'teacher_id' => $this->teacher($mosque, $first->id)->id,
                'program_id' => $ijazah->id,
                'study_session_id' => $first->id,
                'days' => [0, 1],
                'starts_at' => '06:00',
                'ends_at' => '07:00',
            ])
            ->assertSessionHasErrors('program_id');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_schedule_page_exposes_the_shift_program_map(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');

        $first->programs()->attach($tahfeez->id);
        session(['study_session_id' => $first->id]);

        $this->actingAs($manager)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee(json_encode([$first->id => [$tahfeez->id]]), false);
    }

    public function test_programs_page_shows_the_shift_availability(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');

        $first->programs()->attach($tahfeez->id);

        $this->actingAs($manager)
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('الدوامات:')
            ->assertSee($first->name);
    }

    // ------------------------------------------------------------- API

    public function test_api_schedule_form_only_returns_programs_of_the_requested_shift(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');

        $first->programs()->attach($tahfeez->id);

        Sanctum::actingAs($manager);

        $scoped = $this->getJson('/api/v1/admin/schedules?study_session_id='.$first->id)->assertOk();
        $scopedCodes = collect($scoped->json('data.programs'))->pluck('code')->all();

        $this->assertContains('tahfeez', $scopedCodes);
        $this->assertNotContains('ijazah', $scopedCodes);

        $all = $this->getJson('/api/v1/admin/schedules')->assertOk();
        $allCodes = collect($all->json('data.programs'))->pluck('code')->all();

        $this->assertContains('ijazah', $allCodes);
    }

    public function test_api_rejects_a_program_not_available_in_the_shift(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');
        $ijazah = $this->program($mosque, 'ijazah');

        $first->programs()->attach($tahfeez->id);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/admin/schedules', [
            'classroom_id' => $this->classroom($mosque)->id,
            'teacher_id' => $this->teacher($mosque, $first->id)->id,
            'program_id' => $ijazah->id,
            'study_session_id' => $first->id,
            'day_of_week' => 0,
            'starts_at' => '06:00',
            'ends_at' => '07:00',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('program_id');
    }

    public function test_api_programs_endpoint_filters_by_shift(): void
    {
        [$mosque, $manager] = $this->mosqueWithPrograms();
        [$first] = $this->sessions($mosque);
        $tahfeez = $this->program($mosque, 'tahfeez');

        $first->programs()->attach($tahfeez->id);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/admin/programs?study_session_id='.$first->id)->assertOk();
        $codes = collect($response->json('data.programs'))->pluck('code')->all();

        $this->assertSame(['tahfeez'], $codes);
    }

    public function test_program_service_returns_all_programs_without_restrictions(): void
    {
        [$mosque] = $this->mosqueWithPrograms();
        $service = app(ProgramService::class);

        $this->assertFalse($service->sessionRestrictsPrograms(null));
        $this->assertCount(5, $service->availablePrograms(null));
        $this->assertTrue($service->programAllowedInSession(null, null));
    }
}
