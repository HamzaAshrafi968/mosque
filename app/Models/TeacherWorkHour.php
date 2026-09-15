<?php

namespace App\Models;

use App\Enums\WorkDay;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class TeacherWorkHour extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
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

    public function dayLabel(): string
    {
        return WorkDay::tryFrom((int) $this->day_of_week)?->label() ?? '—';
    }

    public function startMinutes(): int
    {
        return self::toMinutes($this->start_time);
    }

    public function endMinutes(): int
    {
        return self::toMinutes($this->end_time);
    }

    public function durationHours(): float
    {
        return round(($this->endMinutes() - $this->startMinutes()) / 60, 2);
    }

    public static function weeklyTotalHours(string $teacherId): float
    {
        return round(
            static::query()
                ->where('teacher_id', $teacherId)
                ->get()
                ->sum(fn (self $hour) => $hour->durationHours()),
            2
        );
    }

    /**
     * إجمالي ساعات شهر كامل: يُحسب من الجدول الأسبوعي المتكرر بعدد مرات كل
     * يوم داخل الشهر (مثال: فترتان كل أحد × عدد أيام الأحد في الشهر).
     */
    public static function monthlyHours(string $teacherId, ?CarbonInterface $month = null): float
    {
        return self::monthlyHoursFromPeriods(
            static::query()->where('teacher_id', $teacherId)->get(),
            $month
        );
    }

    /** @param  Collection<int, self>  $periods */
    public static function monthlyHoursFromPeriods(Collection $periods, ?CarbonInterface $month = null): float
    {
        $month = $month !== null ? CarbonImmutable::parse($month) : CarbonImmutable::now();

        return self::hoursBetweenFromPeriods($periods, $month->startOfMonth(), $month->endOfMonth());
    }

    /** ساعات الجدول المتكرر بين تاريخين (شامل الطرفين) — أساس عدّاد «منذ آخر دفعة». */
    public static function hoursBetween(string $teacherId, CarbonInterface $from, CarbonInterface $to): float
    {
        return self::hoursBetweenFromPeriods(
            static::query()->where('teacher_id', $teacherId)->get(),
            $from,
            $to
        );
    }

    /** @param  Collection<int, self>  $periods */
    public static function hoursBetweenFromPeriods(Collection $periods, CarbonInterface $from, CarbonInterface $to): float
    {
        $cursor = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        if ($end->lt($cursor)) {
            return 0.0;
        }

        $byDay = $periods->groupBy(fn (self $hour) => (int) $hour->day_of_week);
        $total = 0.0;

        while ($cursor->lte($end)) {
            foreach ($byDay->get($cursor->dayOfWeek, collect()) as $hour) {
                $total += $hour->durationHours();
            }

            $cursor = $cursor->addDay();
        }

        return round($total, 2);
    }

    public static function toMinutes(?string $time): int
    {
        $parts = array_map('intval', explode(':', substr((string) $time, 0, 5)));

        return ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
    }
}
