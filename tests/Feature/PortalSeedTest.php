<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Guardian;
use App\Models\ParentStudent;
use App\Models\Role;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo seeder must produce working portal accounts (parent + student),
 * guardian links and the explicit finance grant for the demo sheikh role.
 */
class PortalSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_portal_demo_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $mosque = Tenant::where('code', 'NUR')->firstOrFail();

        $guardianUser = User::where('email', 'parent@mosque.test')->firstOrFail();
        $this->assertSame('guardian', $guardianUser->role);
        $this->assertSame($mosque->id, $guardianUser->tenant_id);

        $guardian = Guardian::where('user_id', $guardianUser->id)->firstOrFail();
        $this->assertTrue($guardian->links()->exists());
        $this->assertTrue(ParentStudent::where('parent_id', $guardian->id)->count() >= 2);

        $studentUser = User::where('email', 'student@mosque.test')->firstOrFail();
        $this->assertSame('student', $studentUser->role);
        $this->assertSame($studentUser->id, Student::whereNotNull('user_id')->firstOrFail()->user_id);

        // Roles rows exist and are attached.
        $this->assertTrue($guardianUser->hasRole('guardian'));
        $this->assertTrue($studentUser->hasRole('student'));

        // Finance is explicitly granted to the demo teacher role (own scope).
        $teacherRole = Role::where('tenant_id', $mosque->id)->where('code', 'teacher')->firstOrFail();
        $financePerms = $teacherRole->permissions()->where('permissions.code', 'like', 'finance.%')->get();

        $this->assertTrue($financePerms->pluck('pivot.scope')->contains('own'));

        // Portal accounts can reach their dashboards (role strings + RBAC).
        $this->actingAs($guardianUser)->get(route('guardian.dashboard'))->assertOk();
        $this->actingAs($studentUser)->get(route('student.dashboard'))->assertOk();

        config(['app.current_tenant_id' => $mosque->id]);
        $this->assertSame(0, FinancialTransaction::count());
    }
}
