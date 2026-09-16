<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\QuranKhamsaReview;
use App\Models\QuranRecitationSession;
use App\Models\Role;
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
 * «مراجعة 5» (الخمسات): تخصيص خمسات متعددة لطالب مع أستاذ ودوام، مع قفل
 * خمسات الأجزاء غير المحفوظة، وإنهاء الأستاذ لخمساته.
 */
class KhamsaReviewTest extends TestCase
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

    /** @param array<int, int> $juz */
    private function memorize(Student $student, array $juz): void
    {
        $service = app(QuranKhamsaService::class);

        foreach ($juz as $number) {
            $service->recordMemorization($student, $number);
        }
    }

    public function test_admin_can_assign_multiple_khamsat_from_different_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1, 2]);

        $response = $this->actingAs($admin)->post(route('admin.quran.khamsa.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => ['1:1', '1:2', '1:3', '2:1'],
        ]);

        $review = QuranKhamsaReview::firstOrFail();
        $response->assertRedirect(route('admin.quran.khamsa.show', $review));

        $this->assertSame(4, $review->items()->count());
        $this->assertSame('pending', $review->status->value);

        $this->assertDatabaseHas('quran_khamsa_review_items', [
            'review_id' => $review->id,
            'juz' => 1, 'khamsa' => 1, 'from_page' => 1, 'to_page' => 5,
        ]);
        $this->assertDatabaseHas('quran_khamsa_review_items', [
            'review_id' => $review->id,
            'juz' => 1, 'khamsa' => 3, 'from_page' => 11, 'to_page' => 15,
        ]);
        $this->assertDatabaseHas('quran_khamsa_review_items', [
            'review_id' => $review->id,
            'juz' => 2, 'khamsa' => 1, 'from_page' => 22, 'to_page' => 26,
        ]);

        $this->assertSame(20, $review->pagesCount());
    }

    public function test_create_page_renders_the_khamsa_picker_with_locks(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session, 'أحمد');
        $this->memorize($student, [1]);

        $this->actingAs($admin)
            ->get(route('admin.quran.khamsa.create', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('الخمسة 1')
            ->assertSee('محفوظ ✓')
            ->assertSee('غير محفوظ — خمساته مقفلة');
    }

    public function test_review_page_renders_items_and_progress(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $review = app(QuranKhamsaService::class)->createReview([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => [['juz' => 1, 'khamsa' => 2]],
        ], $admin);

        $this->actingAs($admin)
            ->get(route('admin.quran.khamsa.show', $review))
            ->assertOk()
            ->assertSee('الخمسة 2')
            ->assertSee('صفحات 6–10')
            ->assertSee('قيد المراجعة')
            ->assertSee('0 من 1 خمسة');
    }

    public function test_khamsat_of_unmemorized_juz_are_rejected_server_side(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $this->actingAs($admin)->post(route('admin.quran.khamsa.store'), [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => ['2:1'],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quran_khamsa_reviews', 0);
        $this->assertDatabaseCount('quran_khamsa_review_items', 0);
    }

    public function test_new_tasmee_covering_a_full_juz_marks_it_memorized(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'new',
            'date' => now()->toDateString(),
            'amount' => 21,
            'from_page' => 1,
            'to_page' => 21,
        ]);

        $this->assertDatabaseHas('student_juz_memorizations', [
            'student_id' => $student->id,
            'juz' => 1,
            'source' => 'tasmee',
        ]);
        $this->assertDatabaseMissing('student_juz_memorizations', [
            'student_id' => $student->id,
            'juz' => 2,
        ]);

        $available = collect(app(QuranKhamsaService::class)->availableKhamsat($student));

        $this->assertTrue($available->firstWhere('juz', 1)['memorized']);
        $this->assertTrue($available->firstWhere('juz', 1)['khamsat'][0]['unlocked']);
        $this->assertFalse($available->firstWhere('juz', 2)['memorized']);
        $this->assertFalse($available->firstWhere('juz', 2)['khamsat'][0]['unlocked']);
    }

    public function test_teacher_completes_his_item_and_the_review_closes(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
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

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.khamsa.items.complete', $item), ['result' => 'excellent'])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('completed', $item->status->value);
        $this->assertSame('excellent', $item->result->value);
        $this->assertSame('completed', $review->refresh()->status->value);
    }

    public function test_another_teacher_cannot_complete_someone_elses_item(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        [$otherUser] = $this->teacher($mosque, $session, 'أستاذ آخر');
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

        $this->actingAs($otherUser)
            ->post(route('teacher.quran.khamsa.items.complete', $item))
            ->assertForbidden();

        $this->assertSame('pending', $item->refresh()->status->value);
    }

    public function test_teacher_cannot_assign_to_an_unrelated_student(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1]);

        $this->actingAs($teacherUser)->post(route('teacher.quran.khamsa.store'), [
            'student_id' => $student->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => ['1:1'],
        ])->assertForbidden();

        $this->assertDatabaseCount('quran_khamsa_reviews', 0);
    }

    public function test_student_portal_shows_only_his_reviews(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session, 'الأستاذ محمد');

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, 'أحمد');
        $student->update(['user_id' => $studentUser->id]);

        $otherStudent = $this->student($mosque, $session, 'خالد');
        [, $otherTeacher] = $this->teacher($mosque, $session, 'الأستاذ سامي');

        $this->memorize($student, [1]);
        $this->memorize($otherStudent, [1]);

        $service = app(QuranKhamsaService::class);

        $service->createReview([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => [['juz' => 1, 'khamsa' => 1]],
        ], $admin);

        $service->createReview([
            'student_id' => $otherStudent->id,
            'teacher_id' => $otherTeacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => [['juz' => 1, 'khamsa' => 2]],
        ], $admin);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('الأستاذ محمد')
            ->assertSee('الخمسة 1')
            ->assertDontSee('الأستاذ سامي');
    }

    public function test_student_form_syncs_memorized_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $this->memorize($student, [1, 2]);

        $this->actingAs($admin)->patch(route('admin.students.update', $student), [
            'name' => $student->name,
            'gender' => $student->gender,
            'memorized_juz_numbers_present' => 1,
            'memorized_juz_numbers' => [1, 3],
        ])->assertRedirect();

        $juz = $student->juzMemorizations()
            ->orderBy('juz')
            ->pluck('juz')
            ->map(fn ($value) => (int) $value)
            ->all();

        $this->assertSame([1, 3], $juz);
    }

    public function test_revoking_permission_blocks_the_feature(): void
    {
        [$mosque, $admin, $session] = $this->mosque();

        $role = Role::where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail();

        $role->permissions()->detach(
            Permission::where('code', 'quran_khamsa.view')->value('id')
        );

        $this->actingAs($admin)
            ->get(route('admin.quran.khamsa.index'))
            ->assertForbidden();
    }

    public function test_admin_shift_filter_hides_other_shift_reviews(): void
    {
        [$mosque, $admin, $first] = $this->mosque();
        $second = StudySession::where('tenant_id', $mosque->id)
            ->where('id', '!=', $first->id)
            ->firstOrFail();

        [, $teacherFirst] = $this->teacher($mosque, $first);
        [, $teacherSecond] = $this->teacher($mosque, $second);
        $studentFirst = $this->student($mosque, $first, 'طالب الدوام الأول');
        $studentSecond = $this->student($mosque, $second, 'طالب الدوام الثاني');

        $this->memorize($studentFirst, [1]);
        $this->memorize($studentSecond, [1]);

        $gating = app(QuranMemorizationGatingService::class);
        $gating->sync($studentFirst);
        $gating->sync($studentSecond);

        $service = app(QuranKhamsaService::class);

        $service->createReview([
            'student_id' => $studentFirst->id,
            'teacher_id' => $teacherFirst->id,
            'study_session_id' => $first->id,
            'assigned_at' => now()->toDateString(),
            'items' => [['juz' => 1, 'khamsa' => 1]],
        ], $admin);

        $service->createReview([
            'student_id' => $studentSecond->id,
            'teacher_id' => $teacherSecond->id,
            'study_session_id' => $second->id,
            'assigned_at' => now()->toDateString(),
            'items' => [['juz' => 1, 'khamsa' => 1]],
        ], $admin);

        $this->actingAs($admin)
            ->withSession(['study_session_id' => $first->id])
            ->get(route('admin.quran.batches.index'))
            ->assertOk()
            ->assertSee('طالب الدوام الأول')
            ->assertDontSee('طالب الدوام الثاني');
    }
}
