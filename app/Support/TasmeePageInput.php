<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Shared validation/normalization for the optional page range on tasmee records.
 */
class TasmeePageInput
{
    public const MAX_PAGE = 604;

    /**
     * Word statuses that are persisted with a tasmee record (correct words are implicit).
     */
    public const ERROR_STATUSES = ['incorrect', 'hesitation', 'tajweed_error', 'added', 'forgotten'];

    public static function rules(): array
    {
        return [
            'from_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE, 'required_with:to_page'],
            'to_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE, 'required_with:from_page', 'gte:from_page'],
        ];
    }

    /**
     * Validation for the word-level error marks captured in the pages preview.
     */
    public static function wordStatusRules(): array
    {
        return [
            'word_statuses' => ['nullable', 'array', 'max:5000'],
            'word_statuses.*' => ['string', Rule::in(['correct', ...self::ERROR_STATUSES, 'unreviewed'])],
        ];
    }

    /**
     * Keeps only the marked errors keyed by "ayah_id:word_position".
     */
    public static function errorStatuses(?array $statuses): ?array
    {
        if (empty($statuses)) {
            return null;
        }

        $errors = array_filter($statuses, fn ($status) => in_array($status, self::ERROR_STATUSES, true));

        return $errors === [] ? null : $errors;
    }

    /**
     * Fills amount (page count) and recited_portion from the page range when omitted.
     */
    public static function normalize(array $data): array
    {
        $from = isset($data['from_page']) ? (int) $data['from_page'] : null;
        $to = isset($data['to_page']) ? (int) $data['to_page'] : null;

        if ($from !== null && $to !== null) {
            if (! isset($data['amount']) || $data['amount'] === null || $data['amount'] === '') {
                $data['amount'] = $to - $from + 1;
            }

            if (empty($data['recited_portion'])) {
                $data['recited_portion'] = "من الصفحة {$from} إلى الصفحة {$to}";
            }
        }

        return $data;
    }
}
