<?php

namespace App\Models;

use App\Traits\MultiTenantTrait;
use App\Traits\UuidTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail entry: actor, action, entity, before/after, timestamp.
 * Existing rows are never modified; corrections produce new entries.
 */
class AuditLog extends Model
{
    use MultiTenantTrait, UuidTrait;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'before',
        'after',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
