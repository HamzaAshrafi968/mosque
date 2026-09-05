<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentTransferTest extends TestCase
{
    use RefreshDatabase;

    private function mosqueWithAdmin(): array
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        $classroomA = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الأول']);
        $classroomB = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الثاني']);
        $sectionA = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroomA->id, 'name' => 'أ']);
        $sectionB = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroomB->id, 'name' => 'أ']);

        return [$tenant, $admin, $classroomA, $classroomB, $sectionA, $sectionB];
    }

    public function test_student_can_be_enrolled_and_snapshot_stays_in_sync(): void
    {
        [, , , , $sectionA] = $this->mosqueWithAdmin();
        $student = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);

        $this->postJson("/api/v1/admin/sections/{$sectionA->id}/students", [
            'student_id' => $student->id,
        ])->assertCreated();

        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'section_id' => $sectionA->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'section_id' => $sectionA->id,
            'classroom_id' => $sectionA->classroom_id,
        ]);
    }

    public function test_duplicate_active_enrollment_is_rejected(): void
    {
        [, , , , $sectionA, $sectionB] = $this->mosqueWithAdmin();
        $student = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);

        $this->postJson("/api/v1/admin/sections/{$sectionA->id}/students", ['student_id' => $student->id])->assertCreated();

        // The student is already active in section A — direct enrollment into B is rejected.
        $this->postJson("/api/v1/admin/sections/{$sectionB->id}/students", ['student_id' => $student->id])
            ->assertStatus(422);

        $this->assertSame(
            1,
            SectionStudent::where('student_id', $student->id)->where('status', 'active')->count()
        );
    }

    public function test_transfer_preserves_membership_history(): void
    {
        [, , , , $sectionA, $sectionB] = $this->mosqueWithAdmin();
        $student = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);

        $this->postJson("/api/v1/admin/sections/{$sectionA->id}/students", ['student_id' => $student->id])->assertCreated();

        // Audit trails the enrollment (the row entity is the membership).
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $student->tenant_id,
            'entity_type' => 'section_student',
            'action' => 'student.enrolled',
        ]);

        $this->postJson("/api/v1/admin/students/{$student->id}/transfer", [
            'section_id' => $sectionB->id,
        ])->assertOk();

        // Old membership closed with status=transferred, new one active.
        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'section_id' => $sectionA->id,
            'status' => 'transferred',
        ]);
        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'section_id' => $sectionB->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, SectionStudent::where('student_id', $student->id)->where('status', 'active')->count());

        // Snapshot points to the new section and the audit trail has the move.
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'section_id' => $sectionB->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $student->tenant_id,
            'entity_type' => 'student',
            'action' => 'student.transferred',
        ]);
    }

    public function test_removing_student_closes_membership_and_clears_snapshot(): void
    {
        [, , , , $sectionA] = $this->mosqueWithAdmin();
        $student = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);

        $this->postJson("/api/v1/admin/sections/{$sectionA->id}/students", ['student_id' => $student->id])->assertCreated();

        $this->deleteJson("/api/v1/admin/sections/{$sectionA->id}/students/{$student->id}")->assertOk();

        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'section_id' => $sectionA->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'section_id' => null,
            'classroom_id' => null,
        ]);

        // Removing a student that is not in the section is rejected.
        $this->deleteJson("/api/v1/admin/sections/{$sectionA->id}/students/{$student->id}")
            ->assertStatus(422);
    }

    public function test_removed_student_can_be_enrolled_again(): void
    {
        [, , , , $sectionA, $sectionB] = $this->mosqueWithAdmin();
        $student = Student::factory()->create(['tenant_id' => config('app.current_tenant_id')]);

        $this->postJson("/api/v1/admin/sections/{$sectionA->id}/students", ['student_id' => $student->id])->assertCreated();
        $this->deleteJson("/api/v1/admin/sections/{$sectionA->id}/students/{$student->id}")->assertOk();

        $this->postJson("/api/v1/admin/sections/{$sectionB->id}/students", ['student_id' => $student->id])->assertCreated();

        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'section_id' => $sectionA->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'section_id' => $sectionB->id,
            'status' => 'active',
        ]);
    }

    public function test_creating_student_with_section_writes_membership(): void
    {
        $this->mosqueWithAdmin();

        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب مسجل مسبقاً',
            'gender' => 'male',
            'section_id' => Section::first()->id,
        ])->assertCreated();

        $student = Student::firstWhere('name', 'طالب مسجل مسبقاً');
        $this->assertNotNull($student);
        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }
}
