<?php

namespace App\Support;

use App\Enums\QuestionType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * تطبيع صف السؤال حسب نوعه قبل الحفظ: يتحقق من الخيارات والإجابة الصحيحة
 * ويعيد [options, correct_answer] بالشكل الذي يُخزَّن في قاعدة البيانات.
 * مشترك بين واجهات الويب (لوحة الإدارة/الأستاذ) والـ API.
 */
final class ExamQuestionNormalizer
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{0: ?array<int, string>, 1: ?string}
     *
     * @throws ValidationException
     */
    public static function normalize(QuestionType $type, array $row, string $prefix = ''): array
    {
        $options = collect($row['options'] ?? [])
            ->map(fn ($option) => trim((string) $option))
            ->filter(fn ($option) => $option !== '')
            ->values();

        $correct = $row['correct_answer'] ?? null;
        $correctOptions = collect($row['correct_options'] ?? [])
            ->map(fn ($option) => trim((string) $option))
            ->filter(fn ($option) => $option !== '')
            ->values();

        return match ($type) {
            QuestionType::Mcq => self::mcq($options, $correct, $correctOptions, $prefix),
            QuestionType::Checkbox => self::checkbox($options, $correct, $correctOptions, $prefix),
            QuestionType::TrueFalse => self::trueFalse($correct, $correctOptions, $prefix),
            QuestionType::Short => [null, $correct === null || $correct === '' ? null : (string) $correct],
            QuestionType::Essay => [null, null],
        };
    }

    /** @return array{0: array<int, string>, 1: string} */
    private static function mcq(Collection $options, mixed $correct, Collection $correctOptions, string $prefix): array
    {
        if ($options->count() < 2) {
            throw ValidationException::withMessages([$prefix.'options' => 'أضف خيارين على الأقل']);
        }

        $answer = self::resolveOption($options, $correct, $correctOptions->first());

        if ($answer === null) {
            throw ValidationException::withMessages([$prefix.'correct_answer' => 'حدد الإجابة الصحيحة من الخيارات']);
        }

        return [$options->all(), $answer];
    }

    /** @return array{0: array<int, string>, 1: string} */
    private static function checkbox(Collection $options, mixed $correct, Collection $correctOptions, string $prefix): array
    {
        if ($options->count() < 2) {
            throw ValidationException::withMessages([$prefix.'options' => 'أضف خيارين على الأقل']);
        }

        $answers = collect();

        foreach ($correctOptions as $candidate) {
            $resolved = self::resolveOption($options, $candidate);

            if ($resolved !== null) {
                $answers->push($resolved);
            }
        }

        // دعم واجهة الـ API التي ترسل correct_answer كنص مفرد أو JSON.
        if ($answers->isEmpty() && $correct !== null && $correct !== '') {
            $decoded = json_decode((string) $correct, true);
            $candidates = is_array($decoded) ? $decoded : [$correct];

            foreach ($candidates as $candidate) {
                $resolved = self::resolveOption($options, $candidate);

                if ($resolved !== null) {
                    $answers->push($resolved);
                }
            }
        }

        if ($answers->isEmpty()) {
            throw ValidationException::withMessages([$prefix.'correct_options' => 'حدد إجابة صحيحة واحدة على الأقل']);
        }

        return [$options->all(), json_encode($answers->unique()->values()->all(), JSON_UNESCAPED_UNICODE)];
    }

    /** @return array{0: null, 1: string} */
    private static function trueFalse(mixed $correct, Collection $correctOptions, string $prefix): array
    {
        $normalized = match (strtolower(trim((string) ($correct ?? $correctOptions->first())))) {
            'true', '1', 'صح', 'صحيح' => 'true',
            'false', '0', 'خطأ', 'خطا' => 'false',
            default => null,
        };

        if ($normalized === null) {
            throw ValidationException::withMessages([$prefix.'correct_answer' => 'حدد الإجابة الصحيحة (صح أو خطأ)']);
        }

        return [null, $normalized];
    }

    /** يقبل قيمة الإجابة الصحيحة كنص خيار أو كفهرس (0-based) للخيارات. */
    private static function resolveOption(Collection $options, mixed $candidate, mixed $fallback = null): ?string
    {
        $candidate ??= $fallback;

        if ($candidate === null || $candidate === '') {
            return null;
        }

        $candidate = trim((string) $candidate);

        if (is_numeric($candidate) && $options->has((int) $candidate)) {
            return (string) $options->get((int) $candidate);
        }

        return $options->contains($candidate) ? $candidate : null;
    }
}
