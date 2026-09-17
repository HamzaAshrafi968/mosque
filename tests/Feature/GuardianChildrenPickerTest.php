<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\ParentStudent;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The guardian form must show only the linked children; adding a child goes
 * through a search picker instead of rendering every student in the mosque.
 */
class GuardianChildrenPickerTest extends TestCase
{
    /** @return array{0: User, 1: Guardian, 2: Student, 3: Student, 4: Tenant} */
    private function fixture(): array
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        $admin = User::factory()->admin()->for($tenant)->create();
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الأول']);

        $child = Student::factory()->create([
            'tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'الابن المرتبط',
        ]);
        $other = Student::factory()->create([
            'tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'طالب غير مرتبط',
        ]);

        $guardian = Guardian::create([
            'tenant_id' => $tenant->id, 'name' => 'ولي الأمر', 'status' => 'active',
        ]);

        ParentStudent::create([
            'tenant_id' => $tenant->id,
            'parent_id' => $guardian->id,
            'student_id' => $child->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        return [$admin, $guardian, $child, $other, $tenant];
    }

    public function test_guardian_form_shows_only_linked_children_with_a_search_to_add(): void
    {
        [$admin, $guardian, $child, $other] = $this->fixture();

        $this->actingAs($admin)
            ->get(route('admin.parents.edit', $guardian))
            ->assertOk()
            ->assertSee('الابن المرتبط')
            ->assertDontSee('طالب غير مرتبط')
            ->assertSee('data-picker-input', false)
            ->assertSee(route('admin.students.search'), false)
            // the stored relationship stays selected instead of resetting to ولي أمر
            ->assertSee('value="father" selected', false);
    }

    public function test_student_search_returns_matches_and_excludes_linked_children(): void
    {
        [$admin, $guardian, $child, $other] = $this->fixture();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.students.search', ['q' => 'طالب', 'exclude' => [$child->id]]))
            ->assertOk();

        $ids = collect($response->json('results'))->pluck('id');

        $this->assertTrue($ids->contains($other->id));
        $this->assertFalse($ids->contains($child->id));
    }

    public function test_student_search_is_scoped_to_the_current_mosque(): void
    {
        [$admin] = $this->fixture();

        $otherTenant = Tenant::factory()->create();
        $foreign = Student::factory()->create([
            'tenant_id' => $otherTenant->id, 'name' => 'طالب خارجي',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.students.search', ['q' => 'طالب']))
            ->assertOk();

        $this->assertFalse(collect($response->json('results'))->pluck('id')->contains($foreign->id));
    }

    public function test_parent_update_reconciles_links_and_relationships(): void
    {
        [$admin, $guardian, $child, $other] = $this->fixture();

        $this->actingAs($admin)->patch(route('admin.parents.update', $guardian), [
            'name' => 'ولي الأمر',
            'student_ids' => [$child->id, $other->id],
            'relationships' => [$child->id => 'father', $other->id => 'other'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('parent_students', [
            'parent_id' => $guardian->id,
            'student_id' => $child->id,
            'relationship' => 'father',
        ]);

        $this->assertDatabaseHas('parent_students', [
            'parent_id' => $guardian->id,
            'student_id' => $other->id,
            'relationship' => 'other',
        ]);
    }

    public function test_parent_update_removes_unchecked_children(): void
    {
        [$admin, $guardian, $child, $other] = $this->fixture();

        $this->actingAs($admin)->patch(route('admin.parents.update', $guardian), [
            'name' => 'ولي الأمر',
            'student_ids' => [$other->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('parent_students', [
            'parent_id' => $guardian->id,
            'student_id' => $child->id,
        ]);

        $this->assertDatabaseHas('parent_students', [
            'parent_id' => $guardian->id,
            'student_id' => $other->id,
        ]);
    }
}
