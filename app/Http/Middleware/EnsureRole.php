<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        // مدير الجوامع can operate inside any mosque (admin area) after
        // "entering" it from the central dashboard (web sessions only).
        if ($user->isSuperAdmin()) {
            // His own central pages (/super-admin/*) are always allowed.
            if ($role === User::ROLE_SUPER_ADMIN) {
                return $next($request);
            }

            $enteredMosque = $request->hasSession() ? $request->session()->get('super_admin_mosque_id') : null;
            $insideValidMosque = $enteredMosque !== null
                && $enteredMosque !== ''
                && Tenant::where('id', $enteredMosque)->exists();

            if ($insideValidMosque && $role === User::ROLE_ADMIN) {
                return $next($request);
            }

            // Central mode (no mosque chosen): send to the central dashboard
            // with a hint instead of a dead 403 page.
            return redirect()
                ->route('super-admin.dashboard')
                ->with('success', 'اختر جامعاً من القائمة العلوية أولاً لفتح لوحة إدارته');
        }

        abort_unless($user->role === $role, 403);

        return $next($request);
    }
}
