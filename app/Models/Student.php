<?php

namespace App\Models;

use App\Enums\SectionStudentStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    public const CUSTOM_FIELD_ENTITY = 'student';

    protected $fillable = [
        'tenant_id',
        'classroom_id',
        'section_id',
        'user_id',
        'name',
        'gender',
        'birth_date',
        'guardian_name',
        'guardian_phone',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Portal login account (nullable — not every student has one). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Guardian links (parent_students). */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(ParentStudent::class);
    }

    /** Guardians connected to this student through parent_students. */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'parent_students', 'student_id', 'parent_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Attendance records captured through session-based attendance. */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** History-preserving section memberships. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(SectionStudent::class);
    }

    /** The currently active section membership, if any. */
    public function activeEnrollment()
    {
        return $this->hasOne(SectionStudent::class)
            ->where('status', SectionStudentStatus::Active)
            ->orderByDesc('created_at');
    }

    /** Custom field values (entity_id is a UUID, collision-free across tables). */
    public function customValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id');
    }

    /** Financial ledger rows for this student. */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'person_id')->where('person_type', 'student');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function rewardPoints(): HasMany
    {
        return $this->hasMany(RewardPoint::class);
    }

    public function quranReviewSessions(): HasMany
    {
        return $this->hasMany(QuranReviewSession::class);
    }

    public function totalPoints(): int
    {
        $earned = (clone $this->rewardPoints())
            ->where('type', 'earned')
            ->sum('points');

        $deducted = (clone $this->rewardPoints())
            ->where('type', 'deducted')
            ->sum('points');

        return (int) $earned - (int) $deducted;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('guardian_name', 'like', "%{$term}%")
                ->orWhere('guardian_phone', 'like', "%{$term}%");
        }));
    }
}
