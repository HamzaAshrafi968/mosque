<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBrowserCache
{
    /**
     * Keep browsers from caching web pages (and their CSRF tokens) so the
     * Back button cannot restore a stale page after login/logout.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // الصفحات العامة (الموقع التعريفي وملفات robots/sitemap) تُترك قابلة
        // للكاش حتى تستطيع محركات البحث فهرستها؛ منع الكاش يبقى لصفحات
        // الدخول ولوحات المستخدمين فقط.
        if ($request->user() === null && ! $request->routeIs('login', 'login.store')) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sun, 02 Jan 1990 00:00:00 GMT');

        return $response;
    }
}
