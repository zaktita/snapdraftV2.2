<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        // Admins must have confirmed 2FA outside local/testing.
        if (! app()->environment(['local', 'testing']) && $user->two_factor_confirmed_at === null) {
            return redirect()
                ->route('two-factor.show')
                ->with('error', 'Enable two-factor authentication before accessing the admin panel.');
        }

        return $next($request);
    }
}
