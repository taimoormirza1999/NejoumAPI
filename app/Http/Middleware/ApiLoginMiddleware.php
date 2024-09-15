<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class ApiLoginMiddleware
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $secret = DB::Table('oauth_clients')
        ->where('id', '=', 2)
        ->select('secret')
        ->first();

        $request->merge([
            'grant_type'    => 'password',
            'client_id'     => 2,
            'client_secret' => $secret,
        ]);
        
        return $next($request);
    }
}
