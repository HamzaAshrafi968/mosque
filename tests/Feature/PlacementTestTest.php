<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranCompletionStatus;
use App\Enums\QuranKhamsaReviewType;
use App\Enums\QuranListeningPlanStatus;
use App\Enums\QuranMemorizationBatchStatus;
use App\Models\HafizProfile;
use App\Models\ProgramEnrollment;
use App\Models\QuranCompletion;
use App\Models\QuranListeningTest;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranKhamsaService;
use App\Services\QuranMemorizationGatingService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Tests\TestCase;

/**
 * الاختبار المباشر للأجزاء المحفوظة مسبقاً (placement test):
 *
 * - الطالب القادم بحفظ سابق يُختبَر مباشرة على جزأي الدفعة دون إنهاء مراجعة 5.
 * - النجاح يثبّت الدفعة ويفتح التالية، والرسوب ينشئ خمسات إعادة للأجزاء الراسبة.
 * - الاختبار المباشر مرفوض بعد بدء مراجعة 5، ولا يُفتح لدفعة غير محفوظة.
 * - تعذّر توليد الدورة (لا دوام/لا أستاذ) لا يُرجع الدفعة الراسبة إلى «مراجعة 5».
 */
class PlacementTestTest extends TestCase
{
    /** @return array{0: Tenant, 1: User, 2: StudySession} */
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();
        $first = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->firstOrFail();

        return [$mosque, $admin, $first];
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque, StudySession $session, string $name = 'الأستاذ محمد'): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id]);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'study_session_id' => $session->id,
            'name' => $name,
        ]);

        return [$user, $teacher];
    }

    private function student(Tenant $mosque, ?StudySession $session, string $name = 'الطالب أحمد'): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $session?->id,
            'name' => $name,
        ]);
    }

    private function gating(): QuranMemorizationGatingService
    {
        return app(QuranMemorizationGatingService::class);
    }

    /** @param array<int, int> $juzNumbers */
    private function memorize(Student $student, array $juzNumbers): void
    {
        $service = app(QuranKhamsaService::class);

        foreach ($juzNumbers as $juz) {
            $service->recordMemorization($student, $juz);
        }

        $this->gating()->sync($student);
    }

    private function batch(Student $student, int $number): QuranMemorizationBatch
    {
        return QuranMemorizationBatch::query()
            ->where('student_id', $student->id)
            ->where('batch_number', $number)
            ->firstOrFail();
    }

    /** @param array<int, string> $results */
    private function placementPayload(array $results): array
    {
        return ['results' => $results];
    }

    /** @return array<int, int> أجزاء خمسات الإعادة المرتبطة بالدفعة. */
    private function retakeJuz(QuranMemorizationBatch $batch): array
    {
        return $batch->retakeReview5()
            ->firstOrFail()
            ->items()
            ->orderBy('juz')
            ->pluck('juz')
            ->map(fn ($juz) => (int) $juz)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $juzNumbers
     * @param  array<int, int>  $failed
     * @return array<int, string>
     */
    private function placementResults(array $juzNumbers, array $failed = []): array
    {
        $results = [];

        foreach ($juzNumbers as $juz) {
            $results[$juz] = in_array($juz, $failed, true) ? 'fail' : 'pass';
        }

        return $results;
    }

    public function test_placement_test_is_available_without_a_cycle_and_opens_the_next_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);

        // لا أستاذ في الدوام: لا خطة ولا مراجعة، لكن الاختبار المباشر متاح.
        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->status);
        $this->assertNull($batch->plan_id);
        $this->assertNull($batch->review_5_id);
        $this->assertTrue($this->gating()->placementTestAllowed($batch));

        // المركز يعرض الأجزاء المحفوظة ونموذج الاختبار المباشر.
        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('الأجزاء المحفوظة للطالب')
            ->assertSee('الجزء 1')
            ->assertSee('الجزء 2')
            ->assertSee('اختبار مباشر للأجزاء المحفوظة مسبقاً');

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload([1 => 'pass', 2 => 'pass']))
            ->assertRedirect();

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $batch->status);
        $this->assertNotNull($batch->passed_at);
        $this->assertFalse($this->gating()->placementTestAllowed($batch));

        $test = QuranListeningTest::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertNull($test->plan_id);
        $this->assertTrue($test->isPass());
        $this->assertSame([1, 2], $test->items()->orderBy('juz')->pluck('juz')->map(fn ($juz) => (int) $juz)->all());

        // الدفعة التالية فُتحت للحفظ.
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 2)->status);
    }

    public function test_placement_test_pass_completes_the_auto_generated_cycle(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);

        $this->assertNotNull($batch->plan_id);
        $this->assertNotNull($batch->review_5_id);
        $this->assertTrue($this->gating()->placementTestAllowed($batch));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload([1 => 'pass', 2 => 'pass']))
            ->assertRedirect();

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $batch->status);
        $this->assertSame(QuranKhamsaReviewType::PostMemorization, $batch->review5()->firstOrFail()->type);
        $this->assertTrue($batch->review5()->firstOrFail()->isCompleted());
        $this->assertSame(QuranListeningPlanStatus::Completed, $batch->plan()->firstOrFail()->status);
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 2)->status);
    }

    public function test_placement_test_without_a_cycle_still_confirms_the_hafiz_automatically(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        // طالب قادم بحفظ كامل (30 جزءاً) بلا أي دورة سابقة.
        $this->memorize($student, range(1, 30));

        $batch = $this->batch($student, 1);

        $this->assertTrue($this->gating()->placementTestAllowed($batch));
        $this->assertSame(range(1, 30), $this->gating()->placementTestJuzNumbers($batch));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload($this->placementResults(range(1, 30))))
            ->assertRedirect();

        // كل الدفعات مثبتة والحافظ معتمد تلقائياً بلا خطوة يدوية.
        $this->assertSame(
            QuranMemorizationBatch::TOTAL_BATCHES,
            QuranMemorizationBatch::query()
                ->where('student_id', $student->id)
                ->where('status', QuranMemorizationBatchStatus::Passed)
                ->count()
        );

        $completion = QuranCompletion::query()->where('student_id', $student->id)->firstOrFail();

        $this->assertSame(QuranCompletionStatus::Confirmed, $completion->status);
        $this->assertSame(1, HafizProfile::where('student_id', $student->id)->count());
        $this->assertTrue(
            ProgramEnrollment::query()
                ->where('student_id', $student->id)
                ->where('program_type', ProgramType::Qualifying)
                ->where('status', ProgramEnrollmentStatus::Active)
                ->exists()
        );
    }

    public function test_placement_test_is_blocked_once_review_5_has_started(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $review = $batch->review5()->firstOrFail();

        app(QuranKhamsaService::class)->completeItem($review->items()->orderBy('from_page')->firstOrFail(), [], $admin);

        $this->assertFalse($this->gating()->placementTestAllowed($batch->refresh()));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload([1 => 'pass', 2 => 'pass']))
            ->assertSessionHasErrors('results');

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->refresh()->status);
        $this->assertSame(0, QuranListeningTest::query()->where('batch_id', $batch->id)->count());
    }

    public function test_placement_test_is_blocked_for_a_locked_future_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        // حفظ الأجزاء 1–4: الدفعة 1 مفتوحة والدفعة 2 مقفلة (لا صف لها).
        $this->memorize($student, [1, 2, 3, 4]);

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $this->batch($student, 1)->status);
        $this->assertFalse(
            QuranMemorizationBatch::query()
                ->where('student_id', $student->id)
                ->where('batch_number', 2)
                ->exists()
        );

        // محاكاة صف دفعة مستقبلية مقفلة (كما يفعل sync عند وجود صف سابق).
        $locked = QuranMemorizationBatch::create([
            'student_id' => $student->id,
            'batch_number' => 2,
            'from_juz' => 3,
            'to_juz' => 4,
            'status' => QuranMemorizationBatchStatus::Locked,
        ]);

        $this->assertFalse($this->gating()->placementTestAllowed($locked));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $locked), $this->placementPayload([3 => 'pass', 4 => 'pass']))
            ->assertSessionHasErrors('results');

        $this->assertSame(QuranMemorizationBatchStatus::Locked, $locked->refresh()->status);
    }

    public function test_placement_test_failure_creates_retake_khamsat_and_keeps_next_batch_locked(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload([1 => 'pass', 2 => 'fail']))
            ->assertRedirect();

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $batch->status);
        $this->assertNotNull($batch->last_test_id);

        $retake = $batch->retakeReview5()->firstOrFail();
        $this->assertSame(QuranKhamsaReviewType::RetakeAfterFail, $retake->type);
        $this->assertSame(4, $retake->items()->count());
        $this->assertSame([2], $retake->items()->orderBy('juz')->pluck('juz')->map(fn ($juz) => (int) $juz)->unique()->values()->all());

        $this->assertFalse(
            QuranMemorizationBatch::query()
                ->where('student_id', $student->id)
                ->where('batch_number', 2)
                ->exists()
        );
    }

    public function test_placement_failure_without_a_teacher_stays_needs_repeat(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);
        $batch = $this->batch($student, 1);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload([1 => 'pass', 2 => 'fail']))
            ->assertRedirect();

        $batch->refresh();

        // لا أستاذ ⇒ تعذّر توليد خمسات الإعادة، لكن الحالة لا ترتد إلى «مراجعة 5».
        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $batch->status);
        $this->assertNull($batch->retake_review_id);

        $this->gating()->sync($student);

        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $batch->refresh()->status);
    }

    public function test_retake_test_after_placement_failure_works_without_a_plan(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);
        $batch = $this->batch($student, 1);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload([1 => 'pass', 2 => 'fail']))
            ->assertRedirect();

        // إضافة أستاذ ثم تخصيص خمسات الإعادة من المركز.
        $this->teacher($mosque, $session);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.retake', $batch), ['mode' => 'failed'])
            ->assertRedirect();

        $batch->refresh();
        $retake = $batch->retakeReview5()->firstOrFail();

        $this->assertSame(4, $retake->items()->count());

        foreach ($retake->items()->orderBy('from_page')->get() as $item) {
            app(QuranKhamsaService::class)->completeItem($item, [], $admin);
        }

        $this->assertSame(QuranMemorizationBatchStatus::ReadyForTest, $batch->refresh()->status);

        // اختبار الإعادة يغطي الأجزاء الراسبة فقط ولا يتطلب خطة استماع.
        $this->actingAs($admin)
            ->post(route('admin.quran.batches.test', $batch), $this->placementPayload([2 => 'pass']))
            ->assertRedirect();

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $batch->status);
        $this->assertNull($batch->plan_id);
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 2)->status);
    }

    public function test_teacher_can_record_the_placement_test_for_a_scoped_student(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        // ربط الطالب بالأستاذ ضمن نطاقه (تسميع سابق).
        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ]);

        $batch = $this->batch($student, 1);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('اختبار مباشر للأجزاء المحفوظة مسبقاً');

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.batches.placement-test', $batch), $this->placementPayload([1 => 'pass', 2 => 'pass']))
            ->assertRedirect();

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $batch->refresh()->status);
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 2)->status);
    }

    public function test_center_shows_a_hint_when_the_cycle_cannot_be_generated(): void
    {
        [$mosque, $admin] = $this->mosque();
        $student = $this->student($mosque, null, 'طالب بلا دوام');

        $this->memorize($student, [1, 2]);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('تعذّر تجهيز دورة الدفعة تلقائياً')
            ->assertSee('الطالب غير مسجّل في دوام');
    }

    public function test_student_profile_is_disabled(): void
    {
        [$mosque, $admin, $session] = $this->mosque();

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $studentUser->id]);

        $this->memorize($student, [1, 2]);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertRedirect(route('portal.disabled'));
    }

    public function test_placement_test_scope_covers_all_consecutive_pre_memorized_batches(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->memorize($student, range(1, 8));

        $batch = $this->batch($student, 1);

        $this->assertTrue($this->gating()->placementTestAllowed($batch));
        $this->assertSame(range(1, 8), $this->gating()->placementTestJuzNumbers($batch));
        $this->assertSame(
            [1, 2, 3, 4],
            collect($this->gating()->placementTestScope($batch))->pluck('batch_number')->all()
        );

        // النموذج يعرض الأجزاء الثمانية موزّعة على بطاقات الدفعات.
        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('اختبار مباشر للأجزاء المحفوظة مسبقاً')
            ->assertSee('الدفعة 1 (الجزآن 1–2)')
            ->assertSee('الدفعة 4 (الجزآن 7–8)')
            ->assertSee('الجزء 8')
            ->assertSee('data-placement-test-form', false)
            ->assertSee('data-placement-summary', false)
            ->assertSee('تعليم الكل ناجح')
            ->assertSee('تسجيل نتيجة الاختبار المباشر (8 أجزاء)');
    }

    public function test_placement_test_all_pass_certifies_every_covered_batch_and_opens_the_next(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, range(1, 8));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $this->batch($student, 1)), $this->placementPayload($this->placementResults(range(1, 8))))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '1–8') && str_contains($message, '4 دفعة'));

        foreach (range(1, 4) as $number) {
            $batch = $this->batch($student, $number);
            $this->assertSame(QuranMemorizationBatchStatus::Passed, $batch->status);
            $this->assertNotNull($batch->passed_at);
            $this->assertNotNull($batch->last_test_id);
        }

        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 5)->status);

        $this->assertCount(4, QuranListeningTest::query()->where('student_id', $student->id)->get());

        $juzPerTest = collect(range(1, 4))
            ->map(fn (int $number) => $this->batch($student, $number)->lastTest
                ->items()
                ->orderBy('juz')
                ->pluck('juz')
                ->map(fn ($juz) => (int) $juz)
                ->all())
            ->all();

        $this->assertSame([[1, 2], [3, 4], [5, 6], [7, 8]], $juzPerTest);
    }

    public function test_placement_test_partial_failure_certifies_passed_batches_and_retakes_failed_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, range(1, 8));

        $this->actingAs($admin)
            ->post(
                route('admin.quran.batches.placement-test', $this->batch($student, 1)),
                $this->placementPayload($this->placementResults(range(1, 8), failed: [2, 7]))
            )
            ->assertRedirect();

        $first = $this->batch($student, 1);
        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $first->status);
        $this->assertSame([2], $this->retakeJuz($first));

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 2)->status);
        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 3)->status);

        $fourth = $this->batch($student, 4);
        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $fourth->status);
        $this->assertSame([7], $this->retakeJuz($fourth));

        // الدفعة الراسبة اللاحقة تبقى ظاهرة «تحتاج إعادة» ولا تقفلها sync.
        $this->assertSame(
            QuranMemorizationBatchStatus::NeedsRepeat,
            $this->gating()->sync($student)->firstWhere('batch_number', 4)['status']
        );

        // الدفعة الحالية هي أول دفعة راسبة، والدفعة 5 لم تُفتح للحفظ بعد.
        $this->assertSame(1, $this->gating()->currentBatch($student)?->batch_number);

        // إتمام خمسات إعادة الجزء 2 ثم اختبار الإعادة يثبّت الدفعة 1.
        $retake = $first->retakeReview5()->firstOrFail();

        foreach ($retake->items()->orderBy('from_page')->get() as $item) {
            app(QuranKhamsaService::class)->completeItem($item, [], $admin);
        }

        $this->assertSame(QuranMemorizationBatchStatus::ReadyForTest, $first->refresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.test', $first), $this->placementPayload([2 => 'pass']))
            ->assertRedirect();

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $first->refresh()->status);
        $this->assertSame(4, $this->gating()->currentBatch($student)?->batch_number);
    }

    public function test_placement_test_scope_stops_at_the_first_gap(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2, 3, 4, 7, 8]);

        $batch = $this->batch($student, 1);

        $this->assertTrue($this->gating()->placementTestAllowed($batch));
        $this->assertSame([1, 2, 3, 4], $this->gating()->placementTestJuzNumbers($batch));
    }

    public function test_placement_test_with_an_odd_memorized_count_stops_at_the_incomplete_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, range(1, 5));

        $batch = $this->batch($student, 1);
        $this->assertSame([1, 2, 3, 4], $this->gating()->placementTestJuzNumbers($batch));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $batch), $this->placementPayload($this->placementResults([1, 2, 3, 4])))
            ->assertRedirect();

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 1)->status);
        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 2)->status);
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 3)->status);
    }

    public function test_placement_test_missing_result_rolls_back_without_partial_certification(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, range(1, 8));

        $this->actingAs($admin)
            ->post(
                route('admin.quran.batches.placement-test', $this->batch($student, 1)),
                $this->placementPayload($this->placementResults(range(1, 7)))
            )
            ->assertSessionHasErrors('results');

        $this->assertSame(0, QuranListeningTest::query()->where('student_id', $student->id)->count());
        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $this->batch($student, 1)->status);
        $this->assertSame(1, QuranMemorizationBatch::query()->where('student_id', $student->id)->count());
    }

    public function test_placement_test_for_thirty_juz_confirms_the_hafiz_automatically(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, range(1, 30));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $this->batch($student, 1)), $this->placementPayload($this->placementResults(range(1, 30))))
            ->assertRedirect();

        $this->assertSame(
            QuranMemorizationBatch::TOTAL_BATCHES,
            QuranMemorizationBatch::query()
                ->where('student_id', $student->id)
                ->where('status', QuranMemorizationBatchStatus::Passed)
                ->count()
        );

        $completion = QuranCompletion::query()->where('student_id', $student->id)->firstOrFail();

        $this->assertSame(QuranCompletionStatus::Confirmed, $completion->status);
        $this->assertSame($admin->id, $completion->confirmed_by);
        $this->assertSame(1, HafizProfile::where('student_id', $student->id)->count());
        $this->assertTrue(
            ProgramEnrollment::query()
                ->where('student_id', $student->id)
                ->where('program_type', ProgramType::Qualifying)
                ->where('status', ProgramEnrollmentStatus::Active)
                ->exists()
        );
    }

    public function test_student_form_with_eight_memorized_juz_opens_an_eight_juz_placement_test(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        // نفس ما يرسله نموذج الطالب عند اختيار «مقدار الحفظ: 8 أجزاء».
        $this->actingAs($admin)->patch(route('admin.students.update', $student), [
            'name' => $student->name,
            'gender' => $student->gender,
            'memorized_juz' => 8,
            'memorized_juz_numbers_present' => 1,
            'memorized_juz_numbers' => range(1, 8),
        ])->assertRedirect();

        $this->assertSame(range(1, 8), $this->gating()->placementTestJuzNumbers($this->batch($student, 1)));

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('تسجيل نتيجة الاختبار المباشر (8 أجزاء)');
    }

    public function test_placement_test_cannot_be_recorded_twice(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, range(1, 8));

        $payload = $this->placementPayload($this->placementResults(range(1, 8)));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $this->batch($student, 1)), $payload)
            ->assertRedirect();

        // إعادة الإرسال بعد التثبيت مرفوضة ولا تُنشئ اختبارات إضافية.
        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $this->batch($student, 1)), $payload)
            ->assertSessionHasErrors('results');

        $this->assertSame(4, QuranListeningTest::query()->where('student_id', $student->id)->count());
    }

    public function test_placement_test_sends_a_single_aggregate_notification(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $studentUser->id]);

        $this->memorize($student, range(1, 8));

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.placement-test', $this->batch($student, 1)), $this->placementPayload($this->placementResults(range(1, 8))))
            ->assertRedirect();

        $titles = $studentUser->notifications()->get()->pluck('data.title');

        $this->assertSame(1, $titles->filter(fn ($title) => $title === 'نجاح في الاختبار المباشر')->count());
        $this->assertSame(0, $titles->filter(fn ($title) => $title === 'نجاح في اختبار الدفعة')->count());
    }
}
