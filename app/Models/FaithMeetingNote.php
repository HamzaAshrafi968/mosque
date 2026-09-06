<?php

namespace App\Models;

use App\Enums\FaithMeetingNoteType;
use App\Enums\MeetingActionStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * General note, student note, suggestion or action item on a faith meeting.
 * Action items carry an assignee, a due date and a completion status.
 */
class FaithMeetingNote extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'meeting_id',
        'student_id',
        'note_type',
        'content',
        'created_by',
        'assigned_to',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'note_type' => FaithMeetingNoteType::class,
            'due_date' => 'date',
            'status' => MeetingActionStatus::class,
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
