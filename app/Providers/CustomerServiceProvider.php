<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CustomerService;

class CustomerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CustomerService::class, function () {
            return new CustomerService;
        });
    }
}
