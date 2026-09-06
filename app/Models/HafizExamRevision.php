<?php

namespace App\Models;

use App\Enums\ExamRevisionStatus;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جزء/مقدار مطلوب إعادته بعد رسوب الحافظ في اختبار شهري
 * (pending → completed → approved).
 */
class HafizExamRevision extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'from_surah',
        'from_ayah',
        'to_surah',
        'to_ayah',
        'juz',
        'amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'from_ayah' => 'integer',
            'to_ayah' => 'integer',
            'juz' => 'integer',
            'amount' => 'decimal:2',
            'status' => ExamRevisionStatus::class,
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(HafizMonthlyExam::class, 'exam_id');
    }

    public function fromSurah(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'from_surah');
    }

    public function toSurah(): BelongsTo
    {
        return $this->belongsTo(QuranSurah::class, 'to_surah');
    }
}
