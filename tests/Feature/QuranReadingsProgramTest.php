<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranEvaluationResult;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningProgramStatus;
use App\Enums\QuranReading;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\Permission;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranRecitationSession;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalNotification;
use App\Services\QuranListeningProgramService;
use App\Services\QuranProgramBatchService;
use App\Services\QuranProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * برنامج القراءات (القراءات العشر) — مرحلة متقدمة تراتبية اختيارية:
 *
 * - لا تسجيل قبل إتمام برنامج الإجازة (البوابة مفروضة في الخدمة على كل المسارات).
 * - تسجيل ذاتي اختياري من بوابة الطالب (quran_training.enroll) + تسجيل المدير/الأستاذ.
 * - قراءات متوازية لنفس الطالب، وإعادة التسجيل لنفس القراءة idempotent.
 * - الإتمام يُنهي الدورة والالتحاق فقط: لا ختم تأهيل ولا تحويل تلقائي.
 */
class QuranReadingsProgramTest extends TestCase
{
    /** @return array{0: Tenant, 1: User, 2: StudySession} */
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();
        $session = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->firstOrFail();

        return [$mosque, $admin, $session];
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque, StudySession $session): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id]);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'study_session_id' => $session->id,
            'name' => 'الأستاذ محمد',
        ]);

        return [$user, $teacher];
    }

    private function student(Tenant $mosque, StudySession $session, string $name = 'الطالب أحمد'): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $session->id,
            'name' => $name,
        ]);
    }

    /** طالب بوابة بحساب مستخدم مرتبط (للاختبارات الذاتية). */
    private function studentUser(Tenant $mosque, StudySession $session): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_STUDENT]);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $user->id]);

        return [$user, $student];
    }

    /** إتمام الإجازة (الشرط التراتبي لدخول القراءات). */
    private function completeIjazah(Student $student): ProgramEnrollment
    {
        return ProgramEnrollment::firstOrCreate(
            [
                'student_id' => $student->id,
                'program_type' => ProgramType::Ijazah,
                'status' => ProgramEnrollmentStatus::Completed,
            ],
            [
                'started_at' => now()->subMonth()->toDateString(),
                'completed_at' => now()->toDateString(),
            ],
        );
    }

    private function enroll(Student $student, QuranReading $reading = QuranReading::Nafeh): QuranListeningProgram
    {
        $this->completeIjazah($student);

        return app(QuranListeningProgramService::class)->enrollReadings($student, $reading);
    }

    private function passBatch(QuranListeningProgramBatch $batch, User $actor, Teacher $teacher): void
    {
        $engine = app(QuranProgramBatchService::class);

        foreach ($batch->items()->orderBy('juz')->get() as $item) {
            if ($item->canBeListened() || $item->isNeedsRepeat()) {
                $engine->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $actor, $teacher->id);
            }
        }

        $results = [];

        foreach ($engine->testScopeJuzNumbers($batch->fresh()) as $juz) {
            $results[$juz] = 'pass';
        }

        $engine->recordBatchTest($batch->fresh(), $results, $actor);
    }

    // ------------------------------------------------------------ البوابة التراتبية

    public function test_admin_cannot_enroll_readings_before_ijazah_completion(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.enroll'), [
                'student_id' => $student->id,
                'reading' => QuranReading::Nafeh->value,
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertSame(0, ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Readings)
            ->count());

        $this->assertSame(0, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Readings)
            ->count());
    }

    public function test_teacher_cannot_enroll_readings_before_ijazah_completion(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        // جلسة تسميع سابقة تُدخل الطالب في نطاق الأستاذ.
        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ]);

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.programs.enroll'), [
                'student_id' => $student->id,
                'reading' => QuranReading::Nafeh->value,
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('program_enrollments', [
            'student_id' => $student->id,
            'program_type' => ProgramType::Readings->value,
        ]);
    }

    public function test_service_rejects_readings_enrollment_without_completed_ijazah(): void
    {
        [$mosque, , $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->expectException(ValidationException::class);

        app(QuranListeningProgramService::class)->enrollReadings($student, QuranReading::Nafeh);
    }

    public function test_gate_opens_after_completing_the_ijazah_cycle(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $enrollment = app(QuranProgramService::class)
            ->enrollIfAbsent(ProgramType::Ijazah, $student->id, now()->toDateString(), $admin);
        $program = app(QuranListeningProgramService::class)->ensureForEnrollment($enrollment);

        $this->assertFalse(app(QuranProgramService::class)->hasCompletedIjazah($student));

        for ($number = 1; $number <= QuranListeningProgramBatch::TOTAL_BATCHES; $number++) {
            $this->passBatch($program->batches()->where('batch_number', $number)->firstOrFail(), $admin, $teacher);
        }

        $this->assertSame(ProgramEnrollmentStatus::Completed, $enrollment->fresh()->status);
        $this->assertTrue(app(QuranProgramService::class)->hasCompletedIjazah($student));

        $readings = app(QuranListeningProgramService::class)->enrollReadings($student, QuranReading::Asim);

        $this->assertSame(ProgramType::Readings, $readings->type);
        $this->assertSame(QuranReading::Asim, $readings->reading);
    }

    public function test_manual_ijazah_completion_opens_the_gate_and_invites_the_student(): void
    {
        Notification::fake();

        [$mosque, $admin, $session] = $this->mosque();
        [$studentUser, $student] = $this->studentUser($mosque, $session);

        $enrollment = app(QuranProgramService::class)
            ->enrollIfAbsent(ProgramType::Ijazah, $student->id, now()->toDateString(), $admin);

        IjazahMonthlyEvaluation::create([
            'student_id' => $student->id,
            'month' => now()->format('Y-m'),
            'amount' => 1,
            'result' => QuranEvaluationResult::Passed,
        ]);

        app(QuranProgramService::class)->completeIjazah($enrollment, $admin);

        $this->assertTrue(app(QuranProgramService::class)->hasCompletedIjazah($student));

        Notification::assertSentTo($studentUser, PortalNotification::class, function (PortalNotification $notification) {
            return str_contains($notification->title, 'القراءات')
                && str_contains($notification->body, 'اختياري');
        });
    }

    // ------------------------------------------------------------ التسجيل (مدير/أستاذ)

    public function test_admin_enrolls_a_student_in_a_reading_and_the_cycle_is_created(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $this->completeIjazah($student);

        $response = $this->actingAs($admin)->post(route('admin.quran.programs.enroll'), [
            'student_id' => $student->id,
            'reading' => QuranReading::Nafeh->value,
        ]);

        $enrollment = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Readings)
            ->firstOrFail();

        $program = QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Readings)
            ->firstOrFail();

        $response->assertRedirect(route('admin.quran.programs.index', [
            'type' => 'readings',
            'program_id' => $program->id,
        ]));

        $this->assertSame(QuranReading::Nafeh, $enrollment->reading);
        $this->assertSame(ProgramEnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(QuranReading::Nafeh, $program->reading);
        $this->assertSame(QuranListeningProgramStatus::Active, $program->status);
        $this->assertSame(6, $program->batches()->count());
        $this->assertSame(30, $program->items()->count());
        $this->assertSame(
            QuranListeningBatchStatus::Listening,
            $program->batches()->where('batch_number', 1)->firstOrFail()->status
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'program.enrollment.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quran_training.program_created']);
    }

    public function test_same_reading_is_idempotent_and_parallel_readings_are_allowed(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $first = $this->enroll($student, QuranReading::Nafeh);
        $again = $this->enroll($student, QuranReading::Nafeh);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Readings)
            ->count());
        $this->assertSame(1, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Readings)
            ->count());

        $second = $this->enroll($student, QuranReading::IbnKathir);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(QuranReading::IbnKathir, $second->reading);
        $this->assertSame(2, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Readings)
            ->count());
        $this->assertSame(2, ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Readings)
            ->count());
    }

    public function test_reading_is_required_and_must_be_one_of_the_ten(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.enroll'), ['student_id' => $student->id])
            ->assertSessionHasErrors('reading');

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.enroll'), [
                'student_id' => $student->id,
                'reading' => 'invalid_reading',
            ])
            ->assertSessionHasErrors('reading');

        $this->assertSame(0, ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Readings)
            ->count());
    }

    public function test_teacher_enrolls_scoped_students_and_outsiders_are_rejected(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $outsider = $this->student($mosque, $session, 'طالب خارج النطاق');

        // جلسة تسميع سابقة تُدخل الطالب في نطاق الأستاذ.
        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ]);

        $this->completeIjazah($student);

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.programs.enroll'), [
                'student_id' => $student->id,
                'reading' => QuranReading::Hamzah->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('program_enrollments', [
            'student_id' => $student->id,
            'program_type' => ProgramType::Readings->value,
            'reading' => QuranReading::Hamzah->value,
        ]);

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.programs.enroll'), [
                'student_id' => $outsider->id,
                'reading' => QuranReading::Nafeh->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('program_enrollments', [
            'student_id' => $outsider->id,
            'program_type' => ProgramType::Readings->value,
        ]);
    }

    public function test_student_role_cannot_use_the_admin_enrollment_route(): void
    {
        [$mosque, , $session] = $this->mosque();
        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);

        $this->actingAs($studentUser)
            ->post(route('admin.quran.programs.enroll'), [
                'student_id' => $student->id,
                'reading' => QuranReading::Nafeh->value,
            ])
            ->assertForbidden();
    }

    // ------------------------------------------------------------ التسجيل الذاتي (الطالب)

    public function test_student_self_enrolls_after_ijazah_completion(): void
    {
        Notification::fake();

        [$mosque, , $session] = $this->mosque();
        [$studentUser, $student] = $this->studentUser($mosque, $session);
        $this->completeIjazah($student);

        $response = $this->actingAs($studentUser)->post(route('student.quran-programs.enroll'), [
            'reading' => QuranReading::Yaqub->value,
        ]);

        $program = QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Readings)
            ->firstOrFail();

        $response->assertRedirect(route('student.quran-programs.index', ['program_id' => $program->id]));

        $this->assertSame(QuranReading::Yaqub, $program->reading);
        $this->assertSame(6, $program->batches()->count());
        $this->assertDatabaseHas('program_enrollments', [
            'student_id' => $student->id,
            'program_type' => ProgramType::Readings->value,
            'reading' => QuranReading::Yaqub->value,
        ]);

        Notification::assertSentTo($studentUser, PortalNotification::class);
    }

    public function test_student_self_enrollment_is_rejected_before_ijazah_completion(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$studentUser, $student] = $this->studentUser($mosque, $session);

        $this->actingAs($studentUser)
            ->post(route('student.quran-programs.enroll'), [
                'reading' => QuranReading::Yaqub->value,
            ])
            ->assertSessionHasErrors('reading');

        $this->assertSame(0, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Readings)
            ->count());
    }

    public function test_student_self_enrollment_requires_the_permission(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$studentUser, $student] = $this->studentUser($mosque, $session);
        $this->completeIjazah($student);

        $permission = Permission::where('code', 'quran_training.enroll')->firstOrFail();

        Role::where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_STUDENT)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($studentUser)
            ->post(route('student.quran-programs.enroll'), [
                'reading' => QuranReading::Yaqub->value,
            ])
            ->assertForbidden();
    }

    // ------------------------------------------------------------ الواجهات

    public function test_readings_tab_renders_the_enrollment_form_for_eligible_students(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $this->completeIjazah($student);

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.index', ['type' => 'readings']))
            ->assertOk()
            ->assertSee('تسجيل طالب في برنامج القراءات')
            ->assertSee('الطالب المؤهل (أتم الإجازة)')
            ->assertSee('نافع المدني')
            ->assertSee('خلف العاشر');
    }

    public function test_readings_tab_shows_the_gate_hint_when_no_eligible_students(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->student($mosque, $session);

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.index', ['type' => 'readings']))
            ->assertOk()
            ->assertSee('لا يوجد طلاب مؤهلون بعد')
            ->assertDontSee('الطالب المؤهل (أتم الإجازة)');
    }

    public function test_student_portal_shows_optional_readings_card_only_when_eligible(): void
    {
        [$mosque, , $session] = $this->mosque();

        [$eligibleUser] = $this->studentUser($mosque, $session);
        $eligibleStudent = Student::where('user_id', $eligibleUser->id)->firstOrFail();
        $this->completeIjazah($eligibleStudent);

        $this->actingAs($eligibleUser)
            ->get(route('student.quran-programs.index'))
            ->assertOk()
            ->assertSee('سجّلني في القراءات')
            ->assertSee('القراءات العشر');

        [$pendingUser] = $this->studentUser($mosque, $session);

        $this->actingAs($pendingUser)
            ->get(route('student.quran-programs.index'))
            ->assertOk()
            ->assertSee('يُفتح التسجيل الاختياري في برنامج القراءات')
            ->assertDontSee('سجّلني في القراءات');
    }

    // ------------------------------------------------------------ الإتمام

    public function test_completing_a_reading_closes_cycle_and_enrollment_without_transfer(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, QuranReading::Nafeh);

        for ($number = 1; $number <= QuranListeningProgramBatch::TOTAL_BATCHES; $number++) {
            $this->passBatch($program->batches()->where('batch_number', $number)->firstOrFail(), $admin, $teacher);
        }

        $this->assertSame(QuranListeningProgramStatus::Completed, $program->fresh()->status);

        $enrollment = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Readings)
            ->where('reading', QuranReading::Nafeh->value)
            ->firstOrFail();

        $this->assertSame(ProgramEnrollmentStatus::Completed, $enrollment->status);

        // لا ختم تأهيل ولا تحويل تلقائي للإجازة ولا أي برنامج آخر.
        $this->assertSame(0, ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('program_type', [ProgramType::Qualifying])
            ->count());

        $this->assertSame(0, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->whereIn('type', [ProgramType::Qualifying])
            ->count());

        $this->assertDatabaseHas('audit_logs', ['action' => 'readings.completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quran_training.program_completed']);
    }

    public function test_readings_cycle_is_not_the_general_active_cycle(): void
    {
        [$mosque, , $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->enroll($student, QuranReading::Nafeh);

        $this->assertNull(app(QuranProgramBatchService::class)->activeCycle($student));
    }

    public function test_hafiz_profile_lists_completed_readings(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, QuranReading::AlKisai);

        for ($number = 1; $number <= QuranListeningProgramBatch::TOTAL_BATCHES; $number++) {
            $this->passBatch($program->batches()->where('batch_number', $number)->firstOrFail(), $admin, $teacher);
        }

        $this->actingAs($admin)
            ->get(route('admin.quran.hafiz.profile', $student))
            ->assertOk()
            ->assertSee('القراءات المكتملة')
            ->assertSee('الكسائي الكوفي');
    }

    // ------------------------------------------------------------ الرحلة (اختياري)

    public function test_journey_shows_the_optional_readings_section_and_the_gate(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        // قبل الإجازة: القفل ظاهر بلا نموذج تسجيل.
        $this->actingAs($admin)
            ->get(route('admin.quran.journey', $student))
            ->assertOk()
            ->assertSee('برنامج القراءات — مرحلة متقدمة اختيارية')
            ->assertSee('يُفتح التسجيل الاختياري في القراءات')
            ->assertDontSee('تسجيل في قراءة جديدة');

        $this->completeIjazah($student);

        // بعد الإجازة: النموذج يفتح والمرحلة تبقى اختيارية.
        $this->actingAs($admin)
            ->get(route('admin.quran.journey', $student))
            ->assertOk()
            ->assertSee('تسجيل في قراءة جديدة')
            ->assertSee('نافع المدني')
            ->assertSee('خلف العاشر')
            ->assertDontSee('يُفتح التسجيل الاختياري في القراءات');
    }

    public function test_journey_marks_readings_as_optional_stage_and_shows_progress(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, QuranReading::Asim);

        $this->passBatch($program->batches()->where('batch_number', 1)->firstOrFail(), $admin, $teacher);

        $this->actingAs($admin)
            ->get(route('admin.quran.journey', $student))
            ->assertOk()
            ->assertSee('القراءات العشر (اختياري)')
            ->assertSee('قراءة عاصم الكوفي')
            ->assertSee('بدء قراءة عاصم الكوفي (اختياري)')
            ->assertSee('5/30')
            ->assertSee('1/6');
    }

    public function test_admin_enrolls_from_the_journey_page_and_returns_to_it(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $this->completeIjazah($student);

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.enroll'), [
                'student_id' => $student->id,
                'reading' => QuranReading::Khalaf->value,
                'redirect_to' => 'journey',
            ])
            ->assertRedirect(route('admin.quran.journey', $student));

        $this->assertDatabaseHas('program_enrollments', [
            'student_id' => $student->id,
            'program_type' => ProgramType::Readings->value,
            'reading' => QuranReading::Khalaf->value,
        ]);
    }

    public function test_teacher_journey_shows_readings_within_scope_only(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $outsider = $this->student($mosque, $session, 'طالب خارج النطاق');

        // جلسة تسميع سابقة تُدخل الطالب في نطاق الأستاذ.
        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ]);

        $this->enroll($student, QuranReading::Yaqub);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.students.journey', $student))
            ->assertOk()
            ->assertSee('القراءات العشر (اختياري)')
            ->assertSee('قراءة يعقوب الحضرمي')
            ->assertSee('تسجيل في قراءة جديدة');

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.students.journey', $outsider))
            ->assertForbidden();
    }
}
