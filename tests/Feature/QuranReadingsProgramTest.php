<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningProgramStatus;
use App\Enums\QuranReading;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranListeningProgramService;
use App\Services\QuranProgramBatchService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Tests\TestCase;

/**
 * برنامج القراءات (القراءات العشر) — نوع اختياري متقدم بنفس محرك دفعات
 * التأهيلي/الإجازة:
 *
 * - تسجيل يدوي من المدير/الأستاذ مع اختيار قراءة واحدة من العشر.
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

    private function enroll(Student $student, QuranReading $reading = QuranReading::Nafeh): QuranListeningProgram
    {
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

    public function test_admin_enrolls_a_student_in_a_reading_and_the_cycle_is_created(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

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

    public function test_student_role_cannot_enroll_in_readings(): void
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
            ->whereIn('program_type', [ProgramType::Qualifying, ProgramType::Ijazah])
            ->count());

        $this->assertSame(0, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->whereIn('type', [ProgramType::Qualifying, ProgramType::Ijazah])
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

    public function test_readings_tab_renders_the_enrollment_form(): void
    {
        [$mosque, $admin, $session] = $this->mosque();

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.index', ['type' => 'readings']))
            ->assertOk()
            ->assertSee('تسجيل طالب في برنامج القراءات')
            ->assertSee('نافع المدني')
            ->assertSee('خلف العاشر');
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
}
