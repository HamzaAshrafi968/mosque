<?php

namespace Tests\Feature;

use App\Enums\ShariaMemorizationStatus;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\RewardPoint;
use App\Models\RewardPointRule;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranKhamsaService;
use App\Services\QuranListeningService;
use App\Services\QuranMemorizationGatingService;
use App\Services\RewardPointSettingsService;
use App\Services\RoleService;
use App\Services\ShariaCourseService;
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

    public function test_student_portal_points_page_is_disabled(): void
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
            ->assertRedirect(route('portal.disabled'));
    }

    public function test_new_rule_types_and_master_switch_are_saved_from_settings(): void
    {
        [$mosque, $admin, $first, $second] = $this->mosque();

        $this->actingAs($admin)
            ->patch(route('admin.settings.rewards.update'), [
                'automatic_enabled' => 0,
                'rules' => [
                    $first->id => [
                        'listening_plan_complete' => ['enabled' => 1, 'points' => 15],
                        'sharia_memorization_complete' => ['enabled' => 1, 'points' => 25],
                    ],
                    $second->id => [
                        'listening_plan_complete' => ['enabled' => 0, 'points' => 15],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reward_point_rules', [
            'study_session_id' => $first->id,
            'rule_type' => RewardPointRule::TYPE_LISTENING_PLAN,
            'points' => 15,
        ]);
        $this->assertDatabaseHas('reward_point_rules', [
            'study_session_id' => $first->id,
            'rule_type' => RewardPointRule::TYPE_SHARIA_MEMORIZATION,
            'points' => 25,
        ]);
        $this->assertDatabaseMissing('reward_point_rules', [
            'study_session_id' => $second->id,
            'rule_type' => RewardPointRule::TYPE_LISTENING_PLAN,
        ]);
        $this->assertDatabaseHas('tenant_settings', [
            'key' => RewardPointSettingsService::KEY_ENABLED,
            'value' => '0',
        ]);
    }

    public function test_disabled_rule_is_removed_even_when_it_has_points(): void
    {
        [$mosque, $admin, $first] = $this->mosque();
        $this->rule($first, RewardPointRule::TYPE_TEST_PASS, 20);

        $this->actingAs($admin)
            ->patch(route('admin.settings.rewards.update'), [
                'rules' => [
                    $first->id => [
                        'test_pass' => ['enabled' => 0, 'points' => 20],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('reward_point_rules', [
            'study_session_id' => $first->id,
            'rule_type' => RewardPointRule::TYPE_TEST_PASS,
        ]);
    }

    public function test_master_switch_stops_and_resumes_automatic_awards(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->rule($session, RewardPointRule::TYPE_TASMEE_PAGES, 10, 5);

        app(RewardPointSettingsService::class)->setEnabled(false);

        $this->tasmee($student, $teacher, 1, 5);
        $this->assertDatabaseCount('reward_points', 0);

        app(RewardPointSettingsService::class)->setEnabled(true);

        $this->tasmee($student, $teacher, 6, 10);
        $this->assertSame(1, RewardPoint::where('student_id', $student->id)->count());
    }

    public function test_listening_plan_completion_awards_points_once(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->rule($session, RewardPointRule::TYPE_LISTENING_PLAN, 15);

        $listening = app(QuranListeningService::class);

        $plan = $listening->createPlan([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'gate_size' => 1,
            'items' => [['type' => 'new', 'juz' => 1, 'from_page' => 1, 'to_page' => 5]],
        ], $admin);

        $item = $plan->items()->firstOrFail();

        $listening->markListened($item, $admin);
        $listening->recordTest($plan, [$item->id => ['result' => 'pass']], $admin);

        $this->assertDatabaseHas('reward_points', [
            'student_id' => $student->id,
            'points' => 15,
            'source_type' => RewardPoint::SOURCE_LISTENING_PLAN,
            'source_id' => $plan->id,
            'study_session_id' => $session->id,
        ]);
        $this->assertSame(1, RewardPoint::where('source_type', RewardPoint::SOURCE_LISTENING_PLAN)->count());
    }

    public function test_sharia_memorization_completion_awards_points_once(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $this->rule($session, RewardPointRule::TYPE_SHARIA_MEMORIZATION, 25);

        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة الفقه',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $enrolled = ShariaCourseStudent::create([
            'tenant_id' => $mosque->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'name' => $student->name,
            'status' => 'active',
        ]);

        $service = app(ShariaCourseService::class);

        $service->updateMemorization($enrolled, ShariaMemorizationStatus::HalfMemorized, null, $admin);
        $this->assertSame(0, RewardPoint::where('source_type', RewardPoint::SOURCE_SHARIA_MEMORIZATION)->count());

        $service->updateMemorization($enrolled, ShariaMemorizationStatus::Memorized, null, $admin);
        $service->updateMemorization($enrolled, ShariaMemorizationStatus::Memorized, 'تأكيد', $admin);

        $this->assertDatabaseHas('reward_points', [
            'student_id' => $student->id,
            'points' => 25,
            'source_type' => RewardPoint::SOURCE_SHARIA_MEMORIZATION,
            'source_id' => $enrolled->id,
            'study_session_id' => $session->id,
        ]);
        $this->assertSame(1, RewardPoint::where('source_type', RewardPoint::SOURCE_SHARIA_MEMORIZATION)->count());
    }

    public function test_settings_center_lists_all_sections_for_the_manager(): void
    {
        [$mosque, $admin] = $this->mosque();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('مركز الإعدادات')
            ->assertSee('برنامج القرآن')
            ->assertSee('نقاط المكافآت')
            ->assertSee('الحسابات والصلاحيات')
            ->assertSee('الدوامات');
    }

    public function test_teacher_cannot_open_the_settings_center(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);

        $this->actingAs($teacherUser)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }
}
