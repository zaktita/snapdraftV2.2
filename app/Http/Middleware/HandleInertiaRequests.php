<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appVersion' => config('app.version', '1.0.0'),
            'labsEnabled' => app()->environment('local'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user()
                    ? array_merge($request->user()->toArray(), [
                        'credits_remaining' => $request->user()->creditsRemaining(),
                        'credits_total' => $request->user()->creditsTotal(),
                        'subscription_tier' => $request->user()->currentTier(),
                        'subscription_plan_name' => $request->user()->subscription()?->plan?->name,
                        'subscription_entitled' => $request->user()->hasActiveSubscription(),
                        'subscription_read_only' => $request->user()->isSubscriptionReadOnly(),
                    ])
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Flash messages (success, error, warning)
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'warning' => session('warning'),
                'info' => session('info'),
            ],
            // Backwards-compatible top-level error for existing pages expecting page.props.error
            'error' => session('error'),
            // Impersonation state
            'impersonating' => session('impersonating_user_id')
                ? (function () {
                    $adminId = session('impersonating_user_id');
                    $admin = \App\Models\User::find($adminId);

                    return $admin ? ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email] : null;
                })()
                : null,
            // PostHog client-side configuration
            'posthog' => [
                'token' => config('posthog.api_key', ''),
                'host' => config('posthog.host', ''),
                'disable_session_recording' => (bool) config('posthog.disable_session_recording', false),
                'capture_dead_clicks' => (bool) config('posthog.capture_dead_clicks', true),
            ],
        ];
    }
}
