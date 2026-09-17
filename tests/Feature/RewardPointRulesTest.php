<?php

namespace Tests\Feature;

use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\RewardPoint;
use App\Models\RewardPointRule;
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
 * نقاط المكافآت لكل دوام: قواعد قابلة للتعديل من الإعدادات (حفظ صفحات جديدة
 * تراكمي مع ترحيل الباقي / إتمام خمسة / اجتياز اختبار الدفعة)، ومنح تلقائي
 * عند الأحداث، وبوابة الطالب.
 */
class RewardPointRulesTest extends TestCase
{
    /** @return array{0: Tenant, 1: User, 2: StudySession, 3: StudySession} */
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();

        $first = StudySession::where('name', StudySessionService::DEFAULT_SESSIONS[0])->firstOrFail();
        $second = StudySession::where('name', StudySessionService::DEFAULT_SESSIONS[1])->firstOrFail();

        return [$mosque, $admin, $first, $second];
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

    private function rule(StudySession $session, string $type, int $points, ?int $pages = null): RewardPointRule
    {
        return RewardPointRule::create([
            'study_session_id' => $session->id,
            'rule_type' => $type,
            'pages_count' => $pages,
            'points' => $points,
        ]);
    }

    private function tasmee(Student $student, Teacher $teacher, int $from, int $to, string $type = 'new'): QuranRecitationSession
    {
        return QuranRecitationSession::create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => $type,
            'date' => now()->toDateString(),
            'from_page' => $from,
            'to_page' => $to,
        ]);
    }

    /** @param array<int, int> $juzNumbers */
    private function memorize(Student $student, array $juzNumbers): void
    {
        $service = app(QuranKhamsaService::class);

        foreach ($juzNumbers as $juz) {
            $service->recordMemorization($student, $juz);
        }

        app(QuranMemorizationGatingService::class)->sync($student);
    }

    private function batch(Student $student, int $number): QuranMemorizationBatch
    {
        return QuranMemorizationBatch::query()
            ->where('student_id', $student->id)
            ->where('batch_number', $number)
            ->firstOrFail();
    }

    private function completeReview(QuranMemorizationBatch $batch, User $actor): void
    {
        $review = $batch->review5()->firstOrFail();

        foreach ($review->items()->orderBy('from_page')->get() as $item) {
            app(QuranKhamsaService::class)->completeItem($item, [], $actor);
        }
    }

    public function test_admin_can_save_rules_for_each_study_session(): void
    {
        [$mosque, $admin, $first, $second] = $this->mosque();

        $this->actingAs($admin)
            ->get(route('admin.settings.rewards.edit'))
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee($second->name);

        $this->actingAs($admin)
            ->patch(route('admin.settings.rewards.update'), [
                'rules' => [
                    $first->id => [
                        'tasmee_pages' => ['pages_count' => 5, 'points' => 10],
                        'khamsa_review' => ['points' => 2],
                        'test_pass' => ['points' => 20],
                    ],
                    $second->id => [
                        'tasmee_pages' => ['pages_count' => null, 'points' => null],
                        'khamsa_review' => ['points' => 1],
                        'test_pass' => ['points' => null],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reward_point_rules', [
            'study_session_id' => $first->id,
            'rule_type' => RewardPointRule::TYPE_TASMEE_PAGES,
            'pages_count' => 5,
            'points' => 10,
        ]);
        $this->assertDatabaseHas('reward_point_rules', [
            'study_session_id' => $first->id,
            'rule_type' => RewardPointRule::TYPE_TEST_PASS,
            'points' => 20,
        ]);
        $this->assertDatabaseHas('reward_point_rules', [
            'study_session_id' => $second->id,
            'rule_type' => RewardPointRule::TYPE_KHAMSA_REVIEW,
            'points' => 1,
        ]);
        $this->assertDatabaseMissing('reward_point_rules', [
            'study_session_id' => $second->id,
            'rule_type' => RewardPointRule::TYPE_TASMEE_PAGES,
        ]);
    }

    public function test_pages_count_is_required_when_tasmee_points_are_enabled(): void
    {
        [$mosque, $admin, $first] = $this->mosque();

        $this->actingAs($admin)
            ->patch(route('admin.settings.rewards.update'), [
                'rules' => [
                    $first->id => [
                        'tasmee_pages' => ['pages_count' => null, 'points' => 10],
                    ],
                ],
            ])
            ->assertSessionHasErrors("rules.{$first->id}.tasmee_pages.pages_count");

        $this->assertDatabaseCount('reward_point_rules', 0);
    }

    public function test_tasmee_points_are_cumulative_with_carry_over(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->rule($session, RewardPointRule::TYPE_TASMEE_PAGES, 10, 5);

        $this->tasmee($student, $teacher, 1, 3);
        $this->assertDatabaseCount('reward_points', 0);

        $this->tasmee($student, $teacher, 4, 5);
        $this->assertDatabaseCount('reward_points', 1);
        $this->assertDatabaseHas('reward_points', [
            'student_id' => $student->id,
            'points' => 10,
            'source_pages' => 5,
            'source_type' => RewardPoint::SOURCE_TASMEE,
            'study_session_id' => $session->id,
        ]);

        $this->tasmee($student, $teacher, 6, 10);

        $this->assertSame(2, RewardPoint::where('student_id', $student->id)->count());
        $this->assertSame(20, (int) RewardPoint::where('student_id', $student->id)->sum('points'));
    }

    public function test_editing_tasmee_does_not_double_award(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->rule($session, RewardPointRule::TYPE_TASMEE_PAGES, 10, 5);

        $tasmee = $this->tasmee($student, $teacher, 1, 5);

        $tasmee->update(['result' => 'good']);
        $tasmee->update(['to_page' => 7]);

        $this->assertSame(1, RewardPoint::where('student_id', $student->id)->count());
        $this->assertSame(10, (int) RewardPoint::where('student_id', $student->id)->sum('points'));
    }

    public function test_rules_are_isolated_per_study_session(): void
    {
        [$mosque, $admin, $first, $second] = $this->mosque();
        [, $teacherFirst] = $this->teacher($mosque, $first);
        [, $teacherSecond] = $this->teacher($mosque, $second, 'الأستاذ الثاني');
        $studentFirst = $this->student($mosque, $first, 'طالب الأول');
        $studentSecond = $this->student($mosque, $second, 'طالب الثاني');

        $this->rule($first, RewardPointRule::TYPE_TASMEE_PAGES, 10, 5);

        $this->tasmee($studentFirst, $teacherFirst, 1, 5);
        $this->tasmee($studentSecond, $teacherSecond, 22, 26);

        $this->assertSame(1, RewardPoint::count());
        $this->assertSame($studentFirst->id, RewardPoint::firstOrFail()->student_id);
    }

    public function test_completing_khamsa_awards_points_once(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->rule($session, RewardPointRule::TYPE_KHAMSA_REVIEW, 2);
        $this->memorize($student, [1]);

        $review = app(QuranKhamsaService::class)->createReview([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'assigned_at' => now()->toDateString(),
            'items' => [['juz' => 1, 'khamsa' => 1]],
        ], $admin);

        $item = $review->items()->firstOrFail();

        app(QuranKhamsaService::class)->completeItem($item, [], $admin);

        $this->assertDatabaseHas('reward_points', [
            'student_id' => $student->id,
            'points' => 2,
            'source_type' => RewardPoint::SOURCE_KHAMSA_ITEM,
            'source_id' => $item->id,
            'study_session_id' => $session->id,
        ]);
        $this->assertSame(1, RewardPoint::where('student_id', $student->id)->count());
    }

    public function test_batch_test_pass_awards_points_and_fail_does_not(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $this->teacher($mosque, $session);
        $this->rule($session, RewardPointRule::TYPE_TEST_PASS, 20);

        $gating = app(QuranMemorizationGatingService::class);

        $passed = $this->student($mosque, $session, 'ناجح');
        $this->memorize($passed, [1, 2]);
        $batch = $this->batch($passed, 1);
        $this->completeReview($batch, $admin);

        $results = [];
        foreach ($gating->testScopeJuzNumbers($batch) as $juz) {
            $results[$juz] = 'pass';
        }

        $gating->recordCumulativeTest($batch, $results, $admin);

        $this->assertDatabaseHas('reward_points', [
            'student_id' => $passed->id,
            'points' => 20,
            'source_type' => RewardPoint::SOURCE_TEST,
            'study_session_id' => $session->id,
        ]);

        $failed = $this->student($mosque, $session, 'راسب');
        $this->memorize($failed, [1, 2]);
        $failedBatch = $this->batch($failed, 1);
        $this->completeReview($failedBatch, $admin);

        $failedResults = [];
        foreach ($gating->testScopeJuzNumbers($failedBatch) as $juz) {
            $failedResults[$juz] = 'fail';
        }

        $gating->recordCumulativeTest($failedBatch, $failedResults, $admin);

        $this->assertSame(0, RewardPoint::where('student_id', $failed->id)
            ->where('source_type', RewardPoint::SOURCE_TEST)
            ->count());
    }

    public function test_teacher_cannot_delete_automatic_points(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->rule($session, RewardPointRule::TYPE_TASMEE_PAGES, 10, 5);
        $this->tasmee($student, $teacher, 1, 5);

        $point = RewardPoint::where('student_id', $student->id)->firstOrFail();

        $this->assertSame($teacherUser->id, $point->awarded_by);

        $this->actingAs($teacherUser)
            ->delete(route('teacher.reward-points.destroy', $point->id))
            ->assertForbidden();
    }

    public function test_teacher_manual_points_are_linked_to_the_students_shift(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->actingAs($teacherUser)
            ->post(route('teacher.reward-points.store'), [
                'student_id' => $student->id,
                'points' => 5,
                'type' => 'earned',
                'reason' => 'مساعدة زملائه',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reward_points', [
            'student_id' => $student->id,
            'study_session_id' => $session->id,
            'points' => 5,
        ]);
    }

    public function test_student_portal_shows_points_balance_and_history(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student->update(['user_id' => $studentUser->id]);

        RewardPoint::create([
            'student_id' => $student->id,
            'awarded_by' => $admin->id,
            'points' => 7,
            'reason' => 'مكافأة تميز',
            'type' => 'earned',
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.reward-points'))
            ->assertOk()
            ->assertSee('مكافأة تميز')
            ->assertSee('+7');
    }
}
