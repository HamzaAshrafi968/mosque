<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            config(['app.current_tenant_id' => $user->tenant_id]);
        }

        return $next($request);
    }
}
