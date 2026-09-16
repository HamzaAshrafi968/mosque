<?php

namespace App\Models;

use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTasmeeType;
use App\Services\QuranKhamsaService;
use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * التسميع: a historical recitation record assigned by a teacher to a student.
 * Records are never overwritten; corrections create new rows or are audited.
 */
class QuranRecitationSession extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'teacher_id',
        'batch_id',
        'type',
        'date',
        'amount',
        'recited_portion',
        'from_page',
        'to_page',
        'result',
        'notes',
        'word_statuses',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuranTasmeeType::class,
            'date' => 'date',
            'amount' => 'decimal:2',
            'from_page' => 'integer',
            'to_page' => 'integer',
            'result' => QuranTasmeeResult::class,
            'word_statuses' => 'array',
        ];
    }

    /**
     * تسميع «جديد» قد يُكمل تغطية جزء كامل → يُسجَّل الجزء محفوظاً تلقائياً
     * (يفتح خمساته في «مراجعة 5»). الإضافة فقط، ولا حذف تلقائي.
     */
    protected static function booted(): void
    {
        static::saved(function (QuranRecitationSession $session) {
            app(QuranKhamsaService::class)->syncMemorizationFromTasmee($session);
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope('study_session');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withoutGlobalScope('study_session');
    }

    /** دفعة الحفظ المرتبطة (لتسميع «جديد» داخل نطاقها). */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(QuranMemorizationBatch::class, 'batch_id');
    }
}
