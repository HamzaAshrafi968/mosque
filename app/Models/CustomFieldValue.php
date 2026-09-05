<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldValue extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'custom_field_id',
        'entity_id',
        'value',
    ];

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
