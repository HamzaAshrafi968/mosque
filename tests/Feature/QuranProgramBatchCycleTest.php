<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningProgramStatus;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Models\StudentJuzMemorization;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranListeningProgramService;
use App\Services\QuranProgramBatchService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * دورة التأهيلي/الإجازة (QuranProgramBatchService) — دفعات 5 أجزاء:
 *
 * - تسميع كل جزء مع الأخطاء ← اكتمال أجزاء الدفعة ← اختبار تراكمي 1..5k.
 * - لا اختبار قبل اكتمال التسميع، والراسب فقط يُعاد تسميعه ثم يُعاد اختباره.
 * - التأهيلي عند 1–30: ختم (إنهاء التحاق + تحويل للإجازة)، والإجازة: إنهاء فقط.
 * - لا خمسات ولا خطط استماع في هذه الدورة.
 */
class QuranProgramBatchCycleTest extends TestCase
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

    private function teacher(Tenant $mosque, StudySession $session): Teacher
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id]);

        return Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'study_session_id' => $session->id,
            'name' => 'الأستاذ محمد',
        ]);
    }

    private function student(Tenant $mosque, StudySession $session, string $name = 'الطالب أحمد'): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $session->id,
            'name' => $name,
        ]);
    }

    private function engine(): QuranProgramBatchService
    {
        return app(QuranProgramBatchService::class);
    }

    private function programFor(Student $student, ProgramType $type): QuranListeningProgram
    {
        $enrollment = ProgramEnrollment::create([
            'student_id' => $student->id,
            'program_type' => $type,
            'started_at' => Carbon::today()->format('Y-m-d'),
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        return app(QuranListeningProgramService::class)->ensureForEnrollment($enrollment);
    }

    private function batch(QuranListeningProgram $program, int $number): QuranListeningProgramBatch
    {
        return $program->batches()->where('batch_number', $number)->firstOrFail();
    }

    private function tasmeeAll(QuranListeningProgramBatch $batch, User $actor, Teacher $teacher): void
    {
        foreach ($batch->items()->orderBy('juz')->get() as $item) {
            if ($item->canBeListened() || $item->isNeedsRepeat()) {
                $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $actor, $teacher->id);
            }
        }
    }

    private function passBatch(QuranListeningProgramBatch $batch, User $actor, Teacher $teacher): void
    {
        $this->tasmeeAll($batch, $actor, $teacher);

        $results = [];

        foreach ($this->engine()->testScopeJuzNumbers($batch->fresh()) as $juz) {
            $results[$juz] = 'pass';
        }

        $this->engine()->recordBatchTest($batch->fresh(), $results, $actor);
    }

    /** @param array<int, int> $juz */
    private function memorize(Student $student, array $juz): void
    {
        foreach ($juz as $number) {
            StudentJuzMemorization::create([
                'student_id' => $student->id,
                'juz' => $number,
                'memorized_at' => now()->toDateString(),
                'source' => StudentJuzMemorization::SOURCE_MANUAL,
            ]);
        }
    }

    public function test_cumulative_test_scope_expands_every_five_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $expected = [
            1 => range(1, 5),
            2 => range(1, 10),
            3 => range(1, 15),
            4 => range(1, 20),
            5 => range(1, 25),
            6 => range(1, 30),
        ];

        foreach ($expected as $number => $scope) {
            $batch = $this->batch($program, $number);

            $this->assertSame($scope, $this->engine()->testScopeJuzNumbers($batch), "نطاق الدفعة {$number}");
            $this->assertSame($scope, $this->engine()->cumulativeJuzNumbers($batch));

            $this->passBatch($batch, $admin, $teacher);

            $this->assertSame(QuranListeningBatchStatus::Passed, $batch->fresh()->status);
        }

        $this->assertSame(QuranListeningProgramStatus::Completed, $program->fresh()->status);
    }

    public function test_test_is_locked_until_the_five_juz_are_fully_tasmee(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $first = $this->batch($program, 1);
        $item = $first->items()->where('juz', 1)->firstOrFail();

        $this->engine()->recordTasmee($item, ['date' => now()->toDateString()], $admin, $teacher->id);

        $this->assertSame(QuranListeningBatchStatus::Listening, $first->fresh()->status);

        try {
            $this->engine()->recordBatchTest($first->fresh(), [1 => 'pass', 2 => 'pass', 3 => 'pass', 4 => 'pass', 5 => 'pass'], $admin);
            $this->fail('الاختبار قبل اكتمال تسميع الأجزاء الخمسة يجب أن يُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        $this->assertSame(0, $program->tests()->count());
    }

    public function test_failed_juz_are_the_only_ones_repeated_and_retested(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $first = $this->batch($program, 1);
        $this->tasmeeAll($first, $admin, $teacher);

        $this->assertSame(QuranListeningBatchStatus::ReadyForTest, $first->fresh()->status);

        $test = $this->engine()->recordBatchTest($first->fresh(), [
            1 => 'pass', 2 => 'pass', 3 => 'fail', 4 => 'pass', 5 => 'fail',
        ], $admin);

        $this->assertFalse($test->isPass());
        $this->assertSame(60.0, (float) $test->score);
        $this->assertSame(QuranListeningBatchStatus::NeedsRepeat, $first->fresh()->status);

        // الراسب فقط يعاد، والناجح يبقى ناجحاً.
        $this->assertSame([3, 5], $this->engine()->failedJuzNumbers($first->fresh()));
        $this->assertSame([3, 5], $this->engine()->testScopeJuzNumbers($first->fresh()));

        $this->assertSame(QuranListeningItemStatus::Passed, $first->items()->where('juz', 1)->firstOrFail()->status);
        $this->assertSame(QuranListeningItemStatus::NeedsRepeat, $first->items()->where('juz', 3)->firstOrFail()->status);
        $this->assertSame(QuranListeningItemStatus::NeedsRepeat, $first->items()->where('juz', 5)->firstOrFail()->status);

        // الاختبار مقفل حتى إعادة تسميع الأجزاء الراسبة.
        try {
            $this->engine()->recordBatchTest($first->fresh(), [3 => 'pass', 5 => 'pass'], $admin);
            $this->fail('إعادة الاختبار قبل إعادة تسميع الراسب يجب أن تُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        foreach ([3, 5] as $juz) {
            $item = $first->items()->where('juz', $juz)->firstOrFail();
            $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        }

        $this->assertSame(QuranListeningBatchStatus::ReadyForTest, $first->fresh()->status);

        // إعادة الاختبار على الأجزاء الراسبة فقط.
        $retest = $this->engine()->recordBatchTest($first->fresh(), [3 => 'pass', 5 => 'pass'], $admin);

        $this->assertTrue($retest->isPass());
        $this->assertSame([3, 5], $retest->items->pluck('juz')->sort()->values()->all());
        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
        $this->assertSame(QuranListeningBatchStatus::Listening, $this->batch($program, 2)->fresh()->status);
    }

    public function test_retest_failure_keeps_the_loop_until_all_pass(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $first = $this->batch($program, 1);
        $this->tasmeeAll($first, $admin, $teacher);
        $this->engine()->recordBatchTest($first->fresh(), [1 => 'pass', 2 => 'fail', 3 => 'pass', 4 => 'fail', 5 => 'pass'], $admin);

        // إعادة تسميع الراسبين ثم رسوب أحدهما مجدداً.
        foreach ([2, 4] as $juz) {
            $item = $first->items()->where('juz', $juz)->firstOrFail();
            $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        }

        $this->engine()->recordBatchTest($first->fresh(), [2 => 'fail', 4 => 'pass'], $admin);

        $this->assertSame([2], $this->engine()->failedJuzNumbers($first->fresh()));
        $this->assertSame(QuranListeningBatchStatus::NeedsRepeat, $first->fresh()->status);

        // إعادة تسميع 2 ثم النجاح.
        $item = $first->items()->where('juz', 2)->firstOrFail();
        $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        $this->engine()->recordBatchTest($first->fresh(), [2 => 'pass'], $admin);

        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
        $this->assertSame(0, $first->items()->where('status', '!=', QuranListeningItemStatus::Passed->value)->count());
    }

    public function test_tasmee_is_rejected_outside_the_current_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $lockedItem = $this->batch($program, 2)->items()->where('juz', 6)->firstOrFail();

        try {
            $this->engine()->recordTasmee($lockedItem, ['date' => now()->toDateString()], $admin, $teacher->id);
            $this->fail('التسميع في دفعة مقفلة يجب أن يُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item', $exception->errors());
        }

        $this->assertSame(QuranListeningItemStatus::Locked, $lockedItem->fresh()->status);
    }

    public function test_qualifying_completion_seals_and_enrolls_ijazah(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        for ($number = 1; $number <= 6; $number++) {
            $this->passBatch($this->batch($program, $number), $admin, $teacher);
        }

        $this->assertSame(QuranListeningProgramStatus::Completed, $program->fresh()->status);

        $qualifying = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Qualifying)
            ->firstOrFail();

        $this->assertSame(ProgramEnrollmentStatus::Completed, $qualifying->status);

        // ختم التأهيل: تحويل تلقائي للإجازة + توليد دورتها.
        $this->assertTrue(ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->exists());

        $this->assertSame(1, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Ijazah)
            ->count());

        // Idempotent: إعادة المزامنة لا تكرر الالتحاق.
        $this->engine()->sync($program->fresh());

        $this->assertSame(1, ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Ijazah)
            ->count());
    }

    public function test_ijazah_completion_completes_enrollment_without_seal(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Ijazah);

        for ($number = 1; $number <= 6; $number++) {
            $this->passBatch($this->batch($program, $number), $admin, $teacher);
        }

        $this->assertSame(QuranListeningProgramStatus::Completed, $program->fresh()->status);

        $ijazah = ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Ijazah)
            ->firstOrFail();

        $this->assertSame(ProgramEnrollmentStatus::Completed, $ijazah->status);

        // بلا ختم تأهيل وبلا تحويل تلقائي.
        $this->assertFalse(ProgramEnrollment::query()
            ->where('student_id', $student->id)
            ->where('program_type', ProgramType::Qualifying)
            ->exists());
    }

    public function test_placement_test_covers_consecutive_memorized_batches(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $this->memorize($student, range(1, 10));

        $first = $this->batch($program, 1);

        $this->assertTrue($this->engine()->placementTestAllowed($first));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $this->engine()->placementTestJuzNumbers($first));

        $results = [];

        foreach (range(1, 10) as $juz) {
            $results[$juz] = 'pass';
        }

        $tests = $this->engine()->recordPlacementTest($first, $results, $admin);

        $this->assertCount(2, $tests);
        $this->assertTrue($tests->every(fn ($test) => $test->isPass()));
        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
        $this->assertSame(QuranListeningBatchStatus::Passed, $this->batch($program, 2)->fresh()->status);
        $this->assertSame(QuranListeningBatchStatus::Listening, $this->batch($program, 3)->fresh()->status);
    }

    public function test_placement_partial_failure_repeats_failed_juz_only(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $this->memorize($student, range(1, 5));

        $first = $this->batch($program, 1);

        $this->assertTrue($this->engine()->placementTestAllowed($first));

        $this->engine()->recordPlacementTest($first, [
            1 => 'pass', 2 => 'pass', 3 => 'fail', 4 => 'fail', 5 => 'pass',
        ], $admin);

        $this->assertSame(QuranListeningBatchStatus::NeedsRepeat, $first->fresh()->status);
        $this->assertSame([3, 4], $this->engine()->testScopeJuzNumbers($first->fresh()));
        $this->assertSame(QuranListeningItemStatus::NeedsRepeat, $first->items()->where('juz', 3)->firstOrFail()->status);

        // إعادة تسميع الراسبين ثم إعادة اختبارهما فقط.
        foreach ([3, 4] as $juz) {
            $item = $first->items()->where('juz', $juz)->firstOrFail();
            $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        }

        $retest = $this->engine()->recordBatchTest($first->fresh(), [3 => 'pass', 4 => 'pass'], $admin);

        $this->assertTrue($retest->isPass());
        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
    }

    public function test_general_tasmee_form_links_to_the_program_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        // تسميع كامل الجزء 1 من الشاشة العامة: مسموح داخل دفعة الدورة رغم عدم وجود دفعات حفظ مفتوحة.
        $this->actingAs($admin)
            ->post(route('admin.quran.tasmee.store'), [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'type' => 'new',
                'date' => now()->toDateString(),
                'from_page' => 1,
                'to_page' => 21,
            ])
            ->assertRedirect(route('admin.quran.programs.show', $program));

        $first = $this->batch($program, 1);
        $session = QuranRecitationSession::query()
            ->where('student_id', $student->id)
            ->whereNotNull('program_batch_id')
            ->firstOrFail();

        $this->assertSame($first->id, $session->program_batch_id);
        $this->assertSame(QuranListeningItemStatus::Listened, $first->items()->where('juz', 1)->firstOrFail()->status);

        // نطاق خارج الدفعة الحالية (الجزء 6) مرفوض.
        $this->actingAs($admin)
            ->post(route('admin.quran.tasmee.store'), [
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'type' => 'new',
                'date' => now()->toDateString(),
                'from_page' => 102,
                'to_page' => 120,
            ])
            ->assertSessionHasErrors('from_page');
    }

    public function test_admin_tasmee_page_renders_the_error_recorder(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $teacher = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $item = $this->batch($program, 1)->items()->where('juz', 1)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.items.tasmee', $item))
            ->assertOk()
            ->assertSee('تسميع الجزء مع تسجيل الأخطاء')
            ->assertSee('حفظ التسميع وإنهاء الجزء')
            ->assertSee('data-preview-viewer', false);

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.items.tasmee.store', $item), [
                'date' => now()->toDateString(),
                'word_statuses' => ['fake-word:1' => 'incorrect'],
            ])
            ->assertRedirect(route('admin.quran.programs.show', $program));

        $this->assertSame(QuranListeningItemStatus::Listened, $item->fresh()->status);
        $this->assertNotNull($item->fresh()->quran_recitation_session_id);
    }

    public function test_student_portal_shows_the_cumulative_cycle_read_only(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $studentUser->id]);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.show', $program))
            ->assertOk()
            ->assertSee('الاختبار التراكمي')
            ->assertSee('التسميع مع المعلم')
            ->assertSee('خريطة الأجزاء الثلاثين');

        $this->actingAs($studentUser)
            ->get(route('student.quran-programs.index'))
            ->assertOk()
            ->assertSee('الاختبار التراكمي')
            ->assertDontSee('فتح الصفحات وتسجيل الأخطاء');
    }
}
