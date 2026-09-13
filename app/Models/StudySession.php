<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دوام (study session/shift) inside a mosque — e.g. الدوام الأول / الثاني.
 *
 * Teachers, students and sections belong to one session; mosque managers
 * switch the active session from the top header to filter their whole panel.
 */
class StudySession extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * البرامج/التخصصات المتاحة في هذا الدوام. القائمة الفارغة تعني أن كل
     * البرامج المفعّلة متاحة.
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_study_session')->withTimestamps();
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
