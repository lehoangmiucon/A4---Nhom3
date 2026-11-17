<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Share the account quota across web sessions, API tokens and client IPs.
        RateLimiter::for('password-change', fn (Request $request) => Limit::perMinute(5)->by('user:'.$request->user()->getAuthIdentifier()));
    }
}
