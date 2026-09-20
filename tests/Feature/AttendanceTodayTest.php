<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardService;
use Tests\TestCase;

class AttendanceTodayTest extends TestCase
{
    /** @return array{Tenant, Classroom, Section, Section, User} */
    private function tenantWithClassroom(): array
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الأول']);
        $sectionA = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $sectionB = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'ب']);

        $admin = User::factory()->admin()->for($tenant)->create();

        return [$tenant, $classroom, $sectionA, $sectionB, $admin];
    }

    private function student(Tenant $tenant, Classroom $classroom, Section $section): Student
    {
        return Student::factory()->create([
            'tenant_id' => $tenant->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
        ]);
    }

    private function mark(Student $student, Section $section, string $status, string $date): void
    {
        $session = AttendanceSession::firstOrCreate(
            [
                'tenant_id' => $student->tenant_id,
                'section_id' => $section->id,
                'date' => $date,
            ],
            ['status' => 'completed'],
        );

        AttendanceRecord::create([
            'tenant_id' => $student->tenant_id,
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }

    public function test_dashboard_rate_is_measured_against_all_mosque_students(): void
    {
        [$tenant, $classroom, $sectionA] = $this->tenantWithClassroom();

        $students = collect(range(1, 5))
            ->map(fn () => $this->student($tenant, $classroom, $sectionA));

        $today = now()->toDateString();

        $this->mark($students[0], $sectionA, 'present', $today);
        $this->mark($students[1], $sectionA, 'late', $today);
        $this->mark($students[2], $sectionA, 'absent', $today);

        $stats = app(DashboardService::class)->stats($tenant->id);

        $this->assertSame(1, $stats['attendance_present_today']);
        $this->assertSame(1, $stats['attendance_late_today']);
        $this->assertSame(1, $stats['attendance_absent_today']);
        $this->assertSame(5, $stats['students_count']);

        // (present + late) / all active students = 2/5.
        $this->assertSame(40.0, $stats['attendance_rate_today']);
    }

    public function test_today_page_shows_class_and_section_student_counts(): void
    {
        [$tenant, $classroom, $sectionA, $sectionB, $admin] = $this->tenantWithClassroom();

        collect(range(1, 18))->each(fn () => $this->student($tenant, $classroom, $sectionA));
        collect(range(1, 22))->each(fn () => $this->student($tenant, $classroom, $sectionB));

        $this->actingAs($admin)
            ->get(route('admin.attendance.today', ['status' => 'all']))
            ->assertOk()
            ->assertSee('الصف الأول')
            ->assertSee('الشعبة أ')
            ->assertSee('الشعبة ب')
            ->assertSee('40 طالب')
            ->assertSee('18 طالب')
            ->assertSee('22 طالب')
            ->assertSee('إجمالي طلاب الجامع: 40');
    }

    public function test_today_page_lists_students_with_their_status(): void
    {
        [$tenant, $classroom, $sectionA, $sectionB, $admin] = $this->tenantWithClassroom();

        $present = $this->student($tenant, $classroom, $sectionA);
        $late = $this->student($tenant, $classroom, $sectionA);
        $absent = $this->student($tenant, $classroom, $sectionB);
        $unrecorded = $this->student($tenant, $classroom, $sectionB);

        $today = now()->toDateString();

        $this->mark($present, $sectionA, 'present', $today);
        $this->mark($late, $sectionA, 'late', $today);
        $this->mark($absent, $sectionB, 'absent', $today);

        // Default tab: only those who attended (present + late).
        $this->actingAs($admin)
            ->get(route('admin.attendance.today'))
            ->assertOk()
            ->assertSee($present->name)
            ->assertSee($late->name)
            ->assertDontSee($absent->name)
            ->assertDontSee($unrecorded->name);

        // The absent filter shows only the absent student.
        $this->actingAs($admin)
            ->get(route('admin.attendance.today', ['status' => 'absent']))
            ->assertOk()
            ->assertSee($absent->name)
            ->assertDontSee($present->name);

        // "all" brings every student, including those with no record yet.
        $this->actingAs($admin)
            ->get(route('admin.attendance.today', ['status' => 'all']))
            ->assertOk()
            ->assertSee($absent->name)
            ->assertSee($unrecorded->name)
            ->assertSee('لم يُسجَّل');
    }

    public function test_today_page_keeps_class_totals_while_filtering_students(): void
    {
        [$tenant, $classroom, $sectionA, $sectionB, $admin] = $this->tenantWithClassroom();

        $present = $this->student($tenant, $classroom, $sectionA);
        $absent = $this->student($tenant, $classroom, $sectionB);

        $today = now()->toDateString();
        $this->mark($present, $sectionA, 'present', $today);
        $this->mark($absent, $sectionB, 'absent', $today);

        $this->actingAs($admin)
            ->get(route('admin.attendance.today', ['status' => 'present']))
            ->assertOk()
            ->assertSee('2 طالب')
            ->assertSee('1 طالب')
            ->assertSee($present->name)
            ->assertDontSee($absent->name);
    }

    public function test_students_without_a_section_appear_in_an_unassigned_group(): void
    {
        [$tenant, , , , $admin] = $this->tenantWithClassroom();

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'classroom_id' => null,
            'section_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.attendance.today', ['status' => 'all']))
            ->assertOk()
            ->assertSee('بدون صف')
            ->assertSee('غير مصنفين')
            ->assertSee($student->name);
    }

    public function test_dashboard_attendance_card_links_to_the_today_page(): void
    {
        [$tenant, $classroom, $sectionA, , $admin] = $this->tenantWithClassroom();

        $student = $this->student($tenant, $classroom, $sectionA);

        $this->mark($student, $sectionA, 'present', now()->toDateString());

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.attendance.today'));
    }
}
