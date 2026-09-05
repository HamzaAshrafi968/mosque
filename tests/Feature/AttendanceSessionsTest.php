<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttendanceMetricService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceSessionsTest extends TestCase
{
    private function teacherWithSection(): array
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الأول']);
        $section = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);

        $teacherUser = User::factory()->for($tenant)->create();
        $teacher = Teacher::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $teacherUser->id,
            'name' => 'أستاذ الصف',
        ]);

        SectionTeacher::create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'role' => 'lead',
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
        ]);

        SectionStudent::create([
            'tenant_id' => $tenant->id,
            'section_id' => $section->id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        Sanctum::actingAs($teacherUser);

        return [$tenant, $teacher, $section, $student];
    }

    public function test_teacher_marks_session_with_all_statuses_and_excused(): void
    {
        [, , $section, $student] = $this->teacherWithSection();
        $date = now()->toDateString();

        $this->postJson('/api/v1/teacher/attendance', [
            'date' => $date,
            'statuses' => [$student->id => 'excused'],
        ])->assertOk();

        $this->assertDatabaseHas('attendance_sessions', [
            'section_id' => $section->id,
            'date' => $date,
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => 'excused',
        ]);

        $session = AttendanceSession::where('section_id', $section->id)->whereDate('date', $date)->firstOrFail();
        $this->assertDatabaseCount('attendance_records', 1);

        // Re-saving the same day updates the record instead of duplicating.
        $this->postJson('/api/v1/teacher/attendance', [
            'date' => $date,
            'statuses' => [$student->id => 'present'],
        ])->assertOk();

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => 'present',
        ]);
        $this->assertSame(1, AttendanceSession::where('section_id', $section->id)->count());
    }

    public function test_session_can_be_marked_and_then_edited_by_admin(): void
    {
        [$tenant, $teacher, $section, $student] = $this->teacherWithSection();
        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        $date = now()->toDateString();

        // Admin records the whole section roster.
        $this->postJson('/api/v1/admin/attendance/students', [
            'date' => $date,
            'statuses' => [$student->id => 'late'],
            'notes' => [$student->id => 'وصل متأخراً'],
        ])->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => 'late',
            'note' => 'وصل متأخراً',
        ]);

        // The teacher cannot mark a section they were removed from.
        SectionTeacher::where('section_id', $section->id)->update(['status' => 'inactive']);
        Sanctum::actingAs(User::findOrFail($teacher->user_id));

        $this->postJson('/api/v1/teacher/attendance', [
            'date' => $date,
            'statuses' => [$student->id => 'absent'],
        ])->assertStatus(422);

        // Admin still can (mosque scope).
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/attendance/students', [
            'date' => $date,
            'statuses' => [$student->id => 'absent'],
        ])->assertOk();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => 'absent',
        ]);
    }

    public function test_percentage_formula_includes_late_and_excludes_excused(): void
    {
        [, , $section, $student] = $this->teacherWithSection();

        $statusesByDate = [
            'present' => 0,
            'late' => 1,
            'absent' => 1,
            'excused' => 1,
        ];

        $base = now();
        $i = 0;

        foreach ($statusesByDate as $status => $offset) {
            $date = $base->copy()->subDays($i)->toDateString();

            $session = AttendanceSession::create([
                'tenant_id' => $student->tenant_id,
                'section_id' => $section->id,
                'date' => $date,
                'status' => 'completed',
            ]);

            AttendanceRecord::create([
                'tenant_id' => $student->tenant_id,
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $status,
            ]);

            $i++;
        }

        $stats = app(AttendanceMetricService::class)->statsForStudent($student->id);

        // present + late = attended (2); absent qualifies (3 total); excused excluded.
        $this->assertSame(2, $stats['attended']);
        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['absent']);
        $this->assertSame(1, $stats['excused']);
        $this->assertSame(66.7, $stats['percentage']);

        // Roster stats expose the same numbers per student for the section.
        $roster = app(AttendanceMetricService::class)->rosterStats($section);
        $this->assertArrayHasKey($student->id, $roster);
        $this->assertSame(66.7, $roster[$student->id]['percentage']);
    }

    public function test_students_without_section_cannot_be_marked(): void
    {
        [$tenant, , $section] = $this->teacherWithSection();

        $unplaced = Student::factory()->create(['tenant_id' => $tenant->id]);
        $placed = Student::where('tenant_id', $tenant->id)->where('section_id', $section->id)->firstOrFail();

        $this->postJson('/api/v1/teacher/attendance', [
            'date' => now()->toDateString(),
            'statuses' => [$unplaced->id => 'present', $placed->id => 'absent'],
        ])->assertStatus(422);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_grid_reports_cells_and_percentages(): void
    {
        [, , $section, $student] = $this->teacherWithSection();

        foreach (['2026-08-01', '2026-08-03', '2026-08-05'] as $date) {
            $session = AttendanceSession::create([
                'tenant_id' => $student->tenant_id,
                'section_id' => $section->id,
                'date' => $date,
                'status' => 'completed',
            ]);

            AttendanceRecord::create([
                'tenant_id' => $student->tenant_id,
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $date === '2026-08-03' ? 'late' : 'present',
            ]);
        }

        $grid = app(AttendanceMetricService::class)->grid($section, '2026-08-01', '2026-08-05');

        $this->assertCount(3, $grid['sessions']);
        $this->assertCount(1, $grid['rows']);
        $row = $grid['rows'][0];
        $this->assertSame('late', $row['cells'][$grid['sessions'][1]->id]->value);
        $this->assertSame(100.0, $row['stats']['percentage']);
    }
}
