<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\View\Composers\SidebarComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Vite::useAggressivePrefetching();

        // روابط البرامج المفعّلة في القائمة الجانبية (تحت «البرامج والتخصصات»).
        View::composer('layouts.app', SidebarComposer::class);

        // حماية مسارات أداء الامتحانات: 30 طلباً في الدقيقة لكل مستخدم/IP.
        RateLimiter::for('exam-actions', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip()));

        // منع تخمين كلمات المرور: 5 محاولات دخول في الدقيقة لكل IP + مستخدم.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) ($request->input('email') ?? $request->input('username') ?? '')).'|'.$request->ip()));

        // السقف العام لواجهة الـ API: 60 طلباً في الدقيقة لكل مستخدم/IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
