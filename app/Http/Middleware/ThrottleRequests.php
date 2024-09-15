<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ThrottleRequests
{
    protected $maxAttempts = 10; // Maximum number of requests
    protected $decayMinutes = 1; // Time window in minutes

    public function handle($request, Closure $next)
    {
        try {
            $ip = $request->ip();
            $key = 'rate_limit:' . $ip;

            Log::info('Connecting to Redis with key: ' . $key);
            Log::info('Using Redis client: ' . config('database.redis.client'));

            $attempts = Redis::get($key) ?? 0;

            if ($attempts >= $this->maxAttempts) {
                return response()->json([
                    'message' => 'Too many requests. Please try again later.'
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            Redis::incr($key);

            if ($attempts == 0) {
                Redis::expire($key, $this->decayMinutes * 60);
            }
        } catch (\Exception $e) {
            Log::error('Error connecting to Redis: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal Server Error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $next($request);
    }
}
