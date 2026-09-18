<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سعر ساعة ساري من تاريخ إلى تاريخ (سجل تاريخي — لا يُستخدم السعر الحالي
 * لحساب فترات سابقة). `effective_to = null` يعني أن السعر مفتوح/ساري.
 */
class HourlyRate extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'rate',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** السعر الساري في تاريخ معين (شامل الطرفين). */
    public function scopeActiveOn(Builder $query, CarbonInterface|string $date): Builder
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $query
            ->whereDate('effective_from', '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day));
    }

    public function isOpen(): bool
    {
        return $this->effective_to === null;
    }

    /** حالة السعر مقارنةً باليوم: ساري / منتهٍ / قادم. */
    public function statusLabel(?CarbonInterface $today = null): string
    {
        $today = $today !== null ? CarbonImmutable::parse($today) : CarbonImmutable::now();

        if ($this->effective_from->gt($today)) {
            return 'قادم';
        }

        if ($this->effective_to !== null && $this->effective_to->lt($today)) {
            return 'منتهٍ';
        }

        return 'ساري';
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->statusLabel()) {
            'ساري' => 'bg-emerald-100 text-emerald-800',
            'قادم' => 'bg-sky-100 text-sky-800',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
