<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SimpleCaptchaGeneratorService;
use App\Services\SlideCaptchaService;
use App\Services\AdminDashboardService;

use App\Models\SimpegCutiRecord;
use App\Models\SimpegIzinRecord;
use App\Observers\LeavePermitObserver;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
// Remove this line if you're not using Image facade in this file
// use Intervention\Image\Facades\Image;

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


        Auth::viaRequest('sso', function ($request) {
            try {
                JWTAuth::parseToken()->getPayload();

            }
            catch (JWTException $e) {
                throw new HttpResponseException(
                    response()->json([
                        'status' => 'error',
                        'message' => 'Token is invalid or expired.',
                        'error_details' => $e->getMessage() // Optional: for debugging
                    ], 401)
                );
            }
        });

        SimpegCutiRecord::observe(LeavePermitObserver::class);
        SimpegIzinRecord::observe(LeavePermitObserver::class);
    }
}