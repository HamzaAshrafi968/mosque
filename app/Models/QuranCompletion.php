<?php

namespace App\Models;

use App\Enums\QuranCompletionStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إتمام حفظ القرآن: recorded, then confirmed. Only a confirmed completion
 * promotes the student to Hafiz and starts the automatic program workflow.
 */
class QuranCompletion extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'status',
        'completed_at',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuranCompletionStatus::class,
            'completed_at' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === QuranCompletionStatus::Confirmed;
    }
}
