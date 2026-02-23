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
        // Bypass limit checks in local/testing environments
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!SubscriptionService::canCreateProject($user)) {
            $limits = SubscriptionService::getTierLimits($user->subscription_tier);
            $tierName = SubscriptionService::getTierDisplayName($user->subscription_tier);

            return back()->with('error', 
                "You've reached the maximum of {$limits['max_projects']} project(s) for your {$tierName}. Please upgrade to create more projects."
            );
        }

        return $next($request);
    }
}
