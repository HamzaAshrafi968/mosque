<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Per-user permission overrides edited by مدير الجوامع: direct grants/denials
 * win over the role grants and affect only the edited user.
 */
class UserPermissionOverrideTest extends TestCase
{
    /** The teacher default matrix requested for the role (spec §6). */
    private const TEACHER_MATRIX = [
        'students.view' => 'own',
        'teachers.view' => 'own',
        'classes.view' => 'own',
        'sections.view' => 'own',
        'subjects.view' => 'own',
        'schedule.view' => 'own', 'schedule.create' => 'own', 'schedule.update' => 'own',
        'attendance.view' => 'own', 'attendance.create' => 'own', 'attendance.update' => 'own',
        'exams.view' => 'own', 'exams.create' => 'own', 'exams.update' => 'own',
        'grades.view' => 'own', 'grades.create' => 'own', 'grades.update' => 'own', 'grades.submit' => 'own',
        'assignments.view' => 'own', 'assignments.create' => 'own', 'assignments.update' => 'own', 'assignments.delete' => 'own', 'assignments.grade' => 'own',
        'lessons.view' => 'own', 'lessons.create' => 'own', 'lessons.update' => 'own', 'lessons.delete' => 'own',
        'announcements.view' => 'mosque',
        'messages.view' => 'own', 'messages.create' => 'own',
        'finance.view' => 'own', 'finance.create' => 'own', 'finance.adjust' => 'own', 'finance.transfer' => 'own',
        'users.view' => 'own',
        'quran.tasmee.view' => 'own', 'quran.tasmee.create' => 'own', 'quran.tasmee.update' => 'own',
        'quran.completion.view' => 'own',
        'quran_review.view' => 'own', 'quran_review.create' => 'own',
        'quran_khamsa.view' => 'own', 'quran_khamsa.create' => 'own', 'quran_khamsa.update' => 'own', 'quran_khamsa.complete' => 'own',
        'quran.memorization.manage' => 'own',
        'reward_points.view' => 'own', 'reward_points.create' => 'own', 'reward_points.delete' => 'own',
        'qualifying.view' => 'own', 'qualifying.create' => 'own', 'qualifying.update' => 'own',
        'ijazah.view' => 'own', 'ijazah.create' => 'own', 'ijazah.update' => 'own',
        'hafiz_exams.view' => 'own', 'hafiz_exams.create' => 'own', 'hafiz_exams.update' => 'own', 'hafiz_exams.grade' => 'own',
        'hafiz_profile.view' => 'own',
        'faith_meetings.view' => 'own', 'faith_meetings.create' => 'own', 'faith_meetings.update' => 'own', 'faith_meetings.attendance' => 'own',
        'work_hours.view' => 'own',
        'sharia_courses.view' => 'own', 'sharia_courses.update' => 'own', 'sharia_courses.attendance' => 'own',
    ];

    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function superAdmin(): User
    {
        $roles = app(RoleService::class);
        $roles->ensureGlobalSuperAdminRole();

        config(['app.current_tenant_id' => null]);

        $user = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $roles->assignRole($user, RoleService::ROLE_SUPER_ADMIN);

        return $user;
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function setOverride(Tenant $mosque, User $target, string $code, string $value): void
    {
        $this->actingAs($this->superAdmin())
            ->patch(route('super-admin.mosques.users.permissions.update', [$mosque, $target]), [
                'permissions' => [$code => $value],
            ])
            ->assertRedirect();
    }

    public function test_teacher_default_matrix_matches_the_requested_spec(): void
    {
        $this->assertSame(self::TEACHER_MATRIX, PermissionCatalog::TEACHER);

        $mosque = $this->mosque();
        $role = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_TEACHER)->firstOrFail();

        $grants = $role->permissions()->pluck('permission_role.scope', 'permissions.code')->all();
        $expected = self::TEACHER_MATRIX;
        ksort($grants);
        ksort($expected);

        $this->assertSame($expected, $grants);
    }

    public function test_super_admin_can_deny_a_role_permission_for_a_single_user(): void
    {
        $mosque = $this->mosque();
        [$teacherA] = $this->teacher($mosque);
        [$teacherB] = $this->teacher($mosque);

        $this->actingAs($teacherA)->get(route('teacher.finance.index'))->assertOk();
        $this->actingAs($teacherB)->get(route('teacher.finance.index'))->assertOk();

        $this->setOverride($mosque, $teacherA, 'finance.view', 'deny');

        $this->actingAs($teacherA)->get(route('teacher.finance.index'))->assertForbidden();
        $this->actingAs($teacherB)->get(route('teacher.finance.index'))->assertOk();
    }

    public function test_super_admin_can_grant_an_extra_permission_to_a_single_user(): void
    {
        $mosque = $this->mosque();
        [$teacherA] = $this->teacher($mosque);
        [$teacherB] = $this->teacher($mosque);

        // Revoke the role default first: only the direct override can grant it.
        Role::where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_TEACHER)
            ->firstOrFail()
            ->permissions()
            ->detach();

        $this->actingAs($teacherA)->get(route('teacher.finance.index'))->assertForbidden();
        $this->actingAs($teacherB)->get(route('teacher.finance.index'))->assertForbidden();

        $this->setOverride($mosque, $teacherA, 'finance.view', 'own');

        $this->actingAs($teacherA)->get(route('teacher.finance.index'))->assertOk();
        $this->actingAs($teacherB)->get(route('teacher.finance.index'))->assertForbidden();
    }

    public function test_inherit_removes_the_override_and_restores_the_role(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $this->setOverride($mosque, $teacher, 'finance.view', 'deny');

        $this->actingAs($teacher)->get(route('teacher.finance.index'))->assertForbidden();
        $this->assertDatabaseCount('permission_user', 1);

        $this->setOverride($mosque, $teacher, 'finance.view', 'inherit');

        $this->actingAs($teacher)->get(route('teacher.finance.index'))->assertOk();
        $this->assertDatabaseCount('permission_user', 0);
    }

    public function test_override_applies_to_api_routes(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/teacher/homeworks')->assertOk();

        $this->setOverride($mosque, $teacher, 'assignments.view', 'deny');

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/teacher/homeworks')->assertForbidden();
    }

    public function test_sidebar_reflects_the_user_override(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee(route('teacher.quran-review.index'));

        $this->setOverride($mosque, $teacher, 'quran_review.view', 'deny');

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertDontSee(route('teacher.quran-review.index'));
    }

    public function test_permissions_matrix_page_lists_the_role_baseline(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $this->actingAs($this->superAdmin())
            ->get(route('super-admin.mosques.users.permissions', [$mosque, $teacher]))
            ->assertOk()
            ->assertSee('مصفوفة صلاحيات')
            ->assertSee('finance.view')
            ->assertSee('وراثة الدور');
    }

    public function test_global_scope_is_rejected_for_mosque_users(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $this->actingAs($this->superAdmin())
            ->patch(route('super-admin.mosques.users.permissions.update', [$mosque, $teacher]), [
                'permissions' => ['finance.view' => 'global'],
            ])
            ->assertSessionHasErrors('permissions.finance.view');

        $this->assertDatabaseCount('permission_user', 0);
    }

    public function test_super_admin_account_cannot_be_edited_through_a_mosque(): void
    {
        $mosque = $this->mosque();
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->get(route('super-admin.mosques.users.permissions', [$mosque, $superAdmin]))
            ->assertNotFound();
    }

    public function test_user_of_another_mosque_is_not_editable(): void
    {
        $mosqueA = $this->mosque();
        [$foreignTeacher] = $this->teacher($mosqueA);

        $mosqueB = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosqueB->id]);

        $this->actingAs($this->superAdmin())
            ->get(route('super-admin.mosques.users.permissions', [$mosqueB, $foreignTeacher]))
            ->assertNotFound();
    }

    // ------------------------------------------------ user form + matrix

    public function test_edit_user_page_contains_the_user_form_and_the_permission_matrix(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $this->actingAs($this->superAdmin())
            ->get(route('super-admin.mosques.users.edit', [$mosque, $teacher]))
            ->assertOk()
            ->assertSee('بيانات المستخدم')
            ->assertSee('مصفوفة الصلاحيات الفردية')
            ->assertSee('finance.view')
            ->assertSee('وراثة الدور')
            ->assertSee($teacher->name);
    }

    public function test_user_form_saves_profile_role_shift_and_permissions_together(): void
    {
        $mosque = $this->mosque();
        [$user, $teacherProfile] = $this->teacher($mosque);

        $session = StudySession::create(['tenant_id' => $mosque->id, 'name' => 'الدوام الأول']);

        $this->actingAs($this->superAdmin())
            ->patch(route('super-admin.mosques.users.update', [$mosque, $user]), [
                'name' => 'أستاذ محدث',
                'email' => 'updated-teacher@mosque.test',
                'role_code' => RoleService::ROLE_TEACHER,
                'gender' => 'male',
                'phone' => '07700000000',
                'specialty' => 'تحفيظ',
                'study_session_ids' => [$session->id],
                'permissions' => [
                    'finance.view' => 'deny',
                    'programs.view' => 'mosque',
                ],
            ])
            ->assertRedirect(route('super-admin.mosques.users.index', $mosque));

        config(['app.current_tenant_id' => $mosque->id]);

        $user->refresh();
        $this->assertSame('أستاذ محدث', $user->name);
        $this->assertSame('updated-teacher@mosque.test', $user->email);
        $this->assertTrue($user->hasRole(RoleService::ROLE_TEACHER));

        $teacherProfile->refresh();
        $this->assertSame('تحفيظ', $teacherProfile->specialty);
        $this->assertSame($session->id, $teacherProfile->study_session_id);
        $this->assertTrue($teacherProfile->studySessions()->whereKey($session->id)->exists());

        $this->assertDatabaseHas('permission_user', [
            'user_id' => $user->id,
            'effect' => 'deny',
        ]);

        // منع صريح يلغي صلاحية الدور لهذا المستخدم فقط.
        $this->actingAs($user)->get(route('teacher.finance.index'))->assertForbidden();
    }

    public function test_user_form_rejects_a_shift_from_another_mosque(): void
    {
        $mosque = $this->mosque();
        [$user] = $this->teacher($mosque);

        $other = Tenant::factory()->create();
        $foreignSession = StudySession::create(['tenant_id' => $other->id, 'name' => 'دوام آخر']);

        $this->actingAs($this->superAdmin())
            ->patch(route('super-admin.mosques.users.update', [$mosque, $user]), [
                'name' => $user->name,
                'email' => $user->email,
                'role_code' => RoleService::ROLE_TEACHER,
                'gender' => 'male',
                'study_session_ids' => [$foreignSession->id],
            ])
            ->assertSessionHasErrors('study_session_ids.0');
    }

    public function test_edit_page_is_protected_like_the_matrix_page(): void
    {
        $mosque = $this->mosque();
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->get(route('super-admin.mosques.users.edit', [$mosque, $superAdmin]))
            ->assertNotFound();
    }
}
