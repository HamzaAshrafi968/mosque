<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\StudySessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenant
{
    public function __construct(private readonly StudySessionService $sessions) {}

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

        // الدوام النشط (first/second shift): only mosque managers (and the
        // مدير الجوامع while inside a mosque) can pick one; portal users
        // always see everything regardless of a leftover browser choice.
        $managesMosque = $user->isAdmin() || $user->isSuperAdmin();

        config([
            'app.current_study_session_id' => $managesMosque && $tenantId !== null
                ? $this->sessions->currentSessionId($tenantId)
                : null,
        ]);

        return $next($request);
    }
}
