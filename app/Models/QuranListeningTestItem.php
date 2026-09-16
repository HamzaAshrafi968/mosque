<?php

namespace App\Models;

use App\Enums\QuranListeningTestResult;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نتيجة جزء واحد داخل محاولة اختبار «خطة الاستماع».
 */
class QuranListeningTestItem extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'test_id',
        'plan_item_id',
        'juz',
        'from_page',
        'to_page',
        'result',
        'needs_repeat',
        'quran_review_session_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'juz' => 'integer',
            'from_page' => 'integer',
            'to_page' => 'integer',
            'result' => QuranListeningTestResult::class,
            'needs_repeat' => 'boolean',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(QuranListeningTest::class, 'test_id');
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(QuranListeningPlanItem::class, 'plan_item_id');
    }

    public function quranReviewSession(): BelongsTo
    {
        return $this->belongsTo(QuranReviewSession::class, 'quran_review_session_id');
    }

    public function pagesCount(): int
    {
        return max(0, $this->to_page - $this->from_page + 1);
    }
}
