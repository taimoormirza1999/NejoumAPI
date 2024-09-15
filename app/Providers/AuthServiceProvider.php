<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Carbon\Carbon;
use Dusterio\LumenPassport\LumenPassport;
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.
        $this->setPassportConfiguration();
        $this->app['auth']->viaRequest('api', function ($request) {
            if ($request->input('api_token')) {
                return User::where('api_token', $request->input('api_token'))->first();
            }
        });

        \Dusterio\LumenPassport\LumenPassport::routes($this->app);
        //Passport::routes($this->app); 
        //Passport::loadKeysFrom(__DIR__.'/../secrets/oauth');
        Passport::tokensExpireIn(Carbon::now()->addMinutes(55));
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(1));
        Passport::personalAccessTokensExpireIn(Carbon::now()->addMonths(6));
    }
    private function setPassportConfiguration(): void
    {
            LumenPassport::allowMultipleTokens();
    }
}
