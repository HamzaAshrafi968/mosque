<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * إعداد مفتاح/قيمة خاص بجامع واحد (tenant_settings).
 */
class TenantSetting extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    /** مدة كاش إعدادات الجامع (ثانية). */
    public const CACHE_TTL = 3600;

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
    ];

    /** قراءة إعداد الجامع الحالي (أو القيمة الافتراضية). */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        $tenantId = config('app.current_tenant_id');

        if ($tenantId === null) {
            $value = static::query()->where('key', $key)->value('value');

            return $value ?? $default;
        }

        $value = Cache::remember(
            self::cacheKey($tenantId, $key),
            self::CACHE_TTL,
            fn () => static::query()->where('key', $key)->value('value')
        );

        return $value ?? $default;
    }

    /** حفظ/تحديث إعداد للجامع الحالي. */
    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        if (($tenantId = config('app.current_tenant_id')) !== null) {
            Cache::forget(self::cacheKey($tenantId, $key));
        }
    }

    private static function cacheKey(string $tenantId, string $key): string
    {
        return "tenant:{$tenantId}:setting:{$key}";
    }
}
