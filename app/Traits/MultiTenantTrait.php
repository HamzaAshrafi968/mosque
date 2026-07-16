<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait MultiTenantTrait
{
    protected static function bootMultiTenantTrait(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = config('app.current_tenant_id');

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id) && config('app.current_tenant_id') !== null) {
                $model->tenant_id = config('app.current_tenant_id');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
