<?php

namespace App\Models;

use App\Enums\QuranMemorizationBatchStatus;
use App\Support\QuranJuzMap;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دفعة حفظ = جزآن متتاليان (1–2، 3–4، ...، 29–30).
 *
 * الدفعة هي منظم دورة الطالب: حفظ الجزأين → مراجعة 5 → اختبار بحد نجاح
 * الجامع → فتح الدفعة التالية. تُشتق حالتها من الأجزاء المحفوظة فعلياً
 * (student_juz_memorizations) + مراجعة 5 + نتيجة آخر اختبار.
 */
class QuranMemorizationBatch extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    public const TOTAL_BATCHES = 15;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'batch_number',
        'from_juz',
        'to_juz',
        'status',
        'review_5_id',
        'retake_review_id',
        'plan_id',
        'last_test_id',
        'passed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'batch_number' => 'integer',
            'from_juz' => 'integer',
            'to_juz' => 'integer',
            'status' => QuranMemorizationBatchStatus::class,
            'passed_at' => 'datetime',
        ];
    }

    /** [الجزء الأول، الجزء الثاني] لرقم دفعة (1..15). */
    public static function juzRange(int $batchNumber): array
    {
        $batchNumber = max(1, min(self::TOTAL_BATCHES, $batchNumber));

        return ['from' => ($batchNumber * 2) - 1, 'to' => $batchNumber * 2];
    }

    /** رقم الدفعة التي ينتمي إليها جزء (1..30). */
    public static function numberForJuz(int $juz): int
    {
        QuranJuzMap::assertJuz($juz);

        return (int) ceil($juz / 2);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope('study_session');
    }

    public function review5(): BelongsTo
    {
        return $this->belongsTo(QuranKhamsaReview::class, 'review_5_id')->withoutGlobalScope('study_session');
    }

    /** مراجعة «خمسات إعادة رسوب الاختبار» المولّدة عند الرسوب (إن وُجدت). */
    public function retakeReview5(): BelongsTo
    {
        return $this->belongsTo(QuranKhamsaReview::class, 'retake_review_id')->withoutGlobalScope('study_session');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QuranListeningPlan::class, 'plan_id')->withoutGlobalScope('study_session');
    }

    public function lastTest(): BelongsTo
    {
        return $this->belongsTo(QuranListeningTest::class, 'last_test_id');
    }

    /** جلسات التسميع «الجديد» المسجّلة داخل نطاق الدفعة. */
    public function tasmeeSessions(): HasMany
    {
        return $this->hasMany(QuranRecitationSession::class, 'batch_id')->orderByDesc('date')->orderByDesc('created_at');
    }

    public function isLocked(): bool
    {
        return $this->status === QuranMemorizationBatchStatus::Locked;
    }

    public function isPassed(): bool
    {
        return $this->status === QuranMemorizationBatchStatus::Passed;
    }

    public function isNeedsRepeat(): bool
    {
        return $this->status === QuranMemorizationBatchStatus::NeedsRepeat;
    }

    public function isReadyForTest(): bool
    {
        return $this->status === QuranMemorizationBatchStatus::ReadyForTest;
    }

    /** وصف مختصر: «الدفعة 4 (الجزآن 7–8)». */
    public function label(): string
    {
        return "الدفعة {$this->batch_number} (الجزآن {$this->from_juz}–{$this->to_juz})";
    }

    /** أول صفحة وآخر صفحة في الدفعة. */
    public function pagesRange(): array
    {
        return [
            'from' => QuranJuzMap::pageRange($this->from_juz)['from'],
            'to' => QuranJuzMap::pageRange($this->to_juz)['to'],
        ];
    }
}
