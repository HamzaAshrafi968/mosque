<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use FlushesTenantCache, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
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
}
