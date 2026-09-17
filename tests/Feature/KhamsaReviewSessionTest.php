<?php

namespace Tests\Feature;

use App\Enums\QuranTasmeeResult;
use App\Models\QuranKhamsaReview;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranKhamsaService;
use App\Services\QuranListeningService;
use App\Services\QuranPageService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Database\Seeders\QuranDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «مراجعة الخمسات مع تسجيل الأخطاء»: الأستاذ يحدد خمسات متتالية، يفتح القرآن
 * عليها، يسجّل الأخطاء كلمة بكلمة مثل التسميع، فتُنشأ جلسة «استماع مع المعلم»
 * وتُنهى الخمسات المحددة مربوطةً بها.
 */
class KhamsaReviewSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(QuranDataSeeder::class);
    }

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
    private function teacher(Tenant $mosque, StudySession $session, string $name = 'الأستاذ فلان'): array
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

    /** @param array<int, int> $juz */
    private function memorize(Student $student, array $juz): void
    {
        $service = app(QuranKhamsaService::class);

        foreach ($juz as $number) {
            $service->recordMemorization($student, $number);
        }
    }

    /**
     * @param  array<int, array{juz: int, khamsa: int}>  $items
     */
    private function reviewFor(Student $student, Teacher $teacher, StudySession $session, array $items): QuranKhamsaReview
    {
        return app(QuranKhamsaService::class)->createReview([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => $items,
        ], $teacher->user);
    }

    private function reviewPageUrl(QuranKhamsaReview $review, array $itemIds, string $prefix = 'teacher'): string
    {
        return route($prefix.'.quran.khamsa.review', $review).'?'.http_build_query(['items' => $itemIds]);
    }

    public function test_teacher_reviews_contiguous_khamsat_and_records_word_errors(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $review = $this->reviewFor($student, $teacher, $session, [
            ['juz' => 1, 'khamsa' => 1],
            ['juz' => 1, 'khamsa' => 2],
        ]);

        $items = $review->items()->orderBy('khamsa')->get();
        $itemIds = $items->pluck('id')->all();

        $this->actingAs($teacherUser)
            ->get($this->reviewPageUrl($review, $itemIds))
            ->assertOk()
            ->assertSee('data-preview-viewer', false)
            ->assertSee('الخمسة 1')
            ->assertSee('الخمسة 2')
            ->assertSee('صفحة 1 → صفحة 10');

        $firstWord = app(QuranPageService::class)->ayahsForRange(1, 10)->first();

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.khamsa.review.store', $review), [
                'items' => $itemIds,
                'date' => now()->toDateString(),
                'notes' => 'مراجعة الثلاثة',
                'word_statuses' => [$firstWord->id.':0' => 'incorrect'],
            ])
            ->assertRedirect(route('teacher.quran.khamsa.show', $review));

        $sessionRow = QuranReviewSession::firstOrFail();

        $this->assertSame(1, $sessionRow->from_page);
        $this->assertSame(10, $sessionRow->to_page);
        $this->assertSame(1, $sessionRow->incorrect_words);
        $this->assertSame((string) $teacher->id, (string) $sessionRow->teacher_id);
        $this->assertGreaterThan(0, QuranReviewWord::where('review_session_id', $sessionRow->id)->count());

        $expectedResult = QuranTasmeeResult::fromMastery((float) $sessionRow->mastery_percentage);

        foreach ($items as $item) {
            $item->refresh();

            $this->assertSame('completed', $item->status->value);
            $this->assertSame((string) $sessionRow->id, (string) $item->quran_review_session_id);
            $this->assertSame($expectedResult->value, $item->result->value);
            $this->assertSame('مراجعة الثلاثة', $item->notes);
        }

        $this->assertSame('completed', $review->refresh()->status->value);
    }

    public function test_khamsa_show_page_renders_selectable_squares(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $review = $this->reviewFor($student, $teacher, $session, [
            ['juz' => 1, 'khamsa' => 1],
            ['juz' => 1, 'khamsa' => 2],
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.khamsa.show', $review))
            ->assertOk()
            ->assertSee('data-khamsa-select-form', false)
            ->assertSee('data-khamsa-square', false)
            ->assertSee('data-khamsa-checkbox', false)
            ->assertSee('.khamsa-square.is-selected', false)
            ->assertSee('بدء المراجعة مع تسجيل الأخطاء');
    }

    public function test_non_contiguous_selection_is_rejected(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $review = $this->reviewFor($student, $teacher, $session, [
            ['juz' => 1, 'khamsa' => 1],
            ['juz' => 1, 'khamsa' => 2],
            ['juz' => 1, 'khamsa' => 3],
        ]);

        $items = $review->items()->orderBy('khamsa')->get();

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.khamsa.review.store', $review), [
                'items' => [$items[0]->id, $items[2]->id],
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quran_review_sessions', 0);
        $this->assertSame(3, $review->items()->where('status', 'pending')->count());
    }

    public function test_selection_exceeding_the_page_limit_is_rejected(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1, 2]);

        $review = $this->reviewFor($student, $teacher, $session, [
            ['juz' => 1, 'khamsa' => 1],
            ['juz' => 1, 'khamsa' => 2],
            ['juz' => 1, 'khamsa' => 3],
            ['juz' => 1, 'khamsa' => 4],
            ['juz' => 2, 'khamsa' => 1],
            ['juz' => 2, 'khamsa' => 2],
            ['juz' => 2, 'khamsa' => 3],
        ]);

        $itemIds = $review->items()->orderBy('from_page')->pluck('id')->all();

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.khamsa.review.store', $review), [
                'items' => $itemIds,
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quran_review_sessions', 0);
    }

    public function test_another_teacher_cannot_start_or_store_someone_elses_review(): void
    {
        [$mosque, , $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        [$otherUser] = $this->teacher($mosque, $session, 'أستاذ آخر');
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $review = $this->reviewFor($student, $teacher, $session, [['juz' => 1, 'khamsa' => 1]]);
        $item = $review->items()->firstOrFail();

        $this->actingAs($otherUser)
            ->get($this->reviewPageUrl($review, [$item->id]))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('teacher.quran.khamsa.review.store', $review), [
                'items' => [$item->id],
                'date' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('quran_review_sessions', 0);
        $this->assertSame('pending', $item->refresh()->status->value);
    }

    public function test_admin_runs_the_review_and_the_session_uses_the_assigned_teacher(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $review = $this->reviewFor($student, $teacher, $session, [['juz' => 1, 'khamsa' => 1]]);
        $item = $review->items()->firstOrFail();

        $this->actingAs($admin)
            ->get($this->reviewPageUrl($review, [$item->id], 'admin'))
            ->assertOk()
            ->assertSee('data-preview-viewer', false);

        $this->actingAs($admin)
            ->post(route('admin.quran.khamsa.review.store', $review), [
                'items' => [$item->id],
                'date' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.quran.khamsa.show', $review));

        $sessionRow = QuranReviewSession::firstOrFail();

        $this->assertSame((string) $teacher->id, (string) $sessionRow->teacher_id);
        $this->assertSame('completed', $item->refresh()->status->value);
    }

    public function test_review_completion_syncs_the_linked_listening_plan_item(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $plan = app(QuranListeningService::class)->createPlan([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                ['type' => 'review', 'juz' => 1, 'khamsa' => 1],
            ],
        ], $admin);

        $planItem = $plan->items()->firstOrFail();
        $review = $planItem->khamsaReviewItem->review;

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.khamsa.review.store', $review), [
                'items' => [$planItem->khamsa_review_item_id],
                'date' => now()->toDateString(),
            ])
            ->assertRedirect(route('teacher.quran.khamsa.show', $review));

        $planItem->refresh();

        $this->assertSame('listened', $planItem->status->value);
        $this->assertNotNull($planItem->quran_review_session_id);
        $this->assertSame('completed', $review->refresh()->status->value);
    }

    public function test_teacher_plan_page_is_listening_free(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $plan = app(QuranListeningService::class)->createPlan([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                ['type' => 'new', 'juz' => 1, 'from_page' => 1, 'to_page' => 5],
            ],
        ], $admin);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.listening.show', $plan))
            ->assertOk()
            ->assertDontSee('data-listening-player', false)
            ->assertDontSee('data-player-start', false)
            ->assertDontSee('تم الاستماع')
            ->assertDontSee('الاستماع / التشغيل')
            ->assertDontSee('العنصر والصفحات')
            ->assertDontSee('بانتظار استماع الطالب');
    }

    public function test_admin_listening_page_is_listening_free(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $plan = app(QuranListeningService::class)->createPlan([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                ['type' => 'new', 'juz' => 1, 'from_page' => 1, 'to_page' => 5],
            ],
        ], $admin);

        $this->actingAs($admin)
            ->get(route('admin.quran.listening.show', $plan))
            ->assertOk()
            ->assertDontSee('data-listening-player', false)
            ->assertDontSee('data-player-start', false)
            ->assertDontSee('تم الاستماع')
            ->assertDontSee('الاستماع / التشغيل')
            ->assertDontSee('العنصر والصفحات')
            ->assertDontSee('بانتظار استماع الطالب');
    }
}
