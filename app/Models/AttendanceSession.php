<?php

namespace App\Models;

use App\Enums\AttendanceSessionStatus;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One attendance-taking event for a section on a date.
 * Individual student statuses live in AttendanceRecord rows.
 *
 * Note: `date` is intentionally NOT a date cast — a plain DATE column keeps
 * the canonical `Y-m-d` string on SQLite (date casts append `00:00:00`),
 * while the accessor below still exposes a Carbon instance for views.
 */
class AttendanceSession extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'section_id',
        'date',
        'starts_at',
        'ends_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceSessionStatus::class,
        ];
    }

    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Carbon::parse($value)
        );
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
