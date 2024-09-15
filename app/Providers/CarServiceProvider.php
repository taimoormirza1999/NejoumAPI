<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CarService;

class CarServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CarService::class, function () {
            return new CarService;
        });
    }
}
