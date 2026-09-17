<?php

namespace App\Services;

use App\Models\TenantSetting;

/**
 * الإعداد العام لنظام نقاط المكافآت لكل جامع (tenant_settings):
 *
 * - تفعيل/إيقاف المنح التلقائي للنقاط (حفظ/خمسات/اختبار/خطة/دورة شرعية).
 *   الإيقاف لا يمس النقاط الممنوحة سابقاً، ويُبقي القواعد محفوظة ليُعاد
 *   تفعيلها لاحقاً.
 */
class RewardPointSettingsService
{
    public const KEY_ENABLED = 'reward_points.enabled';

    public function isEnabled(): bool
    {
        $value = TenantSetting::getValue(self::KEY_ENABLED);

        return $value === null || filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        TenantSetting::putValue(self::KEY_ENABLED, $enabled ? '1' : '0');
    }
}
