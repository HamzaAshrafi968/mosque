<?php

namespace App\Models;

use App\Enums\WorkDay;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public static function toMinutes(?string $time): int
    {
        $parts = array_map('intval', explode(':', substr((string) $time, 0, 5)));

        return ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
    }
}
