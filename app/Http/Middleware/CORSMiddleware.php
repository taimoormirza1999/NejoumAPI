<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class CORSMiddleware
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
        $headers = [
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Methods'     => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With'
        ];
        if ($request->isMethod('OPTIONS'))
        {
            return response()->json('{"method":"OPTIONS"}', 200, $headers);
        }

        $response = $next($request);
        $IlluminateResponse = 'Illuminate\Http\Response';
        $SymfonyResopnse = 'Symfony\Component\HttpFoundation\Response';

        if($response instanceof $IlluminateResponse) {
            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }
            return $response;
        }
        
        if($response instanceof $SymfonyResopnse) {
            foreach ($headers as $key => $value) {
                $response->headers->set($key, $value);
            }
            return $response;
        }        

    }
}
