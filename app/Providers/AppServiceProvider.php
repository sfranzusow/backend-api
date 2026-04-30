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
        RateLimiter::for('login', function (Request $request): Limit {
            $maxAttempts = (int) config('auth.login_rate_limit.max_attempts', 5);
            $decayMinutes = (int) config('auth.login_rate_limit.decay_minutes', 1);

            $email = (string) $request->input('email', '');
            $key = mb_strtolower($email).'|'.$request->ip();

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by($key);
        });
    }
}
