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

        // No active subscription — send to plans
        if (!$user || !$user->hasActiveSubscription()) {
            return redirect()->route('subscription.plans')
                ->with('error', 'Choose a plan or redeem a beta invite to continue.');
        }

        // Credits exhausted
        if (!$user->hasCredits()) {
            return redirect()->route('subscription.plans')
                ->with('error', "You've used all your credits. Upgrade your plan or wait for your next billing cycle.");
        }

        return $next($request);
    }
}
