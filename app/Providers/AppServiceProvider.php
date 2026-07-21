<?php

namespace App\Providers;

use App\Services\PostHogService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AI services resolve through Laravel auto-wiring.
        $this->app->singleton(PostHogService::class, fn () => new PostHogService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prefer APP_URL scheme when set to https (e.g. ngrok tunnel in .env).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Lemon Squeezy can burst events; keep generous headroom for open launch.
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });
    }
}
