<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $tenantId = $user->tenant_id;

        // مدير الجوامع: uses the mosque they entered from the central dashboard.
        if ($user->isSuperAdmin()) {
            $contextId = $request->session()->get('super_admin_mosque_id');

            if ($contextId && Tenant::where('id', $contextId)->exists()) {
                $tenantId = $contextId;
            }
        }

        config(['app.current_tenant_id' => $tenantId]);

        return $next($request);
    }
}
