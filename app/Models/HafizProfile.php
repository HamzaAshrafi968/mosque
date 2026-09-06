<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * بيانات الحافظ — extension of a Student (never a duplicate person).
 * The student keeps his full memorization history on the same row.
 */
class HafizProfile extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    public const CUSTOM_FIELD_ENTITY = 'hafiz';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'mujaz',
        'mujiz',
        'riwayah',
        'ijazah_jazariyyah',
        'scientific_certificate',
        'sharia_courses',
        'training_courses',
        'sharia_academic_study',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'mujaz' => 'boolean',
            'ijazah_jazariyyah' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Custom field values (entity_id = this profile row id). */
    public function customValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id');
    }
}
