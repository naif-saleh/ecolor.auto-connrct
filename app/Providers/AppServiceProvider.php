<?php

namespace App\Providers;

use App\Models\General_Setting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->booting(function (){
            View::composer('*', function ($view){
                $logo = General_Setting::get('logo', 'default-logo.png');
                $view->with('logo', $logo);
            });
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
