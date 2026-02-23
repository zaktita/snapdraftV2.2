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
            // For Inertia requests, redirect back properly
            if ($request->header('X-Inertia')) {
                return redirect()->back()->with('error', 'You have no credits remaining. Please upgrade your plan or purchase additional credits.');
            }
            
            return back()->with('error', 'You have no credits remaining. Please upgrade your plan or purchase additional credits.');
        }

        return $next($request);
    }
}
