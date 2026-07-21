<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckProjectLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass in local only so developers can iterate freely.
        // Testing and production always enforce plan limits.
        if (app()->environment('local')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! SubscriptionService::canCreateProject($user)) {
            $subscription = $user->subscription();
            $limit = $subscription?->projectsLimit() ?? 0;
            $tierName = $subscription?->plan?->name
                ?? SubscriptionService::getTierDisplayName($user->currentTier() ?? 'free');

            return back()->with('error',
                "You've reached the maximum of {$limit} project(s) for your {$tierName}. Please upgrade to create more projects."
            );
        }

        return $next($request);
    }
}
