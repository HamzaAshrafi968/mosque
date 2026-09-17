<?php

namespace App\Services;

use App\Enums\QuranTasmeeType;
use App\Enums\QuranTeacherTimelineDetailLevel;
use App\Enums\QuranTeacherTimelineType;
use App\Models\QuranRecitationSession;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Support\QuranTeacherTimelineItem;
use Illuminate\Support\Collection;

/**
 * «التسميع مع المعلم» — طبقة تحويل (Presenter) توحّد جلسات التسميع
 * (QuranRecitationSession) وجلسات الاستماع التفصيلي (QuranReviewSession)
 * في View Model واحد للعرض، مع إبقاء الكيانين مستقلين تماماً في الـDomain.
 *
 * قواعد التصميم:
 * - الفلتر يُدفع داخل كل استعلام (وليس بعد الدمج) حتى لا يُجوّع مصدر لصالح آخر.
 * - الترتيب حتمي: occurred_at DESC ثم created_at DESC ثم id DESC
 *   (الـUUID كسر تعادل للثبات فقط — ليس ترتيباً زمنياً).
 * - تقدم الدفعة (Coverage) لا يُشتق إلا من تسميع «جديد»؛ الاستماع التفصيلي
 *   دليل إتقان (Mastery) ولا يعدّل صفحات الدفعة.
 */
class QuranTeacherTimelineService
{
    public const FILTER_NEW = 'new';

    public const FILTER_REVISION = 'revision';

    public const FILTER_LISTENING = 'listening';

    /** هامش الجلب من كل مصدر قبل الدمج والاقتصاص (يمنع تجويع مصدر لصالح آخر). */
    private const PER_SOURCE_LIMIT = 30;

    /** @return array<string, string> فلاتر السجل المتاحة للواجهة. */
    public static function filters(): array
    {
        return [
            self::FILTER_NEW => 'تسميع جديد',
            self::FILTER_REVISION => 'مراجعة',
            self::FILTER_LISTENING => 'استماع تفصيلي',
        ];
    }

    /**
     * سجل «التسميع مع المعلم» لطالب واحد، الأحدث أولاً.
     *
     * @param  callable(QuranReviewSession): string|null  $reviewShowUrl  رابط عرض جلسة الاستماع
     * @return Collection<int, QuranTeacherTimelineItem>
     */
    public function forStudent(
        Student $student,
        ?string $filter = null,
        ?string $teacherId = null,
        int $limit = 20,
        ?callable $reviewShowUrl = null,
    ): Collection {
        $filter = $this->normalizeFilter($filter);

        $items = collect();

        if ($filter === null || $filter === self::FILTER_NEW || $filter === self::FILTER_REVISION) {
            $items = $items->concat($this->recitationItems($student, $filter, $teacherId));
        }

        if ($filter === null || $filter === self::FILTER_LISTENING) {
            $items = $items->concat($this->listeningItems($student, $teacherId, $reviewShowUrl));
        }

        return $items
            ->sort($this->latestFirst())
            ->take($limit)
            ->values();
    }

    private function normalizeFilter(?string $filter): ?string
    {
        return array_key_exists((string) $filter, self::filters()) ? $filter : null;
    }

    /** @return Collection<int, QuranTeacherTimelineItem> */
    private function recitationItems(Student $student, ?string $filter, ?string $teacherId): Collection
    {
        return QuranRecitationSession::query()
            ->with(['teacher:id,name', 'batch:id,batch_number,from_juz,to_juz'])
            ->where('student_id', $student->id)
            ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))
            ->when($filter === self::FILTER_NEW, fn ($query) => $query->where('type', QuranTasmeeType::New->value))
            ->when($filter === self::FILTER_REVISION, fn ($query) => $query->where('type', QuranTasmeeType::Revision->value))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (QuranRecitationSession $session) => $this->recitationItem($session));
    }

    private function recitationItem(QuranRecitationSession $session): QuranTeacherTimelineItem
    {
        $isNew = $session->type === QuranTasmeeType::New;
        $wordErrorCount = is_array($session->word_statuses) && $session->word_statuses !== []
            ? count($session->word_statuses)
            : null;

        return new QuranTeacherTimelineItem(
            id: $session->id,
            type: $isNew ? QuranTeacherTimelineType::RecitationNew : QuranTeacherTimelineType::RecitationRevision,
            occurredAt: $session->date ?? $session->created_at,
            createdAt: $session->created_at,
            pageFrom: $session->from_page,
            pageTo: $session->to_page,
            pagesLabel: $this->pagesLabel($session->from_page, $session->to_page),
            amountLabel: $this->amountLabel($session),
            subtitle: $session->recited_portion,
            result: $session->result,
            notes: $session->notes,
            batchLabel: $session->batch?->label(),
            teacherName: $session->teacher?->name,
            detailLevel: QuranTeacherTimelineDetailLevel::Summary,
            wordErrorCount: $wordErrorCount,
        );
    }

    /**
     * @param  callable(QuranReviewSession): string|null  $reviewShowUrl
     * @return Collection<int, QuranTeacherTimelineItem>
     */
    private function listeningItems(Student $student, ?string $teacherId, ?callable $reviewShowUrl): Collection
    {
        return QuranReviewSession::query()
            ->with(['teacher' => fn ($query) => $query->withoutGlobalScope('study_session')->select('id', 'name')])
            ->where('student_id', $student->id)
            ->when($teacherId !== null, fn ($query) => $query->where('teacher_id', $teacherId))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (QuranReviewSession $session) => $this->listeningItem($session, $reviewShowUrl));
    }

    private function listeningItem(QuranReviewSession $session, ?callable $reviewShowUrl): QuranTeacherTimelineItem
    {
        return new QuranTeacherTimelineItem(
            id: $session->id,
            type: QuranTeacherTimelineType::TeacherListening,
            occurredAt: $session->date ?? $session->created_at,
            createdAt: $session->created_at,
            pageFrom: $session->from_page,
            pageTo: $session->to_page,
            pagesLabel: $session->isPageBased() ? $this->pagesLabel($session->from_page, $session->to_page) : null,
            subtitle: $session->isPageBased() ? null : ('الآيات '.$session->from_ayah.'–'.$session->to_ayah),
            masteryPercentage: $session->mastery_percentage !== null ? (float) $session->mastery_percentage : null,
            errorStats: [
                'word_errors' => (int) $session->incorrect_words,
                'tajweed_errors' => (int) $session->tajweed_error_words,
                'hesitations' => (int) $session->hesitation_words,
                'added' => (int) $session->added_words,
                'forgotten' => (int) $session->forgotten_words,
            ],
            teacherName: $session->teacher?->name,
            detailLevel: QuranTeacherTimelineDetailLevel::Detailed,
            showUrl: $reviewShowUrl ? $reviewShowUrl($session) : null,
        );
    }

    /** الترتيب الحتمي: الأحدث وقوعاً، ثم الأحدث إنشاءً، ثم معرّف ثابت كسر تعادل. */
    private function latestFirst(): callable
    {
        return fn (QuranTeacherTimelineItem $a, QuranTeacherTimelineItem $b): int => [
            $b->occurredAt->getTimestamp(),
            $b->createdAt->getTimestamp(),
            $b->id,
        ] <=> [
            $a->occurredAt->getTimestamp(),
            $a->createdAt->getTimestamp(),
            $a->id,
        ];
    }

    private function pagesLabel(?int $from, ?int $to): ?string
    {
        if ($from === null || $to === null) {
            return null;
        }

        return $from === $to ? 'صفحة '.$from : 'صفحات '.$from.'–'.$to;
    }

    private function amountLabel(QuranRecitationSession $session): ?string
    {
        if ($session->from_page !== null || $session->amount === null) {
            return null;
        }

        return 'المقدار: '.rtrim(rtrim(number_format((float) $session->amount, 2, '.', ''), '0'), '.');
    }
}
