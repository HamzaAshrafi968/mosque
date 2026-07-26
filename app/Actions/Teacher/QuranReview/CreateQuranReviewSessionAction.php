<?php

namespace App\Actions\Teacher\QuranReview;

use App\Models\QuranAyah;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\RewardPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateQuranReviewSessionAction
{
    public function execute(array $data, string $teacherId, string $tenantId, Request $request): array
    {
        $now = now();
        $sessionId = (string) Str::uuid();

        $ayahs = QuranAyah::query()
            ->where('surah_id', $data['surah_id'])
            ->whereBetween('ayah_number', [$data['from_ayah'], $data['to_ayah']])
            ->orderBy('ayah_number')
            ->get(['id', 'ayah_number', 'text_simple']);

        $wordRows = [];
        $stats = [
            'correct' => 0, 'incorrect' => 0, 'hesitation' => 0,
            'tajweed_error' => 0, 'added' => 0, 'forgotten' => 0,
        ];
        $totalWords = 0;
        $wordIndex = 0;

        foreach ($ayahs as $ayah) {
            $words = explode(' ', $ayah->text_simple);
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

        DB::transaction(function () use (
            $sessionId, $data, $teacherId, $tenantId, $stats, $totalWords, $masteryPercentage, $wordRows
        ) {
            $session = new QuranReviewSession([
                'tenant_id' => $tenantId,
                'teacher_id' => $teacherId,
                'student_id' => $data['student_id'],
                'surah_id' => $data['surah_id'],
                'from_ayah' => $data['from_ayah'],
                'to_ayah' => $data['to_ayah'],
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
            'reason' => 'نقاط تلقائية من التسميع',
            'type' => 'earned',
            'notes' => 'تم احتساب النقاط تلقائياً بناءً على نسبة الإتقان: '.$masteryPercentage.'%',
        ]);
    }
}
