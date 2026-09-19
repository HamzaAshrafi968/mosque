<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\Enums\QuranListeningProgramStatus;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * «برنامج استماع» (إجازة/تأهيلي): 6 دفعات × 5 أجزاء.
 *
 * يسمّع الطالب أجزاء الدفعة مع تسجيل الأخطاء ثم يُسجَّل اختبارها التراكمي،
 * والنجاح يفتح الدفعة التالية، والرسوب يُعيد الأجزاء الراسبة فقط.
 * الإتمام يُدار في QuranProgramBatchService (ختم التأهيل/إنهاء الإجازة).
 */
class QuranListeningProgram extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'enrollment_id',
        'type',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProgramType::class,
            'status' => QuranListeningProgramStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope('study_session');
    }

    /** التحاق البرنامج الأم (التأهيلي/الإجازة). */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(ProgramEnrollment::class, 'enrollment_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(QuranListeningProgramBatch::class, 'program_id')->orderBy('batch_number');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuranListeningProgramItem::class, 'program_id')->orderBy('juz');
    }

    /** محاولات الاختبار المسجّلة على دفعات البرنامج. */
    public function tests(): HasManyThrough
    {
        return $this->hasManyThrough(
            QuranListeningTest::class,
            QuranListeningProgramBatch::class,
            'program_id',
            'listening_batch_id',
            'id',
            'id',
        );
    }

    public function isActive(): bool
    {
        return $this->status === QuranListeningProgramStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === QuranListeningProgramStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === QuranListeningProgramStatus::Cancelled;
    }

    public function label(): string
    {
        return $this->type->label();
    }
}
