<?php

namespace App\Http\Middleware;

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
        $enteredMosque = $request->hasSession() ? $request->session()->get('super_admin_mosque_id') : null;
        $actingInsideMosque = $user->isSuperAdmin() && $role === User::ROLE_ADMIN && $enteredMosque;

        abort_unless($user->role === $role || $actingInsideMosque, 403);

        return $next($request);
    }
}
