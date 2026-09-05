<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Section extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'classroom_id',
        'name',
        'description',
        'status',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** Students whose current snapshot places them in this section. */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** History-preserving enrollment rows for this section. */
    public function sectionStudents(): HasMany
    {
        return $this->hasMany(SectionStudent::class);
    }

    /** Teachers explicitly assigned to this section. */
    public function assignedTeachers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Teacher::class,
            SectionTeacher::class,
            'section_id',
            'id',
            'id',
            'teacher_id'
        );
    }

    /** Active assignment rows (with pivot role) for this section. */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(SectionTeacher::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
