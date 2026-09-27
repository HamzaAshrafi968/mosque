<?php

namespace App\Services;

use App\Models\TenantSetting;

/**
 * «إعلان بوابة أولياء الأمور» لكل جامع (tenant_settings):
 *
 * نص ثابت يكتبه مدير الجامع من مركز الإعدادات ويظهر في أعلى الصفحة
 * الرئيسية لبوابة ولي الأمر. النص الفارغ = لا يظهر أي إعلان.
 */
class PortalNoticeSettingsService
{
    public const KEY_GUARDIAN_NOTICE = 'portal_notice.guardian';

    public const MAX_LENGTH = 1000;

    /** نص الإعلان الثابت لبوابة ولي الأمر، أو null إن كان فارغاً. */
    public function guardianNotice(): ?string
    {
        $value = TenantSetting::getValue(self::KEY_GUARDIAN_NOTICE);

        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    public function hasGuardianNotice(): bool
    {
        return $this->guardianNotice() !== null;
    }

    public function setGuardianNotice(?string $notice): void
    {
        $notice = $notice === null ? null : trim($notice);

        TenantSetting::putValue(
            self::KEY_GUARDIAN_NOTICE,
            ($notice === null || $notice === '') ? null : $notice
        );
    }
}
