<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanViewProjects
{
    /**
     * Allow entitled subscribers full project access routing, or expired users
     * who still own projects (view/download only — write routes stay behind has.credits).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasActiveSubscription() || $user->projects()->exists()) {
            return $next($request);
        }

        return redirect()->route('subscription.plans')
            ->with('error', 'Choose a plan to continue, or redeem an invite code if you have one.');
    }
}
