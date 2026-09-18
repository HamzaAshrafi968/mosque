<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningProgramStatus;
use App\Models\Classroom;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranListeningProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * «برامج الاستماع» (تدريبي/إجازة/تأهيلي):
 *
 * - 30 جزءاً ÷ 5 = 6 دفعات، والدفعة k لا تُفتح إلا بنجاح اختبار k-1.
 * - لا اختبار قبل استماع الأجزاء الخمسة كاملة.
 * - الرسوب يرجع الأجزاء الراسبة لإعادة الاستماع ثم إعادة الاختبار.
 * - إتمام الدفعة السادسة يُتمّ البرنامج ويحوّل للبرنامج التالي تلقائياً.
 * - «وين موصل» و«شو مسمع»: شبكة الأجزاء + سجل الاستماع + سجل الاختبارات.
 */
class QuranListeningProgramTest extends TestCase
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

    private function service(): QuranListeningProgramService
    {
        return app(QuranListeningProgramService::class);
    }

    private function enroll(Student $student, User $actor): QuranListeningProgram
    {
        return $this->service()->enrollTraining($student, $actor);
    }

    private function batch(QuranListeningProgram $program, int $number): QuranListeningProgramBatch
    {
        return $program->batches()->where('batch_number', $number)->firstOrFail();
    }

    private function listenAll(QuranListeningProgramBatch $batch, User $actor): void
    {
        foreach ($batch->items()->orderBy('juz')->get() as $item) {
            if ($item->canBeListened()) {
                $this->service()->markListened($item, $actor);
            }
        }
    }

    private function passBatch(QuranListeningProgramBatch $batch, User $actor): void
    {
        $this->listenAll($batch, $actor);

        $results = [];

        foreach ($batch->juzNumbers() as $juz) {
            $results[$juz] = 'pass';
        }

        $this->service()->recordBatchTest($batch, $results, $actor);
    }

    public function test_training_enrollment_creates_six_batches_and_thirty_items(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $program = $this->enroll($student, $admin);

        $this->assertSame(ProgramType::Training, $program->type);
        $this->assertTrue($program->isActive());
        $this->assertSame(6, $program->batches()->count());
        $this->assertSame(30, $program->items()->count());

        $first = $this->batch($program, 1);
        $this->assertSame(1, $first->from_juz);
        $this->assertSame(5, $first->to_juz);
        $this->assertSame(QuranListeningBatchStatus::Listening, $first->status);
        $this->assertSame(5, $first->items()->where('status', QuranListeningItemStatus::Available->value)->count());

        $second = $this->batch($program, 2);
        $this->assertSame(6, $second->from_juz);
        $this->assertSame(10, $second->to_juz);
        $this->assertSame(QuranListeningBatchStatus::Locked, $second->status);
        $this->assertSame(5, $second->items()->where('status', QuranListeningItemStatus::Locked->value)->count());

        $enrollment = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Training)
            ->firstOrFail();

        $this->assertSame($enrollment->id, $program->enrollment_id);
        $this->assertSame(ProgramEnrollmentStatus::Active, $enrollment->status);
    }

    public function test_second_batch_is_locked_until_the_first_test_passes(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, $admin);

        $first = $this->batch($program, 1);
        $second = $this->batch($program, 2);

        try {
            $this->service()->markListened($second->items()->firstOrFail(), $admin);
            $this->fail('تسجيل استماع جزء في دفعة مقفلة يجب أن يُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item', $exception->errors());
        }

        $this->passBatch($first, $admin);

        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
        $this->assertSame(QuranListeningBatchStatus::Listening, $second->fresh()->status);
        $this->assertSame(5, $second->items()->where('status', QuranListeningItemStatus::Available->value)->count());
    }

    public function test_test_is_rejected_before_all_five_juz_are_listened(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, $admin);

        $first = $this->batch($program, 1);
        $items = $first->items()->orderBy('juz')->get();

        $this->service()->markListened($items[0], $admin);
        $this->service()->markListened($items[1], $admin);

        $this->assertSame(QuranListeningBatchStatus::Listening, $first->fresh()->status);

        try {
            $this->service()->recordBatchTest($first, [1 => 'pass', 2 => 'pass', 3 => 'pass', 4 => 'pass', 5 => 'pass'], $admin);
            $this->fail('الاختبار قبل استماع الأجزاء الخمسة يجب أن يُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        $this->assertSame(0, $program->tests()->count());
    }

    public function test_failed_juz_are_returned_for_relisten_then_pass_opens_next_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, $admin);

        $first = $this->batch($program, 1);
        $this->listenAll($first, $admin);

        $this->assertSame(QuranListeningBatchStatus::ReadyForTest, $first->fresh()->status);

        $test = $this->service()->recordBatchTest($first, [
            1 => 'pass', 2 => 'fail', 3 => 'pass', 4 => 'fail', 5 => 'pass',
        ], $admin);

        $this->assertFalse($test->isPass());
        $this->assertSame(60.0, (float) $test->score);
        $this->assertSame(QuranListeningBatchStatus::NeedsRepeat, $first->fresh()->status);

        $failed = $first->items()->whereIn('juz', [2, 4])->get();
        $this->assertCount(2, $failed);

        foreach ($failed as $item) {
            $this->assertSame(QuranListeningItemStatus::NeedsRepeat, $item->status);
        }

        // الاختبار مقفل حتى إعادة الاستماع.
        try {
            $this->service()->recordBatchTest($first, [1 => 'pass', 2 => 'pass', 3 => 'pass', 4 => 'pass', 5 => 'pass'], $admin);
            $this->fail('إعادة الاختبار قبل إعادة استماع الأجزاء الراسبة يجب أن تُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        foreach ($failed as $item) {
            $this->service()->markListened($item->fresh(), $admin);
        }

        $this->assertSame(QuranListeningBatchStatus::ReadyForTest, $first->fresh()->status);

        $retest = $this->service()->recordBatchTest($first, [1 => 'pass', 2 => 'pass', 3 => 'pass', 4 => 'pass', 5 => 'pass'], $admin);

        $this->assertTrue($retest->isPass());
        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
        $this->assertSame(QuranListeningBatchStatus::Listening, $this->batch($program, 2)->fresh()->status);
    }

    public function test_completing_the_sixth_batch_completes_program_and_enrolls_ijazah(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, $admin);

        for ($number = 1; $number <= 6; $number++) {
            $this->passBatch($this->batch($program, $number), $admin);
        }

        $this->assertSame(QuranListeningProgramStatus::Completed, $program->fresh()->status);

        $training = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Training)
            ->firstOrFail();

        $this->assertSame(ProgramEnrollmentStatus::Completed, $training->status);

        $ijazah = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->first();

        $this->assertNotNull($ijazah);
        $this->assertNotNull($this->service()->activeProgram($student, ProgramType::Ijazah));
    }

    public function test_qualifying_enrollment_gets_a_listening_cycle_lazily(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $enrollment = ProgramEnrollment::create([
            'student_id' => $student->id,
            'program_type' => ProgramType::Qualifying,
            'started_at' => Carbon::today()->format('Y-m-d'),
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        $programs = $this->service()->programsForStudent($student);
        $program = $programs->firstWhere('type', ProgramType::Qualifying);

        $this->assertNotNull($program);
        $this->assertSame($enrollment->id, $program->enrollment_id);
        $this->assertSame(6, $program->batches()->count());
        $this->assertSame(30, $program->items()->count());

        // Idempotent: لا تتكرر الدورة.
        $this->service()->programsForStudent($student);

        $this->assertSame(1, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Qualifying)
            ->count());
    }

    public function test_ijazah_cycle_completion_enrolls_qualifying(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $enrollment = ProgramEnrollment::create([
            'student_id' => $student->id,
            'program_type' => ProgramType::Ijazah,
            'started_at' => Carbon::today()->format('Y-m-d'),
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        $program = $this->service()->ensureForEnrollment($enrollment);

        for ($number = 1; $number <= 6; $number++) {
            $this->passBatch($this->batch($program, $number), $admin);
        }

        $this->assertSame(QuranListeningProgramStatus::Completed, $program->fresh()->status);

        $this->assertTrue(ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Qualifying)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->exists());

        // التقييمات الشهرية للإجازة لا تتأثر: الالتحاق يبقى نشطاً.
        $this->assertSame(ProgramEnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_progress_and_history_expose_reached_and_listened_details(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, $admin);

        $first = $this->batch($program, 1);
        $items = $first->items()->orderBy('juz')->get();

        $this->service()->markListened($items[0], $admin);
        $this->service()->markListened($items[1], $admin);
        $this->service()->recordProgress($items[0]->fresh(), 300);

        $progress = $this->service()->progress($program);

        $this->assertSame(2, $progress['summary']['listened_juz']);
        $this->assertSame(3, $progress['summary']['next_juz']);
        $this->assertSame(6, $progress['summary']['total_batches']);

        $cell = $progress['juzGrid']->firstWhere('juz', 1);
        $this->assertSame(QuranListeningItemStatus::Listened, $cell['status']);
        $this->assertSame($admin->name, $cell['listened_by']);
        $this->assertSame(300, $cell['listen_seconds']);
        $this->assertSame(1, $cell['batch_number']);

        $history = $this->service()->history($program);
        $this->assertSame(2, $history['listeningLog']->count());
        $this->assertTrue($history['tests']->isEmpty());

        $this->passBatch($first, $admin);

        $history = $this->service()->history($program);
        $this->assertSame(1, $history['tests']->count());
        $this->assertSame(5, $history['tests']->first()->items->count());
    }

    public function test_student_portal_is_read_only(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $studentUser->id]);

        $program = $this->enroll($student, $admin);
        $item = $this->batch($program, 1)->items()->where('juz', 1)->firstOrFail();

        $this->actingAs($studentUser)
            ->get(route('student.quran-programs.index'))
            ->assertOk()
            ->assertSee('برامجي')
            ->assertSee('الجزء 1')
            ->assertDontSee('فتح الصفحات وتسجيل الأخطاء');

        // الطالب يفتح صفحات المصحف (معاينة الجزء/الصفحة) للقراءة فقط.
        $this->actingAs($studentUser)
            ->get(route('quran.pages.show', 1))
            ->assertOk();

        $this->actingAs($studentUser)
            ->get(route('quran.pages.preview', ['page' => 1, 'to' => 5]))
            ->assertOk();

        // لا يملك الطالب أي مسار تسميع — التسميع للأستاذ/المدير فقط.
        $this->assertFalse(Route::has('student.quran-programs.items.listen'));
        $this->assertFalse(Route::has('student.quran-programs.items.tasmee'));
        $this->assertSame(QuranListeningItemStatus::Available, $item->fresh()->status);
    }

    public function test_teacher_in_scope_can_record_tasmee_and_test(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'صف']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'شعبة']);

        SectionTeacher::create([
            'tenant_id' => $mosque->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'role' => 'lead',
            'status' => 'active',
        ]);

        $student->update(['classroom_id' => $classroom->id, 'section_id' => $section->id]);

        SectionStudent::create([
            'tenant_id' => $mosque->id,
            'section_id' => $section->id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        $program = $this->enroll($student, $admin);
        $first = $this->batch($program, 1);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.programs.index', ['type' => 'training', 'student_id' => $student->id]))
            ->assertOk();

        foreach ($first->items()->orderBy('juz')->get() as $item) {
            $this->actingAs($teacherUser)
                ->post(route('teacher.quran.programs.items.tasmee.store', $item), ['date' => now()->toDateString()])
                ->assertRedirect();
        }

        $results = [];

        foreach ($first->juzNumbers() as $juz) {
            $results[$juz] = 'pass';
        }

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.programs.batches.test', $first), ['results' => $results])
            ->assertRedirect();

        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
    }

    public function test_admin_center_and_program_page_render(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->enroll($student, $admin);

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.index', ['type' => 'training']))
            ->assertOk()
            ->assertSee('برامج الاستماع')
            ->assertSee('تسجيل طالب في البرنامج التدريبي');

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.show', $program))
            ->assertOk()
            ->assertSee('الدفعات الست')
            ->assertSee('شو مسمع')
            ->assertSee('صفحات المصحف')
            ->assertSee('خريطة الأجزاء الثلاثين');
    }

    public function test_teacher_and_student_access_is_isolated(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $other = $this->student($mosque, $session, 'طالب آخر');

        $program = $this->enroll($student, $admin);
        $item = $this->batch($program, 1)->items()->firstOrFail();

        // الأستاذ بلا شعب → خارج النطاق.
        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.programs.index', ['student_id' => $student->id]))
            ->assertForbidden();

        // طالب آخر لا يرى برنامج غيره في بوابته.
        $otherUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $other->update(['user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->get(route('student.quran-programs.index'))
            ->assertOk()
            ->assertDontSee('الطالب أحمد');

        $this->assertSame(QuranListeningItemStatus::Available, $item->fresh()->status);
    }
}
