<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePermission;
use App\Models\Classroom;
use App\Models\Homework;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * QA coverage for the "does granting/revoking a permission actually change
 * what a teacher can do?" question: web routes, API routes, the super-admin
 * permission matrix endpoint, own-scope ownership, pivot sync and the sidebar.
 */
class PermissionEnforcementTest extends TestCase
{
    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function syncRole(Tenant $mosque, string $code, array $grants): void
    {
        $role = Role::where('tenant_id', $mosque->id)->where('code', $code)->firstOrFail();

        app(RoleService::class)->syncRolePermissions($role, $grants);
    }

    private function grant(User $user, string $code, string $scope = 'own'): void
    {
        $role = $user->roles()->firstOrFail();
        $permission = Permission::where('code', $code)->firstOrFail();

        if (! $role->permissions()->where('permissions.code', $code)->exists()) {
            $role->permissions()->attach($permission->id, ['scope' => $scope]);
        }
    }

    public function test_revoking_a_teacher_permission_blocks_a_guarded_web_route(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $this->actingAs($teacher)
            ->get(route('teacher.quran.batches.index'))
            ->assertOk();

        $this->syncRole($mosque, RoleService::ROLE_TEACHER, ['attendance.create' => 'own']);

        $this->actingAs($teacher)
            ->get(route('teacher.quran.batches.index'))
            ->assertForbidden();
    }

    public function test_granting_a_teacher_permission_allows_a_previously_forbidden_web_route(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        // Start from a teacher role without any grant: the route is forbidden.
        $this->syncRole($mosque, RoleService::ROLE_TEACHER, []);

        $this->actingAs($teacher)
            ->get(route('teacher.finance.index'))
            ->assertForbidden();

        $this->grant($teacher, 'finance.view');

        $this->actingAs($teacher)
            ->get(route('teacher.finance.index'))
            ->assertOk();
    }

    public function test_permission_matrix_endpoint_grant_and_revoke_changes_teacher_access(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $roles = app(RoleService::class);
        $roles->ensureGlobalSuperAdminRole();

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => 'super_admin']);
        $roles->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        $role = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_TEACHER)->firstOrFail();

        // Grant finance.view through the real super-admin matrix endpoint.
        $this->actingAs($superAdmin)
            ->patch(route('super-admin.mosques.roles.update', [$mosque, $role]), [
                'name' => $role->name,
                'permissions' => ['finance.view' => 'own'],
            ])
            ->assertRedirect();

        $this->actingAs($teacher)
            ->get(route('teacher.finance.index'))
            ->assertOk();

        // Revoke every permission again.
        $this->actingAs($superAdmin)
            ->patch(route('super-admin.mosques.roles.update', [$mosque, $role]), [
                'name' => $role->name,
                'permissions' => [],
            ])
            ->assertRedirect();

        $this->actingAs($teacher)
            ->get(route('teacher.finance.index'))
            ->assertForbidden();
    }

    public function test_revoking_a_teacher_permission_blocks_the_api_route(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/teacher/homeworks')->assertOk();

        $this->syncRole($mosque, RoleService::ROLE_TEACHER, ['attendance.create' => 'own']);

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/teacher/homeworks')->assertForbidden();
    }

    public function test_own_scope_denies_a_subject_owned_by_another_teacher(): void
    {
        $mosque = $this->mosque();
        [$teacherUserA] = $this->teacher($mosque);
        [$teacherUserB, $teacherB] = $this->teacher($mosque);

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

        $route = new Route('GET', '/teacher/homeworks/{homework}/submissions', fn () => 'ok');

        $request = Request::create('/teacher/homeworks/'.$homework->id.'/submissions');
        $request->setRouteResolver(fn () => $route);

        $route->bind($request);
        $route->setParameter('homework', $homework);

        $middleware = app(EnsurePermission::class);

        // Teacher A holds assignments.grade (own) but does not own the homework.
        $request->setUserResolver(fn () => $teacherUserA);

        try {
            $middleware->handle($request, fn () => response('ok'), 'assignments.grade');
            $this->fail('Expected the middleware to deny access to another teacher\'s homework.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        // The owning teacher passes.
        $request->setUserResolver(fn () => $teacherUserB);
        $response = $middleware->handle($request, fn () => response('ok'), 'assignments.grade');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_admin_role_change_syncs_role_pivots(): void
    {
        $mosque = $this->mosque();
        $admin = User::factory()->admin()->for($mosque)->create();
        [$teacher] = $this->teacher($mosque);

        $this->assertTrue($teacher->roles()->where('roles.code', RoleService::ROLE_TEACHER)->exists());

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $teacher), [
                'name' => $teacher->name,
                'email' => $teacher->email,
                'role' => 'admin',
            ])
            ->assertRedirect();

        $teacher->refresh();

        $this->assertFalse($teacher->roles()->where('roles.code', RoleService::ROLE_TEACHER)->exists());
        $this->assertTrue($teacher->roles()->where('roles.code', RoleService::ROLE_MOSQUE_MANAGER)->exists());
    }

    public function test_admin_cannot_demote_the_last_mosque_manager(): void
    {
        $mosque = $this->mosque();
        $admin = User::factory()->admin()->for($mosque)->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'teacher',
            ])
            ->assertStatus(422);
    }

    public function test_sidebar_hides_and_shows_links_with_the_permission(): void
    {
        $mosque = $this->mosque();
        [$teacher] = $this->teacher($mosque);

        $this->syncRole($mosque, RoleService::ROLE_TEACHER, []);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertDontSee(route('teacher.finance.index'));

        $this->grant($teacher, 'finance.view');

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee(route('teacher.finance.index'));
    }
}
