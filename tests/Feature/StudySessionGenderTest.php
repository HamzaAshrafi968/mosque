<?php

namespace Tests\Feature;

use App\Models\StudySession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Tests\TestCase;

/**
 * تخصيص الدوام لجنس معين: التفرد على (الاسم + الجنس) بدل الاسم وحده.
 */
class StudySessionGenderTest extends TestCase
{
    /** @return array{0: Tenant, 1: User} */
    private function mosqueWithManager(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    public function test_same_name_can_exist_for_male_and_female_sessions(): void
    {
        [$mosque, $manager] = $this->mosqueWithManager();

        $this->actingAs($manager)
            ->post(route('admin.sessions.store'), ['name' => 'دوام أول', 'gender' => 'male'])
            ->assertRedirect(route('admin.sessions.index'));

        $this->actingAs($manager)
            ->post(route('admin.sessions.store'), ['name' => 'دوام أول', 'gender' => 'female'])
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertSame(2, StudySession::where('tenant_id', $mosque->id)->where('name', 'دوام أول')->count());
        $this->assertDatabaseHas('study_sessions', ['tenant_id' => $mosque->id, 'name' => 'دوام أول', 'gender' => 'male']);
        $this->assertDatabaseHas('study_sessions', ['tenant_id' => $mosque->id, 'name' => 'دوام أول', 'gender' => 'female']);
    }

    public function test_duplicate_name_with_same_gender_is_rejected(): void
    {
        [$mosque, $manager] = $this->mosqueWithManager();

        StudySession::create(['tenant_id' => $mosque->id, 'name' => 'دوام أول', 'gender' => 'male']);

        $this->actingAs($manager)
            ->from(route('admin.sessions.index'))
            ->post(route('admin.sessions.store'), ['name' => 'دوام أول', 'gender' => 'male'])
            ->assertSessionHasErrors('name');

        $this->assertSame(
            1,
            StudySession::where('tenant_id', $mosque->id)->where('name', 'دوام أول')->where('gender', 'male')->count()
        );
    }

    public function test_duplicate_name_without_gender_is_still_rejected(): void
    {
        [, $manager] = $this->mosqueWithManager();

        // الدوام الأول الافتراضي بلا جنس — إضافته مرة أخرى بلا جنس مرفوضة.
        $this->actingAs($manager)
            ->from(route('admin.sessions.index'))
            ->post(route('admin.sessions.store'), ['name' => 'الدوام الأول'])
            ->assertSessionHasErrors('name');
    }

    public function test_update_rejects_conflicting_gender_and_allows_gender_change(): void
    {
        [$mosque, $manager] = $this->mosqueWithManager();

        $male = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'دوام ثالث', 'gender' => 'male']);
        $female = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'دوام ثالث', 'gender' => 'female']);

        // تعديل دوام الإناث إلى نفس (الاسم + الجنس) للذكور مرفوض.
        $this->actingAs($manager)
            ->from(route('admin.sessions.index'))
            ->patch(route('admin.sessions.update', $female), ['name' => 'دوام ثالث', 'gender' => 'male'])
            ->assertSessionHasErrors('name');

        $this->assertSame('female', $female->fresh()->gender);

        // تغيير الجنس فقط إلى قيمة غير مستخدمة مقبول.
        $this->actingAs($manager)
            ->patch(route('admin.sessions.update', $female), ['name' => 'دوام ثالث', 'gender' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($female->fresh()->gender);
        $this->assertSame('male', $male->fresh()->gender);
    }

    public function test_display_name_and_labels_reflect_gender(): void
    {
        [$mosque] = $this->mosqueWithManager();

        $male = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'دوام أول', 'gender' => 'male']);
        $female = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'دوام أول', 'gender' => 'female']);
        $mixed = StudySession::where('tenant_id', $mosque->id)->where('name', 'الدوام الثاني')->firstOrFail();

        $this->assertSame('دوام أول (ذكور)', $male->display_name);
        $this->assertSame('دوام أول (إناث)', $female->display_name);
        $this->assertSame('الدوام الثاني', $mixed->display_name);
        $this->assertSame('ذكور', $male->genderLabel());
        $this->assertSame('إناث', $female->genderLabel());
        $this->assertSame('غير محدد', $mixed->genderLabel());
    }

    public function test_sessions_page_shows_gender_labels(): void
    {
        [$mosque, $manager] = $this->mosqueWithManager();

        StudySession::create(['tenant_id' => $mosque->id, 'name' => 'دوام أول', 'gender' => 'male']);
        StudySession::create(['tenant_id' => $mosque->id, 'name' => 'دوام أول', 'gender' => 'female']);

        $this->actingAs($manager)
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('دوام أول (ذكور)')
            ->assertSee('دوام أول (إناث)')
            ->assertSee('غير محدد (مختلط)');
    }

    public function test_default_sessions_have_no_gender(): void
    {
        [$mosque] = $this->mosqueWithManager();

        $this->assertSame(0, StudySession::where('tenant_id', $mosque->id)->whereNotNull('gender')->count());
    }
}
