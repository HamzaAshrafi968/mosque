<?php

namespace Tests\Feature;

use App\Enums\QuranKhamsaReviewType;
use App\Enums\QuranListeningPlanStatus;
use App\Enums\QuranMemorizationBatchStatus;
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

    public function test_student_profile_shows_pre_memorized_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $studentUser->id]);

        $this->memorize($student, [1, 2]);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('محفوظ مسبقاً')
            ->assertSee('✓ محفوظ');
    }
}
