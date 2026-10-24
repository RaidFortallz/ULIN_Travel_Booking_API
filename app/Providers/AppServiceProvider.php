<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

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
        RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(10)->by($request->ip());
    });

    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        );
    });

    RateLimiter::for('verification', function (Request $request) {
        return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('password', function (Request $request) {
    return Limit::perMinute(5)->by(
        Str::lower((string) $request->input('email')).'|'.$request->ip()
    );
    });

    }
}
