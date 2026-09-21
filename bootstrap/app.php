<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\InitializeTenant;
use App\Http\Middleware\PreventBrowserCache;
use App\Providers\RepositoryServiceProvider;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        RepositoryServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            InitializeTenant::class,
            PreventBrowserCache::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'tenant' => InitializeTenant::class,
        ]);

        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            InitializeTenant::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale CSRF token (expired session / restored page) should never
        // surface as the raw "419 Page Expired" screen: send the user to a
        // fresh page with a clear message so they can simply try again.
        // Laravel maps TokenMismatchException to HttpException(419) before
        // render callbacks run, so match the status code instead.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $message = 'انتهت صلاحية الجلسة، يرجى المحاولة مرة أخرى.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            // StartSession still saves the session on the way out, so the
            // flashed message and old input survive to the next request.
            return $request->user()
                ? redirect()->back()->withErrors(['session' => $message])
                : redirect()
                    ->route('login')
                    ->withInput($request->except(['password', 'password_confirmation', '_token', '_method']))
                    ->withErrors(['email' => 'انتهت صلاحية الجلسة، يرجى تسجيل الدخول من جديد.']);
        });
    })->create();
