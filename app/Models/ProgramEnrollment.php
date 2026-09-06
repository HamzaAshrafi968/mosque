<?php

namespace App\Models;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * الالتحاق بالبرنامج التأهيلي / برنامج الإجازة.
 * Created automatically by confirmed business events (see QuranProgramService);
 * rows are preserved so the student's program history stays complete.
 */
class ProgramEnrollment extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'program_type',
        'started_at',
        'completed_at',
        'status',
        'completed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'program_type' => ProgramType::class,
            'started_at' => 'date',
            'completed_at' => 'date',
            'status' => ProgramEnrollmentStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isActive(): bool
    {
        return $this->status === ProgramEnrollmentStatus::Active;
    }
}
