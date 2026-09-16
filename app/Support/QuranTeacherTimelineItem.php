<?php

namespace App\Support;

use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTeacherTimelineDetailLevel;
use App\Enums\QuranTeacherTimelineType;
use Carbon\CarbonInterface;

/**
 * View Model موحّد لعنصر «التسميع مع المعلم»:
 * يُبنى من QuranRecitationSession (حكم عام) أو QuranReviewSession (تقييم تفصيلي)
 * دون أي تغيير على الكيانين في الـDomain.
 */
final readonly class QuranTeacherTimelineItem
{
    /**
     * @param  array{word_errors: int, tajweed_errors: int, hesitations: int, added: int, forgotten: int}|null  $errorStats
     */
    public function __construct(
        public string $id,
        public QuranTeacherTimelineType $type,
        public CarbonInterface $occurredAt,
        public CarbonInterface $createdAt,
        public ?int $pageFrom = null,
        public ?int $pageTo = null,
        public ?string $pagesLabel = null,
        public ?string $amountLabel = null,
        public ?string $subtitle = null,
        public ?QuranTasmeeResult $result = null,
        public ?string $notes = null,
        public ?float $masteryPercentage = null,
        public ?array $errorStats = null,
        public ?string $batchLabel = null,
        public ?string $teacherName = null,
        public QuranTeacherTimelineDetailLevel $detailLevel = QuranTeacherTimelineDetailLevel::Summary,
        public ?int $wordErrorCount = null,
        public ?string $showUrl = null,
        public ?string $editUrl = null,
    ) {}

    public function isListening(): bool
    {
        return $this->type === QuranTeacherTimelineType::TeacherListening;
    }

    /** هل يحمل العنصر أخطاء كلمات مسجّلة (للاستماع التفصيلي أو لتسميع بمواضع أخطاء)؟ */
    public function hasErrors(): bool
    {
        if ($this->errorStats !== null) {
            return array_sum($this->errorStats) > 0;
        }

        return (bool) $this->wordErrorCount;
    }

    /** ملخص عربي موجز للأخطاء غير الصفرية: «3 كلمات · خطأ تجويد · 2 تردد». */
    public function errorSummary(): string
    {
        if ($this->errorStats === null) {
            return '';
        }

        $parts = [];

        $wordErrors = (int) ($this->errorStats['word_errors'] ?? 0);
        if ($wordErrors > 0) {
            $parts[] = self::countLabel($wordErrors, 'كلمة', 'كلمتان', 'كلمات');
        }

        $labels = [
            'tajweed_errors' => 'خطأ تجويد',
            'hesitations' => 'تردد',
            'added' => 'زيادة',
            'forgotten' => 'نسيان',
        ];

        foreach ($labels as $key => $label) {
            $count = (int) ($this->errorStats[$key] ?? 0);

            if ($count > 0) {
                $parts[] = $count > 1 ? $count.' '.$label : $label;
            }
        }

        return implode(' · ', $parts);
    }

    private static function countLabel(int $count, string $singular, string $dual, string $plural): string
    {
        return match (true) {
            $count === 1 => $singular,
            $count === 2 => $dual,
            $count <= 10 => $count.' '.$plural,
            default => $count.' '.$singular,
        };
    }
}
