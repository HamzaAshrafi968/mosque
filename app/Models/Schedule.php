<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
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
