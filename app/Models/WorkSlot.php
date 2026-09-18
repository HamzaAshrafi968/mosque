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
use Illuminate\Support\Collection;

/**
 * فترة عمل فعلية (Work Slot): تاريخ محدد + من وقت إلى وقت.
 *
 * هذه هي وحدة «الفعلي» ومصدر احتساب الرواتب، بخلاف `teacher_work_hours`
 * (الجدول الأسبوعي المتكرر) الذي يمثل «المخطط» فقط.
 */
class WorkSlot extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
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

    public function startMinutes(): int
    {
        return self::toMinutes($this->start_time);
    }

    public function endMinutes(): int
    {
        return self::toMinutes($this->end_time);
    }

    /** مدة الفترة بالدقائق (BR-06) — تُحسب ولا تُدخل يدوياً. */
    public static function durationMinutes(string $startTime, string $endTime): int
    {
        return max(0, self::toMinutes($endTime) - self::toMinutes($startTime));
    }

    public function scopeForTeacher(Builder $query, string $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeOnDate(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->whereDate('date', $date instanceof CarbonInterface ? $date->toDateString() : $date);
    }

    public function scopeBetween(Builder $query, CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        return $query
            ->whereDate('date', '>=', $from instanceof CarbonInterface ? $from->toDateString() : $from)
            ->whereDate('date', '<=', $to instanceof CarbonInterface ? $to->toDateString() : $to);
    }

    /** @param  Collection<int, self>  $slots */
    public static function totalMinutesFrom(Collection $slots): int
    {
        return (int) $slots->sum(fn (self $slot) => (int) $slot->duration_minutes);
    }

    public static function toMinutes(?string $time): int
    {
        $parts = array_map('intval', explode(':', substr((string) $time, 0, 5)));

        return ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
    }

    /** تنسيق المدة بالدقائق: 480 → «8س»، 510 → «8س 30د». */
    public static function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0 && $rest === 0) {
            return '0س';
        }

        return trim(($hours > 0 ? $hours.'س ' : '').($rest > 0 ? $rest.'د' : ''));
    }

    /** مفتاح شهر الفترة (Y-m) — يُستخدم لفحص إغلاق الشهر. */
    public function monthKey(): string
    {
        return CarbonImmutable::parse($this->date)->format('Y-m');
    }
}
