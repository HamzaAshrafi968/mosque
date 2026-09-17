<?php

namespace App\Services;

use App\Enums\QuranTasmeeType;
use App\Enums\ShariaMemorizationStatus;
use App\Models\QuranKhamsaReview;
use App\Models\QuranKhamsaReviewItem;
use App\Models\QuranListeningPlan;
use App\Models\QuranListeningTest;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\RewardPoint;
use App\Models\RewardPointRule;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * منح نقاط المكافآت تلقائياً وفق قواعد كل دوام (reward_point_rules):
 *
 * - حفظ صفحات جديدة: تراكمي مع ترحيل الباقي. تُحسب الصفحات المغطاة فعلياً
 *   (دمج نطاق تسميع «جديد»)، وتُخصم الصفحات التي سبق مكافأتها (source_pages)،
 *   ثم كل ما يكمل عدد الصفحات المحدد في القاعدة يُمنح نقاطه.
 * - إتمام خمسة مراجعة: نقاط لكل خمسة تُنجَز (يدوياً أو آلياً عند نجاح الاختبار).
 * - اجتياز اختبار دفعة الحفظ: نقاط مرة واحدة لكل اختبار ناجح.
 * - إتمام خطة الاستماع والاختبار: نقاط مرة واحدة لكل خطة تُنجَز بالكامل.
 * - حفظ الدورة الشرعية كاملاً: نقاط مرة واحدة لكل طالب عند وصوله «حفظ كامل».
 *
 * المنح التلقائي لا يُخصم رجعياً أبداً، وتعديل القاعدة لا يمس النقاط السابقة،
 * ويمكن إيقاف المنح كله من مفتاح «المنح التلقائي» في إعدادات النقاط.
 */
class RewardPointAutoService
{
    public function __construct(private readonly RewardPointSettingsService $settings) {}

    public function ruleFor(?string $studySessionId, string $type): ?RewardPointRule
    {
        if (! $this->settings->isEnabled()) {
            return null;
        }

        if ($studySessionId === null || $studySessionId === '') {
            return null;
        }

        return RewardPointRule::query()
            ->where('study_session_id', $studySessionId)
            ->where('rule_type', $type)
            ->first();
    }

    /** نقاط الحفظ التراكمية عند تسجيل/تعديل تسميع «جديد». */
    public function awardForTasmee(QuranRecitationSession $session): void
    {
        if ($session->type !== QuranTasmeeType::New || $session->student_id === null) {
            return;
        }

        if ($session->from_page === null || $session->to_page === null) {
            return;
        }

        $student = $this->student($session->student_id);

        if (! $student) {
            return;
        }

        $rule = $this->ruleFor($student->study_session_id, RewardPointRule::TYPE_TASMEE_PAGES);

        if (! $rule?->isActive() || ! $rule->pages_count || $rule->pages_count < 1) {
            return;
        }

        DB::transaction(function () use ($session, $student, $rule) {
            $student->newQuery()
                ->withoutGlobalScope('study_session')
                ->whereKey($student->id)
                ->lockForUpdate()
                ->first();

            $covered = $this->coveredPages($student->id);
            $awarded = (int) RewardPoint::query()
                ->where('student_id', $student->id)
                ->where('source_type', RewardPoint::SOURCE_TASMEE)
                ->sum('source_pages');

            $delta = $covered - $awarded;

            if ($delta < $rule->pages_count) {
                return;
            }

            $times = intdiv($delta, $rule->pages_count);
            $pages = $times * $rule->pages_count;

            RewardPoint::create([
                'student_id' => $student->id,
                'awarded_by' => $session->teacher?->user_id,
                'study_session_id' => $student->study_session_id,
                'source_type' => RewardPoint::SOURCE_TASMEE,
                'source_id' => $session->id,
                'source_pages' => $pages,
                'points' => $times * $rule->points,
                'type' => 'earned',
                'reason' => "حفظ {$pages} صفحة جديدة",
                'notes' => "قاعدة الدوام: كل {$rule->pages_count} صفحات = {$rule->points} نقاط",
            ]);
        });
    }

    /** نقاط إتمام خمسة مراجعة (تُمنح مرة واحدة لكل خمسة). */
    public function awardForKhamsaItem(QuranKhamsaReviewItem $item, User $actor): void
    {
        $review = QuranKhamsaReview::withoutGlobalScope('study_session')->find($item->review_id);

        if (! $review) {
            return;
        }

        $rule = $this->ruleFor($review->study_session_id, RewardPointRule::TYPE_KHAMSA_REVIEW);

        if (! $rule?->isActive()) {
            return;
        }

        if ($this->alreadyAwarded(RewardPoint::SOURCE_KHAMSA_ITEM, $item->id)) {
            return;
        }

        RewardPoint::create([
            'student_id' => $review->student_id,
            'awarded_by' => $actor->id,
            'study_session_id' => $review->study_session_id,
            'source_type' => RewardPoint::SOURCE_KHAMSA_ITEM,
            'source_id' => $item->id,
            'points' => $rule->points,
            'type' => 'earned',
            'reason' => 'إتمام خمسة المراجعة ('.$item->label().')',
            'notes' => 'نقاط تلقائية من مراجعة الخمسات',
        ]);
    }

    /** نقاط اجتياز اختبار دفعة الحفظ (مرة واحدة لكل اختبار ناجح). */
    public function awardForTestPass(QuranMemorizationBatch $batch, QuranListeningTest $test, User $actor): void
    {
        $student = $this->student($batch->student_id);

        if (! $student) {
            return;
        }

        $rule = $this->ruleFor($student->study_session_id, RewardPointRule::TYPE_TEST_PASS);

        if (! $rule?->isActive()) {
            return;
        }

        if ($this->alreadyAwarded(RewardPoint::SOURCE_TEST, $test->id)) {
            return;
        }

        RewardPoint::create([
            'student_id' => $student->id,
            'awarded_by' => $actor->id,
            'study_session_id' => $student->study_session_id,
            'source_type' => RewardPoint::SOURCE_TEST,
            'source_id' => $test->id,
            'points' => $rule->points,
            'type' => 'earned',
            'reason' => 'اجتياز '.$batch->label(),
            'notes' => 'نقاط تلقائية من اختبار دفعة الحفظ',
        ]);
    }

    /** نقاط إتمام خطة الاستماع والاختبار (مرة واحدة لكل خطة مكتملة). */
    public function awardForListeningPlan(QuranListeningPlan $plan, User $actor): void
    {
        $student = $this->student($plan->student_id);

        if (! $student) {
            return;
        }

        $sessionId = $plan->study_session_id ?: $student->study_session_id;

        $rule = $this->ruleFor($sessionId, RewardPointRule::TYPE_LISTENING_PLAN);

        if (! $rule?->isActive()) {
            return;
        }

        if ($this->alreadyAwarded(RewardPoint::SOURCE_LISTENING_PLAN, $plan->id)) {
            return;
        }

        RewardPoint::create([
            'student_id' => $student->id,
            'awarded_by' => $actor->id,
            'study_session_id' => $sessionId,
            'source_type' => RewardPoint::SOURCE_LISTENING_PLAN,
            'source_id' => $plan->id,
            'points' => $rule->points,
            'type' => 'earned',
            'reason' => 'إتمام خطة الاستماع'.($plan->title ? ' — '.$plan->title : ''),
            'notes' => 'نقاط تلقائية من إتمام خطة الاستماع والاختبار',
        ]);
    }

    /** نقاط حفظ الدورة الشرعية كاملاً (مرة واحدة لكل طالب في الدورة). */
    public function awardForShariaMemorization(ShariaCourseStudent $enrolled, User $actor): void
    {
        if ($enrolled->student_id === null) {
            return;
        }

        if ($enrolled->memorization_status !== ShariaMemorizationStatus::Memorized) {
            return;
        }

        $student = $this->student($enrolled->student_id);

        if (! $student) {
            return;
        }

        $rule = $this->ruleFor($student->study_session_id, RewardPointRule::TYPE_SHARIA_MEMORIZATION);

        if (! $rule?->isActive()) {
            return;
        }

        if ($this->alreadyAwarded(RewardPoint::SOURCE_SHARIA_MEMORIZATION, $enrolled->id)) {
            return;
        }

        RewardPoint::create([
            'student_id' => $student->id,
            'awarded_by' => $actor->id,
            'study_session_id' => $student->study_session_id,
            'source_type' => RewardPoint::SOURCE_SHARIA_MEMORIZATION,
            'source_id' => $enrolled->id,
            'points' => $rule->points,
            'type' => 'earned',
            'reason' => 'حفظ الدورة الشرعية كاملاً',
            'notes' => 'نقاط تلقائية من إتمام حفظ الدورة الشرعية',
        ]);
    }

    private function coveredPages(string $studentId): int
    {
        $intervals = app(QuranMemorizationGatingService::class)->coveredPageIntervals($studentId);

        return array_sum(array_map(
            fn (array $interval) => $interval[1] - $interval[0] + 1,
            $intervals
        ));
    }

    private function alreadyAwarded(string $sourceType, string $sourceId): bool
    {
        return RewardPoint::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    private function student(string $studentId): ?Student
    {
        return Student::query()
            ->withoutGlobalScope('study_session')
            ->find($studentId);
    }
}
