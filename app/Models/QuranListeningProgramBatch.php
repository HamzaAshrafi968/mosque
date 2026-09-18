<?php

namespace App\Models;

use App\Enums\QuranListeningBatchStatus;
use App\Support\QuranJuzMap;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دفعة برنامج الاستماع = 5 أجزاء متتالية (1–5، 6–10، ...، 26–30).
 *
 * لا تُفتح الدفعة إلا بعد نجاح اختبار الدفعة السابقة، وحالتها تُشتق من
 * حالة عناصر أجزائها الخمسة.
 */
class QuranListeningProgramBatch extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    public const JUZ_PER_BATCH = 5;

    public const TOTAL_BATCHES = 6;

    protected $fillable = [
        'tenant_id',
        'program_id',
        'student_id',
        'batch_number',
        'from_juz',
        'to_juz',
        'status',
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
            'status' => QuranListeningBatchStatus::class,
            'passed_at' => 'datetime',
        ];
    }

    /** [الجزء الأول، الجزء الأخير] لرقم دفعة (1..6). */
    public static function juzRange(int $batchNumber): array
    {
        $batchNumber = max(1, min(self::TOTAL_BATCHES, $batchNumber));

        return [
            'from' => (($batchNumber - 1) * self::JUZ_PER_BATCH) + 1,
            'to' => $batchNumber * self::JUZ_PER_BATCH,
        ];
    }

    /** رقم الدفعة التي ينتمي إليها جزء (1..30). */
    public static function numberForJuz(int $juz): int
    {
        QuranJuzMap::assertJuz($juz);

        return (int) ceil($juz / self::JUZ_PER_BATCH);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(QuranListeningProgram::class, 'program_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope('study_session');
    }

    public function lastTest(): BelongsTo
    {
        return $this->belongsTo(QuranListeningTest::class, 'last_test_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuranListeningProgramItem::class, 'batch_id')->orderBy('juz');
    }

    public function isLocked(): bool
    {
        return $this->status === QuranListeningBatchStatus::Locked;
    }

    public function isPassed(): bool
    {
        return $this->status === QuranListeningBatchStatus::Passed;
    }

    public function isReadyForTest(): bool
    {
        return $this->status === QuranListeningBatchStatus::ReadyForTest;
    }

    public function isNeedsRepeat(): bool
    {
        return $this->status === QuranListeningBatchStatus::NeedsRepeat;
    }

    /** وصف مختصر: «الدفعة 2 (الأجزاء 6–10)». */
    public function label(): string
    {
        return "الدفعة {$this->batch_number} (الأجزاء {$this->from_juz}–{$this->to_juz})";
    }

    /** أول صفحة وآخر صفحة في الدفعة. */
    public function pagesRange(): array
    {
        return [
            'from' => QuranJuzMap::pageRange($this->from_juz)['from'],
            'to' => QuranJuzMap::pageRange($this->to_juz)['to'],
        ];
    }

    /** @return array<int, int> أجزاء الدفعة الخمسة. */
    public function juzNumbers(): array
    {
        return range($this->from_juz, $this->to_juz);
    }
}
