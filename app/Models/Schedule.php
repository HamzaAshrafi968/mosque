<?php

namespace App\Models;

use App\Enums\ScheduleDuration;
use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Schedule extends Model
{
    use MultiTenantTrait, StudySessionScopedTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'classroom_id',
        'section_id',
        'subject_id',
        'teacher_id',
        'program_id',
        'program_period_id',
        'study_session_id',
        'day_of_week',
        'starts_on',
        'ends_on',
        'duration',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'duration' => ScheduleDuration::class,
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function programPeriod(): BelongsTo
    {
        return $this->belongsTo(ProgramPeriod::class);
    }

    /** استثناءات هذه الحصة (إلغاء/تأجيل) في تواريخ محددة. */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'schedule_id');
    }

    /** الحصص التي لم تنتهِ مدتها بعد (المفتوحة بلا نهاية تبقى). */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('ends_on')
            ->orWhereDate('ends_on', '>=', now()->toDateString()));
    }

    /** الحصص التي انتهت مدتها (لتنظيفها تلقائياً). */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('ends_on')
            ->whereDate('ends_on', '<', now()->toDateString());
    }

    /** الحصص السارية في تاريخ معين (ضمن مدتها). */
    public function scopeActiveOn(Builder $query, mixed $date): Builder
    {
        $date = $date instanceof \DateTimeInterface
            ? Carbon::parse($date)->toDateString()
            : (string) $date;

        return $query
            ->where(fn (Builder $q) => $q
                ->whereNull('starts_on')
                ->orWhereDate('starts_on', '<=', $date))
            ->where(fn (Builder $q) => $q
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $date));
    }

    /** وصف مدة الصلاحية للعرض، أو null للحصص المفتوحة بلا تواريخ. */
    public function validityLabel(): ?string
    {
        $start = $this->starts_on?->format('Y/m/d');
        $end = $this->ends_on?->format('Y/m/d');

        if ($start === null && $end === null) {
            return null;
        }

        if ($end === null) {
            return "من {$start}";
        }

        if ($start === null) {
            return "حتى {$end}";
        }

        return "{$start} ← {$end}";
    }

    /**
     * ترتيب الحصص حسب الدوام: حصص الدوام الأول ثم الثاني ثم غير المرتبطة،
     * وداخل كل دوام حسب اليوم ثم وقت البداية.
     */
    public function scopeOrderByStudySession(Builder $query): Builder
    {
        $sessionIds = StudySession::query()->orderBy('name')->pluck('id')->all();

        if ($sessionIds === []) {
            return $query->orderBy('day_of_week')->orderBy('starts_at');
        }

        $cases = [];
        $bindings = [];

        foreach ($sessionIds as $index => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $index;
        }

        return $query
            ->orderByRaw(
                'CASE '.$this->getTable().'.study_session_id '.implode(' ', $cases).' ELSE '.count($sessionIds).' END',
                $bindings
            )
            ->orderBy('day_of_week')
            ->orderBy('starts_at');
    }
}
