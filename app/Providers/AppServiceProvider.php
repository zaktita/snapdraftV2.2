<?php

namespace App\Providers;

use App\Services\PostHogService;
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
        //
    }
}
