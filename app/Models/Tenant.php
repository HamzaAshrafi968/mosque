<?php

namespace App\Models;

use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, UuidTrait;

    protected $fillable = [
        'name',
        'code',
        'phone',
        'email',
        'address',
        'description',
        'map_url',
        'logo',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * جامع موقوف/مؤرشف: لا يُسمح لمستخدميه بالدخول (يُسمح بغياب الحالة
     * للتوافق مع السجلات القديمة).
     */
    public function isSuspended(): bool
    {
        return $this->status !== null && $this->status !== self::STATUS_ACTIVE;
    }

    /** جامع يظهر في الموقع العام (غير موقوف/مؤرشف). */
    public function isPubliclyVisible(): bool
    {
        return ! $this->isSuspended();
    }

    /** المساجد التي يجوز نشرها في الموقع العام. */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', self::STATUS_ACTIVE)->orWhereNull('status');
        });
    }

    /** رابط شعار الجامع إن وُجد (رابط كامل أو مسار داخل public). */
    public function logoUrl(): ?string
    {
        if (blank($this->logo)) {
            return null;
        }

        return Str::startsWith($this->logo, ['http://', 'https://'])
            ? $this->logo
            : asset($this->logo);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
