<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes immutable audit trail entries.
 *
 * Logging is skipped during seeding / console tasks without an authenticated
 * actor so bulk data loads do not pollute the trail.
 */
class AuditLogger
{
    public function log(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?string $tenantId = null,
        array $before = [],
        array $after = [],
        ?User $actor = null,
    ): ?AuditLog {
        $actor ??= auth()->user();

        $resolvedTenant = $tenantId
            ?? ($entityId ? $this->tenantFromAudited($entityType, $entityId) : null)
            ?? $actor?->tenant_id
            ?? config('app.current_tenant_id');

        if ($resolvedTenant === null && $actor === null) {
            // Background/seed context without any tenant — do not pollute.
            return null;
        }

        return AuditLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $resolvedTenant,
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before === [] ? null : $before,
            'after' => $after === [] ? null : $after,
            'ip_address' => request()->ip(),
        ]);
    }

    public function logModel(string $action, Model $model, array $before = [], array $after = [], ?User $actor = null): ?AuditLog
    {
        $after = $after === [] ? $model->getAttributes() : $after;

        return $this->log(
            $action,
            Str::snake(class_basename($model)),
            $model->getKey(),
            $model->tenant_id ?? null,
            $before,
            $after,
            $actor
        );
    }

    private function tenantFromAudited(string $entityType, string $entityId): ?string
    {
        try {
            $class = 'App\\Models\\'.ucfirst(Str::singular($entityType));

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                return null;
            }

            return (string) $class::withoutGlobalScopes()->whereKey($entityId)->value('tenant_id');
        } catch (\Throwable $e) {
            Log::debug('audit tenant resolution failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
