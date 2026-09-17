<?php

namespace App\Models;

use App\Enums\QuranListeningTestResult;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * محاولة اختبار واحدة على دفعة الأجزاء المفتوحة في «خطة الاستماع».
 */
class QuranListeningTest extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'batch_id',
        'student_id',
        'tested_by',
        'tested_at',
        'result',
        'score',
        'passing_percentage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tested_at' => 'datetime',
            'result' => QuranListeningTestResult::class,
            'score' => 'decimal:2',
            'passing_percentage' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(QuranListeningPlan::class, 'plan_id')->withoutGlobalScope('study_session');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QuranMemorizationBatch::class, 'batch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope('study_session');
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuranListeningTestItem::class, 'test_id')->orderBy('juz');
    }

    public function isPass(): bool
    {
        return $this->result === QuranListeningTestResult::Pass;
    }
}
