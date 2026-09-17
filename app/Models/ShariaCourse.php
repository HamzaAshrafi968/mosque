<?php

namespace App\Models;

use App\Enums\ShariaCourseStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShariaCourse extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    public const SOURCE_MOSQUE = 'mosque';

    public const SOURCE_SUPER_ADMIN = 'super_admin';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'location',
        'start_date',
        'end_date',
        'status',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ShariaCourseStatus::class,
        ];
    }

    /** مشرفو الدورة (يمكن أن يكونوا أكثر من واحد). */
    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'sharia_course_supervisor', 'course_id', 'teacher_id')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(ShariaCourseLesson::class, 'course_id');
    }

    /** Independent course roster (no relation to students table). */
    public function students(): HasMany
    {
        return $this->hasMany(ShariaCourseStudent::class, 'course_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ShariaCourseAttendance::class, 'course_id');
    }

    public function isActive(): bool
    {
        return $this->status === ShariaCourseStatus::Active;
    }

    /** أُنشئت من مدير الجوامع (لا من إدارة الجامع). */
    public function isFromSuperAdmin(): bool
    {
        return $this->source === self::SOURCE_SUPER_ADMIN;
    }
}
