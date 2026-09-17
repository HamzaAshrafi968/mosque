<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\ParentStudent;
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

    public function test_create_page_offers_a_search_picker_instead_of_listing_all_guardians(): void
    {
        $admin = $this->adminWithMosque();
        $this->guardian($admin->tenant, 'أب الطالب', '0501111111');

        $this->actingAs($admin)
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('data-picker-input', false)
            ->assertSee(route('admin.parents.search'), false)
            ->assertDontSee('أب الطالب')
            ->assertDontSee('name="guardian_name"', false)
            ->assertDontSee('name="guardian_phone"', false);
    }

    public function test_guardian_search_returns_matching_guardians_from_the_mosque_only(): void
    {
        $admin = $this->adminWithMosque();
        $match = $this->guardian($admin->tenant, 'أب الطالب', '0501111111');
        $this->guardian($admin->tenant, 'ولي أمر آخر', '0503333333');

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->guardian($otherTenant, 'أب الطالب', '0509999999');

        $response = $this->actingAs($admin)
            ->getJson(route('admin.parents.search', ['q' => 'أب الطالب']))
            ->assertOk();

        $ids = collect($response->json('results'))->pluck('id');

        $this->assertTrue($ids->contains($match->id));
        $this->assertFalse($ids->contains($foreign->id));

        $this->actingAs($admin)
            ->getJson(route('admin.parents.search', ['q' => 'أب الطالب', 'exclude' => [$match->id]]))
            ->assertOk()
            ->assertJsonPath('results', []);
    }

    public function test_quick_store_creates_a_guardian_ready_for_selection(): void
    {
        $admin = $this->adminWithMosque();

        $this->actingAs($admin)
            ->postJson(route('admin.parents.quick-store'), ['name' => 'ولي أمر جديد', 'phone' => '0507777777'])
            ->assertCreated()
            ->assertJsonPath('result.name', 'ولي أمر جديد')
            ->assertJsonPath('result.meta', '0507777777');

        $this->assertDatabaseHas('parents', [
            'tenant_id' => $admin->tenant_id,
            'name' => 'ولي أمر جديد',
            'phone' => '0507777777',
        ]);
    }

    public function test_admin_can_link_existing_guardians_when_creating_student(): void
    {
        $admin = $this->adminWithMosque();
        $father = $this->guardian($admin->tenant, 'أب الطالب', '0501111111');
        $mother = $this->guardian($admin->tenant, 'أم الطالب', '0502222222');

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'name' => 'طالب جديد',
            'gender' => 'male',
            'guardian_ids_present' => '1',
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
            'guardian_ids_present' => '1',
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
            'guardian_ids_present' => '1',
            'guardian_ids' => [$foreign->id],
        ])->assertSessionHasErrors('guardian_ids.0');

        $this->assertDatabaseMissing('students', [
            'name' => 'طالب مرفوض',
            'tenant_id' => $admin->tenant_id,
        ]);
    }

    public function test_student_edit_page_manages_guardian_links(): void
    {
        $admin = $this->adminWithMosque();
        $father = $this->guardian($admin->tenant, 'أب الطالب', '0501111111');
        $mother = $this->guardian($admin->tenant, 'أم الطالب', '0502222222');

        $student = Student::factory()->create(['tenant_id' => $admin->tenant_id, 'name' => 'الطالب']);

        ParentStudent::create([
            'tenant_id' => $admin->tenant_id,
            'parent_id' => $father->id,
            'student_id' => $student->id,
            'relationship' => 'guardian',
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.edit', $student))
            ->assertOk()
            ->assertSee('data-picker-input', false)
            ->assertSee('أب الطالب')
            ->assertDontSee('name="guardian_name"', false);

        // Replace the father with the mother from the student form.
        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'guardian_ids_present' => '1',
            'guardian_ids' => [$mother->id],
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertDatabaseMissing('parent_students', [
            'parent_id' => $father->id,
            'student_id' => $student->id,
        ]);

        $this->assertDatabaseHas('parent_students', [
            'parent_id' => $mother->id,
            'student_id' => $student->id,
        ]);

        // An empty selection clears every link.
        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'guardian_ids_present' => '1',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertSame(0, $student->guardianLinks()->count());
    }

    public function test_update_without_the_picker_flag_keeps_existing_links(): void
    {
        $admin = $this->adminWithMosque();
        $father = $this->guardian($admin->tenant, 'أب الطالب', '0501111111');

        $student = Student::factory()->create(['tenant_id' => $admin->tenant_id, 'name' => 'الطالب']);

        ParentStudent::create([
            'tenant_id' => $admin->tenant_id,
            'parent_id' => $father->id,
            'student_id' => $student->id,
            'relationship' => 'guardian',
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب المعدل',
            'gender' => 'male',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertDatabaseHas('parent_students', [
            'parent_id' => $father->id,
            'student_id' => $student->id,
        ]);
    }
}
