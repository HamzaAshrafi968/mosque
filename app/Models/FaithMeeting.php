<?php

namespace App\Models;

use App\Enums\FaithMeetingStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * اللقاء الإيماني: an organized meeting with explicitly selected students,
 * attendance, general/student notes, suggestions and action items.
 */
class FaithMeeting extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'date',
        'start_time',
        'end_time',
        'supervisor_id',
        'teacher_id',
        'location',
        'general_notes',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'status' => FaithMeetingStatus::class,
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'supervisor_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Attendance pivot rows (one per selected student). */
    public function studentAttendances(): HasMany
    {
        return $this->hasMany(FaithMeetingStudent::class, 'meeting_id');
    }

    /** Students attached to this meeting. */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'faith_meeting_students', 'meeting_id', 'student_id')
            ->withPivot(['attendance_status', 'note'])
            ->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(FaithMeetingNote::class, 'meeting_id');
    }
}
