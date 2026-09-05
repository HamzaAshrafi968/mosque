<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        if (! $this->authorization->canAny($user, $permissions)) {
            abort(403, 'ليس لديك صلاحية لتنفيذ هذا الإجراء');
        }

        return $next($request);
    }
}
