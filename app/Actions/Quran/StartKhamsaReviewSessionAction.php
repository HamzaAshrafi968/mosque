<?php

namespace App\Actions\Quran;

use App\Actions\Teacher\QuranReview\CreatePageQuranReviewSessionAction;
use App\Enums\QuranTasmeeResult;
use App\Models\QuranKhamsaReview;
use App\Models\User;
use App\Services\QuranKhamsaService;
use App\Services\QuranPageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «مراجعة الخمسات مع تسجيل الأخطاء»:
 *
 * يفتح جلسة «الاستماع مع المعلم» على نطاق الخمسات المتتالية المحددة (من أول
 * صفحة في أولها إلى آخر صفحة في آخرها)، يسجّل الأخطاء كلمة بكلمة مثل التسميع،
 * ثم يُنهي الخمسات المحددة مربوطةً بالجلسة — ومعها تُزامَن خطة الاستماع
 * والدفعة عبر QuranKhamsaService.
 */
class StartKhamsaReviewSessionAction
{
    public function __construct(
        private readonly QuranKhamsaService $khamsa,
        private readonly CreatePageQuranReviewSessionAction $sessions,
        private readonly QuranPageService $pages,
    ) {}

    /**
     * @param  array<int, string>  $itemIds
     * @param  array{date: string, notes?: ?string, result?: ?string, word_statuses?: array<string, string>}  $data
     * @return array{session_id: string, mastery_percentage: float, completed: int}
     */
    public function execute(QuranKhamsaReview $review, array $itemIds, array $data, User $actor, Request $request): array
    {
        $items = $this->khamsa->pendingSelection($review, $itemIds);
        $this->khamsa->assertContiguousSelection($items);

        $range = $this->khamsa->selectionPageRange($items);
        $this->khamsa->assertSelectionPageLimit($range);

        return DB::transaction(function () use ($review, $items, $data, $actor, $request, $range) {
            $session = $this->sessions->execute([
                'student_id' => $review->student_id,
                'from_page' => $range['from'],
                'to_page' => $range['to'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'word_statuses' => $this->sequentialStatuses($range['from'], $range['to'], $data['word_statuses'] ?? []),
            ], $review->teacher_id, $review->tenant_id, $request, QuranKhamsaService::MAX_REVIEW_PAGES);

            $mastery = (float) $session['mastery_percentage'];

            $completed = $this->khamsa->completeItems(
                $review,
                $items,
                $session['session_id'],
                QuranTasmeeResult::fromMastery($mastery),
                $data['notes'] ?? null,
                $actor,
            );

            return [
                'session_id' => $session['session_id'],
                'mastery_percentage' => $mastery,
                'completed' => $completed,
            ];
        });
    }

    /**
     * واجهة التسميع ترسل الحالات المعلَّمة فقط بمفاتيح «ayah_id:word_position»؛
     * هنا نحوّلها لقائمة متسلسلة (غير المعلَّم = صحيحة، مثل التسميع).
     *
     * @param  array<string, string>  $keyed
     * @return array<int, string>
     */
    private function sequentialStatuses(int $from, int $to, array $keyed): array
    {
        $statuses = [];

        foreach ($this->pages->ayahsForRange($from, $to) as $ayah) {
            foreach (explode(' ', $ayah->text) as $pos => $word) {
                if ($word === '') {
                    continue;
                }

                $statuses[] = $keyed[$ayah->id.':'.$pos] ?? 'correct';
            }
        }

        return $statuses;
    }
}
