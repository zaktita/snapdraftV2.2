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
        $user = $request->user();

        // No active subscription — send to dashboard
        if (!$user || !$user->hasActiveSubscription()) {
            return redirect()->route('dashboard')
                ->with('error', 'You need a beta invite to access this feature. Check your email for your invite code.');
        }

        // Credits exhausted
        if (!$user->hasCredits()) {
            return redirect()->route('dashboard')
                ->with('error', "You've used all your credits. Share your feedback and we'll top you up.");
        }

        return $next($request);
    }
}
