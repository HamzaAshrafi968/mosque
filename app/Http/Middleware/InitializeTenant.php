<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\StudySessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenant
{
    public function __construct(
        private readonly StudySessionService $sessions,
        private readonly AuthorizationService $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // ذاكرة الصلاحيات تبدأ نظيفة في كل طلب (تبقى المنح/السحب فورية).
        $this->authorization->flushMemo();

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $tenantId = $user->tenant_id;

        // مدير الجوامع: uses the mosque they entered from the central dashboard.
        if ($user->isSuperAdmin()) {
            $contextId = $request->session()->get('super_admin_mosque_id');
            $contextValid = $contextId && Tenant::where('id', $contextId)->exists();

            // EnsureRole يقرأ النتيجة بدل تكرار نفس الاستعلام في الطلب.
            $request->attributes->set('super_admin_mosque_valid', (bool) $contextValid);

            if ($contextValid) {
                $tenantId = $contextId;
            }
        }

        config(['app.current_tenant_id' => $tenantId]);

        // الدوام النشط (first/second shift): only mosque managers (and the
        // مدير الجوامع while inside a mosque) can pick one; portal users
        // always see everything regardless of a leftover browser choice.
        $managesMosque = $user->isAdmin() || $user->isSuperAdmin();

        $session = $managesMosque && $tenantId !== null
            ? $this->sessions->currentSession($tenantId)
            : null;

        config([
            'app.current_study_session_id' => $session?->id,
            // جنس الدوام المحدد: تُفلتر به قوائم الطلاب (فارغ = مختلط/غير محدد).
            'app.current_study_session_gender' => $session?->gender,
        ]);

        return $next($request);
    }
}
