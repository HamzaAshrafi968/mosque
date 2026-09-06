<?php

namespace Tests\Feature;

use App\Enums\ProgramType;
use App\Models\Classroom;
use App\Models\CustomField;
use App\Models\FaithMeeting;
use App\Models\FaithMeetingTemplate;
use App\Models\HafizMonthlyExam;
use App\Models\HafizProfile;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\Permission;
use App\Models\ProgramEnrollment;
use App\Models\QualifyingWeeklyEvaluation;
use App\Models\QuranCompletion;
use App\Models\QuranRecitationSession;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\QuranProgramService;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use App\Support\QuranProgramSettings;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuranProgramsTest extends TestCase
{
    // ---------- fixtures ----------

    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $admin];
    }

    private function makeTeacher(string $tenantId): array
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function makeStudent(string $tenantId): Student
    {
        return Student::factory()->create(['tenant_id' => $tenantId]);
    }

    /** Create a student who completed the Quran (recorded + confirmed). */
    private function makeHafiz(string $tenantId, User $actor): Student
    {
        $student = $this->makeStudent($tenantId);
        $programs = app(QuranProgramService::class);
        $programs->confirmCompletion($programs->recordCompletion($student, null, null, $actor), $actor);

        return $student;
    }

    private function makeSection(string $tenantId, ?Classroom $classroom = null): Section
    {
        $classroom ??= Classroom::create(['tenant_id' => $tenantId, 'name' => 'صف اختبار']);

        return Section::create(['tenant_id' => $tenantId, 'classroom_id' => $classroom->id, 'name' => 'شعبة '.fake()->word]);
    }

    private function enrollStudent(Student $student, Section $section): void
    {
        $student->update(['classroom_id' => $section->classroom_id, 'section_id' => $section->id]);

        SectionStudent::create([
            'tenant_id' => $student->tenant_id,
            'section_id' => $section->id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);
    }

    // ---------- RBAC / catalog ----------

    public function test_quran_permissions_exist_in_catalog_and_db(): void
    {
        [$mosque] = $this->mosque();

        foreach ([
            'quran.tasmee.view', 'quran.tasmee.create', 'quran.tasmee.update',
            'quran.completion.view', 'quran.completion.confirm',
            'faith_meetings.view', 'faith_meetings.create', 'faith_meetings.attendance',
            'qualifying.view', 'qualifying.create', 'qualifying.update', 'qualifying.complete',
            'ijazah.view', 'ijazah.create', 'ijazah.complete',
            'hafiz_exams.view', 'hafiz_exams.create', 'hafiz_exams.update', 'hafiz_exams.grade',
            'hafiz_profile.view', 'hafiz_profile.update',
        ] as $code) {
            $this->assertTrue(in_array($code, PermissionCatalog::codes(), true), "catalog missing {$code}");
            $this->assertDatabaseHas('permissions', ['code' => $code]);
        }
    }

    public function test_default_role_grants_follow_the_spec(): void
    {
        [$mosque, $admin] = $this->mosque();
        $teacherUser = User::factory()->create(['tenant_id' => $mosque->id]);

        $auth = app(AuthorizationService::class);

        // Mosque manager: everything at mosque scope.
        foreach ([
            'quran.tasmee.create', 'quran.completion.confirm', 'qualifying.complete',
            'ijazah.complete', 'hafiz_exams.grade', 'hafiz_profile.update', 'faith_meetings.create',
        ] as $code) {
            $this->assertTrue($auth->can($admin, $code), "admin missing {$code}");
        }

        // Teacher: recording rights only — never confirmation / completion.
        $this->assertTrue($auth->can($teacherUser, 'quran.tasmee.create'));
        $this->assertTrue($auth->can($teacherUser, 'qualifying.create'));
        $this->assertTrue($auth->can($teacherUser, 'faith_meetings.attendance'));
        $this->assertFalse($auth->can($teacherUser, 'quran.completion.confirm'));
        $this->assertFalse($auth->can($teacherUser, 'qualifying.complete'));
        $this->assertFalse($auth->can($teacherUser, 'ijazah.complete'));
        $this->assertFalse($auth->can($teacherUser, 'hafiz_profile.update'));
    }

    public function test_permission_catalog_repairs_missing_rows_on_seeded_db(): void
    {
        [$mosque] = $this->mosque();

        $this->assertDatabaseHas('permissions', ['code' => 'quran.tasmee.view']);
        app(Permission::class)::where('code', 'quran.tasmee.view')->delete();
        $this->assertDatabaseMissing('permissions', ['code' => 'quran.tasmee.view']);

        app(RoleService::class)->ensurePermissionCatalog();

        $this->assertDatabaseHas('permissions', ['code' => 'quran.tasmee.view']);
    }

    public function test_quran_admin_pages_are_forbidden_for_teachers(): void
    {
        [$mosque] = $this->mosque();
        $teacherUser = User::factory()->create(['tenant_id' => $mosque->id]);

        $this->actingAs($teacherUser)
            ->get(route('admin.quran.tasmee.index'))
            ->assertForbidden();
    }

    // ---------- Tasmee' ----------

    public function test_admin_records_tasmee_and_history_is_preserved(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($admin)
            ->post(route('admin.quran.tasmee.store'), [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'type' => 'new',
                'date' => '2026-09-01',
                'amount' => 2,
                'recited_portion' => 'سورة البقرة',
                'result' => 'very_good',
                'notes' => 'أداء جيد',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quran_recitation_sessions', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'result' => 'very_good',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quran.tasmee.created']);

        // A second record for the same student is preserved (history never overwritten).
        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => '2026-09-02',
            'amount' => 1,
            'result' => 'excellent',
        ])->assertRedirect();

        $this->assertSame(2, QuranRecitationSession::where('student_id', $student->id)->count());

        $this->actingAs($admin)
            ->get(route('admin.quran.tasmee.index', ['type' => 'new']))
            ->assertOk()
            ->assertSee('سورة البقرة');
    }

    public function test_teacher_can_only_record_tasmee_for_scope_students(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);

        $mySection = $this->makeSection($mosque->id);
        $otherSection = $this->makeSection($mosque->id);

        SectionTeacher::create([
            'tenant_id' => $mosque->id,
            'section_id' => $mySection->id,
            'teacher_id' => $teacher->id,
            'role' => 'lead',
            'status' => 'active',
        ]);

        $inside = $this->makeStudent($mosque->id);
        $outside = $this->makeStudent($mosque->id);
        $this->enrollStudent($inside, $mySection);
        $this->enrollStudent($outside, $otherSection);

        $payload = ['student_id' => $inside->id, 'type' => 'new', 'date' => '2026-09-05', 'amount' => 2];
        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.tasmee.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('quran_recitation_sessions', ['student_id' => $inside->id]);

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.tasmee.store'), ['student_id' => $outside->id, 'type' => 'revision', 'date' => '2026-09-06', 'amount' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('quran_recitation_sessions', ['student_id' => $outside->id]);
    }

    public function test_tasmee_result_change_is_audited(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $session = QuranRecitationSession::create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => '2026-09-01',
            'amount' => 2,
            'result' => 'good',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.quran.tasmee.update', $session), [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'type' => 'new',
                'date' => '2026-09-01',
                'amount' => 2,
                'result' => 'excellent',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'quran.tasmee.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quran.tasmee.result_changed']);
    }

    // ---------- Quran completion → Hafiz workflow ----------

    public function test_completion_confirmation_promotes_student_and_starts_programs(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($admin)
            ->post(route('admin.quran.completions.store'), [
                'student_id' => $student->id,
                'completed_at' => '2026-08-30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quran_completions', [
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $completion = QuranCompletion::where('student_id', $student->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.quran.completions.confirm', $completion))
            ->assertRedirect();

        $this->assertDatabaseHas('quran_completions', ['id' => $completion->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('hafiz_profiles', ['student_id' => $student->id]);
        $this->assertDatabaseHas('program_enrollments', [
            'student_id' => $student->id,
            'program_type' => 'qualifying',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('hafiz_monthly_exams', [
            'student_id' => $student->id,
            'month' => QuranProgramSettings::monthOf(now()),
            'exam_status' => 'not_tested',
        ]);

        foreach ([
            'quran.completion.recorded',
            'quran.completion.confirmed',
            'hafiz.profile.created',
            'program.enrollment.created',
            'hafiz_exam.month_opened',
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_confirmation_is_idempotent_and_duplicate_is_rejected(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->makeHafiz($mosque->id, $admin);

        $this->assertSame(1, ProgramEnrollment::where('student_id', $student->id)->count());
        $this->assertSame(1, HafizProfile::where('student_id', $student->id)->count());

        // A second completion request for the same hafiz cannot be confirmed.
        $programs = app(QuranProgramService::class);
        $second = $programs->recordCompletion($student, now()->toDateString(), null, $admin);

        try {
            $programs->confirmCompletion($second, $admin);
            $this->fail('Expected duplicate confirmation to be rejected');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(1, ProgramEnrollment::where('student_id', $student->id)->count());
        $this->assertSame(1, HafizProfile::where('student_id', $student->id)->count());
    }

    public function test_qualifying_completion_requires_configured_passing_weeks_then_enrolls_ijazah(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->makeHafiz($mosque->id, $admin);

        $programs = app(QuranProgramService::class);
        $enrollment = $programs->activeEnrollment($student, ProgramType::Qualifying);

        // Below the configured minimum of passing weeks → rejected.
        try {
            $programs->completeQualifying($enrollment, $admin);
            $this->fail('Expected completion to be rejected with no passing weeks');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame('active', $enrollment->refresh()->status->value);

        // Record 4 historical weekly evaluations (each week separate).
        foreach (range(1, QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS) as $week) {
            QualifyingWeeklyEvaluation::create([
                'tenant_id' => $mosque->id,
                'student_id' => $student->id,
                'week_start' => Carbon::now()->subWeeks(QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS - $week)->startOfWeek()->toDateString(),
                'week_end' => Carbon::now()->subWeeks(QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS - $week)->endOfWeek()->toDateString(),
                'amount' => 5,
                'result' => 'passed',
            ]);
        }

        $programs->completeQualifying($enrollment, $admin);

        $this->assertSame('completed', $enrollment->refresh()->status->value);
        $this->assertNotNull($enrollment->completed_at);

        // Automatic Ijazah enrollment + audit trail.
        $this->assertDatabaseHas('program_enrollments', [
            'student_id' => $student->id,
            'program_type' => 'ijazah',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'qualifying.completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'program.enrollment.created']);
    }

    public function test_weekly_evaluations_are_never_overwritten(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeHafiz($mosque->id, $admin);

        // Week 1
        $this->actingAs($admin)->post(route('admin.quran.qualifying.evaluations.store'), [
            'student_id' => $student->id,
            'week_start' => '2026-08-31',
            'week_end' => '2026-09-06',
            'amount' => 5,
            'evaluated_by' => $teacher->id,
            'result' => 'passed',
        ])->assertRedirect();

        // Same week cannot be recorded twice…
        $this->actingAs($admin)->post(route('admin.quran.qualifying.evaluations.store'), [
            'student_id' => $student->id,
            'week_start' => '2026-08-31',
            'week_end' => '2026-09-06',
            'amount' => 2,
            'evaluated_by' => $teacher->id,
            'result' => 'failed',
        ])->assertSessionHasErrors('week_start');

        // …but a new week creates a new historical row.
        $this->actingAs($admin)->post(route('admin.quran.qualifying.evaluations.store'), [
            'student_id' => $student->id,
            'week_start' => '2026-09-07',
            'week_end' => '2026-09-13',
            'amount' => 4,
            'evaluated_by' => $teacher->id,
            'result' => 'passed',
        ])->assertRedirect();

        $this->assertSame(2, QualifyingWeeklyEvaluation::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'qualifying.weekly_recorded']);
    }

    public function test_ijazah_monthly_evaluations_one_row_per_month(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        [, , $student] = $this->studentInIjazah($mosque->id, $admin);

        $payload = ['student_id' => $student->id, 'month' => '2026-09', 'amount' => 30, 'evaluated_by' => $teacher->id, 'result' => 'passed'];

        $this->actingAs($admin)->post(route('admin.quran.ijazah.evaluations.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.quran.ijazah.evaluations.store'), $payload)
            ->assertSessionHasErrors('month');

        $this->actingAs($admin)->post(route('admin.quran.ijazah.evaluations.store'), [
            'student_id' => $student->id, 'month' => '2026-10', 'amount' => 30, 'evaluated_by' => $teacher->id, 'result' => 'passed',
        ])->assertRedirect();

        $this->assertSame(2, IjazahMonthlyEvaluation::where('student_id', $student->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ijazah.monthly_recorded']);
    }

    // ---------- Monthly hafiz exams & revisions ----------

    public function test_monthly_exam_grading_uses_configured_pass_mark(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeHafiz($mosque->id, $admin);

        $month = QuranProgramSettings::monthOf(now());
        $exam = HafizMonthlyExam::where('student_id', $student->id)->where('month', $month)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.quran.exams.grade', $exam), [
            'grade' => 88,
            'supervisor_id' => $teacher->id,
            'exam_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('hafiz_monthly_exams', ['id' => $exam->id, 'exam_status' => 'passed', 'grade' => 88.0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hafiz_exam.graded']);
    }

    public function test_failed_exam_requires_and_tracks_repetition_portions(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeHafiz($mosque->id, $admin);

        $month = QuranProgramSettings::monthOf(now());
        $exam = HafizMonthlyExam::where('student_id', $student->id)->where('month', $month)->firstOrFail();

        // Fail without portions → rejected with an error.
        $this->actingAs($admin)->post(route('admin.quran.exams.grade', $exam), [
            'grade' => 55,
            'supervisor_id' => $teacher->id,
        ])->assertSessionHasErrors('grade');

        // Fail + portion row → failed + revision recorded.
        $this->actingAs($admin)->post(route('admin.quran.exams.grade', $exam), [
            'grade' => 55,
            'supervisor_id' => $teacher->id,
            'revisions' => [[
                'juz' => 7,
                'amount' => 1,
                'notes' => 'إعادة جزء 7',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('hafiz_monthly_exams', ['id' => $exam->id, 'exam_status' => 'failed', 'grade' => 55.0]);
        $this->assertDatabaseHas('hafiz_exam_revisions', ['juz' => 7, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hafiz_exam.revision_recorded']);

        // Lifecycle: pending → completed (supervisor) → approved (management).
        $revision = $exam->revisions()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.quran.exams.revisions.complete', $revision))->assertRedirect();
        $this->assertDatabaseHas('hafiz_exam_revisions', ['id' => $revision->id, 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hafiz_exam.revision_completed']);

        $this->actingAs($admin)->post(route('admin.quran.exams.revisions.approve', $revision))->assertRedirect();
        $this->assertDatabaseHas('hafiz_exam_revisions', ['id' => $revision->id, 'status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hafiz_exam.revision_approved']);

        // Month history is preserved: opening another month creates a separate row.
        $previous = QuranProgramSettings::previousMonth($month);
        $this->actingAs($admin)->get(route('admin.quran.exams.index', ['month' => $previous]))->assertOk();
        $this->assertDatabaseHas('hafiz_monthly_exams', ['student_id' => $student->id, 'month' => $previous, 'exam_status' => 'not_tested']);
    }

    public function test_teacher_grades_only_own_scope_hafiz(): void
    {
        [$mosque] = $this->mosque();
        $admin = User::where('tenant_id', $mosque->id)->where('role', 'admin')->firstOrFail();

        $mySection = $this->makeSection($mosque->id);
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);

        SectionTeacher::create([
            'tenant_id' => $mosque->id,
            'section_id' => $mySection->id,
            'teacher_id' => $teacher->id,
            'role' => 'lead',
            'status' => 'active',
        ]);

        $mine = $this->makeStudent($mosque->id);
        $other = $this->makeStudent($mosque->id);
        $this->enrollStudent($mine, $mySection);
        $this->enrollStudent($other, $this->makeSection($mosque->id));

        app(QuranProgramService::class)->confirmCompletion(
            app(QuranProgramService::class)->recordCompletion($mine, null, null, $admin),
            $admin
        );
        app(QuranProgramService::class)->confirmCompletion(
            app(QuranProgramService::class)->recordCompletion($other, null, null, $admin),
            $admin
        );

        $month = QuranProgramSettings::monthOf(now());
        $myExam = HafizMonthlyExam::where('student_id', $mine->id)->where('month', $month)->firstOrFail();
        $otherExam = HafizMonthlyExam::where('student_id', $other->id)->where('month', $month)->firstOrFail();

        $this->actingAs($teacherUser)->get(route('teacher.quran.exams.show', $otherExam))->assertForbidden();

        $this->actingAs($teacherUser)->post(route('teacher.quran.exams.grade', $myExam), [
            'grade' => 90,
            'exam_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('hafiz_monthly_exams', [
            'id' => $myExam->id,
            'exam_status' => 'passed',
            'supervisor_id' => $teacher->id,
        ]);
    }

    // ---------- Faith meetings ----------

    public function test_admin_creates_meeting_selects_students_records_attendance_and_notes(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $supervisor] = $this->makeTeacher($mosque->id);
        $studentA = $this->makeStudent($mosque->id);
        $studentB = $this->makeStudent($mosque->id);
        $excluded = $this->makeStudent($mosque->id);

        $this->actingAs($admin)->post(route('admin.faith-meetings.store'), [
            'title' => 'لقاء التزكية الأسبوعي',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '17:00',
            'supervisor_id' => $supervisor->id,
            'location' => 'قاعة الجامع',
            'student_ids' => [$studentA->id, $studentB->id],
        ])->assertRedirect();

        $meeting = FaithMeeting::firstOrFail();
        $this->assertSame(2, $meeting->studentAttendances()->count());
        $this->assertFalse($meeting->studentAttendances()->where('student_id', $excluded->id)->exists());

        $this->assertDatabaseHas('audit_logs', ['action' => 'faith_meeting.created']);

        // Attendance for selected students only.
        $this->actingAs($admin)->post(route('admin.faith-meetings.attendance', $meeting), [
            'statuses' => [$studentA->id => 'attended', $studentB->id => 'excused'],
            'notes' => [$studentA->id => 'ملتزم', $studentB->id => ''],
        ])->assertRedirect();

        $this->assertDatabaseHas('faith_meeting_students', ['student_id' => $studentA->id, 'attendance_status' => 'attended']);
        $this->assertDatabaseHas('faith_meeting_students', ['student_id' => $studentB->id, 'attendance_status' => 'excused']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'faith_meeting.attendance_changed']);

        // Action item assigned with due date, then completed.
        $this->actingAs($admin)->post(route('admin.faith-meetings.notes.store', $meeting), [
            'note_type' => 'action_item',
            'content' => 'خطة مراجعة ثلاثة أيام للطالب',
            'student_id' => $studentA->id,
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $note = $meeting->notes()->where('note_type', 'action_item')->firstOrFail();
        $this->assertSame('pending', $note->status->value);

        $this->actingAs($admin)->post(route('admin.faith-meetings.notes.complete', $note))->assertRedirect();
        $this->assertDatabaseHas('faith_meeting_notes', ['id' => $note->id, 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'faith_meeting.note_updated']);

        // Removing a student is tracked.
        $this->actingAs($admin)->patch(route('admin.faith-meetings.update', $meeting), [
            'title' => $meeting->title,
            'date' => $meeting->date->format('Y-m-d'),
            'student_ids' => [$studentA->id],
        ])->assertRedirect();

        $this->assertSame(1, $meeting->studentAttendances()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'faith_meeting.students_changed']);
    }

    public function test_teachers_only_access_meetings_they_organize(): void
    {
        [$mosque] = $this->mosque();
        $admin = User::where('role', 'admin')->firstOrFail();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        [, $otherTeacher] = $this->makeTeacher($mosque->id);

        $mine = FaithMeeting::create([
            'tenant_id' => $mosque->id,
            'title' => 'لقائي',
            'date' => now()->toDateString(),
            'supervisor_id' => $teacher->id,
            'created_by' => $admin->id,
        ]);
        $theirs = FaithMeeting::create([
            'tenant_id' => $mosque->id,
            'title' => 'لقاء آخر',
            'date' => now()->toDateString(),
            'supervisor_id' => $otherTeacher->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($teacherUser)->get(route('teacher.quran.faith-meetings.show', $mine))->assertOk();
        $this->actingAs($teacherUser)->get(route('teacher.quran.faith-meetings.show', $theirs))->assertForbidden();
    }

    public function test_meeting_templates_can_be_created_and_used(): void
    {
        [$mosque, $admin] = $this->mosque();

        $this->actingAs($admin)->post(route('admin.faith-meetings.templates.store'), [
            'title' => 'اللقاء الأسبوعي للتزكية',
            'location' => 'القاعة الكبرى',
        ])->assertRedirect();

        $template = FaithMeetingTemplate::firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'faith_meeting_template.created']);

        $this->actingAs($admin)
            ->get(route('admin.faith-meetings.create', ['template' => $template->id]))
            ->assertOk()
            ->assertSee('اللقاء الأسبوعي للتزكية');
    }

    // ---------- Hafiz profile & custom fields ----------

    public function test_hafiz_profile_supports_spec_fields_and_custom_fields(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->makeHafiz($mosque->id, $admin);
        $profile = HafizProfile::where('student_id', $student->id)->firstOrFail();

        $field = CustomField::create([
            'tenant_id' => $mosque->id,
            'entity_type' => 'hafiz',
            'name' => 'سنوات التدريس',
            'field_key' => 'teaching_years',
            'field_type' => 'number',
            'required' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.quran.hafiz.profile.update', $student), [
            'mujaz' => '1',
            'mujiz' => 'الشيخ عمر',
            'riwayah' => 'حفص عن عاصم',
            'ijazah_jazariyyah' => '1',
            'scientific_certificate' => 'بكالوريوس شريعة',
            'custom_fields' => ['teaching_years' => 7],
        ])->assertRedirect();

        $this->assertDatabaseHas('hafiz_profiles', [
            'id' => $profile->id,
            'mujaz' => true,
            'mujiz' => 'الشيخ عمر',
            'ijazah_jazariyyah' => true,
        ]);
        $this->assertDatabaseHas('custom_field_values', [
            'custom_field_id' => $field->id,
            'entity_id' => $profile->id,
            'value' => '7',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'hafiz.profile.updated']);

        // Custom-field validation runs for the hafiz entity too.
        CustomField::create([
            'tenant_id' => $mosque->id,
            'entity_type' => 'hafiz',
            'name' => 'تخصص',
            'field_key' => 'specialization',
            'field_type' => 'select',
            'required' => true,
            'options' => ['قرآن', 'تجويد', 'فقه'],
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.quran.hafiz.profile.update', $student), ['custom_fields' => ['specialization' => '']])
            ->assertSessionHasErrors();
    }

    // ---------- Journey / dashboards ----------

    public function test_journey_dashboard_reflects_stage_and_history(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($admin)->get(route('admin.quran.journey', $student))->assertOk();

        $hafiz = $this->makeHafiz($mosque->id, $admin);
        $hafiz->load('hafizProfile');

        $this->actingAs($admin)->get(route('admin.quran.journey', $hafiz))
            ->assertOk()
            ->assertSee('البرنامج التأهيلي');

        $this->actingAs($admin)->get(route('admin.quran.hafiz.index'))->assertOk()->assertSee($hafiz->name);
        $this->actingAs($admin)->get(route('admin.quran.index'))->assertOk();
    }

    /** Every quran module page renders without blade/view errors. */
    public function test_all_quran_module_pages_render(): void
    {
        [$mosque, $admin] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeHafiz($mosque->id, $admin);
        [, $supervisor] = $this->makeTeacher($mosque->id);

        FaithMeeting::create([
            'tenant_id' => $mosque->id,
            'title' => 'لقاء تجريبي',
            'date' => now()->addDays(2)->toDateString(),
            'supervisor_id' => $supervisor->id,
        ]);

        foreach ([
            'admin.quran.index', 'admin.quran.tasmee.index', 'admin.quran.tasmee.create',
            'admin.quran.completions.index', 'admin.quran.completions.create',
            'admin.quran.hafiz.index', 'admin.quran.qualifying.index', 'admin.quran.ijazah.index',
            'admin.quran.exams.index', 'admin.faith-meetings.index', 'admin.faith-meetings.create',
            'admin.faith-meetings.templates',
        ] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.quran.journey', $student))->assertOk();
        $this->actingAs($admin)->get(route('admin.quran.hafiz.profile', $student))->assertOk();

        $meeting = FaithMeeting::firstOrFail();
        $this->actingAs($admin)->get(route('admin.faith-meetings.show', $meeting))->assertOk();
        $this->actingAs($admin)->get(route('admin.faith-meetings.edit', $meeting))->assertOk();

        $myMeeting = FaithMeeting::create([
            'tenant_id' => $mosque->id,
            'title' => 'لقاء المعلم',
            'date' => now()->addDays(2)->toDateString(),
            'supervisor_id' => $teacher->id,
        ]);

        // Teacher pages with an assigned section + scope student.
        $section = $this->makeSection($mosque->id);
        SectionTeacher::create([
            'tenant_id' => $mosque->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'role' => 'lead',
            'status' => 'active',
        ]);
        $this->enrollStudent($student, $section);

        foreach ([
            'teacher.quran.index', 'teacher.quran.tasmee.index', 'teacher.quran.tasmee.create',
            'teacher.quran.qualifying.index', 'teacher.quran.qualifying.evaluations.create',
            'teacher.quran.ijazah.index', 'teacher.quran.ijazah.evaluations.create',
            'teacher.quran.exams.index', 'teacher.quran.faith-meetings.index',
        ] as $route) {
            $this->actingAs($teacherUser)->get(route($route))->assertOk();
        }

        $this->actingAs($teacherUser)->get(route('teacher.quran.students.journey', $student))->assertOk();
        $this->actingAs($teacherUser)->get(route('teacher.quran.faith-meetings.show', $myMeeting))->assertOk();

        // Exam detail pages (student is now a hafiz within the teacher scope).
        $exam = HafizMonthlyExam::where('student_id', $student->id)->latest('month')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.quran.exams.show', $exam))->assertOk();
        $this->actingAs($teacherUser)->get(route('teacher.quran.exams.show', $exam))->assertOk();
    }

    // ---------- helpers ----------

    /** Put a student into the ijazah program through the full workflow. */
    private function studentInIjazah(string $tenantId, User $actor): array
    {
        $programs = app(QuranProgramService::class);

        $student = $this->makeStudent($tenantId);
        $programs->confirmCompletion($programs->recordCompletion($student, null, null, $actor), $actor);

        $qualifying = $programs->activeEnrollment($student, ProgramType::Qualifying);

        foreach (range(1, QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS) as $week) {
            QualifyingWeeklyEvaluation::create([
                'tenant_id' => $tenantId,
                'student_id' => $student->id,
                'week_start' => Carbon::now()->subWeeks($week)->startOfWeek()->toDateString(),
                'week_end' => Carbon::now()->subWeeks($week)->endOfWeek()->toDateString(),
                'amount' => 5,
                'result' => 'passed',
            ]);
        }

        $programs->completeQualifying($qualifying, $actor);

        return [$tenantId, $actor, $student];
    }
}
