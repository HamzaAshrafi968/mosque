<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The source of truth for one student's status in one attendance session.
 */
class AttendanceRecord extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'attendance_session_id',
        'student_id',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
