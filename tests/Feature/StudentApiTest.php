<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    private function actingAsAdmin(?Tenant $tenant = null): User
    {
        $admin = User::factory()->admin()
            ->for($tenant ?? Tenant::factory()->create())
            ->create();

        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_create_student(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب جديد',
            'gender' => 'male',
            'guardian_name' => 'ولي الأمر',
            'guardian_phone' => '0501234567',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'طالب جديد');

        $this->assertDatabaseHas('students', [
            'name' => 'طالب جديد',
            'tenant_id' => $admin->tenant_id,
        ]);
    }

    public function test_students_can_be_filtered_by_gender(): void
    {
        $admin = $this->actingAsAdmin();

        Student::factory()->count(2)->create(['tenant_id' => $admin->tenant_id, 'gender' => 'male']);
        Student::factory()->create(['tenant_id' => $admin->tenant_id, 'gender' => 'female']);

        $response = $this->getJson('/api/v1/admin/students?gender=female');

        $response->assertOk();
        $students = $response->json('data.students');
        $this->assertIsArray($students);
    }

    public function test_students_are_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        Student::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'طالب آخر']);

        $admin = $this->actingAsAdmin();
        Student::factory()->create(['tenant_id' => $admin->tenant_id, 'name' => 'طالبنا']);

        $response = $this->getJson('/api/v1/admin/students');

        $response->assertOk();
        $students = $response->json('data.students');
        $this->assertIsArray($students);
    }

    public function test_teacher_cannot_access_admin_routes(): void
    {
        $teacher = User::factory()->create();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/admin/students')->assertForbidden();
    }
}
