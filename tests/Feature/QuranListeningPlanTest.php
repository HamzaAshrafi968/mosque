<?php

namespace Tests\Feature;

use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranListeningPlanStatus;
use App\Models\Permission;
use App\Models\QuranKhamsaReview;
use App\Models\QuranKhamsaReviewItem;
use App\Models\QuranListeningPlan;
use App\Models\QuranListeningPlanItem;
use App\Models\QuranRecitationSession;
use App\Models\QuranReviewSession;
use App\Models\QuranSurah;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalNotification;
use App\Services\QuranAudioService;
use App\Services\QuranKhamsaService;
use App\Services\QuranListeningService;
use App\Services\QuranMemorizationGatingService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use App\Support\QuranJuzMap;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * «خطة الاستماع والاختبار»:
 *
 * - اختيار أجزاء متعددة مع نطاق صفحات داخل حدود كل جزء (لا يتجاوزها).
 * - فتح الأجزاء دفعةً دفعة، واختبار كل ما فُتح معاً.
 * - نجاح كل المفتوح يفتح الدفعة التالية، والراسب يُعاد حتى ينجح.
 */
class QuranListeningPlanTest extends TestCase
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

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createPlan(Student $student, Teacher $teacher, StudySession $session, array $items, int $gateSize = 1): QuranListeningPlan
    {
        return app(QuranListeningService::class)->createPlan([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'gate_size' => $gateSize,
            'items' => $items,
        ], $teacher->user ?? User::factory()->admin()->create(['tenant_id' => $student->tenant_id]));
    }

    /** @return array<int, string> */
    private function statuses(QuranListeningPlan $plan): array
    {
        return $plan->items()
            ->orderBy('position')
            ->get()
            ->map(fn (QuranListeningPlanItem $item) => $item->status->value)
            ->all();
    }

    /** @param array<int, int> $juzNumbers */
    private function memorize(Student $student, array $juzNumbers): void
    {
        $khamsa = app(QuranKhamsaService::class);

        foreach ($juzNumbers as $juz) {
            $khamsa->recordMemorization($student, $juz);
        }
    }

    /** @return array{0: QuranKhamsaReview, 1: QuranKhamsaReviewItem} */
    private function khamsaReviewFor(Student $student, Teacher $teacher, StudySession $session, array $items): array
    {
        $actor = $teacher->user ?? User::factory()->admin()->create(['tenant_id' => $student->tenant_id]);

        $review = app(QuranKhamsaService::class)->createReview([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => $items,
        ], $actor);

        return [$review, $review->items()->firstOrFail()];
    }

    private function listeningSession(Student $student, Teacher $teacher, int $fromPage = 1, int $toPage = 5): QuranReviewSession
    {
        $surah = QuranSurah::query()->firstOrCreate(
            ['sort_order' => 1],
            ['name_arabic' => 'الفاتحة', 'revelation_type' => 'makkiah', 'num_ayahs' => 7]
        );

        return QuranReviewSession::create([
            'tenant_id' => $student->tenant_id,
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'surah_id' => $surah->id,
            'from_ayah' => 1,
            'to_ayah' => 7,
            'from_page' => $fromPage,
            'to_page' => $toPage,
            'total_words' => 10,
            'correct_words' => 9,
            'incorrect_words' => 1,
            'mastery_percentage' => 90,
            'date' => now()->toDateString(),
        ]);
    }

    public function test_admin_can_create_plan_with_page_ranges_across_multiple_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $response = $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'gate_size' => 2,
            'items' => [
                2 => ['selected' => 1, 'juz' => 2, 'from_page' => 22, 'to_page' => 26],
                1 => ['selected' => 1, 'juz' => 1, 'from_page' => 15, 'to_page' => 21],
                3 => ['selected' => 1, 'juz' => 3, 'from_page' => 42, 'to_page' => 46],
            ],
        ]);

        $plan = QuranListeningPlan::firstOrFail();
        $response->assertRedirect(route('admin.quran.listening.show', $plan));

        $this->assertSame(2, $plan->gate_size);
        $this->assertSame(QuranListeningPlanStatus::Active, $plan->status);
        $this->assertSame(['available', 'available', 'locked'], $this->statuses($plan));

        $items = $plan->items()->orderBy('position')->get();
        $this->assertSame([1, 2, 3], $items->pluck('juz')->all());
        $this->assertSame([15, 22, 42], $items->pluck('from_page')->all());
        $this->assertSame([21, 26, 46], $items->pluck('to_page')->all());
        $this->assertSame(17, $plan->pagesCount());
    }

    public function test_pages_outside_the_part_are_rejected_server_side(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $base = [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [2 => ['selected' => 1, 'juz' => 2, 'from_page' => 21, 'to_page' => 26]],
        ];

        $this->actingAs($admin)
            ->post(route('admin.quran.listening.store'), $base)
            ->assertSessionHasErrors('items.2.from_page');

        $base['items'] = [6 => ['selected' => 1, 'juz' => 6, 'from_page' => 102, 'to_page' => 121]];

        $this->actingAs($admin)
            ->post(route('admin.quran.listening.store'), $base)
            ->assertSessionHasErrors('items.6.to_page');

        $this->assertDatabaseCount('quran_listening_plans', 0);
        $this->assertDatabaseCount('quran_listening_plan_items', 0);
    }

    public function test_duplicate_juz_and_inverted_range_are_rejected(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'from_page' => 1, 'to_page' => 10],
                2 => ['selected' => 1, 'juz' => 1, 'from_page' => 11, 'to_page' => 21],
            ],
        ])->assertSessionHasErrors('items');

        $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'from_page' => 20, 'to_page' => 15],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quran_listening_plans', 0);
    }

    public function test_plan_builder_renders_each_part_with_its_page_range(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->actingAs($admin)
            ->get(route('admin.quran.listening.create', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('الجزء 1')
            ->assertSee('صفحات 1–21 (21 صفحة)')
            ->assertSee('صفحات 102–120 (19 صفحة)')
            ->assertSee('صفحات 582–604 (23 صفحة)');
    }

    public function test_teacher_can_create_for_a_student_he_supervises(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'amount' => 5,
            'from_page' => 1,
            'to_page' => 5,
        ]);

        $this->actingAs($teacherUser)->post(route('teacher.quran.listening.store'), [
            'student_id' => $student->id,
            'study_session_id' => $session->id,
            'gate_size' => 1,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'from_page' => 15, 'to_page' => 21],
            ],
        ])->assertRedirect();

        $plan = QuranListeningPlan::firstOrFail();
        $this->assertSame($teacher->id, $plan->teacher_id);
        $this->assertSame($teacherUser->id, $plan->created_by);
    }

    public function test_teacher_cannot_create_for_an_unrelated_student(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->actingAs($teacherUser)->post(route('teacher.quran.listening.store'), [
            'student_id' => $student->id,
            'study_session_id' => $session->id,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'from_page' => 1, 'to_page' => 21],
            ],
        ])->assertForbidden();

        $this->assertDatabaseCount('quran_listening_plans', 0);
    }

    public function test_locked_item_cannot_be_marked_listened(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
            ['juz' => 2, 'from_page' => 22, 'to_page' => 41],
        ], 1);

        $locked = $plan->items()->where('juz', 2)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.quran.listening.items.listen', $locked))
            ->assertSessionHasErrors('item');

        $this->assertSame(QuranListeningItemStatus::Locked, $locked->refresh()->status);
    }

    public function test_passing_the_whole_wave_unlocks_the_next_wave_and_completes_the_plan(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
            ['juz' => 2, 'from_page' => 22, 'to_page' => 41],
            ['juz' => 3, 'from_page' => 42, 'to_page' => 61],
            ['juz' => 4, 'from_page' => 62, 'to_page' => 81],
        ], 2);

        $this->assertSame(['available', 'available', 'locked', 'locked'], $this->statuses($plan));

        $items = $plan->items()->orderBy('position')->get();

        foreach ([$items[0], $items[1]] as $item) {
            $this->actingAs($admin)
                ->post(route('admin.quran.listening.items.listen', $item))
                ->assertRedirect();
        }

        $this->actingAs($admin)->post(route('admin.quran.listening.test', $plan), [
            'results' => [
                $items[0]->id => ['result' => 'pass'],
                $items[1]->id => ['result' => 'pass'],
            ],
        ])->assertRedirect();

        $this->assertSame(['passed', 'passed', 'available', 'available'], $this->statuses($plan));
        $this->assertSame(QuranListeningPlanStatus::Active, $plan->refresh()->status);

        foreach ([$items[2], $items[3]] as $item) {
            $this->actingAs($admin)
                ->post(route('admin.quran.listening.items.listen', $item))
                ->assertRedirect();
        }

        $this->actingAs($admin)->post(route('admin.quran.listening.test', $plan), [
            'results' => [
                $items[2]->id => ['result' => 'pass'],
                $items[3]->id => ['result' => 'pass'],
            ],
        ])->assertRedirect();

        $this->assertSame(['passed', 'passed', 'passed', 'passed'], $this->statuses($plan));
        $this->assertSame(QuranListeningPlanStatus::Completed, $plan->refresh()->status);
        $this->assertNotNull($plan->completed_at);
        $this->assertDatabaseCount('quran_listening_tests', 2);
    }

    public function test_failed_part_needs_repeat_and_keeps_the_next_locked_until_it_passes(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
            ['juz' => 2, 'from_page' => 22, 'to_page' => 41],
            ['juz' => 3, 'from_page' => 42, 'to_page' => 61],
        ], 2);

        $items = $plan->items()->orderBy('position')->get();

        foreach ([$items[0], $items[1]] as $item) {
            $this->actingAs($admin)->post(route('admin.quran.listening.items.listen', $item));
        }

        $this->actingAs($admin)->post(route('admin.quran.listening.test', $plan), [
            'results' => [
                $items[0]->id => ['result' => 'pass'],
                $items[1]->id => ['result' => 'fail', 'notes' => 'يحتاج إتقان'],
            ],
        ])->assertRedirect();

        $this->assertSame(['passed', 'needs_repeat', 'locked'], $this->statuses($plan));

        $this->assertDatabaseHas('quran_listening_test_items', [
            'plan_item_id' => $items[1]->id,
            'result' => 'fail',
            'needs_repeat' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.quran.listening.items.listen', $items[1]))->assertRedirect();
        $this->assertSame(['passed', 'listened', 'locked'], $this->statuses($plan));

        $this->actingAs($admin)->post(route('admin.quran.listening.test', $plan), [
            'results' => [$items[1]->id => ['result' => 'pass']],
        ])->assertRedirect();

        $this->assertSame(['passed', 'passed', 'available'], $this->statuses($plan));
        $this->assertSame(2, $items[1]->refresh()->attempts);
    }

    public function test_test_rejects_items_that_are_not_listened(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
        ]);

        $item = $plan->items()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.quran.listening.test', $plan), [
            'results' => [$item->id => ['result' => 'pass']],
        ])->assertSessionHasErrors('results');

        $this->assertSame(QuranListeningItemStatus::Available, $item->refresh()->status);
        $this->assertDatabaseCount('quran_listening_tests', 0);
    }

    public function test_student_portal_shows_only_his_plan_and_blocks_others(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session, 'الأستاذ محمد');

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, 'أحمد');
        $student->update(['user_id' => $studentUser->id]);

        $otherStudent = $this->student($mosque, $session, 'خالد');

        $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 15, 'to_page' => 21],
        ]);

        $otherPlan = $this->createPlan($otherStudent, $teacher, $session, [
            ['juz' => 2, 'from_page' => 22, 'to_page' => 26],
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('الأستاذ محمد')
            ->assertSee('الجزء 1');

        $this->actingAs($studentUser)
            ->get(route('student.quran-listening.show', $otherPlan))
            ->assertForbidden();
    }

    public function test_student_can_mark_listened_but_not_someone_elses_item(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, 'أحمد');
        $student->update(['user_id' => $studentUser->id]);

        $otherStudent = $this->student($mosque, $session, 'خالد');

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 15, 'to_page' => 21],
        ]);

        $otherPlan = $this->createPlan($otherStudent, $teacher, $session, [
            ['juz' => 2, 'from_page' => 22, 'to_page' => 26],
        ]);

        $ownItem = $plan->items()->firstOrFail();
        $otherItem = $otherPlan->items()->firstOrFail();

        $this->actingAs($studentUser)
            ->post(route('student.quran-listening.items.listen', $ownItem))
            ->assertRedirect();

        $this->assertSame(QuranListeningItemStatus::Listened, $ownItem->refresh()->status);

        $this->actingAs($studentUser)
            ->post(route('student.quran-listening.items.listen', $otherItem))
            ->assertForbidden();

        $this->assertSame(QuranListeningItemStatus::Available, $otherItem->refresh()->status);
    }

    public function test_locked_item_audio_is_forbidden(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
            ['juz' => 2, 'from_page' => 22, 'to_page' => 41],
        ]);

        $locked = $plan->items()->where('juz', 2)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.quran.listening.items.audio', $locked))
            ->assertForbidden();
    }

    public function test_audio_service_builds_per_ayah_track_urls(): void
    {
        config(['quran_audio.reciters.test' => ['label' => 'قارئ اختبار', 'base_url' => 'https://cdn.test/quran/']]);
        config(['quran_audio.reciter' => 'test']);

        $audio = app(QuranAudioService::class);

        $this->assertSame('https://cdn.test/quran/001001.mp3', $audio->trackUrl(1, 1));
        $this->assertSame('https://cdn.test/quran/002142.mp3', $audio->trackUrl(2, 142));
        $this->assertSame('https://cdn.test/quran/114006.mp3', $audio->trackUrl(114, 6));
        $this->assertSame('test', $audio->defaultReciter());
        $this->assertSame('قارئ اختبار', $audio->reciters()['test']);
    }

    public function test_test_result_notifies_the_student(): void
    {
        Notification::fake();

        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session);
        $student->update(['user_id' => $studentUser->id]);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
        ]);

        $item = $plan->items()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.quran.listening.items.listen', $item));

        $this->actingAs($admin)->post(route('admin.quran.listening.test', $plan), [
            'results' => [$item->id => ['result' => 'fail']],
        ]);

        Notification::assertSentTo($studentUser, PortalNotification::class);
    }

    public function test_revoking_permission_blocks_the_feature(): void
    {
        [$mosque, $admin, $session] = $this->mosque();

        $role = Role::where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail();

        $role->permissions()->detach(
            Permission::where('code', 'quran_listening.view')->value('id')
        );

        $this->actingAs($admin)
            ->get(route('admin.quran.listening.index'))
            ->assertForbidden();
    }

    public function test_admin_shift_filter_hides_other_shift_plans(): void
    {
        [$mosque, $admin, $first] = $this->mosque();
        $second = StudySession::where('tenant_id', $mosque->id)
            ->where('id', '!=', $first->id)
            ->firstOrFail();

        [, $teacherFirst] = $this->teacher($mosque, $first);
        [, $teacherSecond] = $this->teacher($mosque, $second);
        $studentFirst = $this->student($mosque, $first, 'طالب الدوام الأول');
        $studentSecond = $this->student($mosque, $second, 'طالب الدوام الثاني');

        $this->createPlan($studentFirst, $teacherFirst, $first, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
        ]);

        $this->createPlan($studentSecond, $teacherSecond, $second, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
        ]);

        $gating = app(QuranMemorizationGatingService::class);
        $gating->sync($studentFirst);
        $gating->sync($studentSecond);

        $this->actingAs($admin)
            ->withSession(['study_session_id' => $first->id])
            ->get(route('admin.quran.batches.index'))
            ->assertOk()
            ->assertSee('طالب الدوام الأول')
            ->assertDontSee('طالب الدوام الثاني');
    }

    public function test_plan_merges_khamsa_reviews_with_a_linked_khamsa_review(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [2]);

        $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'gate_size' => 2,
            'items' => [
                2 => ['selected' => 1, 'juz' => 2, 'type' => 'review', 'khamsat' => [1 => 1, 3 => 1]],
                3 => ['selected' => 1, 'juz' => 3, 'type' => 'new', 'from_page' => 42, 'to_page' => 50],
            ],
        ])->assertRedirect();

        $plan = QuranListeningPlan::firstOrFail();
        $this->assertSame(['available', 'available', 'locked'], $this->statuses($plan));

        $reviews = $plan->items()->where('type', 'review')->orderBy('khamsa')->get();
        $this->assertSame([1, 3], $reviews->pluck('khamsa')->all());

        $range = QuranJuzMap::khamsaRange(2, 1);
        $this->assertSame($range['from'], $reviews[0]->from_page);
        $this->assertSame($range['to'], $reviews[0]->to_page);
        $this->assertNotNull($reviews[0]->khamsa_review_item_id);
        $this->assertSame('review', $reviews[0]->type->value);

        $khamsaReview = QuranKhamsaReview::firstOrFail();
        $this->assertSame($plan->id, $khamsaReview->listening_plan_id);
        $this->assertSame($khamsaReview->id, $plan->refresh()->khamsa_review_id);
        $this->assertSame(2, $khamsaReview->items()->where('status', 'pending')->count());
        $this->assertSame(2, $khamsaReview->items()->whereIn('khamsa', [1, 3])->count());

        $this->actingAs($admin)
            ->get(route('admin.quran.listening.show', $plan))
            ->assertOk()
            ->assertSee('مراجعة 5 المرتبطة')
            ->assertDontSee('العنصر والصفحات')
            ->assertDontSee('الخمسة 1');
    }

    public function test_review_item_requires_a_memorized_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'type' => 'review', 'khamsat' => [1 => 1]],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quran_listening_plans', 0);
        $this->assertDatabaseCount('quran_khamsa_reviews', 0);
    }

    public function test_review_item_without_a_khamsa_is_rejected(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'type' => 'review'],
            ],
        ])->assertSessionHasErrors('items.1.khamsat');

        $this->assertDatabaseCount('quran_listening_plans', 0);
    }

    public function test_pending_khamsa_cannot_be_added_to_a_plan(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);
        $this->khamsaReviewFor($student, $teacher, $session, [['juz' => 1, 'khamsa' => 1]]);

        $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'type' => 'review', 'khamsat' => [1 => 1]],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quran_listening_plans', 0);
        $this->assertSame(1, QuranKhamsaReview::count());
    }

    public function test_same_juz_can_be_new_and_review_in_one_plan(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['type' => 'new', 'juz' => 1, 'from_page' => 1, 'to_page' => 21],
            ['type' => 'review', 'juz' => 1, 'khamsa' => 2],
        ], 2);

        $this->assertSame(2, $plan->items()->count());
        $this->assertSame(1, $plan->items()->where('type', 'review')->where('khamsa', 2)->count());
        $this->assertSame(1, $plan->items()->where('type', 'new')->count());
    }

    public function test_passing_a_review_item_completes_the_linked_khamsa_and_opens_the_next_wave(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['type' => 'new', 'juz' => 2, 'from_page' => 22, 'to_page' => 41],
            ['type' => 'review', 'juz' => 1, 'khamsa' => 1],
            ['type' => 'new', 'juz' => 3, 'from_page' => 42, 'to_page' => 61],
        ], 2);

        $items = $plan->items()->orderBy('position')->get();
        $this->assertSame(['available', 'available', 'locked'], $this->statuses($plan));
        $this->assertTrue($items[0]->isReview());

        foreach ([$items[0], $items[1]] as $item) {
            $this->actingAs($admin)
                ->post(route('admin.quran.listening.items.listen', $item))
                ->assertRedirect();
        }

        $this->actingAs($admin)->post(route('admin.quran.listening.test', $plan), [
            'results' => [
                $items[0]->id => ['result' => 'pass'],
                $items[1]->id => ['result' => 'pass'],
            ],
        ])->assertRedirect();

        $this->assertSame(['passed', 'passed', 'available'], $this->statuses($plan));

        $khamsaItem = $items[0]->refresh()->khamsaReviewItem()->first();
        $this->assertNotNull($khamsaItem);
        $this->assertTrue($khamsaItem->isCompleted());

        $khamsaReview = QuranKhamsaReview::firstOrFail();
        $this->assertSame('completed', $khamsaReview->refresh()->status->value);

        $this->actingAs($admin)
            ->get(route('admin.quran.khamsa.show', $khamsaReview))
            ->assertOk()
            ->assertSee('تمت');
    }

    public function test_completing_a_khamsa_from_its_screen_marks_the_plan_item_listened(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['type' => 'review', 'juz' => 1, 'khamsa' => 1],
        ]);

        $planItem = $plan->items()->firstOrFail();
        $this->assertSame(QuranListeningItemStatus::Available, $planItem->status);

        $session = $this->listeningSession($student, $teacher);

        $this->actingAs($admin)->post(route('admin.quran.khamsa.items.complete', $planItem->khamsa_review_item_id), [
            'result' => 'excellent',
            'quran_review_session_id' => $session->id,
        ])->assertRedirect();

        $planItem->refresh();
        $this->assertSame(QuranListeningItemStatus::Listened, $planItem->status);
        $this->assertSame($session->id, $planItem->quran_review_session_id);
    }

    public function test_cancelling_the_plan_cancels_the_linked_khamsa_review(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['type' => 'review', 'juz' => 1, 'khamsa' => 1],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.quran.listening.cancel', $plan))
            ->assertRedirect();

        $this->assertSame(QuranListeningPlanStatus::Cancelled, $plan->refresh()->status);
        $this->assertSame('cancelled', QuranKhamsaReview::firstOrFail()->status->value);
    }

    public function test_gate_size_can_be_up_to_ten(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->actingAs($admin)->post(route('admin.quran.listening.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'gate_size' => 11,
            'items' => [
                1 => ['selected' => 1, 'juz' => 1, 'type' => 'new', 'from_page' => 1, 'to_page' => 21],
            ],
        ])->assertSessionHasErrors('gate_size');

        $items = [];

        foreach (range(1, 12) as $juz) {
            $range = QuranJuzMap::pageRange($juz);
            $items[] = ['juz' => $juz, 'from_page' => $range['from'], 'to_page' => $range['to']];
        }

        $plan = $this->createPlan($student, $teacher, $session, $items, 10);

        $this->assertSame(10, $plan->gate_size);
        $this->assertSame(10, $plan->items()->where('status', 'available')->count());
        $this->assertSame(2, $plan->items()->where('status', 'locked')->count());
    }

    public function test_plan_builder_shows_memorized_parts_and_autofill_button(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1, 2]);

        $this->actingAs($admin)
            ->get(route('admin.quran.listening.create', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('محفوظ')
            ->assertSee('توليد تلقائي من الأجزاء المحفوظة')
            ->assertSee('مراجعة 5');

        $other = $this->student($mosque, $session, 'طالب بلا حفظ');

        $this->actingAs($admin)
            ->get(route('admin.quran.listening.create', ['student_id' => $other->id]))
            ->assertOk()
            ->assertDontSee('توليد تلقائي من الأجزاء المحفوظة');
    }

    public function test_teacher_plan_builder_shows_review_and_memorized_parts(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1, 2]);

        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'amount' => 5,
            'from_page' => 1,
            'to_page' => 5,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.listening.create', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('مراجعة 5')
            ->assertSee('محفوظ')
            ->assertSee('توليد تلقائي من الأجزاء المحفوظة');
    }

    public function test_teacher_show_page_renders_the_khamsa_link_and_student_page_renders_items(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'amount' => 5,
            'from_page' => 1,
            'to_page' => 5,
        ]);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['type' => 'review', 'juz' => 1, 'khamsa' => 1],
            ['type' => 'new', 'juz' => 2, 'from_page' => 22, 'to_page' => 41],
        ], 1);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.listening.show', $plan))
            ->assertOk()
            ->assertSee('مراجعة 5 المرتبطة')
            ->assertDontSee('العنصر والصفحات');

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student->user_id = $studentUser->id;
        $student->save();

        $this->actingAs($studentUser)
            ->get(route('student.quran-listening.show', $plan))
            ->assertOk()
            ->assertSee('العنصر والصفحات')
            ->assertSee('الخمسة 1')
            ->assertSee('جديد');
    }

    public function test_listen_time_can_link_a_listening_with_teacher_session(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $otherStudent = $this->student($mosque, $session, 'طالب آخر');

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 21],
            ['juz' => 2, 'from_page' => 22, 'to_page' => 41],
        ], 2);

        $items = $plan->items()->orderBy('position')->get();
        $session = $this->listeningSession($student, $teacher);
        $foreign = $this->listeningSession($otherStudent, $teacher);

        $this->actingAs($admin)
            ->post(route('admin.quran.listening.items.listen', $items[0]), [
                'quran_review_session_id' => $session->id,
            ])
            ->assertRedirect();

        $this->assertSame($session->id, $items[0]->refresh()->quran_review_session_id);

        $this->actingAs($admin)
            ->post(route('admin.quran.listening.items.listen', $items[1]), [
                'quran_review_session_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('quran_review_session_id');

        $this->assertNull($items[1]->refresh()->quran_review_session_id);
    }

    public function test_student_portal_shows_merged_review_items(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, 'أحمد');
        $student->update(['user_id' => $studentUser->id]);
        $this->memorize($student, [1]);

        $this->createPlan($student, $teacher, $session, [
            ['type' => 'review', 'juz' => 1, 'khamsa' => 1],
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('مراجعة 5');

        $this->actingAs($studentUser)
            ->get(route('student.quran-listening.show', QuranListeningPlan::firstOrFail()))
            ->assertOk()
            ->assertSee('الخمسة 1');
    }
}
