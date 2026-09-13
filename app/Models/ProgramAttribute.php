<?php

namespace App\Models;

use App\Enums\AttributeOptionSource;
use App\Enums\CustomFieldType;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * خصيصة/حقل مخصص داخل برنامج (مثال: عدد الأجزاء، مدة البرنامج، المستوى).
 * التعريف والقيمة في السجل نفسه لأن الخصيصة تخص البرنامج مباشرة.
 */
class ProgramAttribute extends Model
{
    use HasFactory, MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'program_id',
        'name',
        'field_key',
        'field_type',
        'required',
        'options',
        'options_source',
        'options_config',
        'value',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => CustomFieldType::class,
            'required' => 'boolean',
            'options' => 'array',
            'options_source' => AttributeOptionSource::class,
            'options_config' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
