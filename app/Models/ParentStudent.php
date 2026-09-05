<?php

namespace App\Models;

use App\Enums\ParentStudentRelationship;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Guardian ↔ student link (spec §2). The pivot carries the relationship label
 * (father/mother/guardian/other) and the primary flag used for notifications.
 */
class ParentStudent extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'student_id',
        'relationship',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'relationship' => ParentStudentRelationship::class,
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'parent_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
