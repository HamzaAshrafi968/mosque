<?php

namespace App\Actions\Quran;

use App\Enums\QuranListeningItemType;
use App\Rules\PageWithinJuz;
use App\Support\QuranJuzMap;
use Illuminate\Validation\Rule;

/**
 * عناصر «خطة الاستماع» المختلطة:
 * - «جديد»: نطاق صفحات يحدده المستخدم داخل حدود الجزء.
 * - «مراجعة 5»: خمسة أو أكثر من خمسات الأجزاء المحفوظة (بلا نطاق يدوي).
 *
 * يبني قواعد التحقق حسب نوع كل صف ويسطّح الصفوف المحددة إلى عناصر الخطة.
 */
class BuildListeningPlanItemsAction
{
    /**
     * @param  array<int|string, mixed>  $items
     * @return array<string, array<int, mixed>>
     */
    public function rules(array $items): array
    {
        $rules = [];

        foreach (array_keys($items) as $key) {
            $row = $items[$key];

            if (! is_array($row) || ! isset($row['selected'])) {
                continue;
            }

            $juz = $row['juz'] ?? $key;

            $rules["items.{$key}.selected"] = ['required', 'boolean'];
            $rules["items.{$key}.juz"] = ['required', 'integer', 'min:1', 'max:'.QuranJuzMap::TOTAL_JUZ];
            $rules["items.{$key}.type"] = ['nullable', Rule::enum(QuranListeningItemType::class)];

            if (($row['type'] ?? QuranListeningItemType::New->value) === QuranListeningItemType::Review->value) {
                $rules["items.{$key}.khamsat"] = ['required', 'array', 'min:1'];
                $rules["items.{$key}.khamsat.*"] = ['nullable', 'integer', 'min:1', 'max:'.QuranJuzMap::KHAMSAT_PER_JUZ];

                continue;
            }

            $rules["items.{$key}.from_page"] = ['required', 'integer', new PageWithinJuz($juz)];
            $rules["items.{$key}.to_page"] = ['required', 'integer', new PageWithinJuz($juz)];
        }

        return $rules;
    }

    /**
     * الصفوف المحددة فقط (checkbox)، مسطّحة: كل خمسة محددة عنصر مستقل.
     *
     * @param  array<int|string, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    public function selected(array $items): array
    {
        $selected = [];

        foreach ($items as $key => $row) {
            if (! is_array($row) || empty($row['selected'])) {
                continue;
            }

            $juz = (int) ($row['juz'] ?? $key);
            $type = (string) ($row['type'] ?? QuranListeningItemType::New->value);

            if ($type === QuranListeningItemType::Review->value) {
                foreach (array_keys((array) ($row['khamsat'] ?? [])) as $khamsa) {
                    $selected[] = ['type' => $type, 'juz' => $juz, 'khamsa' => (int) $khamsa];
                }

                continue;
            }

            $selected[] = [
                'type' => QuranListeningItemType::New->value,
                'juz' => $juz,
                'from_page' => (int) ($row['from_page'] ?? 0),
                'to_page' => (int) ($row['to_page'] ?? 0),
            ];
        }

        return $selected;
    }
}
