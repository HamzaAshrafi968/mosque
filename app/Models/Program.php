<?php

namespace App\Models;

use App\Enums\ScheduleProgramType;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تخصص جدول (program): برنامج التحفيظ، الإجازة، اختبارات الحفظ،
 * الدورات الشرعية، البرامج القرآنية أو أي برنامج مخصص بفتراته وخصائصه.
 */
class Program extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'description',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScheduleProgramType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ProgramPeriod::class)->orderBy('sort_order')->orderBy('name');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProgramAttribute::class)->orderBy('sort_order')->orderBy('name');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * الدوامات التي يظهر فيها هذا البرنامج. عدم وجود ارتباطات يعني ظهوره
     * في كل الدوامات.
     */
    public function studySessions(): BelongsToMany
    {
        return $this->belongsToMany(StudySession::class, 'program_study_session')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
    }
}
