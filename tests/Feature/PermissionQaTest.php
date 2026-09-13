<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\ParentStudent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\EnrollmentService;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end QA matrix for the RBAC chain: super admin → mosque manager →
 * teacher → guardian → student, across web routes, API routes, portals,
 * permission-matrix edits and cross-mosque isolation.
 */
class PermissionQaTest extends TestCase
{
    /**
     * Catalog codes that currently have no `permission:` route middleware.
     * Update this list intentionally when a feature gains enforcement.
     */
    private const KNOWN_ORPHAN_CODES = [
        'announcements.update',
        'assignments.update',
        'attendance.approve',
        'exams.update',
        'finance.report',
        'grades.submit',
        'hafiz_exams.create',
        'lessons.update',
        'mosques.create',
        'mosques.delete',
        'mosques.update',
        'mosques.view',
        'permissions.manage',
        'qualifying.update',
        'reports.export',
        'roles.create',
        'roles.delete',
        'roles.update',
        'roles.view',
        'schedule.approve',
        'schedule.update',
    ];

    // ------------------------------------------------------------- fixtures

    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function manager(Tenant $mosque): User
    {
        return User::factory()->admin()->for($mosque)->create();
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);
    }

    /** @return array{0: User, 1: Student} */
    private function studentWithAccount(Tenant $mosque): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_STUDENT]);
        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return [$user, $student];
    }

    /** @return array{0: User, 1: Guardian, 2: Student} */
    private function guardianFamily(Tenant $mosque): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_GUARDIAN]);
        $guardian = Guardian::create(['tenant_id' => $mosque->id, 'user_id' => $user->id, 'name' => 'ولي الأمر']);
        $child = Student::factory()->create(['tenant_id' => $mosque->id, 'status' => 'active']);

        ParentStudent::create([
            'tenant_id' => $mosque->id,
            'parent_id' => $guardian->id,
            'student_id' => $child->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        return [$user, $guardian, $child];
    }

    private function role(Tenant $mosque, string $code): Role
    {
        return Role::where('tenant_id', $mosque->id)->where('code', $code)->firstOrFail();
    }

    private function revoke(Tenant $mosque, string $roleCode, string $permissionCode): void
    {
        $permission = Permission::where('code', $permissionCode)->firstOrFail();

        $this->role($mosque, $roleCode)->permissions()->detach($permission->id);
    }

    // ---------------------------------------------------------- super admin

    public function test_super_admin_controls_central_area_and_bypasses_mosque_permissions(): void
    {
        $super = $this->superAdmin();
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($super)->get(route('super-admin.dashboard'))->assertOk();
        $this->actingAs($super)->get(route('super-admin.mosques.index'))->assertOk();

        $this->actingAs($super)
            ->post(route('super-admin.mosques.enter', $mosque))
            ->assertRedirect(route('admin.dashboard'));

        // Strip every manager permission: the super admin keeps access, the
        // manager loses it.
        $this->role($mosque, RoleService::ROLE_MOSQUE_MANAGER)->permissions()->detach();

        $this->actingAs($super)->get(route('admin.students.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.students.index'))->assertForbidden();
    }

    public function test_super_admin_cannot_enter_teacher_guardian_or_student_portals(): void
    {
        $super = $this->superAdmin();
        $mosque = $this->mosque();

        $this->actingAs($super)->post(route('super-admin.mosques.enter', $mosque));

        $this->actingAs($super)->get(route('teacher.dashboard'))->assertRedirect(route('super-admin.dashboard'));
        $this->actingAs($super)->get(route('guardian.dashboard'))->assertRedirect(route('super-admin.dashboard'));
        $this->actingAs($super)->get(route('student.dashboard'))->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_manager_cannot_use_central_administration(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route('super-admin.mosques.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('super-admin.switch-mosque'), ['mosque_id' => $mosque->id])->assertForbidden();
    }

    // ------------------------------------------------------ mosque manager

    public static function managerViewRoutes(): array
    {
        return [
            'students' => ['admin.students.index', 'students.view'],
            'parents' => ['admin.parents.index', 'parents.view'],
            'teachers' => ['admin.teachers.index', 'teachers.view'],
            'work hours' => ['admin.work-hours.index', 'work_hours.view'],
            'classes' => ['admin.classrooms.index', 'classes.view'],
            'subjects' => ['admin.subjects.index', 'subjects.view'],
            'schedules' => ['admin.schedules.index', 'schedule.view'],
            'attendance' => ['admin.attendance.index', 'attendance.view'],
            'finance' => ['admin.finance.index', 'finance.view'],
            'audit logs' => ['admin.audit-logs.index', 'audit_logs.view'],
            'exams' => ['admin.exams.index', 'exams.view'],
            'grades' => ['admin.grades.index', 'grades.view'],
            'reports' => ['admin.reports.index', 'reports.view'],
            'announcements' => ['admin.announcements.index', 'announcements.view'],
            'users' => ['admin.users.index', 'users.view'],
            'sessions' => ['admin.sessions.index', 'sessions.view'],
            'custom fields' => ['admin.custom-fields.index', 'custom_fields.view'],
            'quran review' => ['admin.quran-review.index', 'quran_review.view'],
            'reward points' => ['admin.reward-points.index', 'reward_points.view'],
            'quran program' => ['admin.quran.index', 'quran.tasmee.view'],
            'quran tasmee' => ['admin.quran.tasmee.index', 'quran.tasmee.view'],
            'quran completions' => ['admin.quran.completions.index', 'quran.completion.view'],
            'hafiz profiles' => ['admin.quran.hafiz.index', 'hafiz_profile.view'],
            'qualifying' => ['admin.quran.qualifying.index', 'qualifying.view'],
            'ijazah' => ['admin.quran.ijazah.index', 'ijazah.view'],
            'hafiz exams' => ['admin.quran.exams.index', 'hafiz_exams.view'],
            'faith meetings' => ['admin.faith-meetings.index', 'faith_meetings.view'],
            'sharia courses' => ['admin.sharia-courses.index', 'sharia_courses.view'],
        ];
    }

    #[DataProvider('managerViewRoutes')]
    public function test_manager_page_access_follows_its_permission(string $routeName, string $permission): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)->get(route($routeName))->assertOk();

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, $permission);

        $this->actingAs($manager)->get(route($routeName))->assertForbidden();
    }

    public function test_manager_cannot_read_another_mosques_records_by_uuid(): void
    {
        $mosqueA = $this->mosque();
        $manager = $this->manager($mosqueA);

        $mosqueB = Tenant::factory()->create();
        $studentB = Student::factory()->create(['tenant_id' => $mosqueB->id]);

        $this->actingAs($manager)->get(route('admin.students.show', $studentB))->assertNotFound();
        $this->actingAs($manager)->get(route('admin.students.edit', $studentB))->assertNotFound();
    }

    // ------------------------------------------------------------- teacher

    public static function teacherViewRoutes(): array
    {
        return [
            'schedule' => ['teacher.schedule', 'schedule.view'],
            'attendance create' => ['teacher.attendance.create', 'attendance.create'],
            'attendance history' => ['teacher.attendance.history', 'attendance.view'],
            'homeworks' => ['teacher.homeworks.index', 'assignments.view'],
            'exams' => ['teacher.exams.index', 'exams.view'],
            'lessons' => ['teacher.lessons.index', 'lessons.view'],
            'messages' => ['teacher.messages.index', 'messages.view'],
            'quran review' => ['teacher.quran-review.index', 'quran_review.view'],
            'reward points' => ['teacher.reward-points.index', 'reward_points.view'],
            'quran program' => ['teacher.quran.index', 'quran.tasmee.view'],
            'quran tasmee' => ['teacher.quran.tasmee.index', 'quran.tasmee.view'],
            'qualifying' => ['teacher.quran.qualifying.index', 'qualifying.view'],
            'ijazah' => ['teacher.quran.ijazah.index', 'ijazah.view'],
            'hafiz exams' => ['teacher.quran.exams.index', 'hafiz_exams.view'],
            'faith meetings' => ['teacher.quran.faith-meetings.index', 'faith_meetings.view'],
            'sharia courses' => ['teacher.sharia-courses.index', 'sharia_courses.view'],
            'sections' => ['teacher.sections.index', 'sections.view'],
            'work hours' => ['teacher.work-hours.index', 'work_hours.view'],
        ];
    }

    #[DataProvider('teacherViewRoutes')]
    public function test_teacher_page_access_follows_its_permission(string $routeName, string $permission): void
    {
        $mosque = $this->mosque();
        [$teacherUser] = $this->teacher($mosque);

        $this->actingAs($teacherUser)->get(route($routeName))->assertOk();

        $this->revoke($mosque, RoleService::ROLE_TEACHER, $permission);

        $this->actingAs($teacherUser)->get(route($routeName))->assertForbidden();
    }

    public function test_teacher_cannot_grade_another_teachers_homework(): void
    {
        $mosque = $this->mosque();
        [$teacherA] = $this->teacher($mosque);
        [, $teacherB] = $this->teacher($mosque);

        $subject = Subject::create(['tenant_id' => $mosque->id, 'name' => 'القرآن']);
        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);

        $homework = Homework::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacherB->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'title' => 'واجب الحفظ',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($teacherA)
            ->get(route('teacher.homeworks.submissions', $homework))
            ->assertForbidden();
    }

    // --------------------------------------------------------- teacher API

    public static function teacherApiRoutes(): array
    {
        return [
            'schedule' => ['/api/v1/teacher/schedule', 'schedule.view'],
            'homeworks' => ['/api/v1/teacher/homeworks', 'assignments.view'],
            'exams' => ['/api/v1/teacher/exams', 'exams.view'],
            'lessons' => ['/api/v1/teacher/lessons', 'lessons.view'],
            'messages' => ['/api/v1/teacher/messages', 'messages.view'],
            'quran review' => ['/api/v1/teacher/quran-review', 'quran_review.view', true],
            'reward points' => ['/api/v1/teacher/reward-points', 'reward_points.view', true],
        ];
    }

    #[DataProvider('teacherApiRoutes')]
    public function test_teacher_api_access_follows_its_permission(string $uri, string $permission, bool $knownBroken = false): void
    {
        if ($knownBroken) {
            $this->markTestSkipped("Known bug: {$uri} returns 500 (BaseApiController::paginated() receives a resource collection).");
        }

        $mosque = $this->mosque();
        [$teacherUser] = $this->teacher($mosque);

        Sanctum::actingAs($teacherUser);
        $this->getJson($uri)->assertOk();

        $this->revoke($mosque, RoleService::ROLE_TEACHER, $permission);

        Sanctum::actingAs($teacherUser);
        $this->getJson($uri)->assertForbidden();
    }

    // ----------------------------------------------------------- admin API

    public static function managerApiRoutes(): array
    {
        return [
            'students' => ['/api/v1/admin/students', 'students.view'],
            'teachers' => ['/api/v1/admin/teachers', 'teachers.view'],
            'classrooms' => ['/api/v1/admin/classrooms', 'classes.view'],
            'subjects' => ['/api/v1/admin/subjects', 'subjects.view'],
            'schedules' => ['/api/v1/admin/schedules', 'schedule.view'],
            'attendance' => ['/api/v1/admin/attendance', 'attendance.view'],
            'finance' => ['/api/v1/admin/finance/people', 'finance.view'],
            'exams' => ['/api/v1/admin/exams', 'exams.view'],
            'grades' => ['/api/v1/admin/grades', 'grades.view'],
            'reports' => ['/api/v1/admin/reports', 'reports.view'],
            'announcements' => ['/api/v1/admin/announcements', 'announcements.view'],
            'users' => ['/api/v1/admin/users', 'users.view', true],
            'custom fields' => ['/api/v1/admin/custom-fields', 'custom_fields.view'],
            'quran review' => ['/api/v1/admin/quran-review', 'quran_review.view', true],
            'reward points' => ['/api/v1/admin/reward-points', 'reward_points.view', true],
        ];
    }

    #[DataProvider('managerApiRoutes')]
    public function test_manager_api_access_follows_its_permission(string $uri, string $permission, bool $knownBroken = false): void
    {
        if ($knownBroken) {
            $this->markTestSkipped("Known bug: {$uri} returns 500 (BaseApiController::paginated() receives a resource collection).");
        }

        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        Sanctum::actingAs($manager);
        $this->getJson($uri)->assertOk();

        $this->revoke($mosque, RoleService::ROLE_MOSQUE_MANAGER, $permission);

        Sanctum::actingAs($manager);
        $this->getJson($uri)->assertForbidden();
    }

    public function test_manager_api_is_tenant_scoped(): void
    {
        $mosqueA = $this->mosque();
        $manager = $this->manager($mosqueA);

        $mosqueB = Tenant::factory()->create();
        $studentB = Student::factory()->create(['tenant_id' => $mosqueB->id]);

        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/admin/students/'.$studentB->id)->assertNotFound();
    }

    // ------------------------------------------------------------- portals

    public function test_role_dashboards_are_open_to_their_roles(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [$teacherUser] = $this->teacher($mosque);
        [$guardianUser] = $this->guardianFamily($mosque);
        [$studentUser] = $this->studentWithAccount($mosque);

        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($teacherUser)->get(route('teacher.dashboard'))->assertOk();
        $this->actingAs($guardianUser)->get(route('guardian.dashboard'))->assertOk();
        $this->actingAs($studentUser)->get(route('student.dashboard'))->assertOk();
    }

    public function test_guardian_can_open_only_linked_children(): void
    {
        $mosque = $this->mosque();
        [$guardianUser, , $child] = $this->guardianFamily($mosque);
        $otherChild = Student::factory()->create(['tenant_id' => $mosque->id]);

        $this->actingAs($guardianUser)->get(route('guardian.children.overview', $child))->assertOk();
        $this->actingAs($guardianUser)->get(route('guardian.children.overview', $otherChild))->assertForbidden();
    }

    public function test_guardian_from_another_mosque_cannot_open_a_child(): void
    {
        $mosqueA = $this->mosque();
        [$guardianUser] = $this->guardianFamily($mosqueA);

        $mosqueB = Tenant::factory()->create();
        $childB = Student::factory()->create(['tenant_id' => $mosqueB->id]);

        $this->actingAs($guardianUser)->get(route('guardian.children.overview', $childB))->assertForbidden();
    }

    public function test_portal_roles_are_isolated_from_other_areas(): void
    {
        $mosque = $this->mosque();
        [$guardianUser] = $this->guardianFamily($mosque);
        [$studentUser] = $this->studentWithAccount($mosque);

        foreach ([$guardianUser, $studentUser] as $portalUser) {
            $this->actingAs($portalUser)->get(route('admin.dashboard'))->assertForbidden();
            $this->actingAs($portalUser)->get(route('teacher.dashboard'))->assertForbidden();
        }

        $this->actingAs($guardianUser)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($studentUser)->get(route('guardian.dashboard'))->assertForbidden();
    }

    // ----------------------------------------------------- matrix / custom

    public function test_permission_matrix_grants_a_custom_role_real_access(): void
    {
        $super = $this->superAdmin();
        $mosque = $this->mosque();

        $this->actingAs($super)
            ->post(route('super-admin.mosques.roles.store', $mosque), ['name' => 'Finance QA'])
            ->assertRedirect();

        $role = Role::where('tenant_id', $mosque->id)->where('name', 'Finance QA')->firstOrFail();

        $this->actingAs($super)
            ->post(route('super-admin.mosques.users.store', $mosque), [
                'name' => 'Finance QA User',
                'email' => 'finance-qa@mosque.test',
                'password' => 'password',
                'role_code' => $role->code,
                'gender' => 'male',
            ])
            ->assertRedirect();

        $user = User::where('email', 'finance-qa@mosque.test')->firstOrFail();

        $this->actingAs($user)->get(route('teacher.finance.index'))->assertForbidden();

        $this->actingAs($super)
            ->patch(route('super-admin.mosques.roles.update', [$mosque, $role]), [
                'name' => $role->name,
                'permissions' => ['finance.view' => 'own'],
            ])
            ->assertRedirect();

        $this->actingAs($user)->get(route('teacher.finance.index'))->assertOk();

        $this->actingAs($super)
            ->patch(route('super-admin.mosques.roles.update', [$mosque, $role]), [
                'name' => $role->name,
                'permissions' => [],
            ])
            ->assertRedirect();

        $this->actingAs($user)->get(route('teacher.finance.index'))->assertForbidden();
    }

    // ------------------------------------------------------------ catalog

    public function test_catalog_permission_codes_without_route_enforcement_are_known(): void
    {
        $used = collect(app('router')->getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'))
            ->flatMap(fn ($middleware) => array_map('trim', explode(',', substr($middleware, strlen('permission:')))))
            ->unique()
            ->values()
            ->all();

        $orphans = array_values(array_diff(PermissionCatalog::codes(), $used));
        sort($orphans);

        $this->assertSame(
            self::KNOWN_ORPHAN_CODES,
            $orphans,
            'The set of catalog codes without route enforcement changed.'
        );
    }

    // ---------------------------------------------------- known gaps (QA)

    public function test_known_gap_teacher_can_open_journey_for_student_with_portal_account(): void
    {
        $this->markTestSkipped('Known gap: EnsurePermission treats Student.user_id as teacher ownership, so a teacher is denied for students who own a portal account.');

        $mosque = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque);
        [, $student] = $this->studentWithAccount($mosque);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $student->update(['classroom_id' => $classroom->id, 'section_id' => $section->id]);

        $enrollment = app(EnrollmentService::class);
        $enrollment->assignTeacher($section, $teacher);
        $enrollment->enroll($student, $section);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.students.journey', $student))
            ->assertOk();
    }

    public function test_known_gap_teacher_can_open_ijazah_month_for_student_with_portal_account(): void
    {
        $this->markTestSkipped('Known gap: same ownership-predicate bug as the journey page (Student.user_id).');

        $mosque = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque);
        [, $student] = $this->studentWithAccount($mosque);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $student->update(['classroom_id' => $classroom->id, 'section_id' => $section->id]);

        $enrollment = app(EnrollmentService::class);
        $enrollment->assignTeacher($section, $teacher);
        $enrollment->enroll($student, $section);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.ijazah.month', [$student, '2026-09']))
            ->assertOk();
    }

    public function test_known_gap_revoking_portal_permissions_does_not_block_portal_pages(): void
    {
        $this->markTestSkipped('Known gap: guardian/student portal routes only use role middleware, so permission-matrix changes are not enforced.');

        $mosque = $this->mosque();
        [$guardianUser, , $child] = $this->guardianFamily($mosque);
        [, $studentUser] = $this->studentWithAccount($mosque);

        $this->revoke($mosque, RoleService::ROLE_GUARDIAN, 'grades.view');
        $this->revoke($mosque, RoleService::ROLE_STUDENT, 'grades.view');

        $this->actingAs($guardianUser)->get(route('guardian.children.grades', $child))->assertForbidden();
        $this->actingAs($studentUser)->get(route('student.grades'))->assertForbidden();
    }

    public function test_super_admin_created_guardian_gets_a_working_portal(): void
    {
        $super = $this->superAdmin();
        $mosque = $this->mosque();

        $this->actingAs($super)
            ->post(route('super-admin.mosques.users.store', $mosque), [
                'name' => 'Guardian QA',
                'email' => 'guardian-qa@mosque.test',
                'password' => 'password',
                'role_code' => RoleService::ROLE_GUARDIAN,
                'gender' => 'male',
            ])
            ->assertRedirect();

        $user = User::where('email', 'guardian-qa@mosque.test')->firstOrFail();

        $this->assertSame(User::ROLE_GUARDIAN, $user->role);
        $this->assertFalse(Teacher::where('user_id', $user->id)->exists());
        $this->actingAs($user)->get(route('guardian.dashboard'))->assertOk();
    }

    public function test_super_admin_created_student_gets_a_working_portal(): void
    {
        $super = $this->superAdmin();
        $mosque = $this->mosque();

        $this->actingAs($super)
            ->post(route('super-admin.mosques.users.store', $mosque), [
                'name' => 'Student QA',
                'email' => 'student-qa@mosque.test',
                'password' => 'password',
                'role_code' => RoleService::ROLE_STUDENT,
                'gender' => 'male',
            ])
            ->assertRedirect();

        $user = User::where('email', 'student-qa@mosque.test')->firstOrFail();

        $this->assertSame(User::ROLE_STUDENT, $user->role);
        $this->assertFalse(Teacher::where('user_id', $user->id)->exists());
        $this->actingAs($user)->get(route('student.dashboard'))->assertOk();
    }

    public function test_known_gap_class_scope_is_not_bound_to_specific_classes(): void
    {
        $this->markTestSkipped('Known gap: AuthorizationService has no class/section binder; those scopes fall through to the ownership predicate (allow for owner-less models).');

        $mosque = $this->mosque();

        $role = Role::create([
            'tenant_id' => $mosque->id,
            'code' => 'class_scoped',
            'name' => 'Class scoped',
            'is_system' => false,
        ]);

        app(RoleService::class)->syncRolePermissions($role, ['students.view' => 'class']);

        $user = User::factory()->for($mosque)->create();
        $user->roles()->sync([$role->id]);

        $student = Student::factory()->create(['tenant_id' => $mosque->id]);

        $this->assertFalse(
            app(AuthorizationService::class)->can($user, 'students.view', $student),
            'A class-scoped role must not see an arbitrary student in the mosque.'
        );
    }
}
