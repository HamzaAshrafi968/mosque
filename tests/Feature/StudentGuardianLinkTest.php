<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class StudentGuardianLinkTest extends TestCase
{
    private function adminWithMosque(): User
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        return User::factory()->admin()->for($tenant)->create();
    }

    private function guardian(Tenant $tenant, string $name, string $phone): Guardian
    {
        return Guardian::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => $phone,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_link_existing_guardians_when_creating_student(): void
    {
        $admin = $this->adminWithMosque();
        $father = $this->guardian($admin->tenant, 'أب الطالب', '0501111111');
        $mother = $this->guardian($admin->tenant, 'أم الطالب', '0502222222');

        $this->actingAs($admin)
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('أب الطالب')
            ->assertSee('أم الطالب')
            ->assertDontSee('name="guardian_name"', false)
            ->assertDontSee('name="guardian_phone"', false);

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'name' => 'طالب جديد',
            'gender' => 'male',
            'guardian_ids' => [$father->id, $mother->id],
        ])->assertRedirect(route('admin.students.index'));

        $student = Student::where('name', 'طالب جديد')->firstOrFail();

        $this->assertDatabaseHas('parent_students', [
            'tenant_id' => $admin->tenant_id,
            'parent_id' => $father->id,
            'student_id' => $student->id,
            'relationship' => 'guardian',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('parent_students', [
            'tenant_id' => $admin->tenant_id,
            'parent_id' => $mother->id,
            'student_id' => $student->id,
            'relationship' => 'guardian',
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('أب الطالب')
            ->assertSee('أم الطالب');
    }

    public function test_guardian_link_is_optional_when_creating_student(): void
    {
        $admin = $this->adminWithMosque();

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'name' => 'طالب بدون ولي أمر',
            'gender' => 'female',
        ])->assertRedirect(route('admin.students.index'));

        $student = Student::where('name', 'طالب بدون ولي أمر')->firstOrFail();

        $this->assertSame(0, $student->guardianLinks()->count());
    }

    public function test_guardian_from_another_tenant_cannot_be_linked(): void
    {
        $admin = $this->adminWithMosque();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->guardian($otherTenant, 'ولي أمر خارجي', '0509999999');

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'name' => 'طالب مرفوض',
            'gender' => 'male',
            'guardian_ids' => [$foreign->id],
        ])->assertSessionHasErrors('guardian_ids.0');

        $this->assertDatabaseMissing('students', [
            'name' => 'طالب مرفوض',
            'tenant_id' => $admin->tenant_id,
        ]);
    }
}
