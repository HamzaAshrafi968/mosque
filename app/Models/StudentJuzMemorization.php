<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جزء محفوظ لطالب: يفتح خمسات الجزء في «مراجعة 5».
 * المصادر: يدوي، مقدار الحفظ في ملف الطالب، أو تسميع «جديد» يغطي الجزء.
 */
class StudentJuzMemorization extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_INTAKE = 'intake';

    public const SOURCE_TASMEE = 'tasmee';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'juz',
        'memorized_at',
        'recorded_by',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'juz' => 'integer',
            'memorized_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope('study_session');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_INTAKE => 'من ملف الطالب',
            self::SOURCE_TASMEE => 'من التسميع',
            default => 'يدوي',
        };
    }
}
