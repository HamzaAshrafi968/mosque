<?php

namespace App\Models;

use App\Enums\ShariaMemorizationStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * طالب الدورة الشرعية.
 *
 * قد يكون مسجَّلاً من الطلاب الموجودين (student_id) أو مضافاً يدوياً بسجل
 * مستقل (student_id = null) كما كان.
 */
class ShariaCourseStudent extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'tenant_id',
        'course_id',
        'student_id',
        'name',
        'phone',
        'gender',
        'birth_date',
        'guardian_phone',
        'notes',
        'status',
        'memorization_status',
        'memorization_notes',
        'memorization_updated_by',
        'memorization_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'memorization_status' => ShariaMemorizationStatus::class,
            'memorization_updated_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(ShariaCourse::class, 'course_id');
    }

    /** سجل الطالب الرسمي عند التسجيل من الطلاب الموجودين. */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function memorizationUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'memorization_updated_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ShariaCourseAttendance::class, 'student_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
