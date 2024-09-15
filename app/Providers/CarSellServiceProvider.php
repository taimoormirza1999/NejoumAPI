<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CarSellService;

class CarSellServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CarSellService::class, function () {
            return new CarSellService;
        });
    }
}
