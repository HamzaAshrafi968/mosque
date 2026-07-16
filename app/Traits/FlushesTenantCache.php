<?php

namespace App\Traits;

use App\Services\DashboardService;

trait FlushesTenantCache
{
    protected static function bootFlushesTenantCache(): void
    {
        $flush = function ($model) {
            if (! empty($model->tenant_id)) {
                DashboardService::flush($model->tenant_id);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
