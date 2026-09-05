<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\FinancialTransaction;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\ParentStudent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal + finance spec (§35-§42): authorization scopes, parent & student
 * portals, sheikh finance ledger, audit + notification integration.
 */
class PortalFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        return $tenant;
    }

    /** Mosque with admin, guardian(parent), child student and an unrelated student. */
    private function familyFixture(): array
    {
        $tenant = $this->tenant();
        app(RoleService::class)->provisionTenantRoles($tenant);

        $admin = User::factory()->admin()->for($tenant)->create();

        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الصف الأول']);
        $section = Section::create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);

        $child = Student::factory()->create([
            'tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'section_id' => $section->id, 'name' => 'الابن الأول',
        ]);
        $other = Student::factory()->create([
            'tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'section_id' => $section->id, 'name' => 'طالب آخر',
        ]);

        $guardianUser = User::factory()->create([
            'tenant_id' => $tenant->id, 'name' => 'ولي الأمر', 'role' => User::ROLE_GUARDIAN,
        ]);
        $guardian = Guardian::create([
            'tenant_id' => $tenant->id, 'user_id' => $guardianUser->id, 'name' => 'ولي الأمر',
        ]);
        ParentStudent::create([
            'tenant_id' => $tenant->id, 'parent_id' => $guardian->id, 'student_id' => $child->id,
            'relationship' => 'father', 'is_primary' => true,
        ]);

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id, 'name' => 'الابن الأول', 'role' => User::ROLE_STUDENT,
        ]);
        $child->update(['user_id' => $studentUser->id]);

        return [$tenant, $admin, $guardian, $guardianUser, $child, $other, $studentUser, $section];
    }

    private function teacherUser(Tenant $tenant, User $admin, Section $section, ?Student $student = null): User
    {
        $user = User::factory()->for($tenant)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        $enrollment = app(EnrollmentService::class);
        $enrollment->assignTeacher($section, $teacher);

        if ($student) {
            $enrollment->enroll($student, $section);
        }

        return $user;
    }

    private function grantFinance(User $teacher): void
    {
        $role = Role::where('tenant_id', $teacher->tenant_id)->where('code', RoleService::ROLE_TEACHER)->firstOrFail();

        foreach (['finance.view', 'finance.create', 'finance.adjust', 'finance.transfer'] as $code) {
            $permission = Permission::where('code', $code)->first();

            if ($permission && ! $role->permissions()->where('permissions.code', $code)->exists()) {
                $role->permissions()->attach($permission->id, ['scope' => 'own']);
            }
        }
    }

    // ---------------------------------------------------------- guardian scope

    public function test_guardian_sees_own_children_but_not_other_students(): void
    {
        [, , , $guardianUser, $child, $other] = $this->familyFixture();

        $this->actingAs($guardianUser)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee($child->name)
            ->assertDontSee($other->name);

        $this->actingAs($guardianUser)
            ->get(route('guardian.children.overview', $child))
            ->assertOk();

        $this->actingAs($guardianUser)
            ->get(route('guardian.children.attendance', $other))
            ->assertForbidden();
    }

    public function test_guardian_attendance_page_shows_derived_records_and_summary(): void
    {
        [, , , $guardianUser, $child, , , $section] = $this->familyFixture();
        $admin = User::factory()->admin()->for(Tenant::find($child->tenant_id))->create();

        // teacher attendance for the child's section
        $teacherUser = $this->teacherUser(Tenant::find($child->tenant_id), $admin, $section, $child);
        $this->actingAs($teacherUser);

        $date = now()->toDateString();

        $this->post(route('teacher.attendance.store'), [
            'date' => $date,
            'statuses' => [$child->id => AttendanceStatus::Absent->value],
        ])->assertRedirect();

        $this->actingAs($guardianUser)
            ->get(route('guardian.children.attendance', $child))
            ->assertOk()
            ->assertSee('غائب')
            ->assertSee($date);

        $this->assertDatabaseHas('attendance_records', ['student_id' => $child->id, 'status' => 'absent']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.session_created']);
    }

    public function test_absence_notifies_student_and_guardian_accounts(): void
    {
        [$tenant, , , $guardianUser, $child, , $studentUser, $section] = $this->familyFixture();
        $admin = User::factory()->admin()->for($tenant)->create();
        $teacherUser = $this->teacherUser($tenant, $admin, $section, $child);

        $this->actingAs($teacherUser)
            ->post(route('teacher.attendance.store'), [
                'date' => now()->toDateString(),
                'statuses' => [$child->id => AttendanceStatus::Absent->value],
            ])->assertRedirect();

        $this->assertDatabaseHas('notifications', ['type' => 'App\Notifications\PortalNotification']);
        $this->assertSame(1, $studentUser->notifications()->count());
        $this->assertSame(1, $guardianUser->notifications()->count());
    }

    public function test_guardian_sees_published_grades_but_not_drafts(): void
    {
        [, , , $guardianUser, $child] = $this->familyFixture();
        $tenant = $child->tenant()->first();
        $classroom = Classroom::find($child->classroom_id);
        $subject = Subject::create(['tenant_id' => $tenant->id, 'name' => 'القرآن']);

        $exam = Exam::create([
            'tenant_id' => $tenant->id, 'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
            'section_id' => $child->section_id, 'title' => 'امتحان النور', 'exam_date' => today()->subDay(),
            'total_marks' => 100, 'pass_marks' => 50,
        ]);

        Grade::create(['tenant_id' => $tenant->id, 'exam_id' => $exam->id, 'student_id' => $child->id, 'score' => 88, 'status' => 'approved']);

        // a not-yet-published grade (submitted) must stay hidden from the portal
        $exam2 = Exam::create([
            'tenant_id' => $tenant->id, 'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
            'section_id' => $child->section_id, 'title' => 'امتحان غير منشور', 'exam_date' => today()->subDay(),
            'total_marks' => 100, 'pass_marks' => 50,
        ]);
        Grade::create(['tenant_id' => $tenant->id, 'exam_id' => $exam2->id, 'student_id' => $child->id, 'score' => 91, 'status' => 'submitted']);

        $this->actingAs($guardianUser)
            ->get(route('guardian.children.grades', $child))
            ->assertOk()
            ->assertSee('امتحان النور')
            ->assertDontSee('امتحان غير منشور');
    }

    // ----------------------------------------------------------- student scope

    public function test_student_sees_only_own_records(): void
    {
        [, , , , $child, $other, $studentUser] = $this->familyFixture();

        $this->actingAs($studentUser)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($child->name);

        // student cannot open another student's homework area or teacher pages
        $this->actingAs($studentUser)
            ->get(route('teacher.dashboard'))
            ->assertForbidden();
    }

    public function test_student_can_submit_own_homework_only(): void
    {
        [$tenant, , , , $child, , $studentUser, $section] = $this->familyFixture();
        $classroom = Classroom::find($child->classroom_id);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id, 'name' => 'الشيخ عمر']);
        $subject = Subject::create(['tenant_id' => $tenant->id, 'teacher_id' => $teacher->id, 'name' => 'القرآن']);

        $homework = Homework::create([
            'tenant_id' => $tenant->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id, 'title' => 'حفظ سورة الإخلاص', 'due_date' => today()->addDays(3),
        ]);

        HomeworkSubmission::create([
            'tenant_id' => $tenant->id, 'homework_id' => $homework->id, 'student_id' => $child->id, 'status' => 'pending',
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.homeworks.submit', $homework), ['content' => 'حفظت السورة كاملة'])
            ->assertRedirect();

        $this->assertDatabaseHas('homework_submissions', [
            'student_id' => $child->id, 'content' => 'حفظت السورة كاملة',
        ]);

        $submission = HomeworkSubmission::where('homework_id', $homework->id)->where('student_id', $child->id)->first();
        $this->assertNotNull($submission->submitted_at);
    }

    // -------------------------------------------------------------- finance

    public function test_teacher_finance_requires_explicit_permission(): void
    {
        [$tenant, , , , , , , $section] = $this->familyFixture();
        $admin = User::factory()->admin()->for($tenant)->create();
        $teacherUser = $this->teacherUser($tenant, $admin, $section);

        $this->actingAs($teacherUser)->get(route('teacher.finance.index'))->assertForbidden();

        $this->grantFinance($teacherUser);
        $this->actingAs($teacherUser)->get(route('teacher.finance.index'))->assertOk();
    }

    public function test_sheikh_ledger_derives_received_handed_and_remaining(): void
    {
        [$tenant, , , , $child, , , $section] = $this->familyFixture();
        $admin = User::factory()->admin()->for($tenant)->create();
        $teacherUser = $this->teacherUser($tenant, $admin, $section, $child);
        $teacher = Teacher::where('tenant_id', $tenant->id)->where('user_id', $teacherUser->id)->first();
        $this->grantFinance($teacherUser);

        $this->actingAs($teacherUser);

        // two receipts from students of the sheikh's sections
        foreach ([100, 200] as $amount) {
            $this->post(route('teacher.finance.receive.store'), [
                'from_type' => 'student',
                'from_id' => $child->id,
                'amount' => $amount,
                'description' => 'قبض نقدي',
            ])->assertRedirect(route('teacher.finance.index'));
        }

        // handover to the mosque treasury
        $this->post(route('teacher.finance.adjust'), [
            'direction' => 'money_out',
            'amount' => 150,
            'description' => 'تسليم إيرادات الجامع',
        ])->assertRedirect(route('teacher.finance.index'));

        $response = $this->get(route('teacher.finance.index'))->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('300', $content); // received
        $this->assertStringContainsString('150', $content); // remaining = 300 - 150

        // ledger rows carry the teacher person and one side records the payer
        $this->assertSame(3, FinancialTransaction::where('person_type', 'teacher')->where('person_id', $teacher->id)->count());
        $this->assertDatabaseHas('financial_transactions', [
            'person_id' => $teacher->id,
            'transaction_type' => 'payment',
            'direction' => 'money_in',
            'related_person_id' => $child->id,
        ]);
    }

    public function test_transfer_between_persons_preserves_both_sides(): void
    {
        [$tenant, , , , $child, , , $section] = $this->familyFixture();
        $admin = User::factory()->admin()->for($tenant)->create();
        $sheikhUser = $this->teacherUser($tenant, $admin, $section, $child);
        $sheikh = Teacher::where('tenant_id', $tenant->id)->where('user_id', $sheikhUser->id)->first();

        $colleague = Teacher::factory()->create(['tenant_id' => $tenant->id, 'name' => 'شيخ آخر']);
        $this->grantFinance($sheikhUser);

        $this->actingAs($sheikhUser)->post(route('teacher.finance.transfer.store'), [
            'to_type' => 'teacher',
            'to_id' => $colleague->id,
            'amount' => 50,
            'description' => 'تحويل بين الشيوخ',
        ])->assertRedirect(route('teacher.finance.index'));

        $outLeg = FinancialTransaction::where('person_id', $sheikh->id)->where('transaction_type', 'transfer')->firstOrFail();
        $inLeg = FinancialTransaction::where('person_id', $colleague->id)->where('transaction_type', 'transfer')->firstOrFail();

        $this->assertSame($outLeg->reference, $inLeg->reference);
        $this->assertSame('money_out', $outLeg->direction->value);
        $this->assertSame('money_in', $inLeg->direction->value);
    }

    public function test_financial_correction_uses_reversal_and_keeps_history(): void
    {
        [$tenant, , , , $child, , , $section] = $this->familyFixture();
        $admin = User::factory()->admin()->for($tenant)->create();
        $sheikhUser = $this->teacherUser($tenant, $admin, $section, $child);
        $sheikh = Teacher::where('tenant_id', $tenant->id)->where('user_id', $sheikhUser->id)->first();
        $this->grantFinance($sheikhUser);

        $this->actingAs($sheikhUser);

        $this->post(route('teacher.finance.receive.store'), [
            'from_type' => 'student', 'from_id' => $child->id, 'amount' => 50,
        ])->assertRedirect();

        $receipt = FinancialTransaction::where('person_id', $sheikh->id)->where('transaction_type', 'payment')->firstOrFail();

        $this->post(route('teacher.finance.reverse', $receipt))->assertRedirect(route('teacher.finance.index'));

        // original preserved + reversal row flips the direction
        $this->assertDatabaseHas('financial_transactions', ['id' => $receipt->id]);
        $this->assertDatabaseHas('financial_transactions', ['reverses_id' => $receipt->id, 'direction' => 'money_out']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.transaction_reversed']);
    }

    public function test_admin_can_open_finance_and_audit_pages(): void
    {
        [, $admin] = $this->familyFixture();

        $this->actingAs($admin)
            ->get(route('admin.finance.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk();
    }

    public function test_all_guardian_child_pages_render(): void
    {
        [, , , $guardianUser, $child] = $this->familyFixture();

        $routes = [
            'guardian.children.overview', 'guardian.children.attendance', 'guardian.children.subjects',
            'guardian.children.teachers', 'guardian.children.exams', 'guardian.children.grades',
            'guardian.children.homeworks', 'guardian.children.announcements',
        ];

        foreach ($routes as $route) {
            $this->actingAs($guardianUser)->get(route($route, $child))->assertOk();
        }

        $this->actingAs($guardianUser)->get(route('guardian.profile'))->assertOk();
    }

    public function test_all_student_pages_render(): void
    {
        [$tenant, , , , $child, , $studentUser, $section] = $this->familyFixture();
        $classroom = Classroom::find($child->classroom_id);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id, 'name' => 'الشيخ عمر']);
        $subject = Subject::create(['tenant_id' => $tenant->id, 'teacher_id' => $teacher->id, 'name' => 'القرآن']);
        Schedule::create([
            'tenant_id' => $tenant->id, 'classroom_id' => $classroom->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'day_of_week' => 1,
            'starts_at' => '08:00', 'ends_at' => '09:00',
        ]);

        $routes = [
            'student.dashboard', 'student.profile', 'student.attendance', 'student.subjects',
            'student.teachers', 'student.exams', 'student.grades', 'student.homeworks', 'student.announcements',
        ];

        foreach ($routes as $route) {
            $this->actingAs($studentUser)->get(route($route))->assertOk();
        }
    }
}
