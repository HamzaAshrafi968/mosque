<?php

namespace App\Services;

use App\Enums\QuranKhamsaItemStatus;
use App\Enums\QuranKhamsaReviewStatus;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranTasmeeType;
use App\Models\QuranKhamsaReview;
use App\Models\QuranKhamsaReviewItem;
use App\Models\QuranListeningPlanItem;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranRecitationSession;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Models\StudentJuzMemorization;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\User;
use App\Support\QuranJuzMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «مراجعة 5» (الخمسات):
 *
 * - كل جزء = ٤ خمسات (٥ صفحات لكل خمسة، والخمسة الأخيرة تأخذ الباقي).
 * - لا تُفتح خمسات جزء إلا إذا كان الجزء مسجّلاً محفوظاً للطالب
 *   (student_juz_memorizations).
 * - المراجعة رأس واحد يضم عدة خمسات لطالب مع أستاذ ودوام، والأستاذ المكلَّف
 *   يُنهي كل خمسة على حدة.
 */
class QuranKhamsaService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuranMemorizationGatingService $gating,
    ) {}

    /**
     * كل الخمسات المتاحة للطالب (٣٠ جزءاً × ٤) مع حالة الفتح والقفل.
     *
     * @return array<int, array{juz: int, from_page: int, to_page: int, memorized: bool, khamsat: array<int, array{khamsa: int, from_page: int, to_page: int, pages: int, unlocked: bool, already_assigned: bool}>}>
     */
    public function availableKhamsat(Student $student): array
    {
        $memorized = $this->memorizedJuzNumbers($student);
        $assigned = $this->pendingKhamsaKeys($student);

        $rows = [];

        foreach (range(1, QuranJuzMap::TOTAL_JUZ) as $juz) {
            $range = QuranJuzMap::pageRange($juz);
            $khamsat = [];

            foreach (QuranJuzMap::khamsat($juz) as $khamsa => $pages) {
                $khamsat[] = [
                    'khamsa' => $khamsa,
                    'from_page' => $pages['from'],
                    'to_page' => $pages['to'],
                    'pages' => $pages['to'] - $pages['from'] + 1,
                    'unlocked' => in_array($juz, $memorized, true),
                    'already_assigned' => in_array($juz.':'.$khamsa, $assigned, true),
                ];
            }

            $rows[] = [
                'juz' => $juz,
                'from_page' => $range['from'],
                'to_page' => $range['to'],
                'memorized' => in_array($juz, $memorized, true),
                'khamsat' => $khamsat,
            ];
        }

        return $rows;
    }

    /** @return array<int, int> أرقام الأجزاء المحفوظة للطالب. */
    public function memorizedJuzNumbers(Student $student): array
    {
        return StudentJuzMemorization::query()
            ->where('student_id', $student->id)
            ->orderBy('juz')
            ->pluck('juz')
            ->map(fn ($juz) => (int) $juz)
            ->all();
    }

    public function isJuzMemorized(Student $student, int $juz): bool
    {
        return in_array($juz, $this->memorizedJuzNumbers($student), true);
    }

    /** هل الخمسة (جزء:خمسة) مخصّصة مسبقاً وقيد المراجعة للطالب؟ */
    public function isKhamsaPending(Student $student, int $juz, int $khamsa): bool
    {
        return in_array($juz.':'.$khamsa, $this->pendingKhamsaKeys($student), true);
    }

    /**
     * إنشاء مراجعة جديدة (رأس + خمسات) مع فرض قواعد القفل والدوام.
     * يمكن ربطها بخطة استماع (listening_plan_id) عند دمج «مراجعة 5» فيها.
     *
     * @param  array{student_id: string, teacher_id: string, study_session_id: string, listening_plan_id?: ?string, assigned_at?: string, due_date?: ?string, notes?: ?string, items: array<int, array{juz: int|string, khamsa: int|string}>}  $data
     */
    public function createReview(array $data, User $actor): QuranKhamsaReview
    {
        $student = Student::query()->find($data['student_id']);

        if (! $student) {
            throw ValidationException::withMessages(['student_id' => ['الطالب غير موجود']]);
        }

        $teacher = Teacher::query()->find($data['teacher_id']);

        if (! $teacher) {
            throw ValidationException::withMessages(['teacher_id' => ['الأستاذ غير موجود']]);
        }

        $session = StudySession::query()->find($data['study_session_id']);

        if (! $session) {
            throw ValidationException::withMessages(['study_session_id' => ['الدوام المحدد غير موجود']]);
        }

        if ((string) $student->study_session_id !== (string) $session->id) {
            throw ValidationException::withMessages(['study_session_id' => ['الطالب ليس ضمن الدوام المحدد']]);
        }

        $teacherInSession = (string) $teacher->study_session_id === (string) $session->id
            || $teacher->studySessions()->whereKey($session->id)->exists();

        if (! $teacherInSession) {
            throw ValidationException::withMessages(['teacher_id' => ['الأستاذ ليس ضمن الدوام المحدد']]);
        }

        $items = $this->normalizeItems($data['items'] ?? []);

        if ($items === []) {
            throw ValidationException::withMessages(['items' => ['اختر خمسة واحدة على الأقل']]);
        }

        $memorized = $this->memorizedJuzNumbers($student);
        $pending = $this->pendingKhamsaKeys($student);

        foreach ($items as $item) {
            if (! in_array($item['juz'], $memorized, true)) {
                throw ValidationException::withMessages([
                    'items' => ['لا يمكن تخصيص خمسات من الجزء '.$item['juz'].' قبل تسجيل حفظه للطالب'],
                ]);
            }

            if (in_array($item['juz'].':'.$item['khamsa'], $pending, true)) {
                throw ValidationException::withMessages([
                    'items' => ['الخمسة '.$item['khamsa'].' من الجزء '.$item['juz'].' مخصّصة مسبقاً وقيد المراجعة'],
                ]);
            }
        }

        return DB::transaction(function () use ($student, $teacher, $session, $items, $data, $actor) {
            $review = QuranKhamsaReview::create([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'study_session_id' => $session->id,
                'assigned_by' => $actor->id,
                'listening_plan_id' => $data['listening_plan_id'] ?? null,
                'assigned_at' => $data['assigned_at'] ?? Carbon::today(),
                'due_date' => $data['due_date'] ?? null,
                'status' => QuranKhamsaReviewStatus::Pending,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $range = QuranJuzMap::khamsaRange($item['juz'], $item['khamsa']);

                QuranKhamsaReviewItem::create([
                    'review_id' => $review->id,
                    'teacher_id' => $teacher->id,
                    'juz' => $item['juz'],
                    'khamsa' => $item['khamsa'],
                    'from_page' => $range['from'],
                    'to_page' => $range['to'],
                    'status' => QuranKhamsaItemStatus::Pending,
                ]);
            }

            $this->audit->logModel('khamsa_review.created', $review, actor: $actor);

            return $review->load('items');
        });
    }

    /**
     * إنهاء خمسة: النتيجة والملاحظات اختياريان، ويمكن ربط جلسة «الاستماع مع
     * المعلم» الخاصة بالطالب (فتُحتسب نقاطها من الجلسة نفسها).
     *
     * @param  array{result?: ?string, notes?: ?string, quran_review_session_id?: ?string}  $data
     */
    public function completeItem(QuranKhamsaReviewItem $item, array $data, User $actor, bool $syncPlan = true): void
    {
        if ($item->isCompleted()) {
            throw ValidationException::withMessages(['item' => ['هذه الخمسة منجَزة مسبقاً']]);
        }

        $review = $item->relationLoaded('review')
            ? $item->review
            : ($item->review_id ? QuranKhamsaReview::withoutGlobalScope('study_session')->find($item->review_id) : null);

        if (! $review || $review->isCancelled()) {
            throw ValidationException::withMessages(['item' => ['لا يمكن إنهاء خمسة في مراجعة ملغاة']]);
        }

        $sessionId = $data['quran_review_session_id'] ?? null;

        if ($sessionId !== null && $sessionId !== '') {
            $belongs = QuranReviewSession::query()
                ->whereKey($sessionId)
                ->where('student_id', $review->student_id)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'quran_review_session_id' => ['جلسة الاستماع المحددة لا تخص هذا الطالب'],
                ]);
            }
        } else {
            $sessionId = null;
        }

        DB::transaction(function () use ($item, $data, $sessionId, $actor, $review, $syncPlan) {
            $item->update([
                'status' => QuranKhamsaItemStatus::Completed,
                'result' => $data['result'] ?? null,
                'notes' => $data['notes'] ?? null,
                'quran_review_session_id' => $sessionId,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ]);

            $this->audit->logModel('khamsa_review.item_completed', $item, actor: $actor);

            if ($syncPlan) {
                $this->syncListeningPlanItem($item, $sessionId, $actor);
            }

            if (! $review->items()->where('status', QuranKhamsaItemStatus::Pending)->exists()) {
                $review->update(['status' => QuranKhamsaReviewStatus::Completed]);

                $this->audit->logModel('khamsa_review.completed', $review, actor: $actor);
            }
        });

        $this->syncBatchAfterReview($review);
    }

    /** اكتمال مراجعة مرتبطة بدفعة حفظ ينقل الدفعة إلى «بانتظار الاختبار». */
    private function syncBatchAfterReview(QuranKhamsaReview $review): void
    {
        if (! $review->isCompleted()) {
            return;
        }

        $batch = QuranMemorizationBatch::query()
            ->where('review_5_id', $review->id)
            ->first();

        if (! $batch) {
            return;
        }

        $student = Student::withoutGlobalScope('study_session')->find($batch->student_id);

        if ($student) {
            $this->gating->sync($student);
        }
    }

    /**
     * مزامنة عكسية: إنهاء خمسة مرتبطة بخطة استماع يسجّل استماع عنصر الخطة
     * المقابل (إن كان مفتوحاً) ويربط جلسة «الاستماع مع المعلم» به.
     */
    private function syncListeningPlanItem(QuranKhamsaReviewItem $item, ?string $sessionId, User $actor): void
    {
        $planItem = QuranListeningPlanItem::query()
            ->where('khamsa_review_item_id', $item->id)
            ->first();

        if (! $planItem || $planItem->isPassed() || $planItem->isListened()) {
            return;
        }

        if (! $planItem->canBeListened()) {
            return;
        }

        $planItem->update([
            'status' => QuranListeningItemStatus::Listened,
            'listened_at' => now(),
            'listened_by' => $actor->id,
            'quran_review_session_id' => $sessionId ?? $planItem->quran_review_session_id,
        ]);

        $this->audit->logModel('quran_listening.item_listened', $planItem, actor: $actor);
    }

    public function cancelReview(QuranKhamsaReview $review, ?User $actor = null): void
    {
        if ($review->isCompleted()) {
            throw ValidationException::withMessages(['review' => ['لا يمكن إلغاء مراجعة مكتملة']]);
        }

        if ($review->isCancelled()) {
            return;
        }

        $review->update(['status' => QuranKhamsaReviewStatus::Cancelled]);

        $this->audit->logModel('khamsa_review.cancelled', $review, actor: $actor);
    }

    /** تسجيل جزء محفوظ (يُفتح للطالب). */
    public function recordMemorization(Student $student, int $juz, ?User $actor = null, string $source = StudentJuzMemorization::SOURCE_MANUAL): StudentJuzMemorization
    {
        QuranJuzMap::assertJuz($juz);

        $record = StudentJuzMemorization::firstOrCreate(
            ['student_id' => $student->id, 'juz' => $juz],
            [
                'memorized_at' => Carbon::today(),
                'recorded_by' => $actor?->id,
                'source' => $source,
            ]
        );

        if ($record->wasRecentlyCreated) {
            $this->audit->logModel('juz_memorization.recorded', $record, actor: $actor);
        }

        return $record;
    }

    public function removeMemorization(Student $student, int $juz, ?User $actor = null): void
    {
        $record = StudentJuzMemorization::query()
            ->where('student_id', $student->id)
            ->where('juz', $juz)
            ->first();

        if (! $record) {
            return;
        }

        $this->audit->logModel('juz_memorization.removed', $record, actor: $actor);
        $record->delete();
    }

    /**
     * مزامنة الأجزاء المحفوظة من نموذج الطالب: القائمة المُرسَلة هي جهة
     * الحقيقة (تُضاف الناقصة وتُحذف غير المحددة).
     *
     * @param  array<int, int|string>  $juzNumbers
     */
    public function syncMemorizedJuz(Student $student, array $juzNumbers, User $actor): void
    {
        $numbers = collect($juzNumbers)
            ->map(fn ($juz) => (int) $juz)
            ->filter(fn (int $juz) => $juz >= 1 && $juz <= QuranJuzMap::TOTAL_JUZ)
            ->unique()
            ->values();

        DB::transaction(function () use ($student, $numbers, $actor) {
            $existing = StudentJuzMemorization::query()
                ->where('student_id', $student->id)
                ->get();

            foreach ($existing as $record) {
                if (! $numbers->contains((int) $record->juz)) {
                    $this->audit->logModel('juz_memorization.removed', $record, actor: $actor);
                    $record->delete();
                }
            }

            foreach ($numbers as $juz) {
                if (! $existing->contains(fn (StudentJuzMemorization $record) => (int) $record->juz === $juz)) {
                    $record = StudentJuzMemorization::create([
                        'student_id' => $student->id,
                        'juz' => $juz,
                        'memorized_at' => Carbon::today(),
                        'recorded_by' => $actor->id,
                        'source' => StudentJuzMemorization::SOURCE_MANUAL,
                    ]);

                    $this->audit->logModel('juz_memorization.recorded', $record, actor: $actor);
                }
            }
        });

        $this->gating->sync($student, $actor);
    }

    /**
     * تعبئة أولية من مقدار الحفظ في ملف الطالب (memorized_juz): الأجزاء
     * 1..floor(N) تُسجَّل إن لم تكن مسجّلة (إضافة فقط).
     */
    public function syncIntakeMemorization(Student $student, ?User $actor = null): void
    {
        if ($student->memorized_juz === null) {
            return;
        }

        $count = (int) floor((float) $student->memorized_juz);
        $count = max(0, min(QuranJuzMap::TOTAL_JUZ, $count));
        $recorded = false;

        for ($juz = 1; $juz <= $count; $juz++) {
            $record = StudentJuzMemorization::firstOrCreate(
                ['student_id' => $student->id, 'juz' => $juz],
                [
                    'memorized_at' => Carbon::today(),
                    'recorded_by' => $actor?->id,
                    'source' => StudentJuzMemorization::SOURCE_INTAKE,
                ]
            );

            $recorded = $recorded || $record->wasRecentlyCreated;
        }

        if ($recorded) {
            $this->gating->sync($student, $actor);
        }
    }

    /**
     * عند تسميع «جديد»: دمج نطاقات الصفحات التراكمية، وأي جزء غُطّيت كل
     * صفحاته يُسجَّل محفوظاً تلقائياً (إضافة فقط).
     */
    public function syncMemorizationFromTasmee(QuranRecitationSession $session): void
    {
        if ($session->type !== QuranTasmeeType::New || $session->student_id === null) {
            return;
        }

        $merged = $this->gating->coveredPageIntervals($session->student_id);

        if ($merged === []) {
            return;
        }

        $recorded = false;

        foreach (range(1, QuranJuzMap::TOTAL_JUZ) as $juz) {
            $range = QuranJuzMap::pageRange($juz);

            $covered = collect($merged)->contains(
                fn (array $interval) => $interval[0] <= $range['from'] && $interval[1] >= $range['to']
            );

            if (! $covered) {
                continue;
            }

            $record = StudentJuzMemorization::firstOrCreate(
                ['student_id' => $session->student_id, 'juz' => $juz],
                [
                    'memorized_at' => $session->date ?? Carbon::today(),
                    'recorded_by' => null,
                    'source' => StudentJuzMemorization::SOURCE_TASMEE,
                ]
            );

            $recorded = $recorded || $record->wasRecentlyCreated;
        }

        if ($recorded && $student = Student::query()->find($session->student_id)) {
            $this->gating->sync($student);
        }
    }

    /** @return array<int, string> مفاتيح «جزء:خمسة» قيد المراجعة للطالب. */
    private function pendingKhamsaKeys(Student $student): array
    {
        return QuranKhamsaReviewItem::query()
            ->where('status', QuranKhamsaItemStatus::Pending)
            ->whereHas('review', fn ($query) => $query
                ->where('student_id', $student->id)
                ->where('status', QuranKhamsaReviewStatus::Pending))
            ->get(['juz', 'khamsa'])
            ->map(fn (QuranKhamsaReviewItem $item) => $item->juz.':'.$item->khamsa)
            ->all();
    }

    /**
     * تنظيف مدخلات الخمسات: أرقام صحيحة، إزالة التكرار، ترتيب حسب الجزء.
     *
     * @param  array<int, array{juz: int|string, khamsa: int|string}>  $items
     * @return array<int, array{juz: int, khamsa: int}>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $juz = (int) ($item['juz'] ?? 0);
            $khamsa = (int) ($item['khamsa'] ?? 0);

            if ($juz < 1 || $juz > QuranJuzMap::TOTAL_JUZ || $khamsa < 1 || $khamsa > QuranJuzMap::KHAMSAT_PER_JUZ) {
                continue;
            }

            $normalized[$juz.':'.$khamsa] = ['juz' => $juz, 'khamsa' => $khamsa];
        }

        ksort($normalized);

        return array_values($normalized);
    }
}
