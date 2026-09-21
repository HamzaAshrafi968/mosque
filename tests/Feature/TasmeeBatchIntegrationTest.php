<?php

namespace Tests\Feature;

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
 * دمج التسميع مع دفعات الحفظ:
 *
 * - تسميع «جديد» يُربط تلقائياً بالدفعة التي يقع نطاقه داخلها.
 * - تقدّم الحفظ على مستوى الصفحات داخل الدفعة (تغطية/متبقي/الصفحة التالية).
 * - صفحة سجل التسميع القديمة تعيد التوجيه إلى المركز الموحّد.
 * - المركز يعرض سجل التسميع وتقدّم الصفحات، ونموذج التسجيل يسبق التعبئة.
 * - بوابة الطالب تعرض سجل تسميعه وتقدّم صفحاته.
 */
class TasmeeBatchIntegrationTest extends TestCase
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

    private function student(Tenant $mosque, StudySession $session): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $session->id,
            'name' => 'الطالب أحمد',
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

    private function recordTasmee(User $admin, Student $student, Teacher $teacher, array $overrides = []): QuranRecitationSession
    {
        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), array_merge([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 1,
            'to_page' => 5,
        ], $overrides))->assertRedirect();

        return QuranRecitationSession::query()->latest('created_at')->firstOrFail();
    }

    public function test_new_tasmee_links_to_the_current_batch_automatically(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);
        $batch = $this->batch($student, 1);

        $tasmee = $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 5]);

        $this->assertSame($batch->id, $tasmee->batch_id);
        $this->assertSame($batch->id, $tasmee->batch->id);
    }

    public function test_revision_tasmee_is_not_linked_to_any_batch(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);

        $tasmee = $this->recordTasmee($admin, $student, $teacher, ['type' => 'revision']);

        $this->assertNull($tasmee->batch_id);
    }

    public function test_progress_merges_intervals_and_counts_only_batch_pages(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);
        $batch = $this->batch($student, 1);

        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 5]);
        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 6, 'to_page' => 10]);

        // نطاق قديم خارج الدفعة (صفحات 40–45) — لا يُحتسب منه إلا ما داخل 1–41.
        QuranRecitationSession::create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 40,
            'to_page' => 45,
        ]);

        $progress = $this->gating()->batchMemorizationProgress($batch);

        $this->assertSame(1, $progress['from']);
        $this->assertSame(41, $progress['to']);
        $this->assertSame(41, $progress['total']);
        $this->assertSame(12, $progress['covered']);
        $this->assertSame(29, $progress['remaining']);
        $this->assertSame(11, $progress['next_page']);
        $this->assertContains(1, $progress['covered_pages']);
        $this->assertContains(10, $progress['covered_pages']);
        $this->assertContains(40, $progress['covered_pages']);
        $this->assertContains(41, $progress['covered_pages']);
        $this->assertNotContains(42, $progress['covered_pages']);
        $this->assertSame(10, $progress['juz'][1]['covered']);
        $this->assertSame(21, $progress['juz'][1]['total']);
        $this->assertSame(2, $progress['juz'][2]['covered']);
        $this->assertSame(20, $progress['juz'][2]['total']);
    }

    public function test_batch_for_page_range_rejects_ranges_spanning_two_batches(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);
        $batch = $this->batch($student, 1);

        // 20 (الجزء 1) و45 (الجزء 3 → الدفعة 2) = دفعتان مختلفتان.
        $this->assertNull($this->gating()->batchForPageRange($student, 20, 45));
        $this->assertSame($batch->id, $this->gating()->batchForPageRange($student, 1, 41)?->id);
        $this->assertNull($this->gating()->batchForPageRange($student, 0, 5));
        $this->assertNull($this->gating()->batchForPageRange($student, 5, 3));
        $this->assertNull($this->gating()->batchForPageRange($student, 1, 700));
    }

    public function test_new_tasmee_outside_the_current_batch_is_rejected(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);

        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 100,
            'to_page' => 105,
        ])->assertSessionHasErrors('from_page');

        $this->assertDatabaseCount('quran_recitation_sessions', 0);
    }

    public function test_tasmee_index_redirects_to_the_batches_center(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->actingAs($admin)
            ->get(route('admin.quran.tasmee.index', ['student_id' => $student->id]))
            ->assertRedirect(route('admin.quran.batches.index', ['student_id' => $student->id]));

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.tasmee.index', ['student_id' => $student->id]))
            ->assertRedirect(route('teacher.quran.batches.index', ['student_id' => $student->id]));
    }

    public function test_center_shows_memorization_progress_without_recitation_log(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);
        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 5, 'result' => 'excellent']);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('التسميع مع المعلم')
            ->assertSee('5 / 41 صفحة')
            ->assertSee('الدفعة 1')
            ->assertDontSee('صفحات 1–5')
            ->assertDontSee('ممتاز');
    }

    public function test_create_page_shows_batch_card_and_suggests_next_pages(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);
        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 5]);

        $this->actingAs($admin)
            ->get(route('admin.quran.tasmee.create', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('الدفعة 1')
            ->assertSee('value="6"', false)
            ->assertSee('value="10"', false);
    }

    public function test_teacher_center_shows_own_progress_without_recitation_log(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);

        // تسميع سابق يربط الطالب بنطاق الأستاذ.
        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ])->assertRedirect();

        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 5]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('التسميع مع المعلم')
            ->assertSee('5 / 41 صفحة')
            ->assertDontSee('صفحات 1–5');
    }

    public function test_student_profile_shows_tasmee_history_and_page_progress(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student->update(['user_id' => $studentUser->id]);

        $this->memorize($student, [1]);
        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 5, 'result' => 'very_good']);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('سجل التسميع مع المعلم')
            ->assertSee('صفحات 1–5')
            ->assertSee('5 / 41 صفحة')
            ->assertSee('جيد جداً');
    }

    public function test_batch_progress_reaches_full_coverage_without_overlapping_duplicates(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->memorize($student, [1]);
        $batch = $this->batch($student, 1);

        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 20]);
        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 1, 'to_page' => 20]);
        $this->recordTasmee($admin, $student, $teacher, ['from_page' => 21, 'to_page' => 41]);

        $progress = $this->gating()->batchMemorizationProgress($batch);

        $this->assertSame(41, $progress['covered']);
        $this->assertSame(0, $progress['remaining']);
        $this->assertSame(100.0, $progress['percentage']);
        $this->assertNull($progress['next_page']);
    }
}
