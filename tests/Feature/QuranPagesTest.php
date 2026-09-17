<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuranRecitationSession;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranPageService;
use App\Services\RoleService;
use Database\Seeders\QuranDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuranPagesTest extends TestCase
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

    private function enrollStudent(Student $student, Section $section): void
    {
        $student->update(['classroom_id' => $section->classroom_id, 'section_id' => $section->id]);

        SectionStudent::create([
            'tenant_id' => $student->tenant_id,
            'section_id' => $section->id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);
    }

    public function test_page_layout_matches_the_madani_mushaf_examples(): void
    {
        $pages = app(QuranPageService::class);

        $page1 = $pages->ayahsForPage(1);
        $this->assertCount(7, $page1);
        $this->assertSame(1, $page1->first()->surah->sort_order);
        $this->assertSame(1, $page1->first()->ayah_number);
        $this->assertSame(7, $page1->last()->ayah_number);

        $page2 = $pages->ayahsForPage(2);
        $this->assertCount(5, $page2);
        $this->assertSame(2, $page2->first()->surah->sort_order);
        $this->assertSame(1, $page2->first()->ayah_number);
        $this->assertSame(5, $page2->last()->ayah_number);

        $page604 = $pages->ayahsForPage(604);
        $this->assertSame(114, $page604->last()->surah->sort_order);
        $this->assertSame(6, $page604->last()->ayah_number);
        $this->assertTrue($page604->contains(fn ($ayah) => $ayah->surah->sort_order === 114));
    }

    public function test_page_spanning_two_surahs_returns_them_in_mushaf_order(): void
    {
        $ayahs = app(QuranPageService::class)->ayahsForPage(106);

        $this->assertTrue($ayahs->contains(fn ($ayah) => $ayah->surah->sort_order === 4));
        $this->assertTrue($ayahs->contains(fn ($ayah) => $ayah->surah->sort_order === 5));

        $orders = $ayahs->map(fn ($ayah) => [$ayah->surah->sort_order, $ayah->ayah_number])->all();
        $sorted = $orders;
        sort($sorted);

        $this->assertSame($sorted, $orders);
    }

    public function test_surah_starts_on_page_are_detected(): void
    {
        $starts = app(QuranPageService::class)->surahStartsOnPage(604);

        $this->assertEqualsCanonicalizing([112, 113, 114], $starts->pluck('sort_order')->all());
    }

    public function test_admin_can_view_quran_page_and_json(): void
    {
        [, $admin] = $this->mosque();

        $this->actingAs($admin)
            ->get(route('quran.pages.show', 1))
            ->assertOk()
            ->assertSee('ٱلْفَاتِحَة');

        $this->actingAs($admin)
            ->get(route('quran.pages.json', 2))
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('ayahs.0.surah', 2)
            ->assertJsonPath('ayahs.0.ayah', 1);
    }

    public function test_quran_page_preview_renders_a_range(): void
    {
        [, $admin] = $this->mosque();

        $this->actingAs($admin)
            ->get(route('quran.pages.preview', ['page' => 2, 'to' => 3]))
            ->assertOk()
            ->assertSee('البَقَرَة')
            ->assertSee('۝٥')
            ->assertDontSee('data-ayah-id', false);
    }

    public function test_tasmee_review_page_renders_full_page_marking_view(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $response = $this->actingAs($admin)->get(route('admin.quran.tasmee.review', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 2,
            'to_page' => 3,
        ]))
            ->assertOk()
            ->assertSee('data-preview-viewer', false)
            ->assertSee('data-preview-nav', false)
            ->assertSee('data-preview-step="0"', false)
            ->assertSee('data-preview-step="1"', false)
            ->assertSee('data-ayah-id', false)
            ->assertSee('tasmeePreviewWord', false)
            ->assertSee('data-preview-popup', false)
            ->assertSee('data-preview-stat="mastery"', false)
            ->assertSee('data-quran-preview-form', false)
            ->assertSee('حفظ التسميع');

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/data-preview-step="0" data-page="2" class=""/', $html);
        $this->assertMatchesRegularExpression('/data-preview-step="1" data-page="3" class="hidden"/', $html);
    }

    public function test_tasmee_review_page_requires_a_valid_page_range(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($admin)->get(route('admin.quran.tasmee.review', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'amount' => 2,
        ]))
            ->assertRedirect(route('admin.quran.tasmee.create'))
            ->assertSessionHasErrors('from_page');
    }

    public function test_tasmee_create_page_links_to_the_full_page_review(): void
    {
        [$mosque, $admin] = $this->mosque();
        $this->makeTeacher($mosque->id);
        $this->makeStudent($mosque->id);

        $this->actingAs($admin)
            ->get(route('admin.quran.tasmee.create'))
            ->assertOk()
            ->assertSee('formaction="'.route('admin.quran.tasmee.review').'"', false)
            ->assertDontSee('<div data-pages-modal', false);
    }

    public function test_tasmee_with_page_range_computes_amount_and_portion(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 2,
            'to_page' => 4,
        ])->assertRedirect(route('admin.quran.batches.index', ['student_id' => $student->id]));

        $session = QuranRecitationSession::query()->latest('created_at')->first();
        $this->assertNotNull($session);
        $this->assertSame(2, $session->from_page);
        $this->assertSame(4, $session->to_page);
        $this->assertEquals(3, (float) $session->amount);
        $this->assertSame('من الصفحة 2 إلى الصفحة 4', $session->recited_portion);
    }

    public function test_tasmee_page_range_validation_rejects_invalid_ranges(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $base = [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
        ];

        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), $base + ['from_page' => 5, 'to_page' => 3])
            ->assertSessionHasErrors('to_page');

        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), $base + ['from_page' => 600, 'to_page' => 700])
            ->assertSessionHasErrors('to_page');

        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), $base + ['from_page' => 5])
            ->assertSessionHasErrors('to_page');

        $this->assertSame(0, QuranRecitationSession::query()->count());
    }

    public function test_legacy_tasmee_without_pages_still_works(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'amount' => 2.5,
            'recited_portion' => 'سورة البقرة',
        ])->assertRedirect();

        $session = QuranRecitationSession::query()->first();
        $this->assertNull($session->from_page);
        $this->assertNull($session->to_page);
        $this->assertEquals(2.5, (float) $session->amount);
    }

    public function test_tasmee_stores_error_word_statuses_and_shows_them_on_edit(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $this->actingAs($admin)->post(route('admin.quran.tasmee.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'from_page' => 2,
            'to_page' => 2,
            'word_statuses' => [
                'word-1:0' => 'incorrect',
                'word-1:1' => 'correct',
                'word-1:2' => 'hesitation',
            ],
        ])->assertRedirect();

        $session = QuranRecitationSession::query()->latest('created_at')->firstOrFail();

        $this->assertSame([
            'word-1:0' => 'incorrect',
            'word-1:2' => 'hesitation',
        ], $session->word_statuses);

        $this->actingAs($admin)
            ->get(route('admin.quran.tasmee.edit', $session))
            ->assertOk()
            ->assertSee('2 خطأ محفوظ');

        $this->actingAs($admin)
            ->get(route('admin.quran.tasmee.review', [
                'session' => $session,
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'type' => 'new',
                'date' => now()->toDateString(),
                'from_page' => 2,
                'to_page' => 2,
            ]))
            ->assertOk()
            ->assertSee('data-preview-statuses', false)
            ->assertSee('word-1:0', false);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertDontSee('2 خطأ محدد');
    }

    public function test_tasmee_update_replaces_word_error_statuses(): void
    {
        [$mosque, $admin] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $session = QuranRecitationSession::create([
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'amount' => 1,
            'word_statuses' => ['word-1:0' => 'incorrect'],
        ]);

        $this->actingAs($admin)->patch(route('admin.quran.tasmee.update', $session), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
            'word_statuses' => ['word-2:3' => 'forgotten'],
        ])->assertRedirect();

        $this->assertSame(['word-2:3' => 'forgotten'], $session->fresh()->word_statuses);
    }

    public function test_teacher_can_record_tasmee_with_page_range_for_scope_student(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'صف']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $this->enrollStudent($student, $section);
        SectionTeacher::create([
            'tenant_id' => $mosque->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'status' => 'active',
        ]);

        $this->actingAs($teacherUser)->post(route('teacher.quran.tasmee.store'), [
            'student_id' => $student->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'from_page' => 10,
            'to_page' => 12,
        ])->assertRedirect();

        $session = QuranRecitationSession::query()->first();
        $this->assertSame($teacher->id, $session->teacher_id);
        $this->assertEquals(3, (float) $session->amount);
    }

    public function test_teacher_can_open_full_page_review_for_scope_student(): void
    {
        [$mosque] = $this->mosque();
        [$teacherUser, $teacher] = $this->makeTeacher($mosque->id);
        $student = $this->makeStudent($mosque->id);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'صف']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $this->enrollStudent($student, $section);
        SectionTeacher::create([
            'tenant_id' => $mosque->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'status' => 'active',
        ]);

        $this->actingAs($teacherUser)->get(route('teacher.quran.tasmee.review', [
            'student_id' => $student->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'from_page' => 2,
            'to_page' => 2,
        ]))
            ->assertOk()
            ->assertSee('data-preview-viewer', false)
            ->assertSee('data-quran-preview-form', false);

        $outsider = $this->makeStudent($mosque->id);

        $this->actingAs($teacherUser)->get(route('teacher.quran.tasmee.review', [
            'student_id' => $outsider->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'from_page' => 2,
            'to_page' => 2,
        ]))->assertForbidden();
    }

    public function test_page_range_requires_authentication_and_permission(): void
    {
        $this->get(route('quran.pages.show', 1))->assertRedirect(route('login'));
    }

    public function test_quran_page_api_returns_page_payload_for_admin_and_teacher(): void
    {
        [$mosque, $admin] = $this->mosque();
        [$teacherUser] = $this->makeTeacher($mosque->id);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/quran/pages/2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.page', 2)
            ->assertJsonPath('data.ayahs.0.surah', 2)
            ->assertJsonPath('data.max_page', 604);

        Sanctum::actingAs($teacherUser);
        $this->getJson('/api/v1/teacher/quran/pages/1')
            ->assertOk()
            ->assertJsonPath('data.page', 1)
            ->assertJsonPath('data.ayahs.0.ayah', 1);
    }
}
