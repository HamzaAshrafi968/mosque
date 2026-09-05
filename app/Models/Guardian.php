<?php

namespace App\Models;

use App\Traits\FlushesTenantCache;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parent / guardian profile (spec §2). One guardian may be connected to several
 * students through `parent_students`; a guardian never sees students outside
 * those connections.
 */
class Guardian extends Model
{
    use FlushesTenantCache, HasFactory, MultiTenantTrait, UuidTrait;

    protected $table = 'parents';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'phone',
        'email',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ParentStudent::class, 'parent_id');
    }

    /** Students connected through parent_students (the guardian's scope). */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_students', 'parent_id', 'student_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        }));
    }
}
