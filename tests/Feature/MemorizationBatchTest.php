<?php

namespace Tests\Feature;

use App\Enums\QuranListeningPlanStatus;
use App\Enums\QuranMemorizationBatchStatus;
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
use App\Services\QuranSettingsService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Database\Seeders\QuranDataSeeder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * دورة دفعات الحفظ (كل جزأين = دفعة):
 *
 * - الاشتقاق الديناميكي: لا حالات خاصة لأي عدد أجزاء (26 جزءاً = 13 دفعة).
 * - اكتمال الجزأين يولّد مراجعة 5 وخطة اختبار تلقائياً.
 * - النجاح يُحسب في الـ Backend: score من العناصر >= حد نجاح الجامع.
 * - الرسوب يبقي الدفعة التالية مقفلة، مع إمكانية توليد مراجعة جديدة.
 * - تسميع «جديد» ممنوع خارج نطاق الدفعة الحالية (فرض على الـ Backend).
 * - نجاح الدفعة الأخيرة (29–30) يفتح طلب إتمام الحفظ.
 */
class MemorizationBatchTest extends TestCase
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

    private function student(Tenant $mosque, StudySession $session, string $name = 'الطالب أحمد'): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $session->id,
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

    /** @param array<int, int> $passedBatches */
    private function seedPassedBatches(Student $student, array $passedBatches): void
    {
        foreach ($passedBatches as $number) {
            $range = QuranMemorizationBatch::juzRange($number);

            QuranMemorizationBatch::create([
                'student_id' => $student->id,
                'batch_number' => $number,
                'from_juz' => $range['from'],
                'to_juz' => $range['to'],
                'status' => QuranMemorizationBatchStatus::Passed,
                'passed_at' => now(),
            ]);
        }
    }

    /** إنهاء جميع خمسات المراجعة العادية (خمسات ما بعد الحفظ). */
    private function completeReview(QuranMemorizationBatch $batch, User $actor): void
    {
        $review = $batch->review5()->firstOrFail();

        foreach ($review->items()->orderBy('from_page')->get() as $item) {
            app(QuranKhamsaService::class)->completeItem($item, [], $actor);
        }
    }

    /** إنهاء جميع خمسات الإعادة المرتبطة بالدفعة. */
    private function completeRetakeReview(QuranMemorizationBatch $batch, User $actor): void
    {
        $review = $batch->retakeReview5()->firstOrFail();

        foreach ($review->items()->orderBy('from_page')->get() as $item) {
            app(QuranKhamsaService::class)->completeItem($item, [], $actor);
        }
    }

    /** @param array<int, string> $results juz => pass|fail */
    private function submitTest(QuranMemorizationBatch $batch, User $actor, array $results): QuranListeningTest
    {
        return $this->gating()->recordCumulativeTest($batch, $results, $actor);
    }

    private function passBatch(QuranMemorizationBatch $batch, User $actor): QuranListeningTest
    {
        $this->completeReview($batch, $actor);

        $results = [];

        foreach ($this->gating()->testScopeJuzNumbers($batch) as $juz) {
            $results[$juz] = 'pass';
        }

        return $this->submitTest($batch, $actor, $results);
    }

    public function test_two_memorized_juz_open_the_batch_and_auto_generate_review_and_plan(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->status);
        $this->assertNotNull($batch->plan_id);
        $this->assertNotNull($batch->review_5_id);

        $plan = $batch->plan()->firstOrFail();
        $this->assertSame(8, $plan->items()->count());
        $this->assertSame(8, $plan->gate_size);
        $this->assertTrue($plan->isActive());
        $this->assertSame(8, $plan->items()->where('type', 'review')->count());

        $review = $batch->review5()->firstOrFail();
        $this->assertSame('pending', $review->status->value);
        $this->assertSame(8, $review->items()->count());

        // الدفعة الثانية لم تُفتح بعد.
        $this->assertFalse(
            QuranMemorizationBatch::query()
                ->where('student_id', $student->id)
                ->where('batch_number', 2)
                ->exists()
        );
    }

    public function test_cycle_generation_works_with_multiple_active_teachers(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $this->teacher($mosque, $session, 'الأستاذ سامي');
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->status);
        $this->assertNotNull($batch->plan_id);
        $this->assertNotNull($batch->review_5_id);
    }

    public function test_next_batch_stays_locked_until_the_current_one_passes(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2, 3, 4]);

        $states = $this->gating()->sync($student);
        $second = $states->firstWhere('batch_number', 2);

        $this->assertSame(QuranMemorizationBatchStatus::Locked, $second['status']);
        $this->assertNull($second['batch']);

        $this->passBatch($this->batch($student, 1), $admin);

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 1)->status);

        $second = $this->batch($student, 2);
        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $second->status);
        $this->assertNotNull($second->plan_id);
    }

    public function test_score_is_computed_from_juz_results_and_compared_with_the_threshold(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        $test = $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        $this->assertSame('50.00', (string) $test->score);
        $this->assertSame('80.00', (string) $test->passing_percentage);
        $this->assertSame('fail', $test->result->value);
        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $this->batch($student, 1)->status);

        $this->assertFalse(
            QuranMemorizationBatch::query()
                ->where('student_id', $student->id)
                ->where('batch_number', 2)
                ->exists()
        );
    }

    public function test_passing_percentage_is_snapshotted_on_each_test(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        $firstTest = $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        // المدير يخفض حد النجاح لاحقاً: الاختبار القديم يبقى بلقطته.
        app(QuranSettingsService::class)->setMinimumPassingPercentage(40);

        $this->completeRetakeReview($batch, $admin);

        $secondTest = $this->submitTest($batch, $admin, [2 => 'pass']);

        $this->assertSame('80.00', (string) $firstTest->refresh()->passing_percentage);
        $this->assertSame('fail', $firstTest->result->value);
        $this->assertSame('40.00', (string) $secondTest->passing_percentage);
        $this->assertSame('100.00', (string) $secondTest->score);
        $this->assertSame('pass', $secondTest->result->value);
        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 1)->status);
    }

    public function test_failed_batch_can_regenerate_a_fresh_review(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $oldPlan = $batch->plan()->firstOrFail();

        $this->completeReview($batch, $admin);
        $this->submitTest($batch, $admin, [1 => 'fail', 2 => 'fail']);

        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $this->batch($student, 1)->status);

        $retake = $batch->retakeReview5()->firstOrFail();
        $this->assertSame('retake_after_fail', $retake->type->value);

        $this->gating()->regenerateReview($batch, $admin);

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->status);
        $this->assertNull($batch->last_test_id);
        $this->assertNull($batch->retake_review_id);
        $this->assertSame('cancelled', $retake->refresh()->status->value);
        $this->assertSame(QuranListeningPlanStatus::Cancelled, $oldPlan->refresh()->status);

        $newPlan = $batch->plan()->firstOrFail();
        $this->assertNotSame($oldPlan->id, $newPlan->id);
        $this->assertTrue($newPlan->isActive());
        $this->assertSame(8, $newPlan->items()->count());
    }

    public function test_batch_test_is_rejected_before_all_khamsat_are_finished(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $review = $batch->review5()->firstOrFail();

        // إنهاء خمسات جزئية فقط (٢ من ٨).
        foreach ($review->items()->orderBy('from_page')->take(2)->get() as $item) {
            app(QuranKhamsaService::class)->completeItem($item, [], $admin);
        }

        try {
            $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'pass']);
            $this->fail('Expected ValidationException before the review is completed');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $this->batch($student, 1)->status);
        $this->assertSame(0, QuranListeningTest::query()->where('batch_id', $batch->id)->count());
        $this->assertTrue($batch->plan()->firstOrFail()->isActive());
    }

    public function test_missing_juz_result_is_rejected_for_the_cumulative_test(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        try {
            $this->submitTest($batch, $admin, [1 => 'pass']);
            $this->fail('Expected ValidationException for a missing juz result');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        $this->assertSame(QuranMemorizationBatchStatus::ReadyForTest, $this->batch($student, 1)->status);
        $this->assertSame(0, QuranListeningTest::query()->where('batch_id', $batch->id)->count());
    }

    public function test_passing_the_cumulative_test_closes_the_plan_and_marks_items_passed(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(60);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        $test = $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'pass']);

        $this->assertSame('pass', $test->result->value);
        $this->assertSame('100.00', (string) $test->score);
        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 1)->status);
        $this->assertTrue($batch->plan()->firstOrFail()->refresh()->isCompleted());
        $this->assertSame(8, $batch->plan()->firstOrFail()->items()->where('status', 'passed')->count());
        $this->assertNull($batch->refresh()->retake_review_id);
    }

    public function test_failed_cumulative_test_creates_retake_khamsat_for_failed_juz_only(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        $test = $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        $this->assertSame('fail', $test->result->value);
        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $this->batch($student, 1)->status);
        $this->assertSame([2], $this->gating()->failedJuzNumbers($batch->refresh()));

        $retake = $batch->retakeReview5()->firstOrFail();

        $this->assertSame('retake_after_fail', $retake->type->value);
        $this->assertSame('pending', $retake->status->value);
        $this->assertSame(4, $retake->items()->count());
        $this->assertSame([2], $retake->items()->orderBy('khamsa')->pluck('juz')->unique()->values()->all());

        // الدفعة التالية تبقى مقفلة.
        $this->assertFalse(
            QuranMemorizationBatch::query()
                ->where('student_id', $student->id)
                ->where('batch_number', 2)
                ->exists()
        );
    }

    public function test_retest_is_blocked_until_the_retake_khamsat_are_completed(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);
        $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        // اختبار الإعادة ممنوع قبل إنهاء خمسات الإعادة.
        try {
            $this->submitTest($batch, $admin, [2 => 'pass']);
            $this->fail('Expected ValidationException before the retake review is completed');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        $this->completeRetakeReview($batch, $admin);

        $this->assertSame(QuranMemorizationBatchStatus::ReadyForTest, $this->batch($student, 1)->status);
        $this->assertSame([2], $this->gating()->testScopeJuzNumbers($batch->refresh()));

        $retest = $this->submitTest($batch, $admin, [2 => 'pass']);

        $this->assertSame('100.00', (string) $retest->score);
        $this->assertSame('pass', $retest->result->value);
        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 1)->status);
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 2)->status);
    }

    public function test_cumulative_test_covers_all_juz_up_to_the_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->seedPassedBatches($student, [1, 2]);
        $this->memorize($student, range(1, 6));

        $batch = $this->batch($student, 3);
        $this->completeReview($batch, $admin);

        $this->assertSame([1, 2, 3, 4, 5, 6], $this->gating()->testScopeJuzNumbers($batch));

        $test = $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'pass', 3 => 'fail', 4 => 'pass', 5 => 'pass', 6 => 'pass']);

        $this->assertSame(6, $test->items()->count());
        $this->assertSame('83.33', (string) $test->score);
        $this->assertSame('pass', $test->result->value);
        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 3)->status);
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 4)->status);
    }

    public function test_full_retake_option_covers_all_juz_of_the_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->seedPassedBatches($student, [1, 2]);
        $this->memorize($student, range(1, 6));

        $batch = $this->batch($student, 3);
        $this->completeReview($batch, $admin);
        $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'pass', 3 => 'fail', 4 => 'pass', 5 => 'fail', 6 => 'fail']);

        $failedOnly = $batch->retakeReview5()->firstOrFail();
        $this->assertSame(12, $failedOnly->items()->count());

        // خيار «إعادة الاختبار كاملًا» يستبدل مراجعة الأجزاء الراسبة بمدى 1..6.
        $this->gating()->createRetakeReview($batch, $this->gating()->cumulativeJuzNumbers($batch), $admin);

        $batch->refresh();

        $this->assertSame('cancelled', $failedOnly->refresh()->status->value);
        $this->assertSame([1, 2, 3, 4, 5, 6], $this->gating()->testScopeJuzNumbers($batch));

        $full = $batch->retakeReview5()->firstOrFail();
        $this->assertNotSame($failedOnly->id, $full->id);
        $this->assertSame(24, $full->items()->count());
    }

    public function test_retest_failure_creates_a_new_retake_for_the_newly_failed_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);
        $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        $firstRetake = $batch->retakeReview5()->firstOrFail();
        $this->completeRetakeReview($batch, $admin);

        $this->submitTest($batch, $admin, [2 => 'fail']);

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $batch->status);
        $this->assertSame('completed', $firstRetake->refresh()->status->value);

        $secondRetake = $batch->retakeReview5()->firstOrFail();
        $this->assertNotSame($firstRetake->id, $secondRetake->id);
        $this->assertSame('pending', $secondRetake->status->value);
        $this->assertSame([2], $secondRetake->items()->orderBy('khamsa')->pluck('juz')->unique()->values()->all());
    }

    public function test_regenerate_review_cancels_an_orphaned_pending_review(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $orphan = $batch->review5()->firstOrFail();

        // محاكاة دورة ناقصة: مراجعة قيد الانتظار بلا خطة مرتبطة.
        $batch->update([
            'status' => QuranMemorizationBatchStatus::NeedsRepeat,
            'plan_id' => null,
            'last_test_id' => null,
        ]);

        $this->gating()->regenerateReview($batch, $admin);

        $this->assertSame('cancelled', $orphan->refresh()->status->value);

        $batch->refresh();

        $this->assertNotNull($batch->plan_id);
        $this->assertNotSame($orphan->id, $batch->review_5_id);
        $this->assertSame('pending', $batch->review5()->firstOrFail()->status->value);
        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->status);
    }

    public function test_auto_generated_cycle_is_attributed_to_staff_not_the_student(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $studentUser->id]);

        $this->memorize($student, [1, 2]);

        $plan = $this->batch($student, 1)->plan()->firstOrFail();

        $this->assertSame($teacherUser->id, $plan->created_by);
        $this->assertNotSame($studentUser->id, $plan->created_by);
    }

    public function test_new_tasmee_of_a_locked_juz_is_rejected_server_side(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        // الدفعة 1 قيد مراجعة 5 → الجزء 3 (صفحات 42–61) مقفل.
        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 42,
            'to_page' => 50,
        ])->assertSessionHasErrors('from_page');

        $this->assertSame(0, $student->quranRecitationSessions()->count());

        // داخل نطاق الدفعة الحالية (صفحات 1–41) مسموح.
        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 1,
            'to_page' => 5,
        ])->assertRedirect();

        $this->assertSame(1, $student->quranRecitationSessions()->count());
    }

    public function test_batches_are_dynamic_for_twenty_six_memorized_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->seedPassedBatches($student, range(1, 12));
        $this->memorize($student, range(1, 26));

        $states = $this->gating()->sync($student);

        $thirteenth = $states->firstWhere('batch_number', 13);
        $fourteenth = $states->firstWhere('batch_number', 14);

        $this->assertSame(25, $thirteenth['from_juz']);
        $this->assertSame(26, $thirteenth['to_juz']);
        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $thirteenth['status']);
        $this->assertSame(QuranMemorizationBatchStatus::Locked, $fourteenth['status']);

        $this->passBatch($this->batch($student, 13), $admin);

        $fourteenth = $this->gating()->sync($student)->firstWhere('batch_number', 14);
        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $fourteenth['status']);
    }

    public function test_passing_the_last_batch_opens_the_completion_request(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->seedPassedBatches($student, range(1, 14));
        $this->memorize($student, [29, 30]);

        $batch = $this->batch($student, 15);
        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->status);

        $this->passBatch($batch, $admin);

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 15)->status);

        $completion = QuranCompletion::query()->where('student_id', $student->id)->first();

        $this->assertNotNull($completion);
        $this->assertSame('pending', $completion->status->value);
    }

    public function test_student_profile_shows_the_current_batch_and_locked_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, 'أحمد');
        $student->update(['user_id' => $studentUser->id]);

        $this->memorize($student, [1, 2]);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('ملفي القرآني')
            ->assertSee('الدفعة 1')
            ->assertSee('مراجعة 5')
            ->assertSee('مقفل');
    }

    public function test_admin_can_save_the_minimum_passing_percentage(): void
    {
        [$mosque, $admin] = $this->mosque();

        $this->actingAs($admin)
            ->get(route('admin.settings.quran.edit'))
            ->assertOk()
            ->assertSee('الحد الأدنى للنجاح');

        $this->actingAs($admin)
            ->patch(route('admin.settings.quran.update'), ['minimum_passing_percentage' => 85])
            ->assertRedirect();

        $this->assertSame(85.0, app(QuranSettingsService::class)->minimumPassingPercentage());

        $this->actingAs($admin)
            ->patch(route('admin.settings.quran.update'), ['minimum_passing_percentage' => 0])
            ->assertSessionHasErrors('minimum_passing_percentage');
    }

    public function test_batches_page_renders_for_manager_and_teacher(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        // ربط الأستاذ بالطالب ضمن نطاقه (تسميع سابق).
        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('دفعات الحفظ')
            ->assertSee('الدفعة 1')
            ->assertDontSee('الاستماع / التشغيل')
            ->assertDontSee('العنصر والصفحات')
            ->assertDontSee('بانتظار استماع الطالب');

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('دفعات الحفظ')
            ->assertSee('الدفعة 1')
            ->assertDontSee('الاستماع / التشغيل')
            ->assertDontSee('العنصر والصفحات')
            ->assertDontSee('بانتظار استماع الطالب');
    }

    public function test_memorized_juz_are_still_the_source_of_truth(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);
        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $this->batch($student, 1)->status);

        // إزالة حفظ الجزء الثاني تُعيد الدفعة إلى «قيد الحفظ».
        app(QuranKhamsaService::class)->removeMemorization($student, 2, $admin);
        $this->gating()->sync($student);

        $this->assertSame(QuranMemorizationBatchStatus::PendingMemorization, $this->batch($student, 1)->status);
    }

    public function test_completing_a_batch_review_leads_to_the_test_form(): void
    {
        $this->seed(QuranDataSeeder::class);

        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $review = $batch->review5()->firstOrFail();
        $items = $review->items()->orderBy('from_page')->get();

        // جلسة أولى جزئية (≤ 30 صفحة): لا يظهر زر الاختبار قبل اكتمال كل الخمسات.
        $this->actingAs($admin)
            ->post(route('admin.quran.khamsa.review.store', $review), [
                'items' => $items->take(5)->pluck('id')->all(),
                'date' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.quran.khamsa.show', $review));

        $this->actingAs($admin)
            ->get(route('admin.quran.khamsa.show', $review))
            ->assertOk()
            ->assertDontSee('تسجيل نتيجة الاختبار');

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $this->batch($student, 1)->status);

        // الجلسة الثانية تُكمل الخمسات: الانتقال مباشرة إلى نموذج الاختبار التراكمي.
        $this->actingAs($admin)
            ->post(route('admin.quran.khamsa.review.store', $review), [
                'items' => $items->slice(5)->pluck('id')->all(),
                'date' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.quran.batches.index', ['student_id' => $student->id]).'#batch-test');

        $this->assertSame('completed', $review->refresh()->status->value);
        $this->assertSame(QuranMemorizationBatchStatus::ReadyForTest, $this->batch($student, 1)->status);

        // نموذج الاختبار التراكمي ظاهر في المركز (صف لكل جزء من أجزاء النطاق).
        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('id="batch-test"', false)
            ->assertSee('الاختبار التراكمي')
            ->assertSee('تسجيل نتيجة الاختبار')
            ->assertSee('الجزء 1')
            ->assertSee('الجزء 2');
    }

    public function test_admin_can_record_the_cumulative_test_from_the_center(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.test', $batch), [
                'results' => [1 => 'pass', 2 => 'pass'],
            ])
            ->assertRedirect();

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::Passed, $batch->status);
        $this->assertSame('100.00', (string) $batch->lastTest->score);
    }

    public function test_web_test_route_is_rejected_before_the_khamsat_finish(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.test', $batch), [
                'results' => [1 => 'pass', 2 => 'pass'],
            ])
            ->assertSessionHasErrors('results');

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $this->batch($student, 1)->status);
        $this->assertSame(0, QuranListeningTest::query()->where('batch_id', $batch->id)->count());
    }

    public function test_admin_can_switch_to_a_full_retake_from_the_center(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);
        $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        $this->actingAs($admin)
            ->post(route('admin.quran.batches.retake', $batch), ['mode' => 'full'])
            ->assertRedirect();

        $batch->refresh();

        $this->assertSame([1, 2], $this->gating()->testScopeJuzNumbers($batch));
        $this->assertSame(8, $batch->retakeReview5()->firstOrFail()->items()->count());
    }

    public function test_center_shows_the_retake_section_and_blocks_the_test_after_a_fail(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);
        $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('خمسات إعادة رسوب الاختبار')
            ->assertSee('رسب الطالب في الأجزاء')
            ->assertSee('إعادة الأجزاء الراسبة فقط')
            ->assertSee('إعادة الاختبار كاملًا')
            ->assertDontSee('تسجيل نتيجة الاختبار');
    }

    public function test_student_profile_shows_the_retake_review_and_failed_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, 'أحمد');
        $student->update(['user_id' => $studentUser->id]);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);
        $this->submitTest($batch, $admin, [1 => 'pass', 2 => 'fail']);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('خمسات إعادة رسوب الاختبار')
            ->assertSee('رسبت في الأجزاء');
    }

    public function test_teacher_outside_scope_cannot_record_the_cumulative_test(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $otherSession = StudySession::query()
            ->where('tenant_id', $mosque->id)
            ->whereKeyNot($session->id)
            ->firstOrFail();

        [$otherUser] = $this->teacher($mosque, $otherSession, 'أستاذ دوام آخر');

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        $this->actingAs($otherUser)
            ->post(route('teacher.quran.batches.test', $batch), [
                'results' => [1 => 'pass', 2 => 'pass'],
            ])
            ->assertForbidden();
    }

    public function test_per_item_listening_test_route_rejects_batch_plans(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $batch = $this->batch($student, 1);
        $this->completeReview($batch, $admin);

        $plan = $batch->plan()->firstOrFail();
        $item = $plan->items()->orderBy('position')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.quran.listening.test', $plan), [
                'results' => [$item->id => ['result' => 'pass']],
            ])
            ->assertSessionHasErrors('results');

        $this->assertSame(0, $plan->tests()->count());
    }

    public function test_a_manual_review_without_a_batch_has_no_test_link(): void
    {
        $this->seed(QuranDataSeeder::class);

        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);

        $review = app(QuranKhamsaService::class)->createReview([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => [['juz' => 1, 'khamsa' => 1]],
        ], $admin);

        $item = $review->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.quran.khamsa.review.store', $review), [
                'items' => [$item->id],
                'date' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.quran.khamsa.show', $review));

        $this->actingAs($admin)
            ->get(route('admin.quran.khamsa.show', $review))
            ->assertOk()
            ->assertDontSee('تسجيل نتيجة الاختبار');
    }
}
