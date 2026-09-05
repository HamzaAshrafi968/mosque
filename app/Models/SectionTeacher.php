<?php

namespace App\Models;

use App\Enums\SectionTeacherRole;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit teacher → section assignment used for scoped authorization:
 * a teacher only manages sections they are actively assigned to.
 */
class SectionTeacher extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'section_id',
        'teacher_id',
        'role',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => SectionTeacherRole::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
