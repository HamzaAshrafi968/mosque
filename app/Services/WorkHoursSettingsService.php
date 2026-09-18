<?php

namespace App\Services;

use App\Models\TenantSetting;

/**
 * إعدادات ساعات العمل لكل جامع (tenant_settings):
 *
 * - الحد الأقصى لساعات الفترة الواحدة (افتراضياً 12 — مطابق للقاعدة القائمة).
 * - التوقيت المحلي للجامع (يُعرض في الواجهات؛ الأوقات تُخزَّن جدارية).
 */
class WorkHoursSettingsService
{
    public const KEY_MAX_SLOT_HOURS = 'work_hours.max_slot_hours';

    public const KEY_TIMEZONE = 'work_hours.timezone';

    public const DEFAULT_MAX_SLOT_HOURS = 12.0;

    public const MIN_MAX_SLOT_HOURS = 1.0;

    public const MAX_MAX_SLOT_HOURS = 24.0;

    public function maxSlotHours(): float
    {
        $value = TenantSetting::getValue(self::KEY_MAX_SLOT_HOURS);

        if ($value === null || ! is_numeric($value)) {
            return self::DEFAULT_MAX_SLOT_HOURS;
        }

        return max(
            self::MIN_MAX_SLOT_HOURS,
            min(self::MAX_MAX_SLOT_HOURS, (float) $value)
        );
    }

    public function setMaxSlotHours(float $value): void
    {
        $value = max(self::MIN_MAX_SLOT_HOURS, min(self::MAX_MAX_SLOT_HOURS, $value));

        TenantSetting::putValue(self::KEY_MAX_SLOT_HOURS, (string) $value);
    }

    public function timezone(): string
    {
        return TenantSetting::getValue(self::KEY_TIMEZONE) ?? (string) config('app.timezone', 'UTC');
    }

    public function setTimezone(?string $timezone): void
    {
        TenantSetting::putValue(self::KEY_TIMEZONE, $timezone ?: null);
    }
}
