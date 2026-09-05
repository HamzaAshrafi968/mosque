<?php

namespace App\Models;

use App\Enums\SectionStudentStatus;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History-preserving membership of a student inside a section.
 *
 * A student has at most one active membership. Transfers close the old row
 * (status = transferred, left_at = date) and open a new one.
 */
class SectionStudent extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'section_id',
        'student_id',
        'status',
        'enrolled_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SectionStudentStatus::class,
            'enrolled_at' => 'date',
            'left_at' => 'date',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SectionStudentStatus::Active);
    }
}
