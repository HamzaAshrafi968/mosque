<?php

namespace Tests\Feature;

use App\Models\QuranRecitationSession;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\QuranSurah;
use App\Models\RewardPoint;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranPageService;
use App\Services\RoleService;
use Database\Seeders\QuranDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherQuranReviewPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(QuranDataSeeder::class);
    }

    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $admin];
    }

    private function makeTeacher(string $tenantId): array
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function makeStudent(string $tenantId): Student
    {
        return Student::factory()->create(['tenant_id' => $tenantId]);
    }

    private function statusesForPages(int $from, int $to): array
    {
        $total = 0;

        foreach (app(QuranPageService::class)->ayahsForRange($from, $to) as $ayah) {
            foreach (explode(' ', $ayah->text) as $word) {
                if ($word !== '') {
                    $total++;
                }
            }
        }

        return array_fill(0, $total, 'correct');
    }

    public function test_page_review_create_renders_mushaf_pages_with_clickable_words(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran-review.create', [
                'student_id' => $student->id,
                'from_page' => 1,
                'to_page' => 1,
            ]))
            ->assertOk()
            ->assertSee('ٱلْحَمْدُ')
            ->assertSee('صفحة ١ من ٦٠٤')
            ->assertSee('data-ayah-id', false)
            ->assertSee('.mushaf-review', false)
            ->assertSee('name="word_statuses[]"', false);
    }

    public function test_page_review_create_rejects_ranges_over_the_limit(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran-review.create', [
                'student_id' => $student->id,
                'from_page' => 1,
                'to_page' => 21,
            ]))
            ->assertOk()
            ->assertSee('الحد الأقصى لعدد صفحات الاستماع الواحدة هو 20 صفحات');
    }

    public function test_page_listening_renders_one_page_at_a_time_with_navigation(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $response = $this->actingAs($teacherUser)
            ->get(route('teacher.quran-review.create', [
                'student_id' => $student->id,
                'from_page' => 1,
                'to_page' => 3,
            ]))
            ->assertOk()
            ->assertSee('الاستماع مع المعلم')
            ->assertSee('data-review-step="0"', false)
            ->assertSee('data-review-step="1"', false)
            ->assertSee('data-review-step="2"', false)
            ->assertSee('id="page-prev"', false)
            ->assertSee('id="page-next"', false)
            ->assertSee('id="page-dots"', false)
            ->assertSee('صفحة الاستماع');

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/data-review-step="0" data-page="1" class=""/', $html);
        $this->assertMatchesRegularExpression('/data-review-step="1" data-page="2" class="hidden"/', $html);
        $this->assertMatchesRegularExpression('/data-review-step="2" data-page="3" class="hidden"/', $html);
    }

    public function test_store_page_review_uses_uthmani_words_and_records_page_range(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $statuses = $this->statusesForPages(2, 2);
        $totalWords = count($statuses);
        $statuses[$totalWords - 1] = 'incorrect';

        $response = $this->actingAs($teacherUser)->post(route('teacher.quran-review.store'), [
            'student_id' => $student->id,
            'from_page' => 2,
            'to_page' => 2,
            'date' => now()->toDateString(),
            'word_statuses' => $statuses,
        ]);

        $session = QuranReviewSession::query()->firstOrFail();

        $response->assertRedirect(route('teacher.quran-review.show', $session->id));

        $this->assertSame(2, $session->from_page);
        $this->assertSame(2, $session->to_page);
        $this->assertSame($teacher->id, $session->teacher_id);
        $this->assertSame($totalWords, $session->total_words);
        $this->assertSame($totalWords - 1, $session->correct_words);
        $this->assertSame(1, $session->incorrect_words);
        $this->assertEquals(
            round((($totalWords - 1) / $totalWords) * 100, 2),
            (float) $session->mastery_percentage
        );

        $baqarah = QuranSurah::query()->where('sort_order', 2)->firstOrFail();
        $this->assertSame($baqarah->id, $session->surah_id);
        $this->assertSame(1, $session->from_ayah);
        $this->assertSame(5, $session->to_ayah);

        $incorrectWord = QuranReviewWord::query()->where('status', 'incorrect')->firstOrFail();
        $expectedLastWord = collect(explode(' ', app(QuranPageService::class)->ayahsForRange(2, 2)->last()->text))
            ->filter(fn ($word) => $word !== '')
            ->last();
        $this->assertSame($expectedLastWord, $incorrectWord->word_text);

        $this->assertTrue(
            RewardPoint::query()->where('quran_review_session_id', $session->id)->exists()
        );

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran-review.show', $session->id))
            ->assertOk()
            ->assertSee('صفحة 2')
            ->assertSee('ذَٰلِكَ');

        // «الاستماع مع المعلم» دُمج في سجل «التسميع مع المعلم» داخل مركز «دفعات الحفظ».
        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('التسميع مع المعلم')
            ->assertSee('استماع مع المعلم')
            ->assertSee('صفحة 2');
    }

    public function test_store_page_review_handles_a_page_spanning_two_surahs(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $statuses = $this->statusesForPages(106, 106);

        $this->actingAs($teacherUser)->post(route('teacher.quran-review.store'), [
            'student_id' => $student->id,
            'from_page' => 106,
            'to_page' => 106,
            'date' => now()->toDateString(),
            'word_statuses' => $statuses,
        ])->assertRedirect();

        $session = QuranReviewSession::query()->firstOrFail();
        $nisa = QuranSurah::query()->where('sort_order', 4)->firstOrFail();
        $maidah = QuranSurah::query()->where('sort_order', 5)->firstOrFail();

        $this->assertSame(106, $session->from_page);
        $this->assertSame($nisa->id, $session->surah_id);
        $this->assertSame(count($statuses), $session->total_words);

        $this->assertTrue(
            QuranReviewWord::query()
                ->whereHas('ayah', fn ($query) => $query->where('surah_id', $maidah->id))
                ->exists()
        );
    }

    public function test_store_page_review_validates_the_page_range(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $base = [
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'word_statuses' => ['correct'],
        ];

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran-review.store'), $base + ['from_page' => 5, 'to_page' => 3])
            ->assertSessionHasErrors('to_page');

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran-review.store'), $base + ['from_page' => 0, 'to_page' => 1])
            ->assertSessionHasErrors('from_page');

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran-review.store'), $base + ['from_page' => 600, 'to_page' => 605])
            ->assertSessionHasErrors('to_page');

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran-review.store'), $base + ['from_page' => 1, 'to_page' => 21])
            ->assertSessionHasErrors('to_page');

        $this->assertSame(0, QuranReviewSession::query()->count());
    }

    public function test_legacy_review_without_pages_still_displays_word_badges(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);
        $fatiha = QuranSurah::query()->where('sort_order', 1)->firstOrFail();
        $ayah = $fatiha->ayahs()->orderBy('ayah_number')->firstOrFail();

        $session = QuranReviewSession::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'surah_id' => $fatiha->id,
            'from_ayah' => 1,
            'to_ayah' => 1,
            'total_words' => 4,
            'correct_words' => 3,
            'incorrect_words' => 1,
            'mastery_percentage' => 75,
            'date' => now()->toDateString(),
        ]);

        QuranReviewWord::create([
            'tenant_id' => $mosque->id,
            'review_session_id' => $session->id,
            'ayah_id' => $ayah->id,
            'word_position' => 0,
            'word_text' => 'بسم',
            'status' => 'correct',
        ]);

        $this->assertFalse($session->isPageBased());
        $this->assertSame('1 — 1', $session->pagesLabel());

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran-review.show', $session->id))
            ->assertOk()
            ->assertSee('word-badge', false)
            ->assertSee('بسم');
    }
}
