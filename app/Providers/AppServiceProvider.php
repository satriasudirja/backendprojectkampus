<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SimpleCaptchaGeneratorService;
use App\Services\SlideCaptchaService;
use App\Services\AdminDashboardService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register SimpleCaptchaGeneratorService
        $this->app->singleton(SimpleCaptchaGeneratorService::class, function ($app) {
            return new SimpleCaptchaGeneratorService();
        });
        
        // Register SlideCaptchaService with generator dependency
        $this->app->singleton(SlideCaptchaService::class, function ($app) {
            return new SlideCaptchaService(
                $app->make(SimpleCaptchaGeneratorService::class)
            );
        });
           $this->app->singleton(AdminDashboardService::class, function ($app) {
            return new AdminDashboardService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}