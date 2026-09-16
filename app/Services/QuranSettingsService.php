<?php

namespace App\Services;

use App\Models\TenantSetting;

/**
 * إعدادات برنامج القرآن لكل جامع (tenant_settings):
 *
 * - حد النجاح في اختبار دفعة الحفظ (minimum passing percentage) — يقرأه
 *   الـ Backend عند تسجيل كل اختبار ويحفظ لقطة منه على الاختبار نفسه حتى
 *   لا تتغير نتائج الاختبارات القديمة عند تعديل الإعداد لاحقاً.
 */
class QuranSettingsService
{
    public const KEY_MINIMUM_PASSING_PERCENTAGE = 'quran.minimum_passing_percentage';

    public const DEFAULT_MINIMUM_PASSING_PERCENTAGE = 80.0;

    public const MIN_PASSING_PERCENTAGE = 1.0;

    public const MAX_PASSING_PERCENTAGE = 100.0;

    public function minimumPassingPercentage(): float
    {
        $value = TenantSetting::getValue(self::KEY_MINIMUM_PASSING_PERCENTAGE);

        if ($value === null || ! is_numeric($value)) {
            return self::DEFAULT_MINIMUM_PASSING_PERCENTAGE;
        }

        return max(
            self::MIN_PASSING_PERCENTAGE,
            min(self::MAX_PASSING_PERCENTAGE, (float) $value)
        );
    }

    public function setMinimumPassingPercentage(float $value): void
    {
        $value = max(self::MIN_PASSING_PERCENTAGE, min(self::MAX_PASSING_PERCENTAGE, $value));

        TenantSetting::putValue(self::KEY_MINIMUM_PASSING_PERCENTAGE, (string) $value);
    }
}
