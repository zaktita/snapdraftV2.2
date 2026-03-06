<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCredits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass credit check in local/testing environments so development works without a paid plan
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user || !$user->hasCredits()) {
            // Redirect to plans page — better UX than a dead-end back()
            return redirect()->route('subscription.plans')
                ->with('error', 'You have no credits remaining. Please subscribe to continue generating images.');
        }

        return $next($request);
    }
}
