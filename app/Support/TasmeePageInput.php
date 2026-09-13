<?php

namespace App\Support;

/**
 * Shared validation/normalization for the optional page range on tasmee records.
 */
class TasmeePageInput
{
    public const MAX_PAGE = 604;

    public static function rules(): array
    {
        return [
            'from_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE, 'required_with:to_page'],
            'to_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE, 'required_with:from_page', 'gte:from_page'],
        ];
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
