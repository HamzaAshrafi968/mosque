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
 * Teachers can belong to several sessions (e.g. الأول والثالث); students and
 * sections belong to one session. Mosque managers switch the active session
 * from the top header to filter their whole panel.
 */
class StudySession extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    /** الجنس المخصص له الدوام — القيمة الفارغة تعني «غير محدد/مختلط». */
    public const GENDERS = [
        'male' => 'ذكور',
        'female' => 'إناث',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'gender',
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

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'study_session_teacher')->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
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

    /** ترتيب موحّد للعرض: الاسم ثم ذكور/إناث/غير محدد. */
    public function scopeOrderForDisplay(Builder $query): Builder
    {
        return $query
            ->orderBy('name')
            ->orderByRaw("case gender when 'male' then 1 when 'female' then 2 else 3 end");
    }

    /** التسمية العربية لجنس الدوام («غير محدد» عند غياب الجنس). */
    public function genderLabel(): string
    {
        return self::GENDERS[$this->gender] ?? 'غير محدد';
    }

    /** اسم العرض في القوائم: «الدوام الأول (ذكور)» — بلا لاحقة عند غياب الجنس. */
    public function getDisplayNameAttribute(): string
    {
        return $this->gender
            ? "{$this->name} ({$this->genderLabel()})"
            : $this->name;
    }
}
