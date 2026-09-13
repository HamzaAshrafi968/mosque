<?php

namespace App\Models;

use App\Enums\ShariaLessonType;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShariaCourseLesson extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'course_id',
        'title',
        'type',
        'date',
        'start_time',
        'end_time',
        'teacher_id',
        'description',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShariaLessonType::class,
            'date' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(ShariaCourse::class, 'course_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ShariaCourseAttendance::class, 'lesson_id');
    }
}
