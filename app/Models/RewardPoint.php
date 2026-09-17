<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardPoint extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    /** مصادر المنح التلقائي (source_type) — تُستخدم لحماية التكرار. */
    public const SOURCE_TASMEE = 'quran_recitation_session';

    public const SOURCE_KHAMSA_ITEM = 'quran_khamsa_review_item';

    public const SOURCE_TEST = 'quran_listening_test';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'awarded_by',
        'quran_review_session_id',
        'study_session_id',
        'source_type',
        'source_id',
        'source_pages',
        'points',
        'reason',
        'type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'source_pages' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function quranReviewSession(): BelongsTo
    {
        return $this->belongsTo(QuranReviewSession::class);
    }

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }

    public function isAutomatic(): bool
    {
        return $this->source_type !== null || $this->quran_review_session_id !== null;
    }
}
