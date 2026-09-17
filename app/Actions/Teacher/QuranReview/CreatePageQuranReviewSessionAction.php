<?php

namespace App\Actions\Teacher\QuranReview;

use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\RewardPoint;
use App\Services\QuranPageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePageQuranReviewSessionAction
{
    public function __construct(private readonly QuranPageService $pages) {}

    public function execute(
        array $data,
        string $teacherId,
        string $tenantId,
        Request $request,
        int $maxPages = QuranPageService::MAX_REVIEW_PAGES,
    ): array {
        $fromPage = (int) $data['from_page'];
        $toPage = (int) $data['to_page'];

        if ($fromPage < 1 || $toPage > QuranPageService::MAX_PAGE || $toPage < $fromPage) {
            throw ValidationException::withMessages([
                'from_page' => 'نطاق الصفحات غير صحيح: يجب أن يكون بين ١ و ٦٠٤ وبترتيب صحيح',
            ]);
        }

        if (($toPage - $fromPage + 1) > $maxPages) {
            throw ValidationException::withMessages([
                'to_page' => 'الحد الأقصى لعدد صفحات الاستماع الواحدة هو '.$maxPages.' صفحات',
            ]);
        }

        $now = now();
        $sessionId = (string) Str::uuid();

        $ayahs = $this->pages->ayahsForRange($fromPage, $toPage);

        if ($ayahs->isEmpty()) {
            throw ValidationException::withMessages([
                'from_page' => 'لا توجد آيات في الصفحات المحددة — تأكد من تهيئة بيانات الصفحات',
            ]);
        }

        $wordRows = [];
        $stats = [
            'correct' => 0, 'incorrect' => 0, 'hesitation' => 0,
            'tajweed_error' => 0, 'added' => 0, 'forgotten' => 0,
        ];
        $totalWords = 0;
        $wordIndex = 0;

        foreach ($ayahs as $ayah) {
            $words = explode(' ', $ayah->text);
            foreach ($words as $pos => $word) {
                if ($word === '') {
                    continue;
                }
                $status = $data['word_statuses'][$wordIndex] ?? 'unreviewed';
                $wordNotes = $data['word_notes'][$wordIndex] ?? null;
                $errorType = null;

                if (in_array($status, ['incorrect', 'hesitation', 'tajweed_error', 'added', 'forgotten'])) {
                    $errorType = $status === 'incorrect' ? 'pronunciation'
                        : ($status === 'tajweed_error' ? 'tajweed' : $status);
                }

                $wordRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'review_session_id' => $sessionId,
                    'ayah_id' => $ayah->id,
                    'word_position' => $pos,
                    'word_text' => $word,
                    'status' => $status,
                    'error_type' => $errorType,
                    'notes' => $wordNotes,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (isset($stats[$status])) {
                    $stats[$status]++;
                }
                $totalWords++;
                $wordIndex++;
            }
        }

        $masteryPercentage = $totalWords > 0
            ? round(($stats['correct'] / $totalWords) * 100, 2)
            : 100;

        $firstAyah = $ayahs->first();
        $lastAyahOfFirstSurah = $ayahs
            ->where('surah_id', $firstAyah->surah_id)
            ->last();

        DB::transaction(function () use (
            $sessionId, $data, $teacherId, $tenantId, $stats, $totalWords,
            $masteryPercentage, $wordRows, $fromPage, $toPage, $firstAyah, $lastAyahOfFirstSurah
        ) {
            $session = new QuranReviewSession([
                'tenant_id' => $tenantId,
                'teacher_id' => $teacherId,
                'student_id' => $data['student_id'],
                'surah_id' => $firstAyah->surah_id,
                'from_ayah' => $firstAyah->ayah_number,
                'to_ayah' => $lastAyahOfFirstSurah->ayah_number,
                'from_page' => $fromPage,
                'to_page' => $toPage,
                'total_words' => $totalWords,
                'correct_words' => $stats['correct'],
                'incorrect_words' => $stats['incorrect'],
                'hesitation_words' => $stats['hesitation'],
                'tajweed_error_words' => $stats['tajweed_error'],
                'added_words' => $stats['added'],
                'forgotten_words' => $stats['forgotten'],
                'mastery_percentage' => $masteryPercentage,
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
            ]);
            $session->id = $sessionId;
            $session->save();

            foreach (array_chunk($wordRows, 500) as $chunk) {
                QuranReviewWord::insert($chunk);
            }
        });

        $this->awardPoints($sessionId, $data['student_id'], $masteryPercentage, $request);

        return [
            'session_id' => $sessionId,
            'mastery_percentage' => $masteryPercentage,
        ];
    }

    private function awardPoints(string $sessionId, string $studentId, float $masteryPercentage, Request $request): void
    {
        $points = match (true) {
            $masteryPercentage >= 90 => 10,
            $masteryPercentage >= 80 => 7,
            $masteryPercentage >= 70 => 5,
            $masteryPercentage >= 60 => 3,
            default => 1,
        };

        RewardPoint::create([
            'student_id' => $studentId,
            'awarded_by' => $request->user()->id,
            'quran_review_session_id' => $sessionId,
            'points' => $points,
            'reason' => 'نقاط تلقائية من الاستماع مع المعلم',
            'type' => 'earned',
            'notes' => 'تم احتساب النقاط تلقائياً بناءً على نسبة الإتقان: '.$masteryPercentage.'%',
        ]);
    }
}
