<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomFieldsTest extends TestCase
{
    private function adminWithMosque(): array
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        return [$tenant, $admin];
    }

    public function test_admin_can_create_custom_field_definitions(): void
    {
        [$tenant] = $this->adminWithMosque();

        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'مهنة ولي الأمر',
            'field_key' => 'father_occupation',
            'field_type' => 'text',
            'required' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('custom_fields', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'student',
            'field_key' => 'father_occupation',
            'field_type' => 'text',
            'required' => 1,
        ]);

        // Duplicate key for the same entity type is rejected.
        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'مهنة أخرى',
            'field_key' => 'father_occupation',
            'field_type' => 'text',
        ])->assertStatus(422);

        // Same key is allowed for teachers (entity scope isolation).
        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'teacher',
            'name' => 'مهنة الأستاذ السابقة',
            'field_key' => 'father_occupation',
            'field_type' => 'select',
            'options' => ['ممرض', 'طبيب'],
        ])->assertCreated();
    }

    public function test_field_type_validation_enforced_on_student_values(): void
    {
        [$tenant] = $this->adminWithMosque();

        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'عدد الإخوة',
            'field_key' => 'siblings',
            'field_type' => 'number',
        ])->assertCreated();

        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'حالة التغذية',
            'field_key' => 'diet',
            'field_type' => 'select',
            'options' => ['vegetarian', 'regular'],
        ])->assertCreated();

        // Number field rejects non-numeric values.
        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب',
            'gender' => 'male',
            'custom_fields' => ['siblings' => 'abc'],
        ])->assertStatus(422);

        // Select field rejects out-of-options values.
        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب',
            'gender' => 'male',
            'custom_fields' => ['diet' => 'unknown'],
        ])->assertStatus(422);

        // Valid values pass and are persisted in canonical storage format.
        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب صحيح',
            'gender' => 'male',
            'custom_fields' => ['siblings' => '3', 'diet' => 'vegetarian'],
        ])->assertCreated();

        $student = Student::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertDatabaseHas('custom_field_values', [
            'tenant_id' => $tenant->id,
            'entity_id' => $student->id,
            'value' => '3',
        ]);
    }

    public function test_boolean_and_multiselect_are_normalised(): void
    {
        [, $admin] = $this->adminWithMosque();

        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'يستقل الباص',
            'field_key' => 'takes_bus',
            'field_type' => 'boolean',
        ])->assertCreated();

        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'مواد إضافية',
            'field_key' => 'extra_subjects',
            'field_type' => 'multiselect',
            'options' => ['خط', 'حفظ', 'فقه'],
        ])->assertCreated();

        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب متعدد',
            'gender' => 'female',
            'custom_fields' => [
                'takes_bus' => '1',
                'extra_subjects' => ['خط', 'حفظ'],
            ],
        ])->assertCreated();

        $student = Student::where('tenant_id', $admin->tenant_id)->firstOrFail();
        $this->assertDatabaseHas('custom_field_values', [
            'tenant_id' => $admin->tenant_id,
            'entity_id' => $student->id,
            'value' => '1',
        ]);
        $this->assertDatabaseHas('custom_field_values', [
            'tenant_id' => $admin->tenant_id,
            'entity_id' => $student->id,
            'value' => json_encode(['خط', 'حفظ']),
        ]);
    }

    public function test_teacher_custom_field_values_are_stored_and_isolated(): void
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'teacher',
            'name' => 'الشهادة الجامعية',
            'field_key' => 'degree',
            'field_type' => 'text',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/admin/teachers', [
            'name' => 'أستاذ متميز',
            'gender' => 'male',
            'custom_fields' => ['degree' => 'بكالوريوس قرآن'],
        ])->assertCreated();

        $teacher = $response->json('data.id');
        $this->assertDatabaseHas('custom_field_values', [
            'tenant_id' => $tenant->id,
            'entity_id' => $teacher,
            'value' => 'بكالوريوس قرآن',
        ]);

        // Teacher values never attach to the (uuid) ids of students.
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('custom_field_values', [
            'entity_id' => $student->id,
        ]);
    }

    public function test_required_custom_field_is_enforced(): void
    {
        $this->adminWithMosque();

        $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'حي السكن',
            'field_key' => 'district',
            'field_type' => 'text',
            'required' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/students', [
            'name' => 'بدون حي',
            'gender' => 'male',
        ])->assertStatus(422);

        $this->postJson('/api/v1/admin/students', [
            'name' => 'مع الحي',
            'gender' => 'male',
            'custom_fields' => ['district' => 'الروضة'],
        ])->assertCreated();
    }

    public function test_definitions_can_be_listed_updated_and_deleted(): void
    {
        $this->adminWithMosque();

        $field = $this->postJson('/api/v1/admin/custom-fields', [
            'entity_type' => 'student',
            'name' => 'حقل مؤقت',
            'field_key' => 'temp_field',
            'field_type' => 'textarea',
        ])->json('data.id');

        $this->getJson('/api/v1/admin/custom-fields?entity_type=student')
            ->assertOk()
            ->assertJsonFragment(['field_key' => 'temp_field']);

        $this->patchJson("/api/v1/admin/custom-fields/{$field}", [
            'name' => 'حقل محدث',
            'required' => true,
            'is_active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('custom_fields', [
            'id' => $field,
            'name' => 'حقل محدث',
            'is_active' => 0,
        ]);

        $this->deleteJson("/api/v1/admin/custom-fields/{$field}")->assertOk();
        $this->assertDatabaseMissing('custom_fields', ['id' => $field]);
    }
}
