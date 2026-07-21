<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PostHogService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Display subscription plans.
     */
    public function index(): Response
    {
        try {
            $user = Auth::user();
            
            // Get active public plans (exclude invite-only beta)
            $dbPlans = Plan::active()
                ->ordered()
                ->get()
                ->filter(function (Plan $plan) {
                    $features = $plan->capabilities['features'] ?? [];
                    if (($features['is_public'] ?? true) === false) {
                        return false;
                    }

                    return $plan->slug !== 'beta';
                });

            // Transform plans for frontend
            $plans = $dbPlans->groupBy('slug')->map(function ($planGroup) {
                $plan = $planGroup->first();
                $capabilities = $plan->capabilities ?? [];
                // Guard: if capabilities is a JSON string (double-encoded), decode it
                if (is_string($capabilities)) {
                    $capabilities = json_decode($capabilities, true) ?? [];
                }

                // Get pricing for both periods
                $pricing = SubscriptionService::getTierPricing($plan->slug);

                return [
                    'id' => $plan->slug,
                    'name' => $plan->name,
                    'subtitle' => $this->getSubtitle($plan->slug),
                    'price' => (float) $plan->price,
                    'yearly_price' => (float) $pricing['yearly_price'],
                    'yearly_total' => (float) ($pricing['yearly_total'] ?? ($plan->price * 10)),
                    'currency' => $plan->currency,
                    'credits' => (int) ($capabilities['credits_per_month'] ?? 0),
                    'max_projects' => (int) ($capabilities['max_projects'] ?? 0),
                    'csv_max_rows' => (int) ($capabilities['csv_max_rows'] ?? 0),
                    'popular' => (bool) $plan->is_featured,
                    'features' => $this->formatFeatures($capabilities),
                    'bestFor' => $this->getBestFor($plan->slug),
                ];
            })->values()->toArray();

            // Get current user's subscription info
            $subscription = $user->subscription();
            $currentTier = $subscription?->plan?->slug ?? null;
            $creditsRemaining = $subscription ? $subscription->creditsRemaining() : 0;
            $creditsTotal = $subscription ? $subscription->creditsLimit() : 0;
            $remainingSlots = SubscriptionService::getRemainingProjectSlots($user);

            return Inertia::render('subscription/plans', [
                'plans' => $plans,
                'current_tier' => $currentTier,
                'credits_remaining' => $creditsRemaining,
                'credits_total' => $creditsTotal,
                'remaining_project_slots' => $remainingSlots,
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription plans error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return minimal data on error
            return Inertia::render('subscription/plans', [
                'plans' => [],
                'current_tier' => null,
                'credits_remaining' => 0,
                'credits_total' => 0,
                'remaining_project_slots' => 0,
            ]);
        }
    }

    /**
     * Get subtitle for plan.
     */
    private function getSubtitle(string $slug): string
    {
        return match($slug) {
            'starter'  => 'Brand-consistent posts from CSV',
            'pro'      => 'Scale campaigns without waiting',
            'business' => 'High-volume brands & agencies',
            'beta'     => 'Invite-only beta access',
            default => '',
        };
    }

    /**
     * Format capabilities into feature list.
     */
    private function formatFeatures(array $capabilities): array
    {
        $features = [];
        $flags = $capabilities['features'] ?? [];

        if (isset($capabilities['credits_per_month'])) {
            $features[] = $capabilities['credits_per_month'] . ' Production Credits / month';
        }

        if (isset($capabilities['max_projects'])) {
            $features[] = $capabilities['max_projects'] . ' Brand Project' . ($capabilities['max_projects'] > 1 ? 's' : '');
        }

        if (isset($capabilities['csv_max_rows'])) {
            $features[] = 'CSV upload up to ' . number_format($capabilities['csv_max_rows']) . ' rows';
        }

        if (isset($capabilities['max_team_seats']) && $capabilities['max_team_seats'] > 1) {
            $features[] = 'Team access (' . $capabilities['max_team_seats'] . ' seats included)';
        }

        if ($flags['priority_processing'] ?? false) {
            $features[] = 'Priority processing';
        }

        if ($flags['batch_regeneration'] ?? false) {
            $features[] = 'Batch regeneration';
        }

        if ($flags['advanced_canvas'] ?? false) {
            if ($flags['version_history'] ?? false) {
                $features[] = 'Advanced Canvas Editor + Version history';
            } else {
                $features[] = 'Advanced Canvas Editor';
            }
        } elseif ($flags['basic_canvas'] ?? false) {
            $features[] = 'Basic Canvas Editor';
        }

        if ($flags['batch_generation'] ?? false) {
            $features[] = 'Full Batch Generation';
        }

        if ($flags['brand_dna_analysis'] ?? false) {
            $features[] = 'Brand DNA extraction';
        }

        if (($flags['image_generation'] ?? false) && ($flags['text_generation'] ?? false) && !($flags['batch_generation'] ?? false)) {
            $features[] = 'Image + Text generation';
        }

        if ($flags['standard_processing'] ?? false) {
            $features[] = 'Standard processing speed';
        } elseif ($flags['priority_processing'] ?? false) {
            // already added above, skip
        }

        if ($flags['advanced_canvas'] ?? false) {
            $features[] = 'Full Canvas capabilities';
        }

        return $features;
    }

    /**
     * Get best for list.
     */
    private function getBestFor(string $slug): array
    {
        return match($slug) {
            'starter'  => ['Solo founders', 'Freelancers', 'Getting started'],
            'pro'      => ['Growing brands', 'Weekly campaigns', 'Marketing teams'],
            'business' => ['Agencies', 'High-volume brands', 'Content ops'],
            'beta'     => ['Early adopters'],
            default => [],
        };
    }

    /**
     * Display billing portal.
     */
    public function portal(): Response
    {
        $user = Auth::user();
        $activeSubscription = $user->subscription();

        $subscription = null;
        if ($activeSubscription) {
            $updateSubscriptionUrl = $activeSubscription->metadata['customer_portal_update_subscription']
                ?? $activeSubscription->customer_portal_url;

            $subscription = [
                'id' => $activeSubscription->id,
                'tier' => $activeSubscription->plan?->slug ?? 'plan',
                'plan_name' => $activeSubscription->plan?->name,
                'billing_period' => $activeSubscription->billing_period,
                'status' => $activeSubscription->status,
                'price' => $activeSubscription->price,
                'currency' => $activeSubscription->currency,
                'credits_remaining' => $activeSubscription->creditsRemaining(),
                'credits_total' => $activeSubscription->creditsLimit(),
                'started_at' => $activeSubscription->created_at,
                'renews_at' => $activeSubscription->renews_at,
                'ends_at' => $activeSubscription->ends_at,
                'cancelled_at' => $activeSubscription->cancelled_at,
                'auto_renew' => ! $activeSubscription->cancelled_at && $activeSubscription->status === 'active',
                'on_grace_period' => $activeSubscription->isOnGracePeriod(),
                'customer_portal_url' => $activeSubscription->customer_portal_url,
                'update_payment_url' => $activeSubscription->update_payment_url,
                'update_subscription_url' => $updateSubscriptionUrl,
                'on_trial' => $activeSubscription->onTrial(),
            ];
        }

        $subscriptionHistory = $user->subscriptions()
            ->with('plan')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'tier' => $sub->plan?->slug ?? 'plan',
                    'status' => $sub->status,
                    'billing_period' => $sub->billing_period,
                    'started_at' => $sub->created_at,
                    'ended_at' => $sub->ends_at,
                ];
            });

        return Inertia::render('subscription/portal', [
            'subscription' => $subscription,
            'subscription_history' => $subscriptionHistory,
        ]);
    }

    /**
     * Create checkout for a new subscription.
     * Existing Lemon Squeezy subscribers change plans via the customer portal.
     */
    public function upgrade(Request $request)
    {
        $request->validate([
            'tier' => ['required', 'string', 'in:starter,pro,business'],
            'billing_period' => 'required|in:monthly,yearly',
        ]);

        $user = Auth::user();
        $tier = $request->tier;
        $billingPeriod = $request->billing_period;

        $existing = $user->subscription();
        if ($existing && $existing->lemonsqueezy_id && $existing->provider === 'lemonsqueezy') {
            $portalUrl = $existing->metadata['customer_portal_update_subscription']
                ?? $existing->customer_portal_url;

            if ($portalUrl) {
                return Inertia::location($portalUrl);
            }

            return back()->with('error', 'You already have an active subscription. Use Manage Subscription to change your plan.');
        }

        $plan = Plan::where('slug', $tier)->first();
        if (! $plan) {
            return back()->with('error', 'Invalid subscription plan selected.');
        }

        $variantId = $billingPeriod === 'yearly'
            ? $plan->provider_variant_yearly
            : $plan->provider_variant_monthly;

        if (! $variantId || ! ctype_digit((string) $variantId)) {
            return back()->with('error', 'Plan variant not configured. Please contact support.');
        }

        $storeId = config('services.lemonsqueezy.store_id');
        $apiKey = config('services.lemonsqueezy.api_key');

        if (empty($storeId) || empty($apiKey)) {
            return back()->with('info', 'Demo Mode: Configure Lemon Squeezy API keys in .env to enable live payments.');
        }

        try {
            $httpClient = Http::withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.$apiKey,
            ]);

            if (! config('services.lemonsqueezy.verify_ssl', true)) {
                $httpClient = $httpClient->withOptions(['verify' => false]);
            }

            $response = $httpClient->post('https://api.lemonsqueezy.com/v1/checkouts', [
                'data' => [
                    'type' => 'checkouts',
                    'attributes' => [
                        'product_options' => [
                            'redirect_url' => route('dashboard', absolute: true),
                            'receipt_button_text' => 'Go to dashboard',
                            'enabled_variants' => [(int) $variantId],
                        ],
                        'checkout_data' => [
                            'email' => $user->email,
                            'name' => $user->name,
                            'custom' => [
                                'user_id' => (string) $user->id,
                                'tier' => $tier,
                                'billing_period' => $billingPeriod,
                            ],
                        ],
                    ],
                    'relationships' => [
                        'store' => [
                            'data' => [
                                'type' => 'stores',
                                'id' => $storeId,
                            ],
                        ],
                        'variant' => [
                            'data' => [
                                'type' => 'variants',
                                'id' => $variantId,
                            ],
                        ],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('Lemon Squeezy checkout failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'variant_id' => $variantId,
                ]);
                $detail = data_get($response->json(), 'errors.0.detail');

                return back()->with('error', $detail
                    ? "Checkout failed: {$detail}"
                    : 'Failed to create checkout session. Please try again.');
            }

            $checkoutUrl = $response->json('data.attributes.url');
            if (empty($checkoutUrl)) {
                return back()->with('error', 'Failed to create checkout session. Please try again.');
            }

            app(PostHogService::class)->capture((string) $user->id, 'upgrade_checkout_started', [
                'plan' => $tier,
                'billing_period' => $billingPeriod,
            ]);

            return Inertia::location($checkoutUrl);
        } catch (\Exception $e) {
            Log::error('Lemon Squeezy checkout error', [
                'user_id' => $user->id,
                'tier' => $tier,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to create checkout session. Please try again.');
        }
    }

    /**
     * Cancel subscription at period end (monthly and yearly).
     */
    public function downgrade()
    {
        $user = Auth::user();
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->lemonsqueezy_id) {
            return back()->with('error', 'No active subscription found.');
        }

        if ($subscription->isOnGracePeriod()) {
            return back()->with('info', 'Your subscription is already set to cancel at the end of the billing period.');
        }

        try {
            $apiKey = config('services.lemonsqueezy.api_key');
            $httpClient = Http::withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.$apiKey,
            ]);

            if (! config('services.lemonsqueezy.verify_ssl', true)) {
                $httpClient = $httpClient->withOptions(['verify' => false]);
            }

            $response = $httpClient->delete('https://api.lemonsqueezy.com/v1/subscriptions/'.$subscription->lemonsqueezy_id);

            if ($response->successful()) {
                $attributes = $response->json('data.attributes') ?? [];
                $endsAt = ! empty($attributes['ends_at'])
                    ? $attributes['ends_at']
                    : ($subscription->renews_at ?? now()->addMonth());

                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'ends_at' => $endsAt,
                    'customer_portal_url' => $attributes['urls']['customer_portal'] ?? $subscription->customer_portal_url,
                    'update_payment_url' => $attributes['urls']['update_payment_method'] ?? $subscription->update_payment_url,
                ]);

                $accessUntil = \Carbon\Carbon::parse($endsAt)->toFormattedDateString();

                return back()->with('success', "Subscription cancelled. You keep full access until {$accessUntil}. Unused time is not refunded.");
            }

            return back()->with('error', 'Failed to cancel subscription. Please contact support.');
        } catch (\Exception $e) {
            Log::error('Subscription cancellation error', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'An error occurred while cancelling your subscription.');
        }
    }

    /**
     * Resume a cancelled subscription during the grace period.
     */
    public function resume()
    {
        $user = Auth::user();
        $subscription = $user->subscription();

        if (! $subscription || ! $subscription->lemonsqueezy_id) {
            return back()->with('error', 'No subscription found to resume.');
        }

        if (! $subscription->isOnGracePeriod()) {
            return back()->with('error', 'Only cancelled subscriptions within the paid period can be resumed.');
        }

        try {
            $apiKey = config('services.lemonsqueezy.api_key');
            $httpClient = Http::withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer '.$apiKey,
            ]);

            if (! config('services.lemonsqueezy.verify_ssl', true)) {
                $httpClient = $httpClient->withOptions(['verify' => false]);
            }

            $response = $httpClient->patch(
                'https://api.lemonsqueezy.com/v1/subscriptions/'.$subscription->lemonsqueezy_id,
                [
                    'data' => [
                        'type' => 'subscriptions',
                        'id' => (string) $subscription->lemonsqueezy_id,
                        'attributes' => [
                            'cancelled' => false,
                        ],
                    ],
                ]
            );

            if ($response->successful()) {
                $attributes = $response->json('data.attributes') ?? [];

                $subscription->update([
                    'status' => 'active',
                    'cancelled_at' => null,
                    'ends_at' => null,
                    'renews_at' => ! empty($attributes['renews_at'])
                        ? $attributes['renews_at']
                        : $subscription->renews_at,
                    'customer_portal_url' => $attributes['urls']['customer_portal'] ?? $subscription->customer_portal_url,
                    'update_payment_url' => $attributes['urls']['update_payment_method'] ?? $subscription->update_payment_url,
                ]);

                return back()->with('success', 'Subscription resumed. Billing will continue as before.');
            }

            return back()->with('error', 'Failed to resume subscription. Please try the customer portal or contact support.');
        } catch (\Exception $e) {
            Log::error('Subscription resume error', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'An error occurred while resuming your subscription.');
        }
    }

    /**
     * Purchase additional credits (not available in beta).
     */
    public function purchaseCredits(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1|max:1000',
        ]);

        return back()->with('info', 'Your credits reset automatically each month with your plan. One-time credit top-ups are not available during the beta period - your next batch will be available at your renewal date.');
    }
}
