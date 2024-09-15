<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AccountingService;

class AccountingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AccountingService::class, function () {
            return new AccountingService;
        });
    }
}
