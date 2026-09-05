<?php

namespace App\Models;

use App\Enums\CustomFieldEntityType;
use App\Enums\CustomFieldType;
use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'name',
        'field_key',
        'field_type',
        'required',
        'options',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => CustomFieldEntityType::class,
            'field_type' => CustomFieldType::class,
            'required' => 'boolean',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
