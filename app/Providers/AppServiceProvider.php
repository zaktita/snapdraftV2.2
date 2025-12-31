<?php

namespace App\Providers;

use App\Services\AI\BrandReferenceAnalyzer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind AI helpers
        $this->app->singleton(BrandReferenceAnalyzer::class, fn () => new BrandReferenceAnalyzer());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
