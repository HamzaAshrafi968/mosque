<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\StudySessionScopedTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use FlushesTenantCache, MultiTenantTrait, StudySessionScopedTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'study_session_id',
        'name',
        'description',
        'status',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** Only non-archived sections (navigation & rosters). */
    public function activeSections(): HasMany
    {
        return $this->sections()->where('status', 'active');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * الصف المرتبط بدوام يظهر في دوامه فقط، أما الصف المشترك (بدون دوام)
     * فيظهر في كل الدوامات حتى تبقى شعب الدوامات المختلفة قابلة للوصول.
     */
    public function applyStudySessionScope(Builder $builder, string $sessionId): void
    {
        $builder->where(function (Builder $query) use ($sessionId) {
            $query->where($this->getTable().'.study_session_id', $sessionId)
                ->orWhereNull($this->getTable().'.study_session_id');
        });
    }
}
