<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * فترة داخل برنامج (مثال: الفترة الأولى 6:00–7:00، الفترة الثانية 7:00–8:00).
 * لكل برنامج فتراته الخاصة، ويمكن أن يكون له فترة واحدة فقط.
 */
class ProgramPeriod extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'program_id',
        'name',
        'starts_at',
        'ends_at',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
