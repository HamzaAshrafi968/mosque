<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\QuestionType;
use App\Support\ExamQuestionNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * تحليل صفوف الأسئلة القادمة من النماذج (اختبارات/واجبات):
 * كل صف قد يحمل نوعه الخاص (questions.*.type) فتكون الأسئلة متعددة الأنواع،
 * ويسقط الصف بلا نوع على النوع العام type (توافق الـ API). العلامة الفارغة = 1،
 * والصفوف بلا نص سؤال تُتجاهل.
 */
trait ParsesQuestionRows
{
    /**
     * @return array{0: ?QuestionType, 1: array<int, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    protected function validatedQuestionsData(Request $request): array
    {
        $rows = collect($request->input('questions', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['text'] ?? '')) !== '')
            ->values()
            ->all();

        if ($rows === []) {
            return [null, []];
        }

        $data = Validator::make(
            ['type' => $request->input('type'), 'questions' => $rows],
            [
                'type' => ['nullable', Rule::enum(QuestionType::class)],
                'questions' => ['required', 'array', 'min:1', 'max:100'],
                'questions.*.text' => ['required', 'string', 'max:2000'],
                'questions.*.type' => ['nullable', Rule::enum(QuestionType::class)],
                'questions.*.marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'questions.*.options' => ['nullable', 'array', 'max:10'],
                'questions.*.options.*' => ['nullable', 'string', 'max:500'],
                'questions.*.correct_answer' => ['nullable'],
                'questions.*.correct_options' => ['nullable', 'array', 'max:10'],
                'questions.*.correct_options.*' => ['nullable'],
            ]
        )->validate();

        $fallbackType = $data['type'] !== null ? QuestionType::from($data['type']) : null;

        $rows = array_map(function (array $row) use ($fallbackType): array {
            $type = $row['type'] ?? $fallbackType?->value;

            if ($type === null || $type === '') {
                throw ValidationException::withMessages([
                    'questions' => 'حدد نوع كل سؤال',
                ]);
            }

            $row['type'] = $type;
            $row['marks'] = ($row['marks'] ?? null) === null || ($row['marks'] ?? null) === ''
                ? 1
                : (float) $row['marks'];

            return $row;
        }, array_values($data['questions']));

        return [$fallbackType, $rows];
    }

    /**
     * يطبّع صف السؤال حسب نوعه ويعيد [options, correct_answer].
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ?array<int, string>, 1: ?string}
     *
     * @throws ValidationException
     */
    protected function normalizeQuestion(QuestionType $type, array $row, int $index): array
    {
        return ExamQuestionNormalizer::normalize($type, $row, 'questions.'.$index.'.');
    }
}
