<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابتا الطالب وولي الأمر معطّلتان: الحساب يعمل ويمكنه تسجيل الدخول،
 * لكن أي صفحة داخل البوابة تُحوَّل إلى صفحة «البوابة معطّلة».
 */
class PortalAccessDisabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->isGuardian() || $user->isStudent())) {
            return redirect()
                ->route('portal.disabled')
                ->with('success', 'تم تعطيل بوابة '.($user->isGuardian() ? 'ولي الأمر' : 'الطالب').' مؤقتاً، يرجى التواصل مع إدارة الجامع.');
        }

        return $next($request);
    }
}
