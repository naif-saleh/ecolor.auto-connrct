<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // API Routes
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));

        // Web Routes
        Route::middleware('web')
            ->group(base_path('routes/web.php'));

        RateLimiter::for("WebhookPostData", function(Request $request){
            return Limit::perMinute(2);
        });
    }
}
