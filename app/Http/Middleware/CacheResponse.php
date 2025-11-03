<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $minutes = 5): Response
    {
        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Generate cache key based on URL and query parameters
        $cacheKey = 'response_cache:' . md5($request->fullUrl() . ':' . $request->user()?->id);

        // Check if response is cached
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse !== null) {
            return unserialize($cachedResponse);
        }

        // Get fresh response
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, serialize($response), now()->addMinutes($minutes));
        }

        return $response;
    }
}
