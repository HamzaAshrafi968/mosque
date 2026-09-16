<?php

namespace Tests\Feature;

use App\Enums\QuranListeningPlanStatus;
use App\Enums\QuranMemorizationBatchStatus;
use App\Models\QuranCompletion;
use App\Models\QuranListeningPlan;
use App\Models\QuranListeningTest;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranKhamsaService;
use App\Services\QuranListeningService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranSettingsService;
use App\Services\RoleService;
use App\Services\StudySessionService;
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

    private function listenAll(QuranListeningPlan $plan, User $actor): void
    {
        foreach ($plan->items()->get() as $item) {
            app(QuranListeningService::class)->markListened($item, $actor);
        }
    }

    /** @param array<int, string> $results item_id => pass|fail */
    private function submitTest(QuranListeningPlan $plan, User $actor, array $results): QuranListeningTest
    {
        $payload = [];

        foreach ($results as $itemId => $result) {
            $payload[$itemId] = ['result' => $result];
        }

        return app(QuranListeningService::class)->recordTest($plan, $payload, $actor);
    }

    private function passBatch(QuranMemorizationBatch $batch, User $actor): QuranListeningTest
    {
        $plan = $batch->plan()->firstOrFail();

        $this->listenAll($plan, $actor);

        $results = [];

        foreach ($plan->items()->get() as $item) {
            $results[$item->id] = 'pass';
        }

        return $this->submitTest($plan, $actor, $results);
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

    public function test_score_is_computed_from_items_and_compared_with_the_threshold(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(80);

        $this->memorize($student, [1, 2]);

        $plan = $this->batch($student, 1)->plan()->firstOrFail();
        $this->listenAll($plan, $admin);

        $items = $plan->items()->get();
        $results = [];

        foreach ($items as $index => $item) {
            $results[$item->id] = $index < 6 ? 'pass' : 'fail';
        }

        $test = $this->submitTest($plan, $admin, $results);

        $this->assertSame('75.00', (string) $test->score);
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

        $plan = $this->batch($student, 1)->plan()->firstOrFail();
        $this->listenAll($plan, $admin);

        $items = $plan->items()->get();
        $results = [];

        foreach ($items as $index => $item) {
            $results[$item->id] = $index < 6 ? 'pass' : 'fail';
        }

        $firstTest = $this->submitTest($plan, $admin, $results);

        // المدير يخفض حد النجاح لاحقاً: الاختبار القديم يبقى بلقطته.
        app(QuranSettingsService::class)->setMinimumPassingPercentage(60);

        $failed = $plan->items()->where('status', 'needs_repeat')->get();

        foreach ($failed as $item) {
            app(QuranListeningService::class)->markListened($item, $admin);
        }

        $retry = [];

        foreach ($failed as $item) {
            $retry[$item->id] = 'pass';
        }

        $secondTest = $this->submitTest($plan, $admin, $retry);

        $this->assertSame('80.00', (string) $firstTest->refresh()->passing_percentage);
        $this->assertSame('fail', $firstTest->result->value);
        $this->assertSame('60.00', (string) $secondTest->passing_percentage);
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

        $this->listenAll($oldPlan, $admin);

        $results = [];

        foreach ($oldPlan->items()->get() as $item) {
            $results[$item->id] = 'fail';
        }

        $this->submitTest($oldPlan, $admin, $results);

        $this->assertSame(QuranMemorizationBatchStatus::NeedsRepeat, $this->batch($student, 1)->status);

        $this->gating()->regenerateReview($batch, $admin);

        $batch->refresh();

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $batch->status);
        $this->assertNull($batch->last_test_id);
        $this->assertSame(QuranListeningPlanStatus::Cancelled, $oldPlan->refresh()->status);

        $newPlan = $batch->plan()->firstOrFail();
        $this->assertNotSame($oldPlan->id, $newPlan->id);
        $this->assertTrue($newPlan->isActive());
        $this->assertSame(8, $newPlan->items()->count());
    }

    public function test_partial_test_submission_is_rejected_for_batch_plans(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1, 2]);

        $plan = $this->batch($student, 1)->plan()->firstOrFail();
        $this->listenAll($plan, $admin);

        $first = $plan->items()->orderBy('position')->firstOrFail();

        try {
            $this->submitTest($plan, $admin, [$first->id => 'pass']);
            $this->fail('Expected ValidationException for a partial batch test submission');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('results', $exception->errors());
        }

        $this->assertSame(QuranMemorizationBatchStatus::PendingReview5, $this->batch($student, 1)->status);
        $this->assertTrue($plan->refresh()->isActive());
        $this->assertSame(0, $plan->tests()->count());
    }

    public function test_failed_items_keep_needs_repeat_after_a_scored_batch_pass(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranSettingsService::class)->setMinimumPassingPercentage(60);

        $this->memorize($student, [1, 2]);

        $plan = $this->batch($student, 1)->plan()->firstOrFail();
        $this->listenAll($plan, $admin);

        $items = $plan->items()->get();
        $results = [];

        foreach ($items as $index => $item) {
            $results[$item->id] = $index < 6 ? 'pass' : 'fail';
        }

        $test = $this->submitTest($plan, $admin, $results);

        $this->assertSame('pass', $test->result->value);
        $this->assertSame('75.00', (string) $test->score);
        $this->assertSame(QuranMemorizationBatchStatus::Passed, $this->batch($student, 1)->status);
        $this->assertTrue($plan->refresh()->isCompleted());

        // العنصران الراسبان يبقيان «يحتاج إعادة» ولا يُقلبان إلى «مُستمع».
        $this->assertSame(2, $plan->items()->where('status', 'needs_repeat')->count());
        $this->assertSame(6, $plan->items()->where('status', 'passed')->count());
        $this->assertSame(0, $plan->items()->where('status', 'listened')->count());
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
            ->assertSee('الدفعة 1');

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('دفعات الحفظ')
            ->assertSee('الدفعة 1');
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
}
