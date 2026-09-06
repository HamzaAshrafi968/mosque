<?php

namespace App\Models;

use App\Enums\FaithMeetingAttendanceStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (meeting, student): the explicit selection + attendance record.
 */
class FaithMeetingStudent extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'meeting_id',
        'student_id',
        'attendance_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'attendance_status' => FaithMeetingAttendanceStatus::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(FaithMeeting::class, 'meeting_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
